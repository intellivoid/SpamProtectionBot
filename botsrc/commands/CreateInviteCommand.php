<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\UserCommands;

    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\Chat;
    use Longman\TelegramBot\Entities\Message;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use pop\pop;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtection\Utilities\Hashing;
    use SpamProtectionBot;
    use TelegramClientManager\Abstracts\SearchMethods\TelegramClientSearchMethod;
    use TelegramClientManager\Abstracts\TelegramChatType;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;

    /**
     * Create Invite Command
     *
     * Allows an operator or agent to generate an invite for a private chat
     */
    class CreateInviteCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'CreateInvite';

        /**
         * @var string
         */
        protected $description = 'Allows an operator or agent to generate an invite link for a private chat';

        /**
         * @var string
         */
        protected $usage = '/CreateInvite [ID/PTID/Username]';

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
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramException
         * @noinspection DuplicatedCode
         */
        public function execute()
        {
            // Find clients
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
            $this->WhoisCommand = new WhoisCommand($this->telegram, $this->update);
            $this->WhoisCommand->findClients();

            // Tally DeepAnalytics
            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'create_invite_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->WhoisCommand->ChatObject->ID);
            $DeepAnalytics->tally('tg_spam_protection', 'create_invite_command', (int)$this->WhoisCommand->ChatObject->ID);

            // Ignore forwarded commands
            if($this->getMessage()->getForwardFrom() !== null || $this->getMessage()->getForwardFromChat())
            {
                return null;
            }

            // Parse the options
            if($this->getMessage()->getText(true) !== null && strlen($this->getMessage()->getText(true)) > 0)
            {
                $options = pop::parse($this->getMessage()->getText(true));

                if(isset($options["info"]))
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "parse_mode" => "html",
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "text" =>
                            $this->name . " (v" . $this->version . ")\n" .
                            " Usage: <code>" . $this->usage . "</code>\n\n" .
                            "<i>" . $this->description . "</i>"
                    ]);
                }
            }

            // Check the permissions
            $UserStatus = SettingsManager::getUserStatus($this->WhoisCommand->UserClient);
            if($UserStatus->IsOperator == false)
            {
                if($UserStatus->IsAgent == false)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "This command can only be used by an operator or agent!"
                    ]);
                }
            }

            if($this->getMessage()->getText(true) !== null && strlen($this->getMessage()->getText(true)) > 0)
            {
                $options = pop::parse($this->getMessage()->getText(true));
                $TargetTelegramParameter = array_values($options)[(count($options)-1)];

                if(is_bool($TargetTelegramParameter))
                {
                    $TargetTelegramParameter = array_keys($options)[(count($options)-1)];
                }


                if($TargetTelegramParameter == null)
                {
                    return self::displayUsage($this->getMessage(), "Missing target value parameter");
                }

                $EstimatedPrivateID = Hashing::telegramClientPublicID((int)$TargetTelegramParameter, (int)$TargetTelegramParameter);
                $TargetTelegramClient = null;

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

                if($TargetTelegramClient == null)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "Unable to find the chat '" . self::escapeHTML($TargetTelegramParameter) . "'"
                    ]);
                }
                else
                {
                    if($TargetTelegramClient->Chat->Type !== TelegramChatType::SuperGroup)
                    {
                        if($TargetTelegramClient->Chat->Type !== TelegramChatType::Group)
                        {
                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "text" => "This command is only applicable to groups or super groups"
                            ]);
                        }
                    }
                }

                $ExportResults = Request::exportChatInviteLink(["chat_id" => $TargetTelegramClient->Chat->ID]);
                if($ExportResults->isOk() == false)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "Cannot export chat invite link, " . $ExportResults->getDescription()
                    ]);
                }

                $GetChatResults = Request::getChat(["chat_id" => $TargetTelegramClient->Chat->ID]);
                if($GetChatResults->isOk() == false)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "Cannot get chat, " . $GetChatResults->getDescription()
                    ]);
                }

                /** @var Chat $ChatResults */
                $ChatResults = $GetChatResults->getResult();
                if($ChatResults->getInviteLink() == null)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "The invitation link isn't available for this chat"
                    ]);
                }

                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => $ChatResults->getInviteLink()
                ]);
            }

            return self::displayUsage($this->getMessage());
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
                    "Usage:\n" .
                    "   <b>/createinvite</b> -c [PTID/ID/Username]\n".
                    "For further instructions, send /help createinvite"
            ]);
        }

        /**
         * Escapes problematic characters for HTML content
         *
         * @param string $input
         * @return string
         */
        private static function escapeHTML(string $input): string
        {
            return htmlspecialchars($input, ENT_COMPAT);
        }
    }