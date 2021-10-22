<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use Longman\TelegramBot\Commands\SystemCommand;
    use Longman\TelegramBot\Commands\UserCommands\BlacklistCommand;
    use Longman\TelegramBot\Commands\UserCommands\LanguageCommand;
    use Longman\TelegramBot\Commands\UserCommands\WhoisCommand;
    use Longman\TelegramBot\Entities\InlineKeyboard;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Abstracts\BlacklistFlag;
    use SpamProtection\Exceptions\TelegramClientNotFoundException;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtectionBot;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Objects\TelegramClient;

    /**
     * New chat member command
     */
    class NewchatmembersCommand extends SystemCommand
    {
        /**
         * @var string
         */
        protected $name = 'newchatmember';

        /**
         * @var string
         */
        protected $description = 'New Chat Members';

        /**
         * @var string
         */
        protected $version = '3.0.0';

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
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws \TelegramClientManager\Exceptions\TelegramClientNotFoundException
         * @noinspection DuplicatedCode
         */
        public function execute(): ServerResponse
        {
            // Find all clients
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
            $this->WhoisCommand = new WhoisCommand($this->telegram, $this->update);
            $this->WhoisCommand->findClients();

            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'new_member', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->WhoisCommand->ChatObject->ID);
            $DeepAnalytics->tally('tg_spam_protection', 'new_member', (int)$this->WhoisCommand->ChatObject->ID);

            if(isset($this->getMessage()->getNewChatMembers()[0]))
            {
                $UserObject = TelegramClient\User::fromArray($this->getMessage()->getNewChatMembers()[0]->getRawData());
                $UserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($UserObject);

                if(isset($UserClient->SessionData->Data["user_status"]) == false)
                {
                    $UserStatus = SettingsManager::getUserStatus($UserClient);
                    $UserClient = SettingsManager::updateUserStatus($UserClient, $UserStatus);
                    $TelegramClientManager->getTelegramClientManager()->updateClient($UserClient);
                }
            }
            else
            {
                return Request::emptyResponse();
            }

            if($UserObject->Username == TELEGRAM_BOT_NAME)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        LanguageCommand::localizeChatText($this->WhoisCommand, "Thanks for adding me! Remember to give me the following permissions") . "\n\n".
                        " - <code>" . LanguageCommand::localizeChatText($this->WhoisCommand, "Delete Messages") . "</code>\n".
                        " - <code>" . LanguageCommand::localizeChatText($this->WhoisCommand, "Ban Users") . "</code>\n\n".
                        str_ireplace("%s", "/help",
                            LanguageCommand::localizeChatText(
                                $this->WhoisCommand, "If you need help with setting this up bot, send %s", ['s'])
                        ) . "\n\n".
                        str_ireplace("%s", "@SpamProtectionSupport",
                            LanguageCommand::localizeChatText(
                                $this->WhoisCommand,
                                "I will actively detect and remove spam and ban blacklisted users such as spammers, " .
                                "scammers and raiders, if you need any help than feel free to reach out to us at %s", ['s'])
                        )
                ]);
            }

            // Detect if the user is blacklisted
            if($this->handleBlacklist($UserClient) == null)
            {
                // If they're not blacklisted, maybe they're an potential spammer?
                if($this->handleActiveSpammer($UserClient) !== null)
                {
                    // Tally if success
                    $DeepAnalytics->tally('tg_spam_protection', 'banned_potential', 0);
                    $DeepAnalytics->tally('tg_spam_protection', 'banned_potential', (int)$this->WhoisCommand->ChatObject->ID);
                }
            }
            else
            {
                // Tally if success
                $DeepAnalytics->tally('tg_spam_protection', 'banned_blacklisted', 0);
                $DeepAnalytics->tally('tg_spam_protection', 'banned_blacklisted', (int)$this->WhoisCommand->ChatObject->ID);
            }

            return Request::emptyResponse();
        }

        /**
         * Handles a potential active spammer upon joining
         *
         * @param TelegramClient $userClient
         * @return ServerResponse
         * @throws TelegramException
         * @noinspection DuplicatedCode
         */
        public function handleActiveSpammer(TelegramClient $userClient): ServerResponse
        {
            if($userClient->User->IsBot)
            {
                return Request::emptyResponse();
            }

            $ChatSettings = SettingsManager::getChatSettings($this->WhoisCommand->ChatClient);
            $UserStatus = SettingsManager::getUserStatus($userClient);

            if($ChatSettings->ActiveSpammerProtectionEnabled)
            {
                if($UserStatus->GeneralizedSpamProbability > 0)
                {
                    if($UserStatus->GeneralizedSpamProbability > $UserStatus->GeneralizedHamProbability)
                    {
                        $BanResponse = Request::kickChatMember([
                            "chat_id" => $this->getMessage()->getChat()->getId(),
                            "user_id" => $userClient->User->ID,
                            "until_date" => 0
                        ]);

                        if($BanResponse->isOk())
                        {
                            $Response = str_ireplace("%s", WhoisCommand::generateMention($userClient), LanguageCommand::localizeChatText(
                                $this->WhoisCommand, "%s has been banned because they might be a potential spammer", ['s'], true
                            )) . "\n\n";

                            $Response .= str_ireplace("%s", "<code>" . $userClient->PublicID . "</code>", LanguageCommand::localizeChatText(
                                    $this->WhoisCommand, "Private Telegram ID: %s", ['s'], true
                                )) . "\n\n";

                            $NoticeText = LanguageCommand::localizeChatText($this->WhoisCommand,
                                "You can find evidence of abuse by searching the Private Telegram ID in %s else " .
                                "if you believe that this was a mistake then let us know in %b",
                            ['s', 'b'], true);
                            $NoticeText = str_ireplace("%s", "@" . LOG_CHANNEL, $NoticeText);
                            $NoticeText = str_ireplace("%b", "@SpamProtectionSupport", $NoticeText);

                            $Response .= "<i>$NoticeText</i>";

                            if($ChatSettings->GeneralAlertsEnabled)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "reply_markup" => new InlineKeyboard(
                                        [
                                            ["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Logs", [], true), "url" => "https://t.me/" . LOG_CHANNEL],
                                            ["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "User Info", [], true), "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $userClient->User->ID],
                                            ["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Report Problem", [], true), "url" => "https://t.me/SpamProtectionSupport"]
                                        ]
                                    ),
                                    "text" => $Response
                                ]);
                            }
                        }
                    }
                }
            }

            return Request::emptyResponse();
        }

        /**
         * Handles a blacklisted user
         *
         * @param TelegramClient $userClient
         * @return ServerResponse
         * @throws TelegramException
         * @noinspection DuplicatedCode
         */
        public function handleBlacklist(TelegramClient $userClient): ServerResponse
        {
            $ChatSettings = SettingsManager::getChatSettings($this->WhoisCommand->ChatClient);
            $UserStatus = SettingsManager::getUserStatus($userClient);

            if($ChatSettings->BlacklistProtectionEnabled)
            {
                if($UserStatus->IsBlacklisted)
                {
                    $BanResponse = Request::kickChatMember([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "user_id" => $userClient->User->ID,
                        "until_date" => 0
                    ]);

                    if($BanResponse->isOk())
                    {
                        $Response = WhoisCommand::generateMention($userClient) . " has been banned because they've been blacklisted!\n\n";

                        $Response .= str_ireplace("%s", "<code>" . $userClient->PublicID . "</code>",
                                LanguageCommand::localizeChatText($this->WhoisCommand, "Private Telegram ID: %s", ['s'], true
                                )) . "\n";
                        switch($UserStatus->BlacklistFlag)
                        {
                            case BlacklistFlag::BanEvade:
                                $Response .= str_ireplace("%s", "<code>" . LanguageCommand::localizeChatText($this->WhoisCommand, "Ban Evade", [], true) . "</code>",
                                        LanguageCommand::localizeChatText($this->WhoisCommand, "Blacklist Reason: %s", ['s'], true
                                        )) . "\n";
                                $Response .= str_ireplace("%s", "<code>" . $UserStatus->OriginalPrivateID . "</code>",
                                        LanguageCommand::localizeChatText($this->WhoisCommand, "Original Private ID: %s", ['s'], true
                                        )) . "\n\n";
                                break;

                            default:
                                $Response .= str_ireplace("%s", "<code>" . LanguageCommand::localizeChatText($this->WhoisCommand, BlacklistCommand::blacklistFlagToReason($UserStatus->BlacklistFlag), [], true) . "</code>",
                                        LanguageCommand::localizeChatText($this->WhoisCommand, "Blacklist Reason: %s", ['s'], true
                                        )) . "\n\n";
                                break;
                        }

                        $NoticeText = LanguageCommand::localizeChatText($this->WhoisCommand,
                            "You can find evidence of abuse by searching the Private Telegram ID in %s else " .
                            "if you believe that this was a mistake then let us know in %b",
                            ['s', 'b'], true);
                        $NoticeText = str_ireplace("%s", "@" . LOG_CHANNEL, $NoticeText);
                        $NoticeText = str_ireplace("%b", "@SpamProtectionSupport", $NoticeText);

                        $Response .= "<i>$NoticeText</i>";

                        if($ChatSettings->GeneralAlertsEnabled)
                        {
                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "reply_markup" => new InlineKeyboard(
                                    [
                                        ["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Logs", [], true), "url" => "https://t.me/" . LOG_CHANNEL],
                                        ["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "User Info", [], true), "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $userClient->User->ID],
                                        ["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Report Problem", [], true), "url" => "https://t.me/SpamProtectionSupport"]
                                    ]
                                ),
                                "text" => $Response
                            ]);
                        }
                    }
                }
            }

            return Request::emptyResponse();
        }
    }