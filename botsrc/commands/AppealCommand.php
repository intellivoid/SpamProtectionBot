<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Commands\UserCommands\LanguageCommand;
    use Longman\TelegramBot\Commands\UserCommands\WhoisCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Entities\InlineKeyboard;
    use Longman\TelegramBot\Entities\Message;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtection\Abstracts\BlacklistFlag;
    use SpamProtection\Utilities\Hashing;
    use pop\pop;
    use SpamProtectionBot;
    use TelegramClientManager\Objects\TelegramClient;

    /**
     * Start command
     *
     * Gets executed when a user first starts using the bot.
     */
    class AppealCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'Appeal';

        /**
         * @var string
         */
        protected $description = 'Appeal a user\'s blacklist';

        /**
         * @var string
         */
        protected $usage = '/appeal [Reply/ID/Private Telegram ID/Username/Mention]';

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

            // Check if permissions are applicable
            $UserStatus = SettingsManager::getUserStatus($this->WhoisCommand->UserClient);
            if ($UserStatus->IsOperator == false)
            {
                return null;
            }

            $options = [];

            if($this->getMessage()->getText(true) !== null && strlen($this->getMessage()->getText(true)) > 0)
            {
                // NOTE: Argument parsing is done with pop now.
                $options = pop::parse($this->getMessage()->getText(true));
            }

            $TargetUserClient = null;

            if ($this->getMessage()->getReplyToMessage() !== null)
            {
                $TargetUser = $this->WhoisCommand->findTarget();

                if ($TargetUser == null)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "reply_to_message_id" => $this->ReplyToID,
                        "parse_mode" => "html",
                        "text" => "Unable to get the target user/channel from the replied message"
                    ]);
                }

                return self::appeal($TargetUser);
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
                        return self::appeal($TargetUserClient);
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

                        return self::appeal($TargetUserClient);
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


                        return self::appeal($TargetUserClient);
                    }
                    catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                    {
                        unset($telegramClientNotFoundException);
                    }

                    return Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
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
                            return self::appeal($TargetUserClient);
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
        public function displayUsage(Message $message, string $error="Missing parameter")
        {
            return Request::sendMessage([
                "chat_id" => $message->getChat()->getId(),
                "parse_mode" => "html",
                "reply_to_message_id" => $message->getMessageId(),
                "text" =>
                    "$error\n\n" .
                    "Usage:\n" .
                    "   <b>/approve</b> (In reply to target user)\n" .
                    "   <b>/approve</b> <code>-u=[Private Telegram ID]</code>\n" .
                    "   <b>/approve</b> <code>-u [User/Channel ID]</code>\n" .
                    "   <b>/approve</b> <code>-u [Username]</code>\n" .
                    "   <b>/approve</b> (Mention)\n\n" .
                    "For further instructions, send /help approve"
            ]);
        }

        /**
         * Appeals a blacklist
         * 
         * @param TelegramClient $TargetUserClient
         * @return ServerResponse
         * @throws TelegramException
         */
        public function appeal(TelegramClient $TargetUserClient)
        {
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
            $UserStatus = SettingsManager::getUserStatus($TargetUserClient);
            if ($UserStatus->CanAppeal == true)
            {
                // Remove the blacklist, unset the CanAppeal flag.
                if ($UserStatus->IsBlacklisted == true)
                {
                    $TargetOperatorClient = $this->WhoisCommand->UserClient;

                    $previous_flag = $UserStatus->BlacklistFlag;
                    // Remove the CanAppeal flag
                    $UserStatus->CanAppeal = false;
                    // Remove the blacklist.
                    $UserStatus->updateBlacklist(BlacklistFlag::None);
                    $TargetTelegramClient = SettingsManager::updateUserStatus($TargetUserClient, $UserStatus);
                    $TelegramClientManager->getTelegramClientManager()->updateClient($TargetTelegramClient);
                    
                    $LogMessage = "#manual_appeal\n\n";
                    $LogMessage .= "<b>Private Telegram ID:</b> <code>" . $TargetUserClient->PublicID . "</code>\n";
                    $LogMessage .= "<b>Operator PTID:</b> <code>" . $TargetOperatorClient->PublicID . "</code>\n";
                    $LogMessage .= "\n<i>The previous blacklist flag</i> <code>$previous_flag</code> <i>has been lifted through a manual appeal process</i>";


                    $InlineKeyboard = new InlineKeyboard([
                        [
                            "text" => "View Target",
                            "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $TargetUserClient->User->ID
                        ],
                        [
                            "text" => "View Operator",
                            "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $TargetOperatorClient->User->ID
                        ]
                    ]);


                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Success, this user is no longer blacklisted")
                    ]);
                }
                else
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "This user is eligible for an appeal but is not blacklisted.")
                    ]);
                }
            }
            else
            {
                // Not eligable for appeal.
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "This user is not eligible for an appeal.")
                ]);
            }
        }
    }