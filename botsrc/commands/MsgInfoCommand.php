<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\UserCommands;

    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\InlineKeyboard;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use pop\pop;
    use SpamProtection\Exceptions\DatabaseException;
    use SpamProtection\Exceptions\MessageLogNotFoundException;
    use SpamProtection\Utilities\Hashing;
    use SpamProtectionBot;
    use TelegramClientManager\Abstracts\TelegramChatType;
    use TelegramClientManager\Objects\TelegramClient\Chat;

    /**
     * Message Information command
     *
     * Allows further details about the message hash to be returned
     */
    class MsgInfoCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'msginfo';

        /**
         * @var string
         */
        protected $description = 'Returns further details about the message hash';

        /**
         * @var string
         */
        protected $usage = '/msginfo [Message Hash]';

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
         * When enabled, the results will be sent privately and
         * the message will be deleted
         *
         * @var bool
         */
        public $PrivateMode = false;

        /**
         * The destination chat relative to the private mode
         *
         * @var Chat|null
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
         * @throws TelegramException
         * @throws DatabaseException
         * @noinspection DuplicatedCode
         */
        public function execute()
        {
            // Find all clients
            $this->WhoisCommand = new WhoisCommand($this->telegram, $this->update);
            $this->WhoisCommand->findClients();
            $this->DestinationChat = $this->WhoisCommand->ChatObject;
            $this->ReplyToID = $this->getMessage()->getMessageId();

            // Tally DeepAnalytics
            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'msginfo_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->WhoisCommand->ChatObject->ID);
            $DeepAnalytics->tally('tg_spam_protection', 'msginfo_command', (int)$this->WhoisCommand->ChatObject->ID);

            // Ignore forwarded commands
            if($this->getMessage()->getForwardFrom() !== null || $this->getMessage()->getForwardFromChat())
            {
                return null;
            }

            // Parse the options
            if($this->getMessage()->getText(true) !== null && strlen($this->getMessage()->getText(true)) > 0)
            {
                $options = pop::parse($this->getMessage()->getText(true));

                if(isset($options["p"]) == true || isset($options["private"]))
                {
                    if($this->WhoisCommand->ChatObject->Type !== TelegramChatType::Private)
                    {
                        $this->PrivateMode = true;
                        $this->DestinationChat = new Chat();
                        $this->DestinationChat->ID = $this->WhoisCommand->UserObject->ID;
                        $this->DestinationChat->Type = TelegramChatType::Private;
                        $this->DestinationChat->FirstName = $this->WhoisCommand->UserObject->FirstName;
                        $this->DestinationChat->LastName = $this->WhoisCommand->UserObject->LastName;
                        $this->DestinationChat->Username = $this->WhoisCommand->UserObject->Username;
                        $this->ReplyToID = null;
                    }
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

            if($this->getMessage()->getText(true) !== null)
            {
                if(strlen($this->getMessage()->getText(true)) > 0)
                {
                    $options = pop::parse($this->getMessage()->getText(true));
                    $TargetMessageParameter = array_values($options)[(count($options)-1)];

                    if(is_bool($TargetMessageParameter))
                    {
                        $TargetMessageParameter = array_keys($options)[(count($options)-1)];
                    }

                    $Results = $this->lookupMessageInfo($TargetMessageParameter);

                    if($Results == null)
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
                            "text" => "Unable to resolve the query '$TargetMessageParameter'!"
                        ]);
                    }

                    return $Results;
                }
            }

            return self::displayUsage("Missing message hash parameter");
        }

        /**
         * Looks up a message hash and returns the service response if successful
         *
         * @param string $target
         * @return ServerResponse|null
         * @throws DatabaseException
         * @throws TelegramException
         */
        public function lookupMessageInfo(string $target)
        {
            try
            {
                $SpamProtection = SpamProtectionBot::getSpamProtection();
                $MessageLog = $SpamProtection->getMessageLogManager()->getMessage($target);

                $Response = "<b>Message Hash Lookup</b>\n\n";

                $UserPrivateID = Hashing::telegramClientPublicID($MessageLog->User->ID, $MessageLog->User->ID);
                $ChatPrivateID = Hashing::telegramClientPublicID($MessageLog->Chat->ID, $MessageLog->Chat->ID);
                $Response .= "<b>Private User ID:</b> <code>" . $UserPrivateID . "</code>\n";
                $Response .= "<b>Private Chat ID:</b> <code>" . $ChatPrivateID . "</code>\n";
                $Response .= "<b>Message ID:</b> <code>" . $MessageLog->ID . "</code>\n";
                $Response .= "<b>Content Hash:</b> <code>" . $MessageLog->ContentHash . "</code>\n";

                if($MessageLog->Chat->Username !== null)
                {
                    $Response .= "<b>Link:</b> <a href=\"https://t.me/" . $MessageLog->Chat->Username . "/" . $MessageLog->MessageID . "\">" . $MessageLog->Chat->Username . "/" . $MessageLog->MessageID . "</a>\n";
                }

                if($MessageLog->ForwardFrom->ID !== null)
                {
                    $ForwardUserPrivateID = Hashing::telegramClientPublicID($MessageLog->ForwardFrom->ID, $MessageLog->ForwardFrom->ID);
                    $Response .= "<b>Original Sender Private ID (Forwarded):</b> <code>" . $ForwardUserPrivateID . "</code>\n";
                }

                if($MessageLog->SpamPrediction > 0 && $MessageLog->HamPrediction > 0)
                {
                    $Response .= "<b>Ham Prediction:</b> <code>" . $MessageLog->HamPrediction . "</code>\n";
                    $Response .= "<b>Spam Prediction:</b> <code>" . $MessageLog->SpamPrediction . "</code>\n";

                    if($MessageLog->SpamPrediction > $MessageLog->HamPrediction)
                    {
                        $Response .= "<b>Is Spam:</b> <code>True</code>\n";
                    }
                    else
                    {
                        $Response .= "<b>Is Spam:</b> <code>False</code>\n";
                    }
                }


                if($MessageLog->ForwardFromChat->ID !== null)
                {
                    if($MessageLog->ForwardFrom->ID !== null)
                    {
                        $InlineKeyboard = new InlineKeyboard(
                            [
                                ["text" => "Chat Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $MessageLog->Chat->ID],
                                ["text" => "User Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $MessageLog->User->ID]
                            ],
                            [
                                ["text" => "Channel Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $MessageLog->ForwardFromChat->ID],
                                ["text" => "Original Sender Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $MessageLog->ForwardFrom->ID]
                            ]
                        );
                    }
                    else
                    {
                        $InlineKeyboard = new InlineKeyboard(
                            [
                                ["text" => "Chat Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $MessageLog->Chat->ID],
                                ["text" => "User Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $MessageLog->User->ID]
                            ],
                            [
                                ["text" => "Channel Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $MessageLog->ForwardFromChat->ID]
                            ]
                        );
                    }
                }
                elseif($MessageLog->ForwardFrom->ID !== null)
                {
                    $InlineKeyboard = new InlineKeyboard(
                        [
                            ["text" => "Chat Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $MessageLog->Chat->ID],
                            ["text" => "User Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $MessageLog->User->ID]
                        ],
                        [
                            ["text" => "Original Sender Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $MessageLog->ForwardFrom->ID]
                        ]
                    );
                }
                else
                {
                    $InlineKeyboard = new InlineKeyboard(
                        [
                            ["text" => "Chat Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $MessageLog->Chat->ID],
                            ["text" => "User Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $MessageLog->User->ID]
                        ]
                    );
                }

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
                    "reply_markup" => $InlineKeyboard,
                    "parse_mode" => "html",
                    "text" => $Response
                ]);
            }
            catch(MessageLogNotFoundException $messageLogNotFoundException)
            {
                unset($messageLogNotFoundException);
            }

            return null;
        }

        /**
         * Displays the command usage
         *
         * @param string $error
         * @return ServerResponse
         * @throws TelegramException
         */
        public function displayUsage(string $error="Missing parameter")
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
                "text" =>
                    "$error\n\n" .
                    "Usage:\n" .
                    "<b>/msginfo</b> <code>[Message Hash]</code>"
            ]);
        }

    }