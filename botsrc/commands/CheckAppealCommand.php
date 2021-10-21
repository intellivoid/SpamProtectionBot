<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Commands\UserCommands\WhoisCommand;
    use Longman\TelegramBot\Commands\UserCommands\LanguageCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Entities\Message;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtectionBot;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtection\Utilities\Hashing;
    use pop\pop;
    use TelegramClientManager\Abstracts\SearchMethods\TelegramClientSearchMethod;
    use TelegramClientManager\Abstracts\TelegramChatType;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;
    use TelegramClientManager\Objects\TelegramClient;

    /**
     * Start command
     *
     * Gets executed when a user first starts using the bot.
     */
    class CheckAppealCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'CheckAppeal';

        /**
         * @var string
         */
        protected $description = 'Check if a user is eligible for an appeal';

        /**
         * @var string
         */
        protected $usage = '/checkappeal [Reply/ID/Private Telegram ID/Username/Mention]';

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
         * The message ID to reply to
         *
         * @var int|null
         */
        public $ReplyToID = null;

        /**
         * Command execute method
         *
         * @return ServerResponse
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
            $DeepAnalytics->tally('tg_spam_protection', 'friends_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->WhoisCommand->ChatObject->ID);
            $DeepAnalytics->tally('tg_spam_protection', 'friends_command', (int)$this->WhoisCommand->ChatObject->ID);

            // Ignore forwarded commands
            if($this->getMessage()->getForwardFrom() !== null || $this->getMessage()->getForwardFromChat())
            {
                return Request::emptyResponse();
            }

            // Check the permissions
            $UserStatus = SettingsManager::getUserStatus($this->WhoisCommand->UserClient);
            if($UserStatus->IsOperator == false && $UserStatus->IsAgent == false)
            {
                return Request::emptyResponse();
            }

            $options = [];

            if($this->getMessage()->getText(true) !== null && strlen($this->getMessage()->getText(true)) > 0)
            {
                // NOTE: Argument parsing is done with pop now.
                $options = pop::parse($this->getMessage()->getText(true));
            }

            $this->ReplyToID = $this->getMessage()->getMessageId();
            $TargetUserClient = null;

            if ($this->getMessage()->getReplyToMessage() !== null)
            {
                $TargetUser = $this->WhoisCommand->findTarget();

                if ($TargetUser == null)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->ReplyToID,
                        "parse_mode" => "html",
                        "text" => "Unable to get the target user/channel from the replied message"
                    ]);
                }

                return self::checkAappeal($TargetUser);
            }
            else
            {
                $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
                $TargetUserParameter = null;

                if(isset($options["u"]) || isset($options["user"]) || isset($options["target"]))
                {
                    if(isset($options["u"]))
                    {
                        $TargetUserParameter = $options["u"];
                    }

                    if(isset($options["user"]))
                    {
                        $TargetUserParameter = $options["user"];
                    }

                    if(isset($options["target"]))
                    {
                        $TargetUserParameter = $options["target"];
                    }

                    if(is_bool($TargetUserParameter))
                    {
                        return self::displayUsage($this->getMessage(), "Original Private Telegram ID parameter cannot be empty");
                    }
                }

                if($TargetUserParameter !== null)
                {
                    $EstimatedPrivateID = Hashing::telegramClientPublicID((int)$TargetUserParameter, (int)$TargetUserParameter);

                    try
                    {
                        $TargetUserClient = $TelegramClientManager->getTelegramClientManager()->getClient(
                            TelegramClientSearchMethod::byPublicId, $EstimatedPrivateID
                        );
                        return self::checkAappeal($TargetUserClient);
                    }
                    catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                    {
                        unset($telegramClientNotFoundException);
                    }

                    try
                    {
                        $TargetUserClient = $TelegramClientManager->getTelegramClientManager()->getClient(
                            TelegramClientSearchMethod::byPublicId, $TargetUserParameter
                        );

                        return self::checkAappeal($TargetUserClient);
                    }
                    catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                    {
                        unset($telegramClientNotFoundException);
                    }


                    try
                    {
                        $TargetUserClient = $TelegramClientManager->getTelegramClientManager()->getClient(
                            TelegramClientSearchMethod::byUsername, str_ireplace("@", "", $TargetUserParameter)
                        );


                        return self::checkAappeal($TargetUserClient);
                    }
                    catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                    {
                        unset($telegramClientNotFoundException);
                    }

                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->ReplyToID,
                        "text" => "Unable to resolve the query '$TargetUserParameter'!"
                    ]);
                }
                else
                {
                    if($this->WhoisCommand->MentionUserClients !== null)
                    {
                        if(count($this->WhoisCommand->MentionUserClients) > 0)
                        {
                            $TargetUserClient = $this->WhoisCommand->MentionUserClients[array_keys($this->WhoisCommand->MentionUserClients)[0]];
                            return self::checkAappeal($TargetUserClient);
                        }
                    }
                }
            }

            return self::displayUsage($this->getMessage(), "Missing user parameter");
        }

        /**
         * Displays the command usage
         *
         * @param Message $message
         * @param string $error
         * @return ServerResponse
         * @throws TelegramException
         */
        public function displayUsage(Message $message, string $error="Missing parameter"): ServerResponse
        {
            return Request::sendMessage([
                "chat_id" => $message->getChat()->getId(),
                "parse_mode" => "html",
                "reply_to_message_id" => $message->getMessageId(),
                "text" =>
                    "$error\n\n" .
                    "Usage:\n" .
                    "   <b>/checkappeal</b> (In reply to target user)\n" .
                    "   <b>/checkappeal</b> <code>-u=[Private Telegram ID]</code>\n" .
                    "   <b>/checkappeal</b> <code>-u [User/Channel ID]</code>\n" .
                    "   <b>/checkappeal</b> <code>-u [Username]</code>\n" .
                    "   <b>/checkappeal</b> (Mention)\n\n" .
                    "For further instructions, send /help checkappeal"
            ]);
        }

        /**
         * Appeals a blacklist
         * 
         * @param TelegramClient $TargetUserClient
         * @return ServerResponse
         * @throws TelegramException
         */
        public function checkAappeal(TelegramClient $TargetUserClient): ServerResponse
        {
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
            $UserStatus = SettingsManager::getUserStatus($TargetUserClient);
            if ($UserStatus->CanAppeal == true)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->ReplyToID,
                    "parse_mode" => "html",
                    "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "This user is eligible for an appeal.")
                ]);
            }
            else
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->ReplyToID,
                    "parse_mode" => "html",
                    "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "This user is not eligible for an appeal.")
                ]);
            }
        }
    }