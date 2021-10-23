<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use Longman\TelegramBot\Commands\SystemCommand;
    use Longman\TelegramBot\Commands\UserCommands\BlacklistCommand;
    use Longman\TelegramBot\Commands\UserCommands\LanguageCommand;
    use Longman\TelegramBot\Commands\UserCommands\WhoisCommand;
    use Longman\TelegramBot\Commands\UserCommands\ResetCacheCommand;
    use Longman\TelegramBot\Entities\CallbackQuery;
    use Longman\TelegramBot\Entities\InlineKeyboard;
    use Longman\TelegramBot\Entities\Keyboard;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Abstracts\BlacklistFlag;
    use SpamProtection\Exceptions\InvalidBlacklistFlagException;
    use SpamProtection\Exceptions\MissingOriginalPrivateIdException;
    use SpamProtection\Exceptions\PropertyConflictedException;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtection\Utilities\Hashing;
    use SpamProtectionBot;
    use TelegramClientManager\Abstracts\SearchMethods\TelegramClientSearchMethod;
    use TelegramClientManager\Abstracts\TelegramChatType;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;

    /**
     * Start command
     *
     * Gets executed when a user first starts using the bot.
     */
    class StartCommand extends SystemCommand
    {
        /**
         * @var string
         */
        protected $name = 'start';

        /**
         * @var string
         */
        protected $description = 'Gets executed when a user first starts using the bot.';

        /**
         * @var string
         */
        protected $usage = '/start';

        /**
         * @var string
         */
        protected $version = '2.0.0';

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
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramException
         * @noinspection DuplicatedCode
         */
        public function execute(): ServerResponse
        {
            // Find all clients
            $this->WhoisCommand = new WhoisCommand($this->telegram, $this->update);
            $this->WhoisCommand->findClients();

            // Tally DeepAnalytics
            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'start_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->WhoisCommand->ChatObject->ID);
            $DeepAnalytics->tally('tg_spam_protection', 'start_command', (int)$this->WhoisCommand->ChatObject->ID);

            // Ignore forwarded commands
            if($this->getMessage()->getForwardFrom() !== null || $this->getMessage()->getForwardFromChat())
            {
                return Request::emptyResponse();
            }

            if($this->getMessage()->getText(true) !== null)
            {
                if(strlen($this->getMessage()->getText(true)) > 3)
                {
                    switch(mb_substr($this->getMessage()->getText(true), 0, 3))
                    {
                        case "00_":
                            return $this->whoisLookup((int)mb_substr($this->getMessage()->getText(true), 3));
                    }
                }

                switch(strtolower($this->getMessage()->getText(true)))
                {
                    case "help":
                        $HelpCommand = new HelpCommand($this->getTelegram(), $this->getUpdate());
                        return $HelpCommand->execute();

                    case "add":
                        if($this->WhoisCommand->ChatObject->Type == TelegramChatType::Group || $this->WhoisCommand->ChatObject->Type == TelegramChatType::SuperGroup)
                        {
                            $InlineKeyboard = new InlineKeyboard([
                                [
                                    "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Help"),
                                    "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=help"
                                ]
                            ]);

                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "reply_markup" => $InlineKeyboard,
                                "parse_mode" => "html",
                                "text" =>
                                    LanguageCommand::localizeChatText($this->WhoisCommand, "Thanks for adding me! Remember to give me the following permissions") . "\n\n".
                                    " - <code>" . LanguageCommand::localizeChatText($this->WhoisCommand, "Delete Messages") . "</code>\n".
                                    " - <code>" . LanguageCommand::localizeChatText($this->WhoisCommand, "Ban Users") . "</code>\n\n".
                                    str_ireplace("%s", "/help",
                                        LanguageCommand::localizeChatText($this->WhoisCommand,
                                            "If you need help with setting this up bot, see the %s command", ['s']
                                        )) . "\n\n".
                                    str_ireplace("%s", "@SpamProtectionSupport",
                                        LanguageCommand::localizeChatText($this->WhoisCommand,
                                        "I will actively detect and remove spam and I will ban blacklisted users such as spammers, ".
                                        "scammers and raiders, if you need any help then feel free to reach out to us at %s", ['s']))
                            ]);
                        }
                }
            }

            switch($this->WhoisCommand->ChatObject->Type)
            {
                case TelegramChatType::SuperGroup:
                case TelegramChatType::Group:
                    $ResetCacheCommand = new ResetCacheCommand($this->telegram, $this->update);

                    if ($ResetCacheCommand->isAdmin($this->WhoisCommand, $this->WhoisCommand->UserClient) == true)
                    {
                        return Request::sendMessage([
                            "chat_id" => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "reply_markup" => new InlineKeyboard([
                                [
                                    "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Help"),
                                    "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=start"
                                ]
                            ]),
                            "parse_mode" => "html",
                            "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Hey there! Looking for help?")
                        ]);
                    }
                    else
                        return Request::emptyResponse();
                        

                case TelegramChatType::Private:
                    $AppealResponse = $this->processAppeal();
                    if ($AppealResponse !== null)
                        return $AppealResponse;

                    return Request::sendVideo([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "reply_markup" => new InlineKeyboard(
                            [
                                ["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Help"), "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=help"],
                                ["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Logs"), "url" => "https://t.me/" . LOG_CHANNEL],
                                ["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Support"), "url" => "https://t.me/SpamProtectionSupport"]
                            ],
                            [
                                ["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Add to group"), "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?startgroup=add"]
                            ],
                            [
                                ["text" => "\u{1F310} " . LanguageCommand::localizeChatText($this->WhoisCommand, "Change Language"), "callback_data" => "02"]
                            ]
                        ),
                        "video" => "https://telegra.ph/file/08da75a7dcfa11fb2329d.mp4",
                        "allow_sending_without_reply" => true,
                        "supports_streaming" => true,
                        "parse_mode" => "html",
                        "caption" =>
                            "<b>SpamProtectionBot</b>\n\n" .
                            LanguageCommand::localizeChatText($this->WhoisCommand,
                            "Using machine learning, this bot is capable of detecting and deleting spam from your chat ".
                                "and stop unwanted users from having the chance to post spam in your chat.") . "\n\n".
                            LanguageCommand::localizeChatText($this->WhoisCommand,
                                "if you notice any mistakes or issues then feel free to report it to the official support chat")
                    ]);

                default:
                    break;
            }

            return Request::emptyResponse();
        }

        /**
         * Performs a whois lookup of a user ID
         *
         * @param int $user_id
         * @return ServerResponse
         * @throws TelegramException
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         */
        public function whoisLookup(int $user_id): ServerResponse
        {
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
            $EstimatedPrivateID = Hashing::telegramClientPublicID((int)$user_id, (int)$user_id);
            $WhoisLookup = new WhoisCommand($this->getTelegram());

            try
            {
                $TargetTelegramClient = $TelegramClientManager->getTelegramClientManager()->getClient(
                    TelegramClientSearchMethod::byPublicId, $EstimatedPrivateID
                );

                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "parse_mode" => "html",
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "text" => $WhoisLookup->resolveTarget($TargetTelegramClient, false, "None", false)
                ]);
            }
            catch(TelegramClientNotFoundException $telegramClientNotFoundException)
            {
                unset($telegramClientNotFoundException);
            }

            return Request::sendMessage([
                "chat_id" => $this->getMessage()->getChat()->getId(),
                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                "text" => str_ireplace("%s", $user_id, LanguageCommand::localizeChatText($this->WhoisCommand, "Unable to resolve the query %s", ['s']))
            ]);
        }

        /**
         * @return ServerResponse|null
         * @throws TelegramException
         */
        public function processAppeal(): ?ServerResponse
        {
            $UserStatus = SettingsManager::getUserStatus($this->WhoisCommand->UserClient);
            if($UserStatus->IsBlacklisted)
            {
                $AppealText =
                    str_ireplace("%s", WhoisCommand::generateMention($this->WhoisCommand->UserClient), LanguageCommand::localizeChatText($this->WhoisCommand,
                        "Hi %s, It looks like you've been blacklisted by one of our operators for your misconduct. users ".
                        "use SpamProtectionBot to protect their groups from spammers and raids and one of our operators ".
                        "flagged you under one of these categories.", ['s']));
                $ConclusionText = LanguageCommand::localizeChatText($this->WhoisCommand,
                    "We try to provide as much transparency as possible so that's why we make all evidence ".
                    "(except for child abuse and porn) publicly accessible at %s, you can find proof to your blacklist reason ".
                    "by searching your Private Telegram ID %b in the channel. Alternatively, you can %u and view the logs in your web browser.",
                    ['s', 'b', 'u']);
                $ConclusionText = str_ireplace("%s", "@" . LOG_CHANNEL, $ConclusionText);
                $ConclusionText = str_ireplace("%b", "(<code>" . $this->WhoisCommand->UserClient->PublicID . "</code>)", $ConclusionText);
                $url = "https://t.me/s/SpamProtectionLogs?q=" . $this->WhoisCommand->UserClient->PublicID;
                $ConclusionText = str_ireplace("%b", "<a href=\"$url\">" . LanguageCommand::localizeChatText($this->WhoisCommand, "click here") . "</a>", $ConclusionText);

                switch($UserStatus->BlacklistFlag)
                {
                    case BlacklistFlag::Spam:
                        $ReasonText = LanguageCommand::localizeChatText($this->WhoisCommand, "You've been blacklisted for posting spam such as unwanted promotional materials & unwanted advertisements in chats.");
                        break;

                    case BlacklistFlag::Special:
                        $ReasonText = LanguageCommand::localizeChatText($this->WhoisCommand, "You've been blacklisted for a unspecified reason that we cannot disclose.");
                        break;

                    case BlacklistFlag::Scam:
                        $ReasonText = LanguageCommand::localizeChatText($this->WhoisCommand, "You've been blacklisted for posting content intended to scam users either financially or for personal information, this is considered to be spam as well if not scamming.");
                        break;

                    case BlacklistFlag::Raid:
                        $ReasonText = LanguageCommand::localizeChatText($this->WhoisCommand, "You've been blacklisted for raiding chats by joining a targeted chat intended to spam and cause disruption or to harass members & administrators of said chat.");
                        break;

                    case BlacklistFlag::PrivateSpam:
                        $ReasonText = LanguageCommand::localizeChatText($this->WhoisCommand, "You've been blacklisted for sending unwanted promotional materials & unwanted advertisements in user's private messages.");
                        break;

                    case BlacklistFlag::PornographicSpam:
                        $ReasonText = LanguageCommand::localizeChatText($this->WhoisCommand, "You've been blacklisted for promoting unwanted promotional materials in relation to pornographic content or spamming pornographic/gore content in chats where it isn't allowed.");
                        break;

                    case BlacklistFlag::PiracySpam:
                        $ReasonText = LanguageCommand::localizeChatText($this->WhoisCommand, "You've been blacklisted for promoting unwanted promotional materials & unwanted advertisements in relation to piracy (copyrighted content being distributed illegally)");
                        break;

                    case BlacklistFlag::NameSpam:
                        $ReasonText = LanguageCommand::localizeChatText($this->WhoisCommand, "You've been blacklisted because your name or biography contains a promotional advert or trying to deceive users into contacting you in relation to the services you offer in your name or biography.");
                        break;

                    case BlacklistFlag::MassAdding:
                        $ReasonText = LanguageCommand::localizeChatText($this->WhoisCommand, "You've been blacklisted for adding users without their permission to chats with the intention to unfairly and annoyingly increase member counts");
                        break;

                    case BlacklistFlag::Impersonator:
                        $ReasonText = LanguageCommand::localizeChatText($this->WhoisCommand, "You've been blacklisted for maliciously trying to impersonate a user or person with the intent to spam, defame or scam.");
                        break;

                    case BlacklistFlag::ChildAbuse:
                        $ReasonText = LanguageCommand::localizeChatText($this->WhoisCommand, "You've been blacklisted for promoting or distributing child pornography & promoting child abuse.");
                        break;

                    case BlacklistFlag::BanEvade:
                        $ReasonText = LanguageCommand::localizeChatText($this->WhoisCommand, "You've been blacklisted for trying to evade your original blacklist reason by using an alternative account.");
                        break;

                    default:
                        $ReasonText = LanguageCommand::localizeChatText($this->WhoisCommand, "You were blacklisted for a reason that we cannot identify.");
                }

                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "reply_markup" => new InlineKeyboard(
                        [
                            ["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Ok"), "callback_data" => "0401"]
                        ],
                        [
                            ["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "I won't do this again"), "callback_data" => "0402"]
                        ],
                        [
                            ["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "This is a mistake"), "callback_data" => "0403"]
                        ]
                    ),
                    "parse_mode" => "html",
                    "force_reply" => true,
                    "text" => $AppealText . "\n\n" . $ReasonText . "\n\n" . $ConclusionText
                ]);
            }

            return Request::emptyResponse();
        }

        /**
         * @param CallbackQuery|null $callbackQuery
         * @param WhoisCommand $whoisCommand
         * @return ServerResponse
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @throws InvalidBlacklistFlagException
         * @throws MissingOriginalPrivateIdException
         * @throws PropertyConflictedException
         */
        public function handleAppealCallback(?CallbackQuery $callbackQuery, WhoisCommand $whoisCommand): ServerResponse
        {
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
            $TargetClient = null;

            if($callbackQuery !== null)
            {
                $UserStatus = SettingsManager::getUserStatus($whoisCommand->CallbackQueryUserClient);
                $TargetClient = $whoisCommand->CallbackQueryUserClient;
            }
            else
            {
                $UserStatus = SettingsManager::getUserStatus($whoisCommand->UserClient);
                $TargetClient = $whoisCommand->UserClient;
            }

            if($callbackQuery !== null)
            {
                switch($callbackQuery->getData())
                {
                    case "0401":
                        return Request::editMessageText([
                            "chat_id" => $callbackQuery->getMessage()->getChat()->getId(),
                            "message_id" => $callbackQuery->getMessage()->getMessageId(),
                            "text" => str_ireplace("%s", "@SpamProtectionSupport", LanguageCommand::localizeChatText($whoisCommand,
                                "If you have any questions, please don't hesitate to join %s and ask questions", ['s']))
                        ]);

                    case "0403":
                        return Request::editMessageText([
                            "chat_id" => $callbackQuery->getMessage()->getChat()->getId(),
                            "message_id" => $callbackQuery->getMessage()->getMessageId(),
                            "text" => str_ireplace("%s", "@SpamProtectionSupport", LanguageCommand::localizeChatText($whoisCommand,
                                "If you believe this was a mistake then please join %s and explain why you think this was a mistake", ['s']))
                        ]);

                    case "0402":
                        switch($UserStatus->BlacklistFlag)
                        {
                            case BlacklistFlag::ChildAbuse:
                                return Request::editMessageText([
                                    "chat_id" => $callbackQuery->getMessage()->getChat()->getId(),
                                    "message_id" => $callbackQuery->getMessage()->getMessageId(),
                                    "text" => str_ireplace("%s", "@SpamProtectionSupport", LanguageCommand::localizeChatText($whoisCommand,
                                        "We're sorry, but we cannot allow you to appeal for Child Abuse cases. contact %s", ['s']))
                                ]);

                            case BlacklistFlag::PornographicSpam:
                                return Request::editMessageText([
                                    "chat_id" => $callbackQuery->getMessage()->getChat()->getId(),
                                    "message_id" => $callbackQuery->getMessage()->getMessageId(),
                                    "text" => str_ireplace("%s", "@SpamProtectionSupport", LanguageCommand::localizeChatText($whoisCommand,
                                        "We're sorry, but we cannot allow you to appeal for pornographic spam cases. contact %s", ['s']))
                                ]);

                            case BlacklistFlag::Special:
                                return Request::editMessageText([
                                    "chat_id" => $callbackQuery->getMessage()->getChat()->getId(),
                                    "message_id" => $callbackQuery->getMessage()->getMessageId(),
                                    "text" => str_ireplace("%s", "@SpamProtectionSupport", LanguageCommand::localizeChatText($whoisCommand,
                                        "We need to talk to you before appealing, please contact contact %s", ['s']))
                                ]);

                            default:
                                if($UserStatus->CanAppeal == false)
                                {
                                    return Request::editMessageText([
                                        "chat_id" => $callbackQuery->getMessage()->getChat()->getId(),
                                        "message_id" => $callbackQuery->getMessage()->getMessageId(),
                                        "text" => str_ireplace("%s", "@SpamProtectionSupport", LanguageCommand::localizeChatText($whoisCommand,
                                            "You are not eligible for an automatic appeal process, you need to contact %s for further assistance", ['s']))
                                    ]);
                                }

                                $previous_flag = $UserStatus->BlacklistFlag;
                                $UserStatus->CanAppeal = false;
                                $UserStatus->updateBlacklist(BlacklistFlag::None);
                                $TargetClient = SettingsManager::updateUserStatus($TargetClient, $UserStatus);
                                $TelegramClientManager->getTelegramClientManager()->updateClient($TargetClient);

                                $LogMessage = "#automatic_appeal\n\n";
                                $LogMessage .= "<b>Private Telegram ID:</b> <code>" . $TargetClient->PublicID . "</code>\n";
                                $LogMessage .= "\n<i>The previous blacklist flag</i> <code>$previous_flag</code> <i>has been lifted through an automatic appeal process</i>";


                                $InlineKeyboard = new InlineKeyboard([
                                    [
                                        "text" => "View Target",
                                        "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $TargetClient->User->ID
                                    ]
                                ]);

                                Request::sendMessage([
                                    "chat_id" => "@" . LOG_CHANNEL,
                                    "disable_web_page_preview" => true,
                                    "disable_notification" => true,
                                    "reply_markup" => $InlineKeyboard,
                                    "parse_mode" => "html",
                                    "text" => $LogMessage
                                ]);

                                return Request::editMessageText([
                                    "chat_id" => $callbackQuery->getMessage()->getChat()->getId(),
                                    "message_id" => $callbackQuery->getMessage()->getMessageId(),
                                    "text" => LanguageCommand::localizeChatText($whoisCommand, "Your appeal has been approved and your blacklist flag has been successfully lifted")
                                ]);

                        }
                }
            }

            return Request::emptyResponse();
        }
    }