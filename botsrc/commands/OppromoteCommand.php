<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Commands\UserCommands\WhoisCommand;
    use Longman\TelegramBot\Commands\UserCommands\LanguageCommand;
    use Longman\TelegramBot\Entities\Message;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use pop\pop;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtection\Utilities\Hashing;
    use TelegramClientManager\Abstracts\SearchMethods\TelegramClientSearchMethod;
    use TelegramClientManager\Abstracts\TelegramChatType;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;
    use SpamProtectionBot;

    /**
     * Oppromote command
     *
     * Promotes a user to an operator
     */
    class OppromoteCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'Oppromote';

        /**
         * @var string
         */
        protected $description = 'Allows a main operator to promote a user to operator';

        /**
         * @var string
         */
        protected $usage = '/oppromote';

        /**
         * @var string
         */
        protected $version = '1.0.0';

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
            // Find all clients
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
            $this->WhoisCommand = new WhoisCommand($this->telegram, $this->update);
            $this->WhoisCommand->findClients();

            // Tally DeepAnalytics
            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'friends_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->WhoisCommand->ChatObject->ID);
            $DeepAnalytics->tally('tg_spam_protection', 'friends_command', (int)$this->WhoisCommand->ChatObject->ID);

            // Ignore forwarded commands
            if($this->getMessage()->getForwardFrom() !== null || $this->getMessage()->getForwardFromChat())
            {
                return null;
            }

            // They must be a main operator
            if(in_array($this->WhoisCommand->UserObject->ID, MAIN_OPERATOR_IDS, true) !== true)
            {
                return null;
            }

            $TargetTelegramClient = null;
            // If the ID was provided as a username or PTID
            if ($this->getMessage()->getText(true) !== null && strlen($this->getMessage()->getText(true)) > 0)
            {
                $options = pop::parse($this->getMessage()->getText(true));
                $TargetTelegramParameter = array_values($options)[(count($options)-1)];

                if(is_bool($TargetTelegramParameter))
                {
                    $TargetTelegramParameter = array_keys($options)[(count($options)-1)];
                }


                if($TargetTelegramParameter == null)
                {
                    return self::displayUsage($this->getMessage(), LanguageCommand::localizeChatText($this->WhoisCommand, "Missing target value parameter"));
                }

                $EstimatedPrivateID = Hashing::telegramClientPublicID((int)$TargetTelegramParameter, (int)$TargetTelegramParameter);
                
                try
                {
                    $TargetTelegramClient = $TelegramClientManager->getTelegramClientManager()->getClient(TelegramClientSearchMethod::byPublicId, $EstimatedPrivateID);
                }
                catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                {
                    unset($telegramClientNotFoundException);
                }

                try
                {
                    $TargetTelegramClient = $TelegramClientManager->getTelegramClientManager()->getClient(
                        TelegramClientSearchMethod::byPublicId, $TargetTelegramParameter
                    );
                }
                catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                {
                    unset($telegramClientNotFoundException);
                }

                try
                {
                    $TargetTelegramClient = $TelegramClientManager->getTelegramClientManager()->getClient(
                        TelegramClientSearchMethod::byUsername, str_ireplace("@", "", $TargetTelegramParameter)
                    );
                }
                catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                {
                    unset($telegramClientNotFoundException);
                }

                if ($TargetTelegramClient == null)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => str_ireplace("%s", self::escapeHTML($TargetTelegramParameter), LanguageCommand::localizeChatText(
                            $this->WhoisCommand, "Unable to find the client '%s'", ['s']
                        ))
                    ]);
                }
            }
            else if ($this->getMessage()->getReplyToMessage() !== null)
            {
                $TargetTelegramClient = $this->WhoisCommand->findTarget();
            }

            if($TargetTelegramClient == null)
            {
                return self::displayUsage($this->getMessage());
            }

            if ($TargetTelegramClient->Chat->Type !== TelegramChatType::Private)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "This command is not applicable to this entity type")
                ]);
            }

            $UserStatus = SettingsManager::getUserStatus($TargetTelegramClient);
            if ($UserStatus->IsBlacklisted == true)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "This user is blacklisted and cannot be made an operator")
                ]);
            }

            if ($UserStatus->IsOperator == true)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "This user is already an operator")
                ]);
            }

            $UserStatus->IsOperator = true;
            SettingsManager::updateUserStatus($TargetTelegramClient, $UserStatus);
            $TelegramClientManager->getTelegramClientManager()->updateClient($TargetTelegramClient);

            return Request::sendMessage([
                "chat_id" => $this->getMessage()->getChat()->getId(),
                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                "parse_mode" => "html",
                "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Success, this user is now an operator")
            ]);
        }

        /**
         * Displays the command usage
         *
         * @param Message $message
         * @param string $error
         * @return ServerResponse|null
         * @throws TelegramException
         */
        public function displayUsage(Message $message, string $error="Missing parameter")
        {
            return Request::sendMessage([
                "chat_id" => $message->getChat()->getId(),
                "parse_mode" => "html",
                "reply_to_message_id" => $message->getMessageId(),
                "text" =>
                    "$error\n\n" .
                    LanguageCommand::localizeChatText($this->WhoisCommand, "Usage:") . "\n" .
                    "   <b>/oppromote</b> [PTID/ID/Username]\n".
                    LanguageCommand::localizeChatText($this->WhoisCommand, "For further instructions, send /help oppromote")
            ]);
        }

        /**
         * Escapes problematic characters for HTML content
         *
         * @param string $input
         * @return string
         */
        public static function escapeHTML(string $input): string
        {
            return htmlspecialchars($input, ENT_COMPAT);
        }
    }