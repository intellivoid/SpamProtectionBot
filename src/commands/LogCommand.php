<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\UserCommands;

    use Exception;
    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\Message;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
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
        protected $name = 'Log message command';

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
        protected $version = '1.0.1';

        /**
         * @var bool
         */
        protected $private_only = false;

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
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();

            $ChatObject = TelegramClient\Chat::fromArray($this->getMessage()->getChat()->getRawData());
            $UserObject = TelegramClient\User::fromArray($this->getMessage()->getFrom()->getRawData());

            try
            {
                $TelegramClient = $TelegramClientManager->getTelegramClientManager()->registerClient($ChatObject, $UserObject);

                // Define and update chat client
                $ChatClient = $TelegramClientManager->getTelegramClientManager()->registerChat($ChatObject);
                if(isset($UserClient->SessionData->Data["chat_settings"]) == false)
                {
                    $ChatSettings = SettingsManager::getChatSettings($ChatClient);
                    $ChatClient = SettingsManager::updateChatSettings($ChatClient, $ChatSettings);
                    $TelegramClientManager->getTelegramClientManager()->updateClient($ChatClient);
                }

                // Define and update user client
                $UserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($UserObject);
                if(isset($UserClient->SessionData->Data["user_status"]) == false)
                {
                    $UserStatus = SettingsManager::getUserStatus($UserClient);
                    $UserClient = SettingsManager::updateUserStatus($UserClient, $UserStatus);
                    $TelegramClientManager->getTelegramClientManager()->updateClient($UserClient);
                }

                // Define and update the forwarder if available
                if($this->getMessage()->getForwardFrom() !== null)
                {
                    $ForwardUserObject = TelegramClient\User::fromArray($this->getMessage()->getForwardFrom()->getRawData());
                    $ForwardUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($ForwardUserObject);
                    if(isset($ForwardUserClient->SessionData->Data["user_status"]) == false)
                    {
                        $ForwardUserStatus = SettingsManager::getUserStatus($ForwardUserClient);
                        $ForwardUserClient = SettingsManager::updateUserStatus($ForwardUserClient, $ForwardUserStatus);
                        $TelegramClientManager->getTelegramClientManager()->updateClient($ForwardUserClient);
                    }
                }

                // Define and update the channel forwarder if available
                if($this->getMessage()->getForwardFromChat() !== null)
                {
                    $ForwardChannelObject = TelegramClient\Chat::fromArray($this->getMessage()->getForwardFromChat()->getRawData());
                    $ForwardChannelClient = $TelegramClientManager->getTelegramClientManager()->registerChat($ForwardChannelObject);
                    if(isset($ForwardChannelClient->SessionData->Data["channel_status"]) == false)
                    {
                        $ForwardChannelStatus = SettingsManager::getChannelStatus($ForwardChannelClient);
                        $ForwardChannelClient = SettingsManager::updateChannelStatus($ForwardChannelClient, $ForwardChannelStatus);
                        $TelegramClientManager->getTelegramClientManager()->updateClient($ForwardChannelClient);
                    }
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

            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
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
                $TargetUser = TelegramClient\User::fromArray($this->getMessage()->getReplyToMessage()->getFrom()->getRawData());
                $TargetUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($TargetUser);

                $CommandParameters = explode(" ", $this->getMessage()->getText(true));
                $CommandParameters = array_values(array_filter($CommandParameters, 'strlen'));

                if(count($CommandParameters) > 0)
                {
                    // If to target the forwarder
                    if(strtolower($CommandParameters[0]) == "-f")
                    {
                        if($this->getMessage()->getReplyToMessage()->getForwardFrom() == null)
                        {
                            if($this->getMessage()->getReplyToMessage()->getForwardFromChat() == null)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "Unable to get the target user or channel from the forwarded message"
                                ]);
                            }
                        }

                        if($this->getMessage()->getReplyToMessage()->getForwardFromChat() !== null)
                        {
                            if(count($CommandParameters) > 1)
                            {
                                if(strtolower($CommandParameters[1]) == "-u")
                                {
                                    $TargetUser = TelegramClient\User::fromArray($this->getMessage()->getReplyToMessage()->getFrom()->getRawData());
                                    $TargetUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($TargetUser);

                                    $Message = \SpamProtection\Objects\TelegramObjects\Message::fromArray($this->getMessage()->getReplyToMessage()->getRawData());
                                    $Message->ForwardFrom = null;
                                    $Message->From = $TargetUserClient->User;
                                    $Message->Chat = $ChatObject;

                                    $this->logSpam($TargetUserClient, $UserClient, $Message);
                                }
                            }

                            $TargetForwardChannel = TelegramClient\Chat::fromArray($this->getMessage()->getReplyToMessage()->getForwardFromChat()->getRawData());
                            $TargetForwardChannelClient = $TelegramClientManager->getTelegramClientManager()->registerChat($TargetForwardChannel);

                            $Message = \SpamProtection\Objects\TelegramObjects\Message::fromArray($this->getMessage()->getReplyToMessage()->getRawData());
                            $Message->ForwardFrom = null;
                            $Message->From = $TargetForwardChannelClient->User;
                            $Message->Chat = $TargetForwardChannelClient->Chat;

                            return $this->logSpam($TargetForwardChannelClient, $UserClient, $Message);
                        }

                        if($this->getMessage()->getReplyToMessage()->getForwardFrom() !== null)
                        {
                            $TargetForwardUser = TelegramClient\User::fromArray($this->getMessage()->getReplyToMessage()->getForwardFrom()->getRawData());
                            $TargetForwardUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($TargetForwardUser);

                            $Message = \SpamProtection\Objects\TelegramObjects\Message::fromArray($this->getMessage()->getReplyToMessage()->getRawData());
                            $Message->ForwardFrom = null;
                            $Message->From = $TargetForwardUserClient->User;
                            $Message->Chat = $TargetForwardUserClient->Chat;

                            return $this->logSpam($TargetForwardUserClient, $UserClient, $Message);
                        }
                    }
                }

                $Message = \SpamProtection\Objects\TelegramObjects\Message::fromArray($this->getMessage()->getReplyToMessage()->getRawData());
                return $this->logSpam($TargetUserClient, $UserClient, $Message);
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
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "You can't log an operator"
                    ]);
                }

                if($UserStatus->IsAgent)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "You can't log an agent"
                    ]);
                }

                if($UserStatus->IsWhitelisted)
                {

                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "You can't log a user who's whitelisted"
                    ]);
                }
            }

            if($targetUserClient->Chat->Type == TelegramChatType::Channel)
            {
                $ChannelStatus = SettingsManager::getChannelStatus($targetUserClient);

                if($ChannelStatus->IsWhitelisted)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "You can't log a channel that's whitelisted"
                    ]);
                }

                if($ChannelStatus->IsOfficial)
                {
                    Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "Notice! This channel is considered to be official, this does not protect it from logging though."
                    ]);
                }
            }

            if($message->getText() == null)
            {
                if($message->Photo == null)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "This message type isn't supported yet, archive this message yourself if necessary"
                    ]);
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
                            "chat_id" => "570787098",
                            "disable_web_page_preview" => true,
                            "disable_notification" => true,
                            "parse_mode" => "html",
                            "text" => $LogMessage
                        ]);

                        continue;
                    }
                }
                else
                {
                    Request::sendPhoto([
                        "chat_id" => "570787098",
                        "photo" => $MessageLogObject->PhotoSize->FileID,
                        "disable_notification" => true,
                        "parse_mode" => "html",
                        "caption" => $LogMessage,
                    ]);

                    continue;
                }

                Request::sendMessage([
                    "chat_id" => "570787098",
                    "disable_web_page_preview" => true,
                    "disable_notification" => true,
                    "parse_mode" => "html",
                    "text" => $LogMessage
                ]);
            }

            if(count($MessageLogs) > 1)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => "Multiple messages has been archived successfully.\n\n". $MessageHashes
                ]);
            }
            else
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "This message has been archived successfully.\n\n".
                        "<code>" . $MessageLogs[0]->MessageHash . "</code>"
                ]);
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