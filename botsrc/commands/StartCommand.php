<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use Longman\TelegramBot\Commands\SystemCommand;
    use Longman\TelegramBot\Commands\UserCommands\WhoisCommand;
    use Longman\TelegramBot\Entities\InlineKeyboard;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
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
        public function execute()
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


            var_dump($this->getMessage()->getRawData());

            // Ignore forwarded commands
            if($this->getMessage()->getForwardFrom() !== null || $this->getMessage()->getForwardFromChat())
            {
                return null;
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

                var_dump(strtolower($this->getMessage()->getText(true)));

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
                                    "text" => "Help",
                                    "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=help"
                                ]
                            ]);

                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "reply_markup" => $InlineKeyboard,
                                "parse_mode" => "html",
                                "text" =>
                                    "Thanks for adding me! Remember to give me the following permissions\n\n".
                                    " - <code>Delete Messages</code>\n".
                                    " - <code>Ban Users</code>\n\n".
                                    "If you need help with setting this up bot, see the help command\n\n".
                                    "I will actively detect and remove spam and I will ban blacklisted users such as spammers, ".
                                    "scammers and raiders, if you need any help then feel free to reach out to us at @SpamProtectionSupport"
                            ]);
                        }
                }
            }

            switch($this->WhoisCommand->ChatObject->Type)
            {
                case TelegramChatType::SuperGroup:
                case TelegramChatType::Group:
                    $InlineKeyboard = new InlineKeyboard([
                        [
                            "text" => "Help",
                            "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=start"
                        ]
                    ]);

                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "reply_markup" => $InlineKeyboard,
                        "parse_mode" => "html",
                        "text" => "Hey there! Looking for help?"
                    ]);

                case TelegramChatType::Private:
                    $InlineKeyboard = new InlineKeyboard(
                        [
                            ["text" => "Help", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=help"],
                            ["text" => "Logs", "url" => "https://t.me/SpamProtectionLogs"],
                            ["text" => "Support", "url" => "https://t.me/SpamProtectionSupport"]
                        ],
                        [
                            ["text" => "Add to group", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?startgroup=add"]
                        ]
                    );

                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "reply_markup" => $InlineKeyboard,
                        "parse_mode" => "html",
                        "text" =>
                            "<b>SpamProtectionBot</b>\n\n" .
                            "Using machine learning, this bot is capable of detecting and deleting spam from your chat ".
                            "and stop unwanted users from having the chance to post spam in your chat.\n\n".
                            "if you notice any mistakes or issues then feel free to report it to the official support chat"
                    ]);

                default:
                    break;
            }

            return null;
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
                "text" => "Unable to resolve the query '$user_id'!"
            ]);
        }
    }