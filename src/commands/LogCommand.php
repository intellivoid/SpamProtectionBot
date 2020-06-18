<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\UserCommands;

    use DeepAnalytics\DeepAnalytics;
    use Exception;
    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\Message;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Abstracts\TelegramChatType;
    use SpamProtection\Exceptions\DatabaseException;
    use SpamProtection\Exceptions\InvalidSearchMethod;
    use SpamProtection\Exceptions\MessageLogNotFoundException;
    use SpamProtection\Exceptions\TelegramClientNotFoundException;
    use SpamProtection\Exceptions\UnsupportedMessageException;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtection\Objects\TelegramClient;
    use SpamProtection\Objects\TelegramClient\Chat;
    use SpamProtection\Objects\TelegramClient\User;
    use SpamProtection\SpamProtection;

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
        protected $name = 'Log message command';

        /**
         * @var string
         */
        protected $description = 'Allows an operator or agent to log spam manually if it wasn\'t detected or if it was forwarded evidence';

        /**
         * @var string
         */
        protected $usage = '/log (-f if forwarded content) [Reply]';

        /**
         * @var string
         */
        protected $version = '1.0.0';

        /**
         * @var bool
         */
        protected $private_only = false;

        /**
         * Command execute method
         *
         * @return ServerResponse
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws MessageLogNotFoundException
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @throws UnsupportedMessageException
         * @noinspection DuplicatedCode
         */
        public function execute()
        {
            $SpamProtection = new SpamProtection();

            $ChatObject = Chat::fromArray($this->getMessage()->getChat()->getRawData());
            $UserObject = User::fromArray($this->getMessage()->getFrom()->getRawData());

            try
            {
                $TelegramClient = $SpamProtection->getTelegramClientManager()->registerClient($ChatObject, $UserObject);

                // Define and update chat client
                $ChatClient = $SpamProtection->getTelegramClientManager()->registerChat($ChatObject);
                if(isset($UserClient->SessionData->Data["chat_settings"]) == false)
                {
                    $ChatSettings = SettingsManager::getChatSettings($ChatClient);
                    $ChatClient = SettingsManager::updateChatSettings($ChatClient, $ChatSettings);
                }
                $SpamProtection->getTelegramClientManager()->updateClient($ChatClient);

                // Define and update user client
                $UserClient = $SpamProtection->getTelegramClientManager()->registerUser($UserObject);
                if(isset($UserClient->SessionData->Data["user_status"]) == false)
                {
                    $UserStatus = SettingsManager::getUserStatus($UserClient);
                    $UserClient = SettingsManager::updateUserStatus($UserClient, $UserStatus);
                }
                $SpamProtection->getTelegramClientManager()->updateClient($UserClient);

                // Define and update the forwarder if available
                if($this->getMessage()->getForwardFrom() !== null)
                {
                    $ForwardUserObject = User::fromArray($this->getMessage()->getForwardFrom()->getRawData());
                    $ForwardUserClient = $SpamProtection->getTelegramClientManager()->registerUser($ForwardUserObject);
                    if(isset($ForwardUserClient->SessionData->Data["user_status"]) == false)
                    {
                        $ForwardUserStatus = SettingsManager::getUserStatus($ForwardUserClient);
                        $ForwardUserClient = SettingsManager::updateUserStatus($ForwardUserClient, $ForwardUserStatus);
                    }
                    $SpamProtection->getTelegramClientManager()->updateClient($ForwardUserClient);
                }
            }
            catch(Exception $e)
            {

                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "Oops! Something went wrong! contact someone in @IntellivoidDiscussions\n\n" .
                        "Error Code: <code>" . $e->getCode() . "</code>\n" .
                        "Object: <code>Commands/log.bin</code>"
                ]);
            }

            $DeepAnalytics = new DeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'log_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$TelegramClient->getChatId());
            $DeepAnalytics->tally('tg_spam_protection', 'log_command', (int)$TelegramClient->getChatId());

            $UserStatus = SettingsManager::getUserStatus($UserClient);
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

            if($this->getMessage()->getReplyToMessage() !== null)
            {
                $TargetUser = User::fromArray($this->getMessage()->getReplyToMessage()->getFrom()->getRawData());
                $TargetUserClient = $SpamProtection->getTelegramClientManager()->registerUser($TargetUser);

                $CommandParameters = explode(" ", $this->getMessage()->getText(true));
                $CommandParameters = array_values(array_filter($CommandParameters, 'strlen'));

                if(count($CommandParameters) > 0)
                {
                    // If to target the forwarder
                    if(strtolower($CommandParameters[0]) == "-f")
                    {
                        if($this->getMessage()->getReplyToMessage()->getForwardFrom() == null)
                        {

                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "text" => "Unable to get the target user from the forwarded message"
                            ]);
                        }
                        else
                        {
                            $TargetForwardUser = User::fromArray($this->getMessage()->getReplyToMessage()->getForwardFrom()->getRawData());
                            $TargetForwardUserClient = $SpamProtection->getTelegramClientManager()->registerUser($TargetForwardUser);

                            $Message = \SpamProtection\Objects\TelegramObjects\Message::fromArray($this->getMessage()->getReplyToMessage()->getRawData());
                            $Message->ForwardFrom = null;
                            $Message->From = User::fromArray($this->getMessage()->getReplyToMessage()->getForwardFrom()->getRawData());

                            return self::logSpam(
                                $SpamProtection, $TargetForwardUserClient, $UserClient, $Message
                            );
                        }
                    }
                }

                $Message = \SpamProtection\Objects\TelegramObjects\Message::fromArray($this->getMessage()->getReplyToMessage()->getRawData());
                return self::logSpam(
                    $SpamProtection, $TargetUserClient, $UserClient, $Message
                );
            }


            return self::displayUsage($this->getMessage(), "Missing target message");
        }

        /**
         * Displays the command usage
         *
         * @param Message $message
         * @param string $error
         * @return ServerResponse
         * @throws TelegramException
         */
        public static function displayUsage(Message $message, string $error="Missing parameter")
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
                    "For further instructions, refer to the operator manual"
            ]);
        }

        /**
         * Blacklists a user
         *
         * @param SpamProtection $spamProtection
         * @param TelegramClient $targetUserClient
         * @param TelegramClient $operatorClient
         * @param \SpamProtection\Objects\TelegramObjects\Message $message
         * @return ServerResponse
         * @throws DatabaseException
         * @throws TelegramException
         * @throws MessageLogNotFoundException
         * @throws UnsupportedMessageException
         * @noinspection DuplicatedCode
         */
        public static function logSpam(SpamProtection $spamProtection, TelegramClient $targetUserClient, TelegramClient $operatorClient, \SpamProtection\Objects\TelegramObjects\Message $message)
        {
            if($targetUserClient->Chat->Type !== TelegramChatType::Private)
            {

                return Request::sendMessage([
                    "chat_id" => $message->Chat->ID,
                    "parse_mode" => "html",
                    "reply_to_message_id" => $message->MessageID,
                    "text" => "This operation is not applicable to this user."
                ]);
            }

            $UserStatus = SettingsManager::getUserStatus($targetUserClient);

            if($UserStatus->IsOperator)
            {

                return Request::sendMessage([
                    "chat_id" => $message->Chat->ID,
                    "parse_mode" => "html",
                    "reply_to_message_id" => $message->MessageID,
                    "text" => "You can't log an operator"
                ]);
            }

            if($UserStatus->IsAgent)
            {

                return Request::sendMessage([
                    "chat_id" => $message->Chat->ID,
                    "parse_mode" => "html",
                    "reply_to_message_id" => $message->MessageID,
                    "text" => "You can't log an agent"
                ]);
            }

            if($UserStatus->IsWhitelisted)
            {

                return Request::sendMessage([
                    "chat_id" => $message->Chat->ID,
                    "parse_mode" => "html",
                    "reply_to_message_id" => $message->MessageID,
                    "text" => "You can't log a user who's whitelisted"
                ]);
            }

            if($message->Text == null)
            {
                if($message->Caption == null)
                {

                    return Request::sendMessage([
                        "chat_id" => $message->Chat->ID,
                        "parse_mode" => "html",
                        "reply_to_message_id" => $message->MessageID,
                        "text" => "This message type isn't supported yet, archive this message yourself if necessary"
                    ]);
                }
            }

            $MessageLogObject = $spamProtection->getMessageLogManager()->registerMessage($message, 0, 0);


            $LogMessage = "#spam\n\n";
            $LogMessage .= "<b>Private Telegram ID:</b> <code>" . $targetUserClient->PublicID . "</code>\n";
            $LogMessage .= "<b>Operator PTID:</b> <code>" . $operatorClient->PublicID . "</code>\n";
            $LogMessage .= "<b>Message Hash:</b> <code>" . $MessageLogObject->MessageHash . "</code>\n";
            $LogMessage .= "<b>Timestamp:</b> <code>" . $MessageLogObject->Timestamp . "</code>";

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
                "chat_id" => $message->Chat->ID,
                "parse_mode" => "html",
                "reply_to_message_id" => $message->MessageID,
                "text" =>
                    "This message has been archived successfully.\n\n".
                    "Message Hash: <code>" . $MessageLogObject->MessageHash . "</code>"
            ]);

            return Request::sendMessage([
                "chat_id" => "@SpamProtectionLogs",
                "disable_web_page_preview" => true,
                "disable_notification" => true,
                "parse_mode" => "html",
                "text" => $LogMessage
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
            $input = str_ireplace("<", "&lt;", $input);
            $input = str_ireplace(">", "&gt;", $input);
            $input = str_ireplace("&", "&amp;", $input);

            return $input;
        }

    }