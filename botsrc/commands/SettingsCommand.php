<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\UserCommands;

    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\CallbackQuery;
    use Longman\TelegramBot\Entities\InlineKeyboard;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Abstracts\DetectionAction;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtectionBot;
    use TelegramClientManager\Abstracts\TelegramChatType;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;

    /**
     * Settings Command
     *
     * Allows the chat administrator to alter settings for the chat
     */
    class SettingsCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'settings';

        /**
         * @var string
         */
        protected $description = 'Allows the chat administrator to alter settings for the chat';

        /**
         * @var string
         */
        protected $usage = '/settings';

        /**
         * @var string
         */
        protected $version = '3.0.0';

        /**
         * @var bool
         */
        protected $private_only = false;

        /**
         * The whois command used for finding targets
         *
         * @var WhoisCommand|null
         */
        public $WhoisCommand = null;

        /**
         * Command execute method
         *
         * @return ServerResponse
         * @throws TelegramException
         * @noinspection DuplicatedCode
         */
        public function execute()
        {
            // Find clients
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
            $this->WhoisCommand = new WhoisCommand($this->telegram, $this->update);
            $this->WhoisCommand->findClients();

            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'settings_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->WhoisCommand->ChatObject->ID);
            $DeepAnalytics->tally('tg_spam_protection', 'settings_command', (int)$this->WhoisCommand->ChatObject->ID);

            // Ignore forwarded commands
            if($this->getMessage()->getForwardFrom() !== null || $this->getMessage()->getForwardFromChat())
            {
                return null;
            }

            if($this->WhoisCommand->ChatObject->Type !== TelegramChatType::Group)
            {
                if($this->WhoisCommand->ChatObject->Type !== TelegramChatType::SuperGroup)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "This command can only be used in group chats!"
                    ]);
                }
            }

            // Verify if the user is an administrator
            $UserChatMember = Request::getChatMember([
                "user_id" => $this->WhoisCommand->UserObject->ID,
                "chat_id" => $this->WhoisCommand->ChatObject->ID
            ]);

            if($UserChatMember->isOk() == false)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "This command can only be used by chat administrators")
                ]);
            }

            if($UserChatMember->getResult()->status !== "creator" && $UserChatMember->getResult()->status !== "administrator")
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "This command can only be used by chat administrators")
                ]);
            }

            return $this->handleSettingsManager();
        }

        /**
         * Handles the callback query
         *
         * @param CallbackQuery $callbackQuery
         * @return ServerResponse|null
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         */
        public function handleCallbackQuery(CallbackQuery $callbackQuery): ?ServerResponse
        {
            if($this->WhoisCommand == null)
            {
                $this->WhoisCommand = new WhoisCommand($this->telegram, $this->update);
            }

            $this->WhoisCommand->findCallbackClients($callbackQuery);

            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'callback_settings_query', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'callback_settings_query', (int)$this->WhoisCommand->CallbackQueryChatObject->ID);

            // Verify if the user is an administrator
            $UserChatMember = Request::getChatMember([
                "user_id" => $this->WhoisCommand->CallbackQueryUserObject->ID,
                "chat_id" => $this->WhoisCommand->CallbackQueryChatObject->ID
            ]);

            if($UserChatMember->isOk() == false)
            {
                return $callbackQuery->answer([
                    "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "You need to be a chat administrator to preform this action"),
                    "show_alert" => true
                ]);
            }

            if($UserChatMember->getResult()->status !== "creator" && $UserChatMember->getResult()->status !== "administrator")
            {
                return $callbackQuery->answer([
                    "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "You need to be a chat administrator to preform this action"),
                    "show_alert" => true
                ]);
            }

            switch(mb_substr($callbackQuery->getData(), 0, 4))
            {
                case "0100": // Go back to main menu
                    return $this->handleSettingsManager($callbackQuery);

                case "0101": // Spam Detection
                    return $this->handleSpamDetectionConfiguration($callbackQuery);

                case "0102": // NSFW Detection
                    return $this->handleNsfwDetectionConfiguration($callbackQuery);

                case "0103": // Blacklist Protection
                    return $this->handleBlacklistConfiguration($callbackQuery);

                case "0104": // Potential spammer Protection
                    return $this->handlePotentialSpammersConfiguration($callbackQuery);

                case "0105": // General Alerts
                    return $this->handleGeneralAlertsConfiguration($callbackQuery);

                case "0106": // Language Change
                    $LanguageCommand = new LanguageCommand($this->telegram, $this->update);
                    return $LanguageCommand->handleChatLanguageChange($callbackQuery, $this->WhoisCommand);

                case "0107":
                    Request::deleteMessage([
                        "chat_id" => $callbackQuery->getMessage()->getChat()->getId(),
                        "message_id" => $callbackQuery->getMessage()->getMessageId()
                    ]);
                    Request::deleteMessage([
                        "chat_id" => $callbackQuery->getMessage()->getChat()->getId(),
                        "message_id" => $callbackQuery->getMessage()->getReplyToMessage()->getMessageId()
                    ]);
                    return null;

                default:
                    return $callbackQuery->answer([
                        "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "This query isn't understood, are you using an official client?"),
                        "show_alert" => true
                    ]);
            }
        }

        /**
         * Displays the settings menu
         *
         * @param CallbackQuery|null $callbackQuery
         * @return ServerResponse|null
         * @throws TelegramException
         */
        public function handleSettingsManager(CallbackQuery  $callbackQuery=null): ?ServerResponse
        {
            $Text = LanguageCommand::localizeChatText($this->WhoisCommand,
                "You can configure SpamProtectionBot's settings in this chat, just select the section you want to ".
                "configure and more information will be presented, if you have any questions or help then feel free ".
                "to join our %s.", ['s']);
            $SupportChatLink = "<a href=\"https://t.me/SpamProtectionSupport\">" . LanguageCommand::localizeChatText($this->WhoisCommand, "support chat") . "</a>";

            $ResponseMessage = [
                "parse_mode" => "html",
                "disable_web_page_preview" => true,
                "reply_markup" => new InlineKeyboard(
                    [
                        [
                            "text" => "\u{1F4E8} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Spam Detection"),
                            "callback_data" => "0101"
                        ],
                        [
                            "text" => "\u{1F51E} " . LanguageCommand::localizeChatText($this->WhoisCommand, "NSFW Filter"),
                            "callback_data" => "0102"
                        ]
                    ],
                    [

                        [
                            "text" => "\u{1F4A3} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Blacklisted Users"),
                            "callback_data" => "0103"
                        ],
                        [
                            "text" => "\u{26A0} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Potential Spammers"),
                            "callback_data" => "0104"
                        ]
                    ],
                    [
                        [
                            "text" => "\u{1F4E3} " . LanguageCommand::localizeChatText($this->WhoisCommand, "General Alerts"),
                            "callback_data" => "0105"
                        ],
                        [
                            "text" => "\u{1F310} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Language"),
                            "callback_data" => "0106"
                        ]
                    ],
                    [
                        [
                            "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Close Menu"),
                            "callback_data" => "0107"
                        ]
                    ]
                ),
                "text" =>
                    "\u{2699} <b>" .
                    LanguageCommand::localizeChatText($this->WhoisCommand, "Settings Manager") . "</b>\n\n".
                    str_ireplace("%s", $SupportChatLink, $Text)
            ];

            if($callbackQuery == null)
            {
                $ResponseMessage["chat_id"] = $this->getMessage()->getChat()->getId();
                $ResponseMessage["reply_to_message_id"] = $this->getMessage()->getMessageId();
                return Request::sendMessage($ResponseMessage);
            }
            else
            {
                $ResponseMessage["chat_id"] = $callbackQuery->getMessage()->getChat()->getId();
                $ResponseMessage["message_id"] = $callbackQuery->getMessage()->getMessageId();
                return Request::editMessageText($ResponseMessage);
            }

        }

        /**
         * Handles general alerts
         *
         * @param CallbackQuery $callbackQuery
         * @return ServerResponse|null
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         */
        public function handleGeneralAlertsConfiguration(CallbackQuery $callbackQuery): ?ServerResponse
        {
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
            $ChatSettings = SettingsManager::getChatSettings($this->WhoisCommand->CallbackQueryChatClient);

            $StatusResponse = (string)null;

            switch($callbackQuery->getData())
            {
                case "010501":
                    if($ChatSettings->GeneralAlertsEnabled)
                    {
                        $ChatSettings->GeneralAlertsEnabled = false;
                        $callbackQuery->answer(["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "General Alerts has been disabled successfully")]);
                    }
                    else
                    {
                        $ChatSettings->GeneralAlertsEnabled = true;
                        $callbackQuery->answer(["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "General Alerts has been enabled successfully")]);
                    }

                    $this->WhoisCommand->CallbackQueryChatClient = SettingsManager::updateChatSettings(
                        $this->WhoisCommand->CallbackQueryChatClient, $ChatSettings
                    );
                    $TelegramClientManager->getTelegramClientManager()->updateClient($this->WhoisCommand->CallbackQueryChatClient);

                    break;

                case "010502":
                    if($ChatSettings->DeleteOlderMessages)
                    {
                        $ChatSettings->DeleteOlderMessages = false;
                        $callbackQuery->answer(["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Older general alerts will not be deleted")]);
                    }
                    else
                    {
                        $ChatSettings->DeleteOlderMessages = true;
                        $callbackQuery->answer(["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Older general alerts will be deleted")]);
                    }

                    $this->WhoisCommand->CallbackQueryChatClient = SettingsManager::updateChatSettings(
                        $this->WhoisCommand->CallbackQueryChatClient, $ChatSettings
                    );
                    $TelegramClientManager->getTelegramClientManager()->updateClient($this->WhoisCommand->CallbackQueryChatClient);

                    break;

            }

            if($ChatSettings->GeneralAlertsEnabled)
            {
                $GeneralAlertsButton = [
                    "text" => "\u{274C} " . LanguageCommand::localizeChatText($this->WhoisCommand,"Disable"),
                    "callback_data" => "010501"
                ];

                $StatusValue = LanguageCommand::localizeChatText($this->WhoisCommand, "Currently posting alerts about activities");
                $StatusText =  LanguageCommand::localizeChatText($this->WhoisCommand, "Status: %s", ['s']);
            }
            else
            {
                $GeneralAlertsButton = [
                    "text" => "\u{2714} " . LanguageCommand::localizeChatText($this->WhoisCommand,"Enable"),
                    "callback_data" => "010501"
                ];

                $StatusValue = LanguageCommand::localizeChatText($this->WhoisCommand, "Posting alerts quietly to recent actions");
                $StatusText =  LanguageCommand::localizeChatText($this->WhoisCommand, "Status: %s", ['s']);
            }

            if($ChatSettings->DeleteOlderMessages)
            {
                $DeleteOldMessagesButton = [
                    "text" => "\u{274C} " . LanguageCommand::localizeChatText($this->WhoisCommand,"Delete older messages"),
                    "callback_data" => "010502"
                ];

                if($ChatSettings->GeneralAlertsEnabled)
                {
                    $StatusDeletionValue = LanguageCommand::localizeChatText($this->WhoisCommand, "Currently deleting older general alerts");
                }
            }
            else
            {
                $DeleteOldMessagesButton = [
                    "text" => "\u{2714} " . LanguageCommand::localizeChatText($this->WhoisCommand,"Delete older messages"),
                    "callback_data" => "010502"
                ];

                if($ChatSettings->GeneralAlertsEnabled)
                {
                    $StatusDeletionValue = LanguageCommand::localizeChatText($this->WhoisCommand, "Not deleting older alerts");
                }
            }

            $StatusResponse .= "<b>" . str_ireplace("%s", $StatusValue, $StatusText) . "</b>\n";

            if($ChatSettings->GeneralAlertsEnabled)
            {
                /** @noinspection PhpUndefinedVariableInspection */
                $StatusResponse .= "<b>" . $StatusDeletionValue . "</b>\n";
            }

            $ResponseMessage = [
                "chat_id" => $callbackQuery->getMessage()->getChat()->getId(),
                "message_id" => $callbackQuery->getMessage()->getMessageId(),
                "parse_mode" => "html",
                "disable_web_page_preview" => true,
                "text" =>
                    "\u{1F4E3} <b>" . LanguageCommand::localizeChatText($this->WhoisCommand,"General Alerts Settings") . "</b>\n\n".
                    LanguageCommand::localizeChatText($this->WhoisCommand,
                        "SpamProtectionBot is designed to alert users in the chat about the activities it's detecting and performing ".
                        "but if you find this too annoying, you can disable this and the bot will operate silently") .
                    "\n\n" .
                    LanguageCommand::localizeChatText($this->WhoisCommand,
                        "You can also enable/disable the option for deleting older messages to keep your chat clean " .
                        "from general alerts, with this option enabled, new general alerts will be shown while older ones are ".
                        "deleted from the chat") .
                    "\n\n" . $StatusResponse,
                "reply_markup" => new InlineKeyboard(
                    [
                        $GeneralAlertsButton,
                    ],
                    [
                        $DeleteOldMessagesButton
                    ],
                    [
                        [
                            "text" => "\u{1F519} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Go back"),
                            "callback_data" => "0100"
                        ]
                    ]
                )
            ];

            return Request::editMessageText($ResponseMessage);
        }

        /**
         * Handles potential spammer configuration
         *
         * @param CallbackQuery $callbackQuery
         * @return ServerResponse|null
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         */
        public function handlePotentialSpammersConfiguration(CallbackQuery $callbackQuery): ?ServerResponse
        {
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
            $ChatSettings = SettingsManager::getChatSettings($this->WhoisCommand->CallbackQueryChatClient);

            $StatusResponse = (string)null;

            switch($callbackQuery->getData())
            {
                case "010401":
                    if($ChatSettings->ActiveSpammerProtectionEnabled)
                    {
                        $ChatSettings->ActiveSpammerProtectionEnabled = false;
                        $callbackQuery->answer(["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Potential Spammer Protection has been disabled successfully")]);
                    }
                    else
                    {
                        $ChatSettings->ActiveSpammerProtectionEnabled = true;
                        $callbackQuery->answer(["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Potential Spammer Protection has been enabled successfully")]);
                    }

                    $this->WhoisCommand->CallbackQueryChatClient = SettingsManager::updateChatSettings(
                        $this->WhoisCommand->CallbackQueryChatClient, $ChatSettings
                    );
                    $TelegramClientManager->getTelegramClientManager()->updateClient($this->WhoisCommand->CallbackQueryChatClient);

                    break;

            }

            if($ChatSettings->ActiveSpammerProtectionEnabled)
            {
                $PotentialSpammerDetectionButton = [
                    "text" => "\u{274C} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Disable"),
                    "callback_data" => "010401"
                ];
                $StatusValue = LanguageCommand::localizeChatText($this->WhoisCommand, "Currently banning potential spammers");
                $StatusText = LanguageCommand::localizeChatText($this->WhoisCommand, "Status: %s", ['s']) . "\n";
            }
            else
            {
                $PotentialSpammerDetectionButton = [
                    "text" => "\u{2714} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Enable"),
                    "callback_data" => "010401"
                ];

                $StatusValue = LanguageCommand::localizeChatText($this->WhoisCommand, "Not banning potential spammers (Disabled)");
                $StatusText = LanguageCommand::localizeChatText($this->WhoisCommand, "Status: %s", ['s']);
            }

            $StatusResponse .= "<b>" . str_ireplace("%s", $StatusValue, $StatusText) . "</b>";


            $ResponseMessage = [
                "chat_id" => $callbackQuery->getMessage()->getChat()->getId(),
                "message_id" => $callbackQuery->getMessage()->getMessageId(),
                "parse_mode" => "html",
                "disable_web_page_preview" => true,
                "text" =>
                    "\u{26A0} <b>" . LanguageCommand::localizeChatText($this->WhoisCommand, "Potential Spammer Protection Settings") . "</b>\n\n".
                    LanguageCommand::localizeChatText($this->WhoisCommand,
                        "Using AI SpamProtectionBot can protect your group from potential spammers, potential spammers are ".
                        "automatically flagged by SpamProtectionBot based off their past activities, this flag is usually ".
                        "enabled for spam bots that are just throw-away accounts used to promote spam.") .
                    "\n\n" . $StatusResponse,
                "reply_markup" => new InlineKeyboard(
                    [
                        $PotentialSpammerDetectionButton,
                    ],
                    [
                        [
                            "text" => "\u{1F519} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Go back"),
                            "callback_data" => "0100"
                        ]
                    ]
                )
            ];

            return Request::editMessageText($ResponseMessage);
        }

        /**
         * Handles blacklist configuration
         *
         * @param CallbackQuery $callbackQuery
         * @return ServerResponse|null
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         */
        public function handleBlacklistConfiguration(CallbackQuery  $callbackQuery): ?ServerResponse
        {
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
            $ChatSettings = SettingsManager::getChatSettings($this->WhoisCommand->CallbackQueryChatClient);

            $StatusResponse = (string)null;

            switch($callbackQuery->getData())
            {
                case "010301":
                    if($ChatSettings->BlacklistProtectionEnabled)
                    {
                        $ChatSettings->BlacklistProtectionEnabled = false;
                        $callbackQuery->answer(["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Blacklist Protection has been disabled successfully")]);
                    }
                    else
                    {
                        $ChatSettings->BlacklistProtectionEnabled = true;
                        $callbackQuery->answer(["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Blacklist Protection has been enabled successfully")]);
                    }

                    $this->WhoisCommand->CallbackQueryChatClient = SettingsManager::updateChatSettings(
                        $this->WhoisCommand->CallbackQueryChatClient, $ChatSettings
                    );
                    $TelegramClientManager->getTelegramClientManager()->updateClient($this->WhoisCommand->CallbackQueryChatClient);

                    break;

            }

            if($ChatSettings->BlacklistProtectionEnabled)
            {
                $BlacklistDetectionButton = [
                    "text" => "\u{274C} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Disable"),
                    "callback_data" => "010301"
                ];

                $StatusValue = LanguageCommand::localizeChatText($this->WhoisCommand, "Currently banning blacklisted users");
                $StatusText = LanguageCommand::localizeChatText($this->WhoisCommand, "Status: %s", ['s']);
            }
            else
            {
                $BlacklistDetectionButton = [
                    "text" => "\u{2714} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Enable"),
                    "callback_data" => "010301"
                ];

                $StatusValue = LanguageCommand::localizeChatText($this->WhoisCommand, "Not banning blacklisted users (Disabled)");
                $StatusText = LanguageCommand::localizeChatText($this->WhoisCommand, "Status: %s", ['s']);
            }

            $StatusResponse .= "<b>" . str_ireplace("%s", $StatusValue, $StatusText) . "</b>";

            $ResponseText = LanguageCommand::localizeChatText($this->WhoisCommand,
                "Outsourced by trusted operators, blacklisted users are users that are blacklisted by operators due ".
                "to a strict reason in relation to spam or abuse, for example a blacklisted user may be known for " .
                "mass-sending spam, mass-adding users or initializing/participating in raids. Most blacklists are " .
                "backed up by proof in %s and can be found by searching for the users Private Telegram ID (PTID) in the channel.", ['s']) . "\n\n" .
                LanguageCommand::localizeChatText($this->WhoisCommand,
                    "If you believe a user was blacklisted incorrectly then ask for support in our %b ".
                    "or ask the user to message %c to go through a automatic appeal process.", ['b', 'c']);

            $SupportGroupText = LanguageCommand::localizeChatText($this->WhoisCommand, "Support Group");
            $ResponseText = str_ireplace("%s", " <a href=\"https://t.me/SpamProtectionLogs\">SpamProtectionLogs</a> ", $ResponseText);
            $ResponseText = str_ireplace("%b", " <a href=\"https://t.me/SpamProtectionSupport\">$SupportGroupText</a> ", $ResponseText);
            $ResponseText = str_ireplace("%c", " @SpamProtectionBot ", $ResponseText);


            $ResponseMessage = [
                "chat_id" => $callbackQuery->getMessage()->getChat()->getId(),
                "message_id" => $callbackQuery->getMessage()->getMessageId(),
                "parse_mode" => "html",
                "disable_web_page_preview" => true,
                "text" =>
                    "\u{1F51E} <b>" . LanguageCommand::localizeChatText($this->WhoisCommand,"Blacklist Protection Settings") . "</b>\n\n".
                    $ResponseText . "\n\n". $StatusResponse,
                "reply_markup" => new InlineKeyboard(
                    [
                        $BlacklistDetectionButton,
                    ],
                    [
                        [
                            "text" => "\u{1F519} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Go back"),
                            "callback_data" => "0100"
                        ]
                    ]
                )
            ];

            return Request::editMessageText($ResponseMessage);
        }

        /**
         * Handle NSFW Filter configuration
         *
         * @param CallbackQuery $callbackQuery
         * @return ServerResponse|null
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         */
        public function handleNsfwDetectionConfiguration(CallbackQuery  $callbackQuery): ?ServerResponse
        {
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
            $ChatSettings = SettingsManager::getChatSettings($this->WhoisCommand->CallbackQueryChatClient);

            $StatusResponse = (string)null;

            switch($callbackQuery->getData())
            {
                case "010201":
                    if($ChatSettings->NsfwFilterEnabled)
                    {
                        $ChatSettings->NsfwFilterEnabled = false;
                        $callbackQuery->answer(["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "NSFW Filter has been disabled successfully")]);
                    }
                    else
                    {
                        $ChatSettings->NsfwFilterEnabled = true;
                        $callbackQuery->answer(["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "NSFW Filter has been enabled successfully")]);
                    }

                    $this->WhoisCommand->CallbackQueryChatClient = SettingsManager::updateChatSettings(
                        $this->WhoisCommand->CallbackQueryChatClient, $ChatSettings
                    );
                    $TelegramClientManager->getTelegramClientManager()->updateClient($this->WhoisCommand->CallbackQueryChatClient);

                    break;


                case "010202":
                    if($ChatSettings->NsfwFilterEnabled == false)
                    {
                        $callbackQuery->answer(
                            [
                                "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "You must enable NSFW Filter before changing the detection action"),
                                "show_alert" => true
                            ]
                        );
                    }
                    else
                    {
                        $ChatSettings->NsfwDetectionAction = self::cycleAction($ChatSettings->NsfwDetectionAction);
                        $this->WhoisCommand->CallbackQueryChatClient = SettingsManager::updateChatSettings(
                            $this->WhoisCommand->CallbackQueryChatClient, $ChatSettings
                        );
                        $TelegramClientManager->getTelegramClientManager()->updateClient($this->WhoisCommand->CallbackQueryChatClient);
                    }
                    break;
            }

            $DetectionActionButton = [];

            if($ChatSettings->NsfwFilterEnabled)
            {
                $NsfwDetectionButton = [
                    "text" => "\u{274C} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Disable"),
                    "callback_data" => "010201"
                ];
                $StatusValue = LanguageCommand::localizeChatText($this->WhoisCommand, "Currently detecting NSFW content");
                $StatusText = LanguageCommand::localizeChatText($this->WhoisCommand, "Status: %s", ['s']);
            }
            else
            {
                $NsfwDetectionButton = [
                    "text" => "\u{2714} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Enable"),
                    "callback_data" => "010201"
                ];
                $StatusValue = LanguageCommand::localizeChatText($this->WhoisCommand, "Not detecting NSFW content (Disabled)");
                $StatusText = LanguageCommand::localizeChatText($this->WhoisCommand, "Status: %s", ['s']);
            }

            $StatusResponse .= "<b>" . str_ireplace("%s", $StatusValue, $StatusText) . "</b>\n";

            $DetectionActionText = LanguageCommand::localizeChatText($this->WhoisCommand, "Detection Action: %s", ['s']);
            $DetectionActionValue = (string)null;

            switch($ChatSettings->NsfwDetectionAction)
            {
                case DetectionAction::Nothing:
                    $DetectionActionButton = [
                        "text" => "\u{26AA} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Do Nothing"),
                        "callback_data" => "010202"
                    ];
                    if($ChatSettings->NsfwFilterEnabled) $DetectionActionValue = LanguageCommand::localizeChatText($this->WhoisCommand, "Show NSFW detection alerts only");
                    break;

                case DetectionAction::BanOffender:
                    $DetectionActionButton = [
                        "text" => "\u{1F534} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Delete & Ban Offender"),
                        "callback_data" => "010202"
                    ];
                    if($ChatSettings->NsfwFilterEnabled) $DetectionActionValue = LanguageCommand::localizeChatText($this->WhoisCommand, "Delete NSFW content and ban offender");
                    break;

                case DetectionAction::KickOffender:
                    $DetectionActionButton = [
                        "text" => "\u{1F7E0} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Delete & Kick Offender"),
                        "callback_data" => "010202"
                    ];
                    if($ChatSettings->NsfwFilterEnabled) $DetectionActionValue = LanguageCommand::localizeChatText($this->WhoisCommand, "Delete NSFW content and kick offender");
                    break;
                case DetectionAction::DeleteMessage:
                    $DetectionActionButton = [
                        "text" => "\u{1F535} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Delete Message"),
                        "callback_data" => "010202"
                    ];
                    if($ChatSettings->NsfwFilterEnabled) $DetectionActionValue = LanguageCommand::localizeChatText($this->WhoisCommand, "Delete NSFW content only");
                    break;
            }

            if($ChatSettings->NsfwFilterEnabled) $StatusResponse .= "<b>" . str_ireplace("%s", $DetectionActionValue, $DetectionActionText) . "</b>";

            $ResponseMessage = [
                "chat_id" => $callbackQuery->getMessage()->getChat()->getId(),
                "message_id" => $callbackQuery->getMessage()->getMessageId(),
                "parse_mode" => "html",
                "disable_web_page_preview" => true,
                "text" =>
                    "\u{1F51E} <b>" . LanguageCommand::localizeChatText($this->WhoisCommand, "NSFW Filter Settings") . "</b>\n\n".
                    LanguageCommand::localizeChatText($this->WhoisCommand,
                        "Using Machine Learning SpamProtectionBot will check images for NSFW content and protect your group ".
                        "from content that can get your group flagged for hosting pornographic content") . "
                        \n" . $StatusResponse,
                "reply_markup" => new InlineKeyboard(
                    [
                        $NsfwDetectionButton,
                        $DetectionActionButton
                    ],
                    [
                        [
                            "text" => "\u{1F519} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Go back"),
                            "callback_data" => "0100"
                        ]
                    ]
                )
            ];

            return Request::editMessageText($ResponseMessage);
        }

        /**
         * Handles the spam detection configuration
         *
         * @param CallbackQuery $callbackQuery
         * @return ServerResponse|null
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         */
        public function handleSpamDetectionConfiguration(CallbackQuery $callbackQuery): ?ServerResponse
        {
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
            $ChatSettings = SettingsManager::getChatSettings($this->WhoisCommand->CallbackQueryChatClient);

            $StatusResponse = (string)null;

            switch($callbackQuery->getData())
            {
                case "010101":
                    if($ChatSettings->DetectSpamEnabled)
                    {
                        $ChatSettings->DetectSpamEnabled = false;
                        $callbackQuery->answer(["text" =>  LanguageCommand::localizeChatText($this->WhoisCommand, "Spam Detection has been disabled successfully")]);
                    }
                    else
                    {
                        $ChatSettings->DetectSpamEnabled = true;
                        $callbackQuery->answer(["text" =>  LanguageCommand::localizeChatText($this->WhoisCommand, "Spam Detection has been enabled successfully")]);
                    }

                    $this->WhoisCommand->CallbackQueryChatClient = SettingsManager::updateChatSettings(
                        $this->WhoisCommand->CallbackQueryChatClient, $ChatSettings
                    );
                    $TelegramClientManager->getTelegramClientManager()->updateClient($this->WhoisCommand->CallbackQueryChatClient);

                    break;

                case "010102":
                    if($ChatSettings->DetectSpamEnabled == false)
                    {
                        $callbackQuery->answer(
                            [
                                "text" =>  LanguageCommand::localizeChatText($this->WhoisCommand, "You must enable Spam Detection before changing the detection action"),
                                "show_alert" => true
                            ]
                        );
                    }
                    else
                    {
                        $ChatSettings->DetectSpamAction = self::cycleAction($ChatSettings->DetectSpamAction);
                        $this->WhoisCommand->CallbackQueryChatClient = SettingsManager::updateChatSettings(
                            $this->WhoisCommand->CallbackQueryChatClient, $ChatSettings
                        );
                        $TelegramClientManager->getTelegramClientManager()->updateClient($this->WhoisCommand->CallbackQueryChatClient);
                    }
                    break;
            }

            $DetectionActionButton = [];

            if($ChatSettings->DetectSpamEnabled)
            {
                $SpamDetectionButton = [
                    "text" => "\u{274C} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Disable"),
                    "callback_data" => "010101"
                ];

                $StatusValue = LanguageCommand::localizeChatText($this->WhoisCommand, "Currently detecting spam");
                $StatusText = LanguageCommand::localizeChatText($this->WhoisCommand, "Status: %s", ['s']);
            }
            else
            {
                $SpamDetectionButton = [
                    "text" => "\u{2714} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Enable"),
                    "callback_data" => "010101"
                ];

                $StatusValue = LanguageCommand::localizeChatText($this->WhoisCommand, "Not detecting spam (Disabled)");
                $StatusText = LanguageCommand::localizeChatText($this->WhoisCommand, "Status: %s", ['s']);
            }

            $StatusResponse .= "<b>" . str_ireplace("%s", $StatusValue, $StatusText) . "</b>\n";

            $DetectionActionText = LanguageCommand::localizeChatText($this->WhoisCommand, "Detection Action: %s", ['s']);
            $DetectionActionValue = (string)null;

            switch($ChatSettings->DetectSpamAction)
            {
                case DetectionAction::Nothing:
                    $DetectionActionButton = [
                        "text" => "\u{26AA} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Do Nothing"),
                        "callback_data" => "010102"
                    ];
                    if($ChatSettings->DetectSpamEnabled) $DetectionActionValue = LanguageCommand::localizeChatText($this->WhoisCommand, "Show spam detection alerts only");
                    break;

                case DetectionAction::BanOffender:
                    $DetectionActionButton = [
                        "text" => "\u{1F534} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Delete & Ban Offender"),
                        "callback_data" => "010102"
                    ];
                    if($ChatSettings->DetectSpamEnabled) $DetectionActionValue = LanguageCommand::localizeChatText($this->WhoisCommand, "Delete spam content and ban offender");
                    break;

                case DetectionAction::KickOffender:
                    $DetectionActionButton = [
                        "text" => "\u{1F7E0} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Delete & Kick Offender"),
                        "callback_data" => "010102"
                    ];
                    if($ChatSettings->DetectSpamEnabled) $DetectionActionValue = LanguageCommand::localizeChatText($this->WhoisCommand, "Delete spam content and kick offender");
                    break;
                case DetectionAction::DeleteMessage:
                    $DetectionActionButton = [
                        "text" => "\u{1F535} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Delete Message"),
                        "callback_data" => "010102"
                    ];
                    if($ChatSettings->DetectSpamEnabled) $DetectionActionValue = LanguageCommand::localizeChatText($this->WhoisCommand, "Delete spam content only");
                    break;
            }

            if($ChatSettings->DetectSpamEnabled) $StatusResponse .= "<b>" . str_ireplace("%s", $DetectionActionValue, $DetectionActionText) . "</b>";

            $ResponseMessage = [
                "chat_id" => $callbackQuery->getMessage()->getChat()->getId(),
                "message_id" => $callbackQuery->getMessage()->getMessageId(),
                "parse_mode" => "html",
                "disable_web_page_preview" => true,
                "text" =>
                    "\u{1F4E8} <b>" . LanguageCommand::localizeChatText($this->WhoisCommand, "Spam Detection Settings") . "</b>\n\n".
                    LanguageCommand::localizeChatText($this->WhoisCommand,
                        "Using machine learning SpamProtectionBot can protect your group from unwanted spam before it even ".
                        "becomes effective, this works by checking the message content for spam-like features and predicting ".
                        "overall score to determine if the message is spam or not."). "\n\n" . $StatusResponse,
                "reply_markup" => new InlineKeyboard(
                    [
                        $SpamDetectionButton,
                        $DetectionActionButton
                    ],
                    [
                        [
                            "text" => "\u{1F519}"  . LanguageCommand::localizeChatText($this->WhoisCommand, "Go back"),
                            "callback_data" => "0100"
                        ]
                    ]
                )
            ];

            return Request::editMessageText($ResponseMessage);
        }

        /**
         * Cycles the detection action
         *
         * @param string $action
         * @return string
         */
        private static function cycleAction(string $action): string
        {
            switch($action)
            {
                case DetectionAction::Nothing:
                    return DetectionAction::DeleteMessage;

                case DetectionAction::DeleteMessage:
                    return DetectionAction::KickOffender;

                case DetectionAction::KickOffender:
                    return DetectionAction::BanOffender;

                case DetectionAction::BanOffender:
                default:
                    return DetectionAction::Nothing;
            }
        }
    }