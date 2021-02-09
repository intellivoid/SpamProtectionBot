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
                    "text" => "This command can only be used by chat administrators"
                ]);
            }

            if($UserChatMember->getResult()->status !== "creator" && $UserChatMember->getResult()->status !== "administrator")
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => "This command can only be used by chat administrators"
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
            $DeepAnalytics->tally('tg_spam_protection', 'callback_query', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'callback_query', (int)$this->WhoisCommand->CallbackQueryChatObject->ID);

            // Verify if the user is an administrator
            $UserChatMember = Request::getChatMember([
                "user_id" => $this->WhoisCommand->CallbackQueryUserObject->ID,
                "chat_id" => $this->WhoisCommand->CallbackQueryChatObject->ID
            ]);

            if($UserChatMember->isOk() == false)
            {
                return $callbackQuery->answer([
                    "text" => "You need to be a chat administrator to preform this action",
                    "show_alert" => true
                ]);
            }

            if($UserChatMember->getResult()->status !== "creator" && $UserChatMember->getResult()->status !== "administrator")
            {
                return $callbackQuery->answer([
                    "text" => "You need to be a chat administrator to preform this action",
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
                    return $this->handleLanguageChange($callbackQuery);

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
                        "text" => "This query isn't understood, are you using an official client?",
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
            $ResponseMessage = [
                "parse_mode" => "html",
                "disable_web_page_preview" => true,
                "reply_markup" => new InlineKeyboard(
                    [
                        [
                            "text" => "\u{1F4E8} Spam Detection",
                            "callback_data" => "0101"
                        ],
                        [
                            "text" => "\u{1F51E} NSFW Filter",
                            "callback_data" => "0102"
                        ]
                    ],
                    [

                        [
                            "text" => "\u{1F4A3} Blacklisted Users",
                            "callback_data" => "0103"
                        ],
                        [
                            "text" => "\u{26A0} Potential Spammers",
                            "callback_data" => "0104"
                        ]
                    ],
                    [
                        [
                            "text" => "\u{1F4E3} General Alerts",
                            "callback_data" => "0105"
                        ],
                        [
                            "text" => "\u{1F310} Language",
                            "callback_data" => "0106"
                        ]
                    ],
                    [
                        [
                            "text" => "Close Menu",
                            "callback_data" => "0107"
                        ]
                    ]
                ),
                "text" =>
                    "\u{2699} <b>Settings Manager</b>\n\n".
                    "You can configure SpamProtectionBot's settings in this chat, just select the section you want to ".
                    "configure and more information will be presented, if you have any questions or help then feel free ".
                    "to join our <a href=\"https://t.me/SpamProtectionSupport\">support chat</a>."
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
         * Handles language localization changes
         *
         * @param CallbackQuery $callbackQuery
         * @return ServerResponse|null
         * @throws TelegramException
         */
        public function handleLanguageChange(CallbackQuery $callbackQuery): ?ServerResponse
        {
            $ResponseMessage = [
                "chat_id" => $callbackQuery->getMessage()->getChat()->getId(),
                "message_id" => $callbackQuery->getMessage()->getMessageId(),
                "parse_mode" => "html",
                "disable_web_page_preview" => true,
                "text" =>
                    "\u{1F4E3} <b>Bot Language</b>\n\n".
                    "SpamProtectionBot is using an experimental localization method, so the localization may not always be accurate ".
                    "but nevertheless you can change the language of this bot and SpamProtectionBot will respond in said language ".
                    "(Including alerts) in this chat.",
                "reply_markup" => new InlineKeyboard(
                    [
                        ["text" => "\u{1F1EC}\u{1F1E7}", "callback_data" => "0106en"],
                        ["text" => "\u{1F32E}", "callback_data" => "0106es"],
                        ["text" => "\u{1F1EF}\u{1F1F5}", "callback_data" => "0106ja"],
                        ["text" => "\u{1F1E8}\u{1F1F3}", "callback_data" => "0106zh"],
                        ["text" => "\u{1F1E9}\u{1F1EA}", "callback_data" => "0106de"],
                        ["text" => "\u{1F1F5}\u{1F1F1}", "callback_data" => "0106pl"],
                    ],
                    [
                        ["text" => "\u{1F1EB}\u{1F1F7}", "callback_data" => "0106fr"],
                        ["text" => "\u{1F1F3}\u{1F1F1}", "callback_data" => "0106nr"],
                        ["text" => "\u{1F1F0}\u{1F1F7}", "callback_data" => "0106kr"],
                        ["text" => "\u{1F1EE}\u{1F1F9}", "callback_data" => "0106it"],
                        ["text" => "\u{1F1F9}\u{1F1F7}", "callback_data" => "0106tr"],
                        ["text" => "\u{1F1F7}\u{1F1FA}", "callback_data" => "0106ru"],
                    ],
                    [
                        [
                            "text" => "\u{1F519} Go back",
                            "callback_data" => "0100"
                        ]
                    ]
                )
            ];

            return Request::editMessageText($ResponseMessage);
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
                        $callbackQuery->answer(["text" => "General Alerts has been disabled successfully"]);
                    }
                    else
                    {
                        $ChatSettings->GeneralAlertsEnabled = true;
                        $callbackQuery->answer(["text" => "General Alerts has been enabled successfully"]);
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
                    "text" => "\u{274C} Disable",
                    "callback_data" => "010501"
                ];

                $StatusResponse .= "<b>Status:</b> <i>Currently posting alerts about activities</i>\n";
            }
            else
            {
                $GeneralAlertsButton = [
                    "text" => "\u{2714} Enable",
                    "callback_data" => "010501"
                ];

                $StatusResponse .= "<b>Status:</b> <i>Posting alerts quietly to recent actions</i>";
            }


            $ResponseMessage = [
                "chat_id" => $callbackQuery->getMessage()->getChat()->getId(),
                "message_id" => $callbackQuery->getMessage()->getMessageId(),
                "parse_mode" => "html",
                "disable_web_page_preview" => true,
                "text" =>
                    "\u{1F4E3} <b>General Alerts Settings</b>\n\n".
                    "SpamProtectionBot is designed to alert users in the chat about the activites it's detecting and performing ".
                    "but if you find this too annoying, you can disable this and SpamProtectionBot will redirect all the alerts to ".
                    "recent actions so administrators can view actions taken by SpamProtectionBot in a more organized format.\n\n" . $StatusResponse,
                "reply_markup" => new InlineKeyboard(
                    [
                        $GeneralAlertsButton,
                    ],
                    [
                        [
                            "text" => "\u{1F519} Go back",
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
                        $callbackQuery->answer(["text" => "Potential Spammer Protection has been disabled successfully"]);
                    }
                    else
                    {
                        $ChatSettings->ActiveSpammerProtectionEnabled = true;
                        $callbackQuery->answer(["text" => "Potential Spammer Protection has been enabled successfully"]);
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
                    "text" => "\u{274C} Disable",
                    "callback_data" => "010401"
                ];

                $StatusResponse .= "<b>Status:</b> <i>Currently banning potential spammers</i>\n";
            }
            else
            {
                $PotentialSpammerDetectionButton = [
                    "text" => "\u{2714} Enable",
                    "callback_data" => "010401"
                ];

                $StatusResponse .= "<b>Status:</b> <i>Not banning potential spammers (Disabled)</i>";
            }


            $ResponseMessage = [
                "chat_id" => $callbackQuery->getMessage()->getChat()->getId(),
                "message_id" => $callbackQuery->getMessage()->getMessageId(),
                "parse_mode" => "html",
                "disable_web_page_preview" => true,
                "text" =>
                    "\u{26A0} <b>Potential Spammer Protection Settings</b>\n\n".
                    "Using AI SpamProtectionBot can protect your group from potential spammers, potential spammers are ".
                    "automatically flagged by SpamProtectionBot based off their past activities, this flag is usually ".
                    "enabled for spam bots that are just throw-away accounts used to promote spam.\n\n" . $StatusResponse,
                "reply_markup" => new InlineKeyboard(
                    [
                        $PotentialSpammerDetectionButton,
                    ],
                    [
                        [
                            "text" => "\u{1F519} Go back",
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
                        $callbackQuery->answer(["text" => "Blacklist Protection has been disabled successfully"]);
                    }
                    else
                    {
                        $ChatSettings->BlacklistProtectionEnabled = true;
                        $callbackQuery->answer(["text" => "Blacklist Protection has been enabled successfully"]);
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
                    "text" => "\u{274C} Disable",
                    "callback_data" => "010301"
                ];

                $StatusResponse .= "<b>Status:</b> <i>Currently banning blacklisted users</i>\n";
            }
            else
            {
                $BlacklistDetectionButton = [
                    "text" => "\u{2714} Enable",
                    "callback_data" => "010301"
                ];

                $StatusResponse .= "<b>Status:</b> <i>Not banning blacklisted users (Disabled)</i>";
            }


            $ResponseMessage = [
                "chat_id" => $callbackQuery->getMessage()->getChat()->getId(),
                "message_id" => $callbackQuery->getMessage()->getMessageId(),
                "parse_mode" => "html",
                "disable_web_page_preview" => true,
                "text" =>
                    "\u{1F51E} <b>Blacklist Protection Settings</b>\n\n".
                    "Outsourced by trusted operators, blacklisted users are users that are blacklisted by operators due ".
                    "to a strict reason in relation to spam or abuse, for example a blacklisted user may be known for " .
                    "mass-sending spam, mass-adding users or initializing/participating in raids. Most blacklists are " .
                    "backed up by proof in <a href=\"https://t.me/SpamProtectionLogs\">SpamProtectionLogs</a> and can be " .
                    "found by searching for the users Private Telegram ID (PTID) in the channel.\n\n" .
                    "If you believe a user was blacklisted incorrectly then ask for support in our <a href=\"https://t.me/SpamProtectionSupport\">Support Group</a> ".
                    "or ask the user to message @SpamProtectionBot to go through a automatic appeal process.\n\n". $StatusResponse,
                "reply_markup" => new InlineKeyboard(
                    [
                        $BlacklistDetectionButton,
                    ],
                    [
                        [
                            "text" => "\u{1F519} Go back",
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
                        $callbackQuery->answer(["text" => "NSFW Filter has been disabled successfully"]);
                    }
                    else
                    {
                        $ChatSettings->NsfwFilterEnabled = true;
                        $callbackQuery->answer(["text" => "NSFW Filter has been enabled successfully"]);
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
                                "text" => "You must enable NSFW Filter before changing the detection action",
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
                    "text" => "\u{274C} Disable",
                    "callback_data" => "010201"
                ];

                $StatusResponse .= "<b>Status:</b> <i>Currently detecting NSFW content</i>\n";
            }
            else
            {
                $NsfwDetectionButton = [
                    "text" => "\u{2714} Enable",
                    "callback_data" => "010201"
                ];

                $StatusResponse .= "<b>Status:</b> <i>Not detecting NSFW content (Disabled)</i>";
            }

            switch($ChatSettings->NsfwDetectionAction)
            {
                case DetectionAction::Nothing:
                    $DetectionActionButton = [
                        "text" => "\u{26AA} Do Nothing",
                        "callback_data" => "010202"
                    ];
                    if($ChatSettings->NsfwFilterEnabled) $StatusResponse .= "<b>Detection Action:</b> <i>Show NSFW detection alerts only</i>";
                    break;

                case DetectionAction::BanOffender:
                    $DetectionActionButton = [
                        "text" => "\u{1F534} Delete & Ban Offender",
                        "callback_data" => "010202"
                    ];
                    if($ChatSettings->NsfwFilterEnabled) $StatusResponse .= "<b>Detection Action:</b> <i>Delete NSFW content and ban offender</i>";
                    break;

                case DetectionAction::KickOffender:
                    $DetectionActionButton = [
                        "text" => "\u{1F7E0} Delete & Kick Offender",
                        "callback_data" => "010202"
                    ];
                    if($ChatSettings->NsfwFilterEnabled) $StatusResponse .= "<b>Detection Action:</b> <i>Delete NSFW content and kick offender</i>";
                    break;
                case DetectionAction::DeleteMessage:
                    $DetectionActionButton = [
                        "text" => "\u{1F535} Delete Message",
                        "callback_data" => "010202"
                    ];
                    if($ChatSettings->NsfwFilterEnabled) $StatusResponse .= "<b>Detection Action:</b> <i>Delete NSFW content only</i>";
                    break;
            }

            $ResponseMessage = [
                "chat_id" => $callbackQuery->getMessage()->getChat()->getId(),
                "message_id" => $callbackQuery->getMessage()->getMessageId(),
                "parse_mode" => "html",
                "disable_web_page_preview" => true,
                "text" =>
                    "\u{1F51E} <b>NSFW Filter Settings</b>\n\n".
                    "Using Machine Learning SpamProtectionBot will check images for NSFW content and protect your group ".
                    "from content that can get your group flagged for hosting pornographic content\n\n" . $StatusResponse,
                "reply_markup" => new InlineKeyboard(
                    [
                        $NsfwDetectionButton,
                        $DetectionActionButton
                    ],
                    [
                        [
                            "text" => "\u{1F519} Go back",
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
                        $callbackQuery->answer(["text" => "Spam Detection has been disabled successfully"]);
                    }
                    else
                    {
                        $ChatSettings->DetectSpamEnabled = true;
                        $callbackQuery->answer(["text" => "Spam Detection has been enabled successfully"]);
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
                                "text" => "You must enable Spam Detection before changing the detection action",
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
                    "text" => "\u{274C} Disable",
                    "callback_data" => "010101"
                ];

                $StatusResponse .= "<b>Status:</b> <i>Currently detecting spam</i>\n";
            }
            else
            {
                $SpamDetectionButton = [
                    "text" => "\u{2714} Enable",
                    "callback_data" => "010101"
                ];

                $StatusResponse .= "<b>Status:</b> <i>Not detecting spam (Disabled)</i>";
            }

            switch($ChatSettings->DetectSpamAction)
            {
                case DetectionAction::Nothing:
                    $DetectionActionButton = [
                        "text" => "\u{26AA} Do Nothing",
                        "callback_data" => "010102"
                    ];
                    if($ChatSettings->DetectSpamEnabled) $StatusResponse .= "<b>Detection Action:</b> <i>Show spam detection alerts only</i>";
                    break;

                case DetectionAction::BanOffender:
                    $DetectionActionButton = [
                        "text" => "\u{1F534} Delete & Ban Offender",
                        "callback_data" => "010102"
                    ];
                    if($ChatSettings->DetectSpamEnabled) $StatusResponse .= "<b>Detection Action:</b> <i>Delete spam and ban offender</i>";
                    break;

                case DetectionAction::KickOffender:
                    $DetectionActionButton = [
                        "text" => "\u{1F7E0} Delete & Kick Offender",
                        "callback_data" => "010102"
                    ];
                    if($ChatSettings->DetectSpamEnabled) $StatusResponse .= "<b>Detection Action:</b> <i>Delete spam and kick offender</i>";
                    break;
                case DetectionAction::DeleteMessage:
                    $DetectionActionButton = [
                        "text" => "\u{1F535} Delete Message",
                        "callback_data" => "010102"
                    ];
                    if($ChatSettings->DetectSpamEnabled) $StatusResponse .= "<b>Detection Action:</b> <i>Delete spam only</i>";
                    break;
            }

            $ResponseMessage = [
                "chat_id" => $callbackQuery->getMessage()->getChat()->getId(),
                "message_id" => $callbackQuery->getMessage()->getMessageId(),
                "parse_mode" => "html",
                "disable_web_page_preview" => true,
                "text" =>
                    "\u{1F4E8} <b>Spam Detection Settings</b>\n\n".
                    "Using machine learning SpamProtectionBot can protect your group from unwanted spam before it even ".
                    "becomes effective, this works by checking the message content for spam-like features and predicting ".
                    "overall score to determine if the message is spam or not.\n\n" . $StatusResponse,
                "reply_markup" => new InlineKeyboard(
                    [
                        $SpamDetectionButton,
                        $DetectionActionButton
                    ],
                    [
                        [
                            "text" => "\u{1F519} Go back",
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