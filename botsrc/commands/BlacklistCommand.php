<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\UserCommands;

    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\InlineKeyboard;
    use Longman\TelegramBot\Entities\Message;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use pop\pop;
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
    use TelegramClientManager\Objects\TelegramClient;

    /**
     * Blacklist user command
     *
     * Allows the operator with sufficient permissions to blacklist a user, upon blacklisting if the user
     * sends a message or joins a chat with the bot in the chat, the detection action will be made accordingly
     */
    class BlacklistCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = "blacklist";

        /**
         * @var string
         */
        protected $description = "Allows the operator with sufficient permissions to blacklist a user";

        /**
         * @var string
         */
        protected $usage = "/blacklist [Reply/ID/Private Telegram ID/Username/Mention]";

        /**
         * @var string
         */
        protected $version = "2.0.0";

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
         * When enabled, the results will be sent privately and
         * the message will be deleted
         *
         * @var bool
         */
        public $PrivateMode = false;

        /**
         * When enabled, success messages will be suppressed
         *
         * @var bool
         */
        public $SilentMode = false;

        /**
         * When enabled, all messages will be suppressed
         *
         * @var bool
         */
        public $CompleteSilentMode = false;

        /**
         * The destination chat relative to the private mode
         *
         * @var TelegramClient\Chat|null
         */
        public $DestinationChat = null;

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
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @noinspection DuplicatedCode
         */
        public function execute()
        {
            // Find clients
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
            $this->WhoisCommand = new WhoisCommand($this->telegram, $this->update);
            $this->WhoisCommand->findClients();
            $this->DestinationChat = $this->WhoisCommand->ChatObject;
            $this->ReplyToID = $this->getMessage()->getMessageId();

            // Tally DeepAnalytics
            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'blacklist_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->WhoisCommand->ChatObject->ID);
            $DeepAnalytics->tally('tg_spam_protection', 'blacklist_command', (int)$this->WhoisCommand->ChatObject->ID);

            // Ignore forwarded commands
            if($this->getMessage()->getForwardFrom() !== null || $this->getMessage()->getForwardFromChat())
            {
                return null;
            }

            // Parse the options
            if($this->getMessage()->getText(true) !== null && strlen($this->getMessage()->getText(true)) > 0)
            {
                $options = pop::parse($this->getMessage()->getText(true));

                if(isset($options["p"]) == true || isset($options["private"]) == true)
                {
                    if($this->WhoisCommand->ChatObject->Type !== TelegramChatType::Private)
                    {
                        $this->PrivateMode = true;
                        $this->DestinationChat = new TelegramClient\Chat();
                        $this->DestinationChat->ID = $this->WhoisCommand->UserObject->ID;
                        $this->DestinationChat->Type = TelegramChatType::Private;
                        $this->DestinationChat->FirstName = $this->WhoisCommand->UserObject->FirstName;
                        $this->DestinationChat->LastName = $this->WhoisCommand->UserObject->LastName;
                        $this->DestinationChat->Username = $this->WhoisCommand->UserObject->Username;
                        $this->ReplyToID = null;
                    }
                }

                if(isset($options["s"]) == true || isset($options["silent"]) == true)
                {
                    $this->SilentMode = true;
                }

                if(isset($options["cs"]) == true || isset($options["complete-silent"]) == true)
                {
                    $this->SilentMode = true;
                    $this->CompleteSilentMode = true;
                }

                if(isset($options["info"]))
                {
                    if($this->PrivateMode)
                    {
                        Request::deleteMessage([
                            "chat_id" => $this->WhoisCommand->ChatObject->ID,
                            "message_id" => $this->getMessage()->getMessageId()
                        ]);
                    }

                    return Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "parse_mode" => "html",
                        "reply_to_message_id" => $this->ReplyToID,
                        "text" =>
                            $this->name . " (v" . $this->version . ")\n" .
                            " Usage: <code>" . $this->usage . "</code>\n\n" .
                            "<i>" . $this->description . "</i>"
                    ]);
                }
            }

            // Check if permissions are applicable
            $UserStatus = SettingsManager::getUserStatus($this->WhoisCommand->UserClient);
            if($UserStatus->IsOperator == false)
            {
                if($this->PrivateMode)
                {
                    Request::deleteMessage([
                        "chat_id" => $this->WhoisCommand->ChatObject->ID,
                        "message_id" => $this->getMessage()->getMessageId()
                    ]);
                }

                return Request::sendMessage([
                    "chat_id" => $this->DestinationChat->ID,
                    "reply_to_message_id" => $this->ReplyToID,
                    "parse_mode" => "html",
                    "text" => "This command can only be used by an operator!"
                ]);
            }

            $options = [];

            if($this->getMessage()->getText(true) !== null && strlen($this->getMessage()->getText(true)) > 0)
            {
                // NOTE: Argument parsing is done with pop now.
                $options = pop::parse($this->getMessage()->getText(true));
            }

            // Parse the parameters
            $BlacklistFlag = null;
            $OriginalPrivateID = null;

            // Determine blacklist reason
            if(isset($options["r"]) || isset($options["reason"]) || isset($options["flag"]))
            {
                if(isset($options["r"]))
                {
                    $BlacklistFlag = $options["r"];
                }

                if(isset($options["reason"]))
                {
                    $BlacklistFlag = $options["reason"];
                }

                if(isset($options["flag"]))
                {
                    $BlacklistFlag = $options["flag"];
                }

                if(is_bool($BlacklistFlag))
                {
                    return self::displayUsage($this->getMessage(), "Blacklist parameter cannot be empty");
                }
            }
            else
            {
                return self::displayUsage($this->getMessage(), "Missing blacklist parameter option (-r, --reason, --flag)");
            }


            if(isset($options["o"]) || isset($options["optid"]))
            {
                if(isset($options["o"]))
                {
                    $OriginalPrivateID = $options["o"];
                }

                if(isset($options["optid"]))
                {
                    $OriginalPrivateID = $options["optid"];
                }

                if(is_bool($OriginalPrivateID))
                {
                    return self::displayUsage($this->getMessage(), "Original Private Telegram ID parameter cannot be empty");
                }
            }

            if($BlacklistFlag == BlacklistFlag::BanEvade)
            {
                if($OriginalPrivateID == null)
                {
                    return self::displayUsage($this->getMessage(), "Blacklisting a user for ban evade requires the original private telegram ID parameter (-o, -optid)");
                }
            }

            // Is it a reply to message?
            if($this->getMessage()->getReplyToMessage() !== null)
            {
                // If to target the forwarder
                if(isset($options["f"]))
                {
                    $TargetUser = $this->WhoisCommand->findForwardedTarget();

                    if($TargetUser == null)
                    {
                        if($this->PrivateMode)
                        {
                            Request::deleteMessage([
                                "chat_id" => $this->WhoisCommand->ChatObject->ID,
                                "message_id" => $this->getMessage()->getMessageId()
                            ]);
                        }

                        if($this->CompleteSilentMode == false)
                        {
                            return Request::sendMessage([
                                "chat_id" => $this->DestinationChat->ID,
                                "reply_to_message_id" => $this->ReplyToID,
                                "parse_mode" => "html",
                                "text" => "Unable to get the target user/channel from the forwarded message"
                            ]);
                        }
                        else
                        {
                            return null;
                        }


                    }

                    // If it contains the original user ID
                    if($OriginalPrivateID !== null)
                    {
                        return self::blacklistTarget($TargetUser, $this->WhoisCommand->UserClient, $BlacklistFlag, $OriginalPrivateID);
                    }
                    else
                    {
                        return self::blacklistTarget($TargetUser, $this->WhoisCommand->UserClient, $BlacklistFlag);
                    }
                }
                // Target the user the operator replied to
                else
                {
                    $TargetUser = $this->WhoisCommand->findTarget();

                    if($TargetUser == null)
                    {
                        if($this->PrivateMode)
                        {
                            Request::deleteMessage([
                                "chat_id" => $this->WhoisCommand->ChatObject->ID,
                                "message_id" => $this->getMessage()->getMessageId()
                            ]);
                        }

                        if($this->CompleteSilentMode == false)
                        {
                            return Request::sendMessage([
                                "chat_id" => $this->DestinationChat->ID,
                                "reply_to_message_id" => $this->ReplyToID,
                                "parse_mode" => "html",
                                "text" => "Unable to get the target user/channel from the replied message"
                            ]);
                        }
                        else
                        {
                            return null;
                        }

                    }

                    if($OriginalPrivateID !== null)
                    {
                        return self::blacklistTarget($TargetUser, $this->WhoisCommand->UserClient, $BlacklistFlag, $OriginalPrivateID);
                    }
                    else
                    {
                        return self::blacklistTarget($TargetUser, $this->WhoisCommand->UserClient, $BlacklistFlag);
                    }
                }
            }
            // Check if the target user is specified in text or a mention
            else
            {
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
                        return self::blacklistTarget(
                            $TargetUserClient, $this->WhoisCommand->UserClient, $BlacklistFlag, $OriginalPrivateID
                        );
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
                        return self::blacklistTarget(
                            $TargetUserClient, $this->WhoisCommand->UserClient, $BlacklistFlag, $OriginalPrivateID
                        );
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
                        return self::blacklistTarget(
                            $TargetUserClient, $this->WhoisCommand->UserClient, $BlacklistFlag, $OriginalPrivateID
                        );
                    }
                    catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                    {
                        unset($telegramClientNotFoundException);
                    }

                    if($this->PrivateMode)
                    {
                        Request::deleteMessage([
                            "chat_id" => $this->WhoisCommand->ChatObject->ID,
                            "message_id" => $this->getMessage()->getMessageId()
                        ]);
                    }

                    if($this->CompleteSilentMode == false)
                    {
                        return Request::sendMessage([
                            "chat_id" => $this->DestinationChat->ID,
                            "reply_to_message_id" => $this->ReplyToID,
                            "text" => "Unable to resolve the query '$TargetUserParameter'!"
                        ]);
                    }
                    else
                    {
                        return null;
                    }

                }
                else
                {
                    if($this->WhoisCommand->MentionUserClients !== null)
                    {
                        if(count($this->WhoisCommand->MentionUserClients) > 0)
                        {
                            $TargetUserClient = $this->WhoisCommand->MentionUserClients[array_keys($this->WhoisCommand->MentionUserClients)[0]];
                            return self::blacklistTarget($TargetUserClient, $this->WhoisCommand->UserClient, $BlacklistFlag, $OriginalPrivateID);
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
            if($this->PrivateMode)
            {
                Request::deleteMessage([
                    "chat_id" => $this->WhoisCommand->ChatObject->ID,
                    "message_id" => $this->getMessage()->getMessageId()
                ]);
            }

            if($this->CompleteSilentMode == false)
            {
                return Request::sendMessage([
                    "chat_id" => $message->getChat()->getId(),
                    "parse_mode" => "html",
                    "reply_to_message_id" => $message->getMessageId(),
                    "text" =>
                        "$error\n\n" .
                        "Usage:\n" .
                        "   <b>/blacklist</b> (In reply to target user) <code>-r [Blacklist Flag]</code>\n" .
                        "   <b>/blacklist</b> (In reply to forwarded content/channel content) -f <code>-r [Blacklist Flag]</code>\n" .
                        "   <b>/blacklist</b> <code>-u [Private Telegram ID]</code> <code>-r [Blacklist Flag]</code>\n" .
                        "   <b>/blacklist</b> <code>-u [User/Channel ID]</code> <code>-r [Blacklist Flag]</code>\n" .
                        "   <b>/blacklist</b> <code>-u [Username]</code> <code>-r [Blacklist Flag]</code>\n" .
                        "   <b>/blacklist</b> (Mention) <code>-r [Blacklist Flag]</code>\n\n" .
                        "For further instructions, send /help blacklist"
                ]);
            }
            else
            {
                return null;
            }

        }

        /**
         * Takes a blacklist flag and converts it into a user-readable message
         *
         * @param string $flag
         * @return string
         */
        public static function blacklistFlagToReason(string $flag): string
        {
            switch($flag)
            {
                case BlacklistFlag::None:
                    return "None";

                case BlacklistFlag::Spam:
                    return "Spam / Unwanted Promotion";

                case BlacklistFlag::BanEvade:
                    return "Ban Evade";

                case BlacklistFlag::ChildAbuse:
                    return "Child Pornography / Child Abuse";

                case BlacklistFlag::Impersonator:
                    return "Malicious Impersonator";

                case BlacklistFlag::PiracySpam:
                    return "Promotes/Spam Pirated Content";

                case BlacklistFlag::PornographicSpam:
                    return "Promotes/Spam NSFW Content";

                case BlacklistFlag::PrivateSpam:
                    return "Spam / Unwanted Promotion via a unsolicited private message";

                case BlacklistFlag::Raid:
                    return "RAID Initializer / Participator";

                case BlacklistFlag::Scam:
                    return "Scamming";

                case BlacklistFlag::Special:
                    return "Special Reason, consult @IntellivoidSupport";

                case BlacklistFlag::MassAdding:
                    return "Mass adding users to groups/channels";

                case BlacklistFlag::NameSpam:
                    return "Promotion/Spam via Name or Bio";

                default:
                    return "Unknown";
            }
        }

        /**
         * Logs a blacklist action
         *
         * @param TelegramClient $targetClient
         * @param TelegramClient $operatorUserClient
         * @param bool $removal
         * @param bool $update
         * @param string|null $previous_flag
         * @return ServerResponse
         * @throws TelegramException
         */
        private function logAction(TelegramClient $targetClient, TelegramClient $operatorUserClient, bool $removal=false, bool $update=false, string $previous_flag=null)
        {
            switch($targetClient->Chat->Type)
            {
                case TelegramChatType::Private:
                    $TargetUserStatus = SettingsManager::getUserStatus($targetClient);
                    $NewBlacklistFlag = $TargetUserStatus->BlacklistFlag;
                    break;

                case TelegramChatType::Channel:
                    $TargetChannelStatus = SettingsManager::getChannelStatus($targetClient);
                    $NewBlacklistFlag = $TargetChannelStatus->BlacklistFlag;
                    break;

                default:
                    $NewBlacklistFlag = "0x0";
            }

            if($removal)
            {
                $LogMessage = "#blacklist_removal\n\n";
            }
            elseif($update)
            {
                $LogMessage = "#blacklist_update\n\n";
            }
            else
            {
                $LogMessage = "#blacklist\n\n";
            }

            $LogMessage .= "<b>Private Telegram ID:</b> <code>" . $targetClient->PublicID . "</code>\n";
            $LogMessage .= "<b>Operator PTID:</b> <code>" . $operatorUserClient->PublicID . "</code>\n";

            if($removal)
            {
                $LogMessage .= "\n<i>The previous blacklist flag</i> <code>$previous_flag</code> <i>has been lifted</i>";
            }
            elseif($update)
            {
                $LogMessage .= "<b>Previous Flag:</b> <code>" . $previous_flag . "</code> (" . self::blacklistFlagToReason($previous_flag) . ")\n";
                $LogMessage .= "<b>New Flag:</b> <code>" . $NewBlacklistFlag . "</code> (" . self::blacklistFlagToReason($NewBlacklistFlag) . ")\n";
            }
            else
            {
                $LogMessage .= "<b>Blacklist Flag:</b> <code>" . $NewBlacklistFlag. "</code> (" . self::blacklistFlagToReason($NewBlacklistFlag) . ")\n";
            }

            $InlineKeyboard = new InlineKeyboard([
                [
                    "text" => "View Target",
                    "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $targetClient->User->ID
                ],
                [
                    "text" => "View Operator",
                    "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $operatorUserClient->User->ID
                ]
            ]);

            return Request::sendMessage([
                "chat_id" => "-497877807",
                "disable_web_page_preview" => true,
                "disable_notification" => true,
                "reply_markup" => $InlineKeyboard,
                "parse_mode" => "html",
                "text" => $LogMessage
            ]);
        }

        /**
         * Determines the target type and applies the appropriate blacklist flag to the client
         *
         * @param TelegramClient $targetClient
         * @param TelegramClient $operatorClient
         * @param string $blacklistFlag
         * @param string|null $originalPrivateID
         * @return ServerResponse|null
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         */
        public function blacklistTarget(TelegramClient $targetClient, TelegramClient $operatorClient, string $blacklistFlag, string $originalPrivateID=null)
        {
            switch($targetClient->Chat->Type)
            {
                case TelegramChatType::Group:
                case TelegramChatType::SuperGroup:
                    if($this->PrivateMode)
                    {
                        Request::deleteMessage([
                            "chat_id" => $this->WhoisCommand->ChatObject->ID,
                            "message_id" => $this->getMessage()->getMessageId()
                        ]);
                    }

                    if($this->CompleteSilentMode == false)
                    {
                        return Request::sendMessage([
                            "chat_id" => $this->DestinationChat->ID,
                            "reply_to_message_id" => $this->ReplyToID,
                            "parse_mode" => "html",
                            "text" => "You can't blacklist a chat"
                        ]);
                    }
                    else
                    {
                        return null;
                    }

                case TelegramChatType::Private:
                    return self::blacklistUser($targetClient, $operatorClient, $blacklistFlag, $originalPrivateID);

                case TelegramChatType::Channel:
                    return self::blacklistChannel($targetClient, $operatorClient, $blacklistFlag);

                default:
                    if($this->PrivateMode)
                    {
                        Request::deleteMessage([
                            "chat_id" => $this->WhoisCommand->ChatObject->ID,
                            "message_id" => $this->getMessage()->getMessageId()
                        ]);
                    }

                    if($this->CompleteSilentMode == false)
                    {
                        return Request::sendMessage([
                            "chat_id" => $this->DestinationChat->ID,
                            "reply_to_message_id" => $this->ReplyToID,
                            "parse_mode" => "html",
                            "text" => "The entity type '" .  $targetClient->Chat->Type . "' cannot be blacklisted"
                        ]);
                    }
                    else
                    {
                        return null;
                    }
            }
        }

        /**
         * Blacklists a user
         *
         * @param TelegramClient $targetUserClient
         * @param TelegramClient $operatorClient
         * @param string $blacklistFlag
         * @param string|null $originalPrivateID
         * @return ServerResponse
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @noinspection DuplicatedCode
         */
        public function blacklistUser(TelegramClient $targetUserClient, TelegramClient $operatorClient, string $blacklistFlag, string $originalPrivateID=null)
        {
            if($targetUserClient->Chat->Type !== TelegramChatType::Private)
            {
                if($this->PrivateMode)
                {
                    Request::deleteMessage([
                        "chat_id" => $this->WhoisCommand->ChatObject->ID,
                        "message_id" => $this->getMessage()->getMessageId()
                    ]);
                }

                if($this->CompleteSilentMode)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "reply_to_message_id" => $this->ReplyToID,
                        "parse_mode" => "html",
                        "text" => "This operation is not applicable to this entity."
                    ]);
                }
                else
                {
                    return null;
                }
            }

            $UserStatus = SettingsManager::getUserStatus($targetUserClient);
            $OriginalUserStatus = SettingsManager::getUserStatus($targetUserClient);

            if($UserStatus->IsBlacklisted)
            {
                if($UserStatus->BlacklistFlag == $blacklistFlag)
                {
                    if($UserStatus->BlacklistFlag == BlacklistFlag::BanEvade)
                    {
                        if($UserStatus->OriginalPrivateID == $originalPrivateID)
                        {
                            if($this->PrivateMode)
                            {
                                Request::deleteMessage([
                                    "chat_id" => $this->WhoisCommand->ChatObject->ID,
                                    "message_id" => $this->getMessage()->getMessageId()
                                ]);
                            }

                            if($this->CompleteSilentMode == false)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->DestinationChat->ID,
                                    "reply_to_message_id" => $this->ReplyToID,
                                    "parse_mode" => "html",
                                    "text" =>
                                        "This user is already blacklisted for ban evade with the same ".
                                        "Original Private ID, you can only update this user's blacklist if the ".
                                        "Original Private ID is different."
                                ]);
                            }
                            else
                            {
                                return null;
                            }
                        }
                    }
                    else
                    {
                        if($this->PrivateMode)
                        {
                            Request::deleteMessage([
                                "chat_id" => $this->WhoisCommand->ChatObject->ID,
                                "message_id" => $this->getMessage()->getMessageId()
                            ]);
                        }

                        if($this->CompleteSilentMode == false)
                        {
                            return Request::sendMessage([
                                "chat_id" => $this->DestinationChat->ID,
                                "reply_to_message_id" => $this->ReplyToID,
                                "parse_mode" => "html",
                                "text" => "This user is already blacklisted with the same flag."
                            ]);
                        }
                        else
                        {
                            return null;
                        }
                    }
                }
            }

            try
            {
                switch(str_ireplace('X', 'x', strtoupper($blacklistFlag)))
                {
                    case BlacklistFlag::Special:
                        if($operatorClient->User->Username !== "Netkas")
                        {
                            if($this->PrivateMode)
                            {
                                Request::deleteMessage([
                                    "chat_id" => $this->WhoisCommand->ChatObject->ID,
                                    "message_id" => $this->getMessage()->getMessageId()
                                ]);
                            }

                            if($this->CompleteSilentMode == false)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->DestinationChat->ID,
                                    "reply_to_message_id" => $this->ReplyToID,
                                    "parse_mode" => "html",
                                    "text" => "Only IntellivoidSupport can blacklist using the flag 0xSP"
                                ]);
                            }
                            else
                            {
                                return null;
                            }
                        }


                        $UserStatus->updateBlacklist($blacklistFlag);
                        $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                        SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetUserClient);
                        break;

                    case BlacklistFlag::BanEvade:
                        if($originalPrivateID == null)
                        {
                            if($this->PrivateMode)
                            {
                                Request::deleteMessage([
                                    "chat_id" => $this->WhoisCommand->ChatObject->ID,
                                    "message_id" => $this->getMessage()->getMessageId()
                                ]);
                            }

                            if($this->CompleteSilentMode == false)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->DestinationChat->ID,
                                    "reply_to_message_id" => $this->ReplyToID,
                                    "parse_mode" => "html",
                                    "text" => "This blacklist flag requires an additional parameter 'Private Telegram ID'"
                                ]);
                            }
                            else
                            {
                                return null;
                            }
                        }

                        try
                        {
                            SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->getClient(TelegramClientSearchMethod::byPublicId, $originalPrivateID);
                        }
                        catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                        {
                            unset($telegramClientNotFoundException);

                            if($this->PrivateMode)
                            {
                                Request::deleteMessage([
                                    "chat_id" => $this->WhoisCommand->ChatObject->ID,
                                    "message_id" => $this->getMessage()->getMessageId()
                                ]);
                            }

                            if($this->CompleteSilentMode == false)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->DestinationChat->ID,
                                    "reply_to_message_id" => $this->ReplyToID,
                                    "parse_mode" => "html",
                                    "text" => "The given private ID does not exist"
                                ]);
                            }
                            else
                            {
                                return null;
                            }
                        }

                        $UserStatus->updateBlacklist($blacklistFlag, $originalPrivateID);
                        $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                        SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetUserClient);
                        break;

                    default:
                        $UserStatus->updateBlacklist($blacklistFlag);
                        $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                        SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetUserClient);
                        break;
                }
            }
            catch (InvalidBlacklistFlagException $e)
            {
                if($this->PrivateMode)
                {
                    Request::deleteMessage([
                        "chat_id" => $this->WhoisCommand->ChatObject->ID,
                        "message_id" => $this->getMessage()->getMessageId()
                    ]);
                }

                if($this->CompleteSilentMode == false)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "reply_to_message_id" => $this->ReplyToID,
                        "parse_mode" => "html",
                        "text" =>
                            "Invalid blacklist flag <code>" . str_ireplace('X', 'x', strtoupper($blacklistFlag)) . "</code>, did you mean <code>" . $e->getBestMatch() . "</code>?"
                    ]);
                }
                else
                {
                    return null;
                }
            }
            catch (MissingOriginalPrivateIdException $e)
            {
                if($this->PrivateMode)
                {
                    Request::deleteMessage([
                        "chat_id" => $this->WhoisCommand->ChatObject->ID,
                        "message_id" => $this->getMessage()->getMessageId()
                    ]);
                }

                if($this->CompleteSilentMode == false)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "reply_to_message_id" => $this->ReplyToID,
                        "parse_mode" => "html",
                        "text" => "Blacklisting a user for ban evade requires the original private telegram ID parameter (-o, -optid)"
                    ]);
                }
                else
                {
                    return null;
                }
            }
            catch (PropertyConflictedException $e)
            {
                if($this->PrivateMode)
                {
                    Request::deleteMessage([
                        "chat_id" => $this->WhoisCommand->ChatObject->ID,
                        "message_id" => $this->getMessage()->getMessageId()
                    ]);
                }

                if($this->CompleteSilentMode == false)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "reply_to_message_id" => $this->ReplyToID,
                        "parse_mode" => "html",
                        "text" => $e->getMessage()
                    ]);
                }
                else
                {
                    return null;
                }
            }

            if($UserStatus->BlacklistFlag == BlacklistFlag::None)
            {
                if($OriginalUserStatus->BlacklistFlag == BlacklistFlag::None)
                {
                    if($this->PrivateMode)
                    {
                        Request::deleteMessage([
                            "chat_id" => $this->WhoisCommand->ChatObject->ID,
                            "message_id" => $this->getMessage()->getMessageId()
                        ]);
                    }

                    if($this->CompleteSilentMode == false)
                    {
                        return Request::sendMessage([
                            "chat_id" => $this->DestinationChat->ID,
                            "reply_to_message_id" => $this->ReplyToID,
                            "parse_mode" => "html",
                            "text" => "The user " .  WhoisCommand::generateMention($targetUserClient) . " already has no blacklist flag."
                        ]);
                    }
                    else
                    {
                        return null;
                    }
                }

                if($this->PrivateMode)
                {
                    Request::deleteMessage([
                        "chat_id" => $this->WhoisCommand->ChatObject->ID,
                        "message_id" => $this->getMessage()->getMessageId()
                    ]);
                }

                if($this->SilentMode == false)
                {
                    Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "reply_to_message_id" => $this->ReplyToID,
                        "parse_mode" => "html",
                        "text" => "The user " .  WhoisCommand::generateMention($targetUserClient) . " blacklist flag has been removed"
                    ]);
                }

                return self::logAction($targetUserClient, $operatorClient, true, false, $OriginalUserStatus->BlacklistFlag);
            }

            if($OriginalUserStatus->IsBlacklisted)
            {
                if($this->PrivateMode)
                {
                    Request::deleteMessage([
                        "chat_id" => $this->WhoisCommand->ChatObject->ID,
                        "message_id" => $this->getMessage()->getMessageId()
                    ]);
                }

                if($this->SilentMode == false)
                {
                    Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "reply_to_message_id" => $this->ReplyToID,
                        "parse_mode" => "html",
                        "text" =>
                            "The user " . WhoisCommand::generateMention($targetUserClient) . " blacklist flag has been updated from " .
                            "<code>" . str_ireplace('X', 'x', strtoupper($OriginalUserStatus->BlacklistFlag)) . "</code> to ".
                            "<code>" . str_ireplace('X', 'x', strtoupper($UserStatus->BlacklistFlag)) . "</code>"
                    ]);
                }

                return self::logAction($targetUserClient, $operatorClient, false, true, $OriginalUserStatus->BlacklistFlag);
            }
            else
            {
                if($this->PrivateMode)
                {
                    Request::deleteMessage([
                        "chat_id" => $this->WhoisCommand->ChatObject->ID,
                        "message_id" => $this->getMessage()->getMessageId()
                    ]);
                }

                if($this->SilentMode == false)
                {
                    Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "reply_to_message_id" => $this->ReplyToID,
                        "parse_mode" => "html",
                        "text" =>
                            "The user " . WhoisCommand::generateMention($targetUserClient) . " has been blacklisted with the flag " .
                            "<code>" . str_ireplace('X', 'x', strtoupper($UserStatus->BlacklistFlag)) . "</code>"
                    ]);
                }

                return self::logAction($targetUserClient, $operatorClient, false, false);
            }
        }

        /**
         * Blacklists a channel
         *
         * @param TelegramClient $targetChannelClient
         * @param TelegramClient $operatorClient
         * @param string $blacklistFlag
         * @return ServerResponse
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @noinspection DuplicatedCode
         */
        public function blacklistChannel(TelegramClient $targetChannelClient, TelegramClient $operatorClient, string $blacklistFlag)
        {
            if($targetChannelClient->Chat->Type !== TelegramChatType::Channel)
            {
                if($this->PrivateMode)
                {
                    Request::deleteMessage([
                        "chat_id" => $this->WhoisCommand->ChatObject->ID,
                        "message_id" => $this->getMessage()->getMessageId()
                    ]);
                }

                if($this->CompleteSilentMode == false)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "reply_to_message_id" => $this->ReplyToID,
                        "parse_mode" => "html",
                        "text" => "This operation is not applicable to this entity."
                    ]);
                }
                else
                {
                    return null;
                }
            }

            $ChannelStatus = SettingsManager::getChannelStatus($targetChannelClient);
            $OriginalChannelStatus = SettingsManager::getChannelStatus($targetChannelClient);

            if($ChannelStatus->IsWhitelisted)
            {
                if($this->PrivateMode)
                {
                    Request::deleteMessage([
                        "chat_id" => $this->WhoisCommand->ChatObject->ID,
                        "message_id" => $this->getMessage()->getMessageId()
                    ]);
                }

                if($this->CompleteSilentMode == false)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "reply_to_message_id" => $this->ReplyToID,
                        "parse_mode" => "html",
                        "text" => "You can't blacklist this channel since it's whitelisted"
                    ]);
                }
                else
                {
                    return null;
                }
            }

            if($ChannelStatus->IsOfficial)
            {
                if($this->PrivateMode)
                {
                    Request::deleteMessage([
                        "chat_id" => $this->WhoisCommand->ChatObject->ID,
                        "message_id" => $this->getMessage()->getMessageId()
                    ]);
                }

                if($this->CompleteSilentMode == false)
                {
                    Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "reply_to_message_id" => $this->ReplyToID,
                        "parse_mode" => "html",
                        "text" => "Notice! This channel is considered to be official by Intellivoid, nothing is stopping you from blacklisting it."
                    ]);
                }
            }

            if($ChannelStatus->IsBlacklisted)
            {
                if($ChannelStatus->BlacklistFlag == $blacklistFlag)
                {
                    if($this->PrivateMode)
                    {
                        Request::deleteMessage([
                            "chat_id" => $this->WhoisCommand->ChatObject->ID,
                            "message_id" => $this->getMessage()->getMessageId()
                        ]);
                    }

                    if($this->CompleteSilentMode == false)
                    {
                        return Request::sendMessage([
                            "chat_id" => $this->DestinationChat->ID,
                            "reply_to_message_id" => $this->ReplyToID,
                            "parse_mode" => "html",
                            "text" => "This channel is already blacklisted with the same flag."
                        ]);
                    }
                    else
                    {
                        return null;
                    }
                }
            }

            try
            {
                switch(str_ireplace('X', 'x', strtoupper($blacklistFlag)))
                {
                    case BlacklistFlag::Special:
                        if($operatorClient->User->Username !== "Netkas")
                        {
                            if($this->PrivateMode)
                            {
                                Request::deleteMessage([
                                    "chat_id" => $this->WhoisCommand->ChatObject->ID,
                                    "message_id" => $this->getMessage()->getMessageId()
                                ]);
                            }

                            if($this->CompleteSilentMode == false)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->DestinationChat->ID,
                                    "reply_to_message_id" => $this->ReplyToID,
                                    "parse_mode" => "html",
                                    "text" => "Only IntellivoidSupport can blacklist using the flag 0xSP"
                                ]);
                            }
                            else
                            {
                                return null;
                            }
                        }

                        $ChannelStatus->updateBlacklist($blacklistFlag);
                        $targetChannelClient = SettingsManager::updateChannelStatus($targetChannelClient, $ChannelStatus);
                        SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetChannelClient);
                        break;

                    case BlacklistFlag::BanEvade:
                        if($this->PrivateMode)
                        {
                            Request::deleteMessage([
                                "chat_id" => $this->WhoisCommand->ChatObject->ID,
                                "message_id" => $this->getMessage()->getMessageId()
                            ]);
                        }

                        if($this->CompleteSilentMode == false)
                        {
                            return Request::sendMessage([
                                "chat_id" => $this->DestinationChat->ID,
                                "reply_to_message_id" => $this->ReplyToID,
                                "parse_mode" => "html",
                                "text" => "You can't blacklist a channel for ban evade."
                            ]);
                        }
                        else
                        {
                            return null;
                        }

                    default:
                        $ChannelStatus->updateBlacklist($blacklistFlag);
                        $targetChannelClient = SettingsManager::updateChannelStatus($targetChannelClient, $ChannelStatus);
                        SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetChannelClient);
                        break;
                }
            }
            catch (InvalidBlacklistFlagException $e)
            {
                if($this->PrivateMode)
                {
                    Request::deleteMessage([
                        "chat_id" => $this->WhoisCommand->ChatObject->ID,
                        "message_id" => $this->getMessage()->getMessageId()
                    ]);
                }

                if($this->CompleteSilentMode == false)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "reply_to_message_id" => $this->ReplyToID,
                        "parse_mode" => "html",
                        "text" =>
                            "Invalid blacklist flag <code>" . str_ireplace('X', 'x', strtoupper($blacklistFlag)) . "</code>, did you mean <code>" . $e->getBestMatch() . "</code>?"
                    ]);
                }
                else
                {
                    return null;
                }
            }
            catch (PropertyConflictedException $e)
            {
                if($this->PrivateMode)
                {
                    Request::deleteMessage([
                        "chat_id" => $this->WhoisCommand->ChatObject->ID,
                        "message_id" => $this->getMessage()->getMessageId()
                    ]);
                }

                if($this->CompleteSilentMode == false)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "reply_to_message_id" => $this->ReplyToID,
                        "parse_mode" => "html",
                        "text" => $e->getMessage()
                    ]);
                }
                else
                {
                    return null;
                }
            }

            if($ChannelStatus->BlacklistFlag == BlacklistFlag::None)
            {
                if($OriginalChannelStatus->BlacklistFlag == BlacklistFlag::None)
                {
                    if($this->PrivateMode)
                    {
                        Request::deleteMessage([
                            "chat_id" => $this->WhoisCommand->ChatObject->ID,
                            "message_id" => $this->getMessage()->getMessageId()
                        ]);
                    }

                    if($this->CompleteSilentMode == false)
                    {
                        return Request::sendMessage([
                            "chat_id" => $this->DestinationChat->ID,
                            "reply_to_message_id" => $this->ReplyToID,
                            "parse_mode" => "html",
                            "text" => "The channel " .  WhoisCommand::generateMention($targetChannelClient) . " already has no blacklist flag."
                        ]);
                    }
                    else
                    {
                        return null;
                    }
                }

                if($this->PrivateMode)
                {
                    Request::deleteMessage([
                        "chat_id" => $this->WhoisCommand->ChatObject->ID,
                        "message_id" => $this->getMessage()->getMessageId()
                    ]);
                }

                if($this->SilentMode == false)
                {
                    Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "reply_to_message_id" => $this->ReplyToID,
                        "parse_mode" => "html",
                        "text" => "The channel " . WhoisCommand::generateMention($targetChannelClient) . " blacklist flag has been removed"
                    ]);
                }

                return self::logAction($targetChannelClient, $operatorClient, true, false, $OriginalChannelStatus->BlacklistFlag);
            }

            if($OriginalChannelStatus->IsBlacklisted)
            {
                if($this->PrivateMode)
                {
                    Request::deleteMessage([
                        "chat_id" => $this->WhoisCommand->ChatObject->ID,
                        "message_id" => $this->getMessage()->getMessageId()
                    ]);
                }

                if($this->SilentMode == false)
                {
                    Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "reply_to_message_id" => $this->ReplyToID,
                        "parse_mode" => "html",
                        "text" =>
                            "The channel " . WhoisCommand::generateMention($targetChannelClient) . " blacklist flag has been updated from " .
                            "<code>" . str_ireplace('X', 'x', strtoupper($OriginalChannelStatus->BlacklistFlag)) . "</code> to ".
                            "<code>" . str_ireplace('X', 'x', strtoupper($ChannelStatus->BlacklistFlag)) . "</code>"
                    ]);
                }

                return self::logAction($targetChannelClient, $operatorClient, false, true, $OriginalChannelStatus->BlacklistFlag);
            }
            else
            {
                if($this->PrivateMode)
                {
                    Request::deleteMessage([
                        "chat_id" => $this->WhoisCommand->ChatObject->ID,
                        "message_id" => $this->getMessage()->getMessageId()
                    ]);
                }

                if($this->SilentMode == false)
                {
                    Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "reply_to_message_id" => $this->ReplyToID,
                        "parse_mode" => "html",
                        "text" =>
                            "The channel " . WhoisCommand::generateMention($targetChannelClient) . " has been blacklisted with the flag " .
                            "<code>" . str_ireplace('X', 'x', strtoupper($ChannelStatus->BlacklistFlag)) . "</code>"
                    ]);
                }

                return self::logAction($targetChannelClient, $operatorClient, false, false);
            }
        }

    }