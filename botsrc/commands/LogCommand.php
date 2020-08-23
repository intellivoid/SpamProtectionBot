<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\UserCommands;

    use Exception;
    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\InlineKeyboard;
    use Longman\TelegramBot\Entities\Message;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use pop\pop;
    use SpamProtection\Abstracts\BlacklistFlag;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtectionBot;
    use TelegramClientManager\Abstracts\TelegramChatType;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;
    use TelegramClientManager\Objects\TelegramClient;

    /**
     * Log message command
     *
     * Allows an operator or agent to log spam manually if it wasn't detected or if it was forwarded evidence
     */
    class LogCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'log';

        /**
         * @var string
         */
        protected $description = 'Allows an operator or agent to log spam manually if it wasn\'t detected or if it was forwarded evidence';

        /**
         * @var string
         */
        protected $usage = '/log (-f if forwarded content) (-u if forwarded content is from channel, this option will also effect the user) [Reply]';

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
         * Command execute method
         *
         * @return ServerResponse
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @throws DatabaseException
         * @noinspection DuplicatedCode
         */
        public function execute()
        {
            // Find clients
            $this->WhoisCommand = new WhoisCommand($this->telegram, $this->update);
            $this->WhoisCommand->findClients();
            $this->DestinationChat = $this->WhoisCommand->ChatObject;
            $this->ReplyToID = $this->getMessage()->getMessageId();

            // Tally DeepAnalytics
            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'log_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->WhoisCommand->ChatObject->ID);
            $DeepAnalytics->tally('tg_spam_protection', 'log_command', (int)$this->WhoisCommand->ChatObject->ID);

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

            // Check the permissions
            $UserStatus = SettingsManager::getUserStatus($this->WhoisCommand->UserClient);
            if($UserStatus->IsOperator == false)
            {
                if($UserStatus->IsAgent == false)
                {
                    if($this->CompleteSilentMode == false)
                    {
                        return Request::sendMessage([
                            "chat_id" => $this->DestinationChat->ID,
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            "text" => "This command can only be used by an operator or agent!"
                        ]);
                    }
                    else
                    {
                        return null;
                    }
                }
            }

            // Parse the parameters
            if($this->getMessage()->getReplyToMessage() !== null)
            {
                if($this->getMessage()->getText(true) !== null && strlen($this->getMessage()->getText(true)) > 0)
                {
                    $options = pop::parse($this->getMessage()->getText(true));

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

                        // If the target user is the reply target too
                        if(isset($options["t"]))
                        {
                            $TargetReplyUser = $this->WhoisCommand->findTarget();

                            if($TargetReplyUser == null)
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
                                        "text" => "Unable to get the target user/channel from the reply message (-t option)"
                                    ]);
                                }
                                else
                                {
                                    return null;
                                }
                            }

                            $Message = \SpamProtection\Objects\TelegramObjects\Message::fromArray($this->getMessage()->getReplyToMessage()->getRawData());
                            $Message->ForwardFrom = $TargetUser;
                            $Message->From = $TargetReplyUser->User;
                            $Message->Chat = $TargetReplyUser->Chat;

                            $this->processBlacklistSubCommand($TargetReplyUser);
                            $this->logSpam($TargetReplyUser, $this->WhoisCommand->UserClient, $Message);
                        }

                        // Log the message from the forwarded content
                        $Message = \SpamProtection\Objects\TelegramObjects\Message::fromArray($this->getMessage()->getReplyToMessage()->getRawData());
                        $Message->ForwardFrom = null;
                        $Message->From = $TargetUser->User;
                        $Message->Chat = $TargetUser->Chat;

                        $this->processBlacklistSubCommand($TargetUser);
                        return $this->logSpam($TargetUser, $this->WhoisCommand->UserClient, $Message);
                    }
                }

                $TargetReplyUser = $this->WhoisCommand->findTarget();

                if($TargetReplyUser == null)
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
                            "text" => "Unable to get the target user/channel from the reply message"
                        ]);
                    }
                    else
                    {
                        return null;
                    }
                }

                $this->processBlacklistSubCommand($TargetReplyUser);
                $Message = \SpamProtection\Objects\TelegramObjects\Message::fromArray($this->getMessage()->getReplyToMessage()->getRawData());
                return $this->logSpam($TargetReplyUser, $this->WhoisCommand->UserClient, $Message);
            }

            return self::displayUsage($this->getMessage(), "Missing target message");
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
            if($this->CompleteSilentMode == false)
            {
                return Request::sendMessage([
                    "chat_id" => $message->getChat()->getId(),
                    "parse_mode" => "html",
                    "reply_to_message_id" => $message->getMessageId(),
                    "text" =>
                        "$error\n\n" .
                        "Usage:\n" .
                        "   <b>/log</b> (In reply to target user)\n".
                        "   <b>/log</b> (In reply to forwarded content) -f\n".
                        "For further instructions, send /help log"
                ]);
            }
            else
            {
                return null;
            }
        }

        /**
         * Processes a blacklist sub command
         *
         * @param TelegramClient $targetClient
         * @return ServerResponse|null
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @noinspection DuplicatedCode
         */
        public function processBlacklistSubCommand(TelegramClient $targetClient)
        {
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
                return null;
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

            $BlacklistCommand = new BlacklistCommand($this->getTelegram(), $this->getUpdate());
            $BlacklistCommand->subExecute();
            return $BlacklistCommand->blacklistTarget($targetClient, $this->WhoisCommand->UserClient, $BlacklistFlag, $OriginalPrivateID);
        }

        /**
         * Manually logs the message
         *
         * @param TelegramClient $targetUserClient
         * @param TelegramClient $operatorClient
         * @param \SpamProtection\Objects\TelegramObjects\Message $message
         * @return ServerResponse
         * @throws TelegramException
         */
        public function logSpam(TelegramClient $targetUserClient, TelegramClient $operatorClient, \SpamProtection\Objects\TelegramObjects\Message $message)
        {
            $SpamProtection = SpamProtectionBot::getSpamProtection();

            if($targetUserClient->Chat->Type == TelegramChatType::Private)
            {
                $UserStatus = SettingsManager::getUserStatus($targetUserClient);

                if($UserStatus->IsOperator)
                {
                    if($this->CompleteSilentMode == false)
                    {
                        return Request::sendMessage([
                            "chat_id" => $this->DestinationChat->ID,
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            "text" => "You can't log an operator"
                        ]);
                    }
                    else
                    {
                        return null;
                    }
                }

                if($UserStatus->IsAgent)
                {
                    if($this->CompleteSilentMode == false)
                    {
                        return Request::sendMessage([
                            "chat_id" => $this->DestinationChat->ID,
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            "text" => "You can't log an agent"
                        ]);
                    }
                    else
                    {
                        return null;
                    }
                }

                if($UserStatus->IsWhitelisted)
                {
                    if($this->CompleteSilentMode == false)
                    {
                        return Request::sendMessage([
                            "chat_id" => $this->DestinationChat->ID,
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            "text" => "You can't log a user who's whitelisted"
                        ]);
                    }
                    else
                    {
                        return null;
                    }
                }
            }

            if($targetUserClient->Chat->Type == TelegramChatType::Channel)
            {
                $ChannelStatus = SettingsManager::getChannelStatus($targetUserClient);

                if($ChannelStatus->IsWhitelisted)
                {
                    if($this->CompleteSilentMode == false)
                    {
                        return Request::sendMessage([
                            "chat_id" => $this->DestinationChat->ID,
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            "text" => "You can't log a channel that's whitelisted"
                        ]);
                    }
                    else
                    {
                        return null;
                    }
                }

                if($ChannelStatus->IsOfficial)
                {
                    if($this->SilentMode == false)
                    {
                        Request::sendMessage([
                            "chat_id" => $this->DestinationChat->ID,
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            "text" => "Notice! This channel is considered to be official, this does not protect it from logging though."
                        ]);
                    }
                }
            }

            if($message->getText() == null)
            {
                if($message->Photo == null)
                {
                    if($this->CompleteSilentMode == false)
                    {
                        return Request::sendMessage([
                            "chat_id" => $this->DestinationChat->ID,
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            "text" => "This message type isn't supported yet, archive this message yourself if necessary"
                        ]);
                    }
                    else
                    {
                        return null;
                    }
                }
            }

            if($message->Photo !== null)
            {
                if(count($message->Photo) > 0)
                {
                    $photoSize = $message->Photo[(count($message->Photo) - 1)];
                    $File = Request::getFile(["file_id" => $photoSize->FileID]);

                    if($File->isOk())
                    {
                        $photoSize->URL = Request::downloadFileLocation($File->getResult());
                        $photoSize->HamPrediction = 0;
                        $photoSize->SpamPrediction = 0;
                        $message->Photo = [$photoSize];
                    }
                }
            }

            try
            {
                $MessageLogs = $SpamProtection->getMessageLogManager()->registerMessages($message, 0, 0);
            }
            catch(Exception $e)
            {
                if($this->CompleteSilentMode == false)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" =>
                            "Oops! Something went wrong! contact someone in @IntellivoidDiscussions\n\n" .
                            "Error Code: <code>" . $e->getCode() . "</code>\n" .
                            "Object: <code>Commands/log.bin</code>"
                    ]);
                }
                else
                {
                    return null;
                }
            }

            $MessageHashes = "";

            foreach($MessageLogs as $MessageLogObject)
            {
                $MessageHashes .= "<code>" . $MessageLogObject->MessageHash . "</code>\n";

                $LogMessage = "#spam\n\n";
                if($targetUserClient->Chat->Type == TelegramChatType::Private)
                {
                    $LogMessage .= "<b>Private Telegram ID:</b> <code>" . $targetUserClient->PublicID . "</code>\n";
                }
                else
                {
                    $LogMessage .= "<b>Channel PTID:</b> <code>" . $targetUserClient->PublicID . "</code>\n";
                }
                $LogMessage .= "<b>Operator PTID:</b> <code>" . $operatorClient->PublicID . "</code>\n";
                $LogMessage .= "<b>Message Hash:</b> <code>" . $MessageLogObject->MessageHash . "</code>\n";
                $LogMessage .= "<b>Timestamp:</b> <code>" . $MessageLogObject->Timestamp . "</code>";

                // TODO: Make it callback
                $InlineKeyboard = new InlineKeyboard(
                    [
                        [
                            "text" => "View Target",
                            "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $targetUserClient->User->ID
                        ],
                        [
                            "text" => "View Operator",
                            "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $operatorClient->User->ID
                        ]
                    ]
                );

                if($MessageLogObject->PhotoSize->URL == null)
                {
                    if($message->getText() !== null)
                    {
                        $LogMessageWithContent = $LogMessage . "\n\n<i>===== CONTENT =====</i>\n\n" . self::escapeHTML($message->getText());
                        if(strlen($LogMessageWithContent) > 4096)
                        {
                            $LogMessage .= "\n\nThe content is too large to be shown\n";
                        }
                        else
                        {
                            $LogMessage = $LogMessageWithContent;
                        }

                        Request::sendMessage([
                            "chat_id" => "@" . LOG_CHANNEL,
                            "disable_web_page_preview" => true,
                            "disable_notification" => true,
                            "reply_markup" => $InlineKeyboard,
                            "parse_mode" => "html",
                            "text" => $LogMessage
                        ]);

                        continue;
                    }
                }
                else
                {

                    Request::sendPhoto([
                        "chat_id" => "@" . LOG_CHANNEL,
                        "photo" => $MessageLogObject->PhotoSize->FileID,
                        "disable_notification" => true,
                        "reply_markup" => $InlineKeyboard,
                        "parse_mode" => "html",
                        "caption" => $LogMessage,
                    ]);

                    continue;
                }

                Request::sendMessage([
                    "chat_id" => "@" . LOG_CHANNEL,
                    "disable_web_page_preview" => true,
                    "disable_notification" => true,
                    "reply_markup" => $InlineKeyboard,
                    "parse_mode" => "html",
                    "text" => $LogMessage
                ]);
            }

            if(count($MessageLogs) > 1)
            {
                if($this->SilentMode == false)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "Multiple messages has been archived successfully.\n\n". $MessageHashes
                    ]);
                }
                else
                {
                    return null;
                }
            }
            else
            {
                if($this->SilentMode == false)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" =>
                            "This message has been archived successfully.\n\n".
                            "<code>" . $MessageLogs[0]->MessageHash . "</code>"
                    ]);
                }
                else
                {
                    return null;
                }
            }

        }

        /**
         * Escapes problematic characters for HTML content
         *
         * @param string $input
         * @return string
         */
        private static function escapeHTML(string $input): string
        {
            $input = str_ireplace("<", "&lt;", $input);
            $input = str_ireplace(">", "&gt;", $input);
            $input = str_ireplace("&", "&amp;", $input);

            return $input;
        }

    }