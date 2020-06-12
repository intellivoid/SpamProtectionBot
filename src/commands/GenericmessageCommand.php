<?php

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use CoffeeHouse\CoffeeHouse;
    use CoffeeHouse\Objects\Results\SpamPredictionResults;
    use DeepAnalytics\DeepAnalytics;
    use Exception;
    use Longman\TelegramBot\Commands\SystemCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Abstracts\DetectionAction;
    use SpamProtection\Exceptions\DatabaseException;
    use SpamProtection\Exceptions\InvalidSearchMethod;
    use SpamProtection\Exceptions\MessageLogNotFoundException;
    use SpamProtection\Exceptions\TelegramClientNotFoundException;
    use SpamProtection\Exceptions\UnsupportedMessageException;
    use SpamProtection\Objects\ChatSettings;
    use SpamProtection\Objects\MessageLog;
    use SpamProtection\Objects\TelegramClient;
    use SpamProtection\Objects\TelegramClient\Chat;
    use SpamProtection\Objects\TelegramClient\User;
    use SpamProtection\Objects\TelegramObjects\Message;
    use SpamProtection\Objects\UserStatus;
    use SpamProtection\SpamProtection;

    /**
     * Generic Command
     *
     * Gets executed when a user sends a generic message
     */
    class GenericmessageCommand extends SystemCommand
    {

        /**
         * @var string
         */
        protected $name = 'Generic Information';

        /**
         * @var string
         */
        protected $description = 'Handles spam detection in a groupchatt';

        /**
         * @var bool
         */
        protected $private_only = false;

        /**
         * @var string
         */
        protected $version = '1.0.0';

        /**
         * Executes the generic message command
         *
         * @return ServerResponse|null
         * @throws TelegramException
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws MessageLogNotFoundException
         * @throws TelegramClientNotFoundException
         * @throws UnsupportedMessageException
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
                if(isset($UserClient->SessionData->Data['chat_settings']) == false)
                {
                    $ChatSettings = $SpamProtection->getSettingsManager()->getChatSettings($ChatClient);
                    $ChatClient = $SpamProtection->getSettingsManager()->updateChatSettings($ChatClient, $ChatSettings);
                }
                $SpamProtection->getTelegramClientManager()->updateClient($ChatClient);

                // Define and update user client
                $UserClient = $SpamProtection->getTelegramClientManager()->registerUser($UserObject);
                if(isset($UserClient->SessionData->Data['user_status']) == false)
                {
                    $UserStatus = $SpamProtection->getSettingsManager()->getUserStatus($UserClient);
                    $UserClient = $SpamProtection->getSettingsManager()->updateUserStatus($UserClient, $UserStatus);
                }
                $SpamProtection->getTelegramClientManager()->updateClient($UserClient);
            }
            catch(Exception $e)
            {
                return null;
            }

            $DeepAnalytics = new DeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$TelegramClient->getChatId());

            $CoffeeHouse = new CoffeeHouse();

            // Process message text
            $Message = Message::fromArray($this->getMessage()->getRawData());
            if($Message->getText() !== null)
            {
                $MessageObject = Message::fromArray($this->getMessage()->getRawData());
                $ChatSettings = $SpamProtection->getSettingsManager()->getChatSettings($ChatClient);

                if($ChatSettings->LogSpamPredictions == false)
                {
                    return null;
                }

                if($ChatSettings->ForwardProtectionEnabled)
                {
                    if($MessageObject->isForwarded())
                    {
                        if($MessageObject->getForwardedOriginalUser() !== null)
                        {
                            // Define and update forwarder user client
                            $UserObject = User::fromArray($this->getMessage()->getFrom()->getRawData());
                            $UserClient = $SpamProtection->getTelegramClientManager()->registerUser($UserObject);
                            if(isset($UserClient->SessionData->Data['user_status']) == false)
                            {
                                $UserStatus = $SpamProtection->getSettingsManager()->getUserStatus($UserClient);
                                $UserClient = $SpamProtection->getSettingsManager()->updateUserStatus($UserClient, $UserStatus);
                            }
                            $SpamProtection->getTelegramClientManager()->updateClient($UserClient);
                        }
                    }
                }

                $UserStatus = $SpamProtection->getSettingsManager()->getUserStatus($UserClient);

                try
                {
                    if($UserStatus->GeneralizedID == null || $UserStatus->GeneralizedID == "None")
                    {
                        $Results = $CoffeeHouse->getSpamPrediction()->predict($Message->getText(), true);
                    }
                    else
                    {
                        $Results = $CoffeeHouse->getSpamPrediction()->predict($Message->getText(), true, $UserStatus->GeneralizedID);
                    }
                }
                catch(Exception $exception)
                {
                    unset($exception);
                    return null;
                }

                $MessageLogObject = $SpamProtection->getMessageLogManager()->registerMessage(
                    $MessageObject, $Results->SpamPrediction, $Results->HamPrediction
                );

                $UserStatus->GeneralizedSpam = $Results->GeneralizedSpam;
                $UserStatus->GeneralizedHam = $Results->GeneralizedHam;
                $UserStatus->GeneralizedID = $Results->GeneralizedID;
                $UserClient = $SpamProtection->getSettingsManager()->updateUserStatus($UserClient, $UserStatus);
                $SpamProtection->getTelegramClientManager()->updateClient($UserClient);

                if($Results->SpamPrediction > $Results->HamPrediction)
                {
                    if($ChatSettings->LogSpamPredictions)
                    {
                        self::logDetectedSpam($MessageObject, $MessageLogObject, $UserClient);
                    }

                    self::handleSpam(
                        $MessageObject, $MessageLogObject,
                        $UserClient, $UserStatus,
                        $ChatClient, $ChatSettings, $Results);

                    $DeepAnalytics->tally('tg_spam_protection', 'detected_spam', 0);
                    $DeepAnalytics->tally('tg_spam_protection', 'detected_spam', (int)$TelegramClient->getChatId());
                    $DeepAnalytics->tally('tg_spam_protection', 'detected_spam', (int)$TelegramClient->getUserId());

                    return null;
                }
                else
                {
                    $DeepAnalytics->tally('tg_spam_protection', 'detected_ham', 0);
                    $DeepAnalytics->tally('tg_spam_protection', 'detected_ham', (int)$TelegramClient->getChatId());
                    $DeepAnalytics->tally('tg_spam_protection', 'detected_ham', (int)$TelegramClient->getUserId());
                }
            }


            return null;
        }

        /**
         * Handles the detected spam configured by the group administrator
         *
         * @param Message $message
         * @param MessageLog $messageLog
         * @param TelegramClient $userClient
         * @param UserStatus $userStatus
         * @param TelegramClient $chatClient
         * @param ChatSettings $chatSettings
         * @param SpamPredictionResults $spamPredictionResults
         * @throws TelegramException
         */
        private static function handleSpam(
            Message $message, MessageLog $messageLog,
            TelegramClient $userClient, UserStatus $userStatus,
            TelegramClient $chatClient, ChatSettings $chatSettings,
            SpamPredictionResults $spamPredictionResults
        )
        {
            if($chatSettings->ForwardProtectionEnabled)
            {
                if($message->isForwarded())
                {
                    if($message->getForwardedOriginalUser() !== null)
                    {
                        if($chatSettings->GeneralAlertsEnabled)
                        {
                            Request::sendMessage([
                                "chat_id" => $message->Chat->ID,
                                "reply_to_message_id" => $message->MessageID,
                                "parse_mode" => "html",
                                "text" =>
                                    self::generateDetectionMessage($message, $messageLog, $userClient, $spamPredictionResults) . "\n\n" .
                                    "No action will be taken since this group has Forward Protection Enabled"
                            ]);
                        }

                        return;
                    }
                }
            }

            if($userStatus->IsWhitelisted)
            {
                if($chatSettings->GeneralAlertsEnabled)
                {
                    Request::sendMessage([
                        "chat_id" => $message->Chat->ID,
                        "reply_to_message_id" => $message->MessageID,
                        "parse_mode" => "html",
                        "text" =>
                            self::generateDetectionMessage($message, $messageLog, $userClient, $spamPredictionResults) . "\n\n" .
                            "No action will be taken since this user is whitelisted"
                    ]);
                }

                return;
            }

            switch($chatSettings->DetectSpamAction)
            {
                case DetectionAction::Nothing:
                    if($chatSettings->GeneralAlertsEnabled)
                    {
                        Request::sendMessage([
                            "chat_id" => $message->Chat->ID,
                            "reply_to_message_id" => $message->MessageID,
                            "parse_mode" => "html",
                            "text" =>
                                self::generateDetectionMessage($message, $messageLog, $userClient, $spamPredictionResults) . "\n\n" .
                                "No action will be taken since the the current detection rule in this group is to do nothing"
                        ]);
                    }
                    break;

                case DetectionAction::DeleteMessage:
                    $Response = Request::deleteMessage([
                        "chat_id" => $message->Chat->ID,
                        "message_id" => $message->MessageID
                    ]);
                    if($Response->isOk())
                    {
                        Request::sendMessage([
                            "chat_id" => $message->Chat->ID,
                            "parse_mode" => "html",
                            "text" =>
                                self::generateDetectionMessage($message, $messageLog, $userClient, $spamPredictionResults) . "\n\n" .
                                "The message has been deleted"
                        ]);
                    }
                    else
                    {
                        Request::sendMessage([
                            "chat_id" => $message->Chat->ID,
                            "reply_to_message_id" => $message->MessageID,
                            "parse_mode" => "html",
                            "text" =>
                                self::generateDetectionMessage($message, $messageLog, $userClient, $spamPredictionResults) . "\n\n" .
                                "<b>The message cannot be deleted because of insufficient administrator privileges</b>"
                        ]);
                    }
                    break;

                case DetectionAction::KickOffender:
                    $DeleteResponse = Request::deleteMessage([
                        "chat_id" => $message->Chat->ID,
                        "message_id" => $message->MessageID
                    ]);

                    $KickResponse = Request::kickChatMember([
                        "chat_id" => $message->Chat->ID,
                        "user_id" => $userClient->User->ID,
                        "until_date" => (int)time() + 60
                    ]);

                    $Response = self::generateDetectionMessage($message, $messageLog, $userClient, $spamPredictionResults) . "\n\n";

                    if($DeleteResponse->isOk() == false)
                    {
                        $Response .= "<b>The message cannot be deleted because of insufficient administrator privileges</b>\n\n";
                    }

                    if($KickResponse->isOk() == false)
                    {
                        $Response .= "<b>The user cannot be removed because of insufficient administrator privileges</b>\n\n";
                    }

                    if($KickResponse == true && $DeleteResponse == true)
                    {
                        $Response .= "The message was deleted and the offender was removed from the group";
                    }

                    Request::sendMessage([
                        "chat_id" => $message->Chat->ID,
                        "reply_to_message_id" => $message->MessageID,
                        "parse_mode" => "html",
                        "text" => $Response
                    ]);

                    break;

                case DetectionAction::BanOffender:
                    $DeleteResponse = Request::deleteMessage([
                        "chat_id" => $message->Chat->ID,
                        "message_id" => $message->MessageID
                    ]);

                    $BanResponse = Request::kickChatMember([
                        "chat_id" => $message->Chat->ID,
                        "user_id" => $userClient->User->ID,
                        "until_date" => 0
                    ]);

                    $Response = self::generateDetectionMessage($message, $messageLog, $userClient, $spamPredictionResults) . "\n\n";

                    if($DeleteResponse->isOk() == false)
                    {
                        $Response .= "<b>The message cannot be deleted because of insufficient administrator privileges</b>\n\n";
                    }

                    if($BanResponse->isOk() == false)
                    {
                        $Response .= "<b>The user cannot be banned because of insufficient administrator privileges</b>\n\n";
                    }

                    if($BanResponse == true && $DeleteResponse == true)
                    {
                        $Response .= "The message was deleted and the offender was banned from the group";
                    }

                    Request::sendMessage([
                        "chat_id" => $message->Chat->ID,
                        "reply_to_message_id" => $message->MessageID,
                        "parse_mode" => "html",
                        "text" => $Response
                    ]);

                    break;

                default:
                    Request::sendMessage([
                        "chat_id" => $message->Chat->ID,
                        "parse_mode" => "html",
                        "text" =>
                            self::generateDetectionMessage($message, $messageLog, $userClient, $spamPredictionResults) . "\n\n" .
                            "No action was taken because the detection action is not recognized"
                    ]);
            }

        }

        /**
         * Generates a generic spam detection message
         *
         * @param Message $message
         * @param MessageLog $messageLog
         * @param TelegramClient $userClient
         * @param SpamPredictionResults $spamPredictionResults
         * @return string
         */
        private static function generateDetectionMessage(Message $message, MessageLog $messageLog, TelegramClient $userClient, SpamPredictionResults $spamPredictionResults): string
        {
            $Response = "\u{26A0} <b>SPAM DETECTED</b> \u{26A0}\n\n";
            $Response .= "<b>Private Telegram ID:</b> <code>" . $userClient->PublicID . "</code>\n";
            $Response .= "<b>Message Hash:</b> <code>" . $messageLog->MessageHash . "</code>\n";
            $Response .= "<b>Spam Probability:</b> <code>" . $spamPredictionResults->SpamPrediction . "%</code>";

            return $Response;
        }

        /**
         * Logs detected spam to the public channel
         *
         * @param Message $message
         * @param MessageLog $messageLog
         * @param TelegramClient $userClient
         * @throws TelegramException
         */
        private static function logDetectedSpam(Message $message,  MessageLog $messageLog, TelegramClient $userClient)
        {
            $LogMessage = "#spam_prediction\n\n";
            $LogMessage .= "<b>Private Telegram ID:</b> <code>" . $userClient->PublicID . "</code>\n";
            $LogMessage .= "<b>Prediction Results:</b> <code>" . $messageLog->SpamPrediction . "</code>\n";
            $LogMessage .= "<b>Message Hash:</b> <code>" . $messageLog->MessageHash . "</code>\n";
            $LogMessage .= "<b>Timestamp:</b> <code>" . $messageLog->Timestamp . "</code>";

            $LogMessageWithContent = $LogMessage . "\n\n<i>===== CONTENT =====</i>\n\n" . $message->getText();
            if(strlen($LogMessageWithContent) > 4096)
            {
                $LogMessage .= "\n\nThe content is too large to be shown\n";
            }
            else
            {
                $LogMessage = $LogMessageWithContent;
            }

            Request::sendMessage([
                "chat_id" => 570787098,
                "disable_web_page_preview" => true,
                "disable_notification" => true,
                "parse_mode" => "html",
                "text" => $LogMessage
            ]);
        }
    }
