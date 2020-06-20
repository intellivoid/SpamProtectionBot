<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use CoffeeHouse\CoffeeHouse;
    use CoffeeHouse\Objects\Results\SpamPredictionResults;
    use DeepAnalytics\DeepAnalytics;
    use Exception;
    use Longman\TelegramBot\Commands\SystemCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Abstracts\BlacklistFlag;
    use SpamProtection\Abstracts\DetectionAction;
    use SpamProtection\Exceptions\DatabaseException;
    use SpamProtection\Exceptions\MessageLogNotFoundException;
    use SpamProtection\Exceptions\UnsupportedMessageException;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtection\Objects\ChatSettings;
    use SpamProtection\Objects\MessageLog;
    use SpamProtection\Objects\TelegramObjects\Message;
    use SpamProtection\Objects\UserStatus;
    use SpamProtection\SpamProtection;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;
    use TelegramClientManager\Objects\TelegramClient;
    use TelegramClientManager\TelegramClientManager;

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
        protected $description = 'Handles spam detection in a group chat';

        /**
         * @var bool
         */
        protected $private_only = false;

        /**
         * @var string
         */
        protected $version = '1.0.1';

        /**
         * Executes the generic message command
         *
         * @return ServerResponse|null
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws MessageLogNotFoundException
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @throws UnsupportedMessageException
         * @throws \TelegramClientManager\Exceptions\DatabaseException
         * @noinspection DuplicatedCode
         */
        public function execute()
        {
            $TelegramClientManager = new TelegramClientManager();

            $ChatObject = Chat::fromArray($this->getMessage()->getChat()->getRawData());
            $UserObject = User::fromArray($this->getMessage()->getFrom()->getRawData());

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
                    $ForwardUserObject = User::fromArray($this->getMessage()->getForwardFrom()->getRawData());
                    $ForwardUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($ForwardUserObject);
                    if(isset($ForwardUserClient->SessionData->Data["user_status"]) == false)
                    {
                        $ForwardUserStatus = SettingsManager::getUserStatus($ForwardUserClient);
                        $ForwardUserClient = SettingsManager::updateUserStatus($ForwardUserClient, $ForwardUserStatus);
                        $TelegramClientManager->getTelegramClientManager()->updateClient($ForwardUserClient);
                    }
                }
            }
            catch(Exception $e)
            {
                return null;
            }

            $DeepAnalytics = new DeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$TelegramClient->getChatId());

            if($ChatObject->Type == TelegramChatType::Private)
            {
                return null;
            }

            $UserStatus = SettingsManager::getUserStatus($UserClient);
            $ChatSettings = SettingsManager::getChatSettings($ChatClient);

            if($ChatSettings->BlacklistProtectionEnabled)
            {
                if($UserStatus->IsBlacklisted)
                {
                    $BanResponse = Request::kickChatMember([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "user_id" => $UserClient->User->ID,
                        "until_date" => 0
                    ]);

                    if($BanResponse->isOk())
                    {
                        $Response = "This user has been banned because they've been blacklisted!\n\n";
                        $Response .= "<b>Private Telegram ID:</b> <code>" . $UserClient->PublicID . "</code>\n";

                        switch($UserStatus->BlacklistFlag)
                        {
                            case BlacklistFlag::None:
                                $Response .= "<b>Blacklist Reason:</b> <code>None</code>\n";
                                break;

                            case BlacklistFlag::Spam:
                                $Response .= "<b>Blacklist Reason:</b> <code>Spam / Unwanted Promotion</code>\n";
                                break;

                            case BlacklistFlag::BanEvade:
                                $Response .= "<b>Blacklist Reason:</b> <code>Ban Evade</code>\n";
                                $Response .= "<b>Original Private ID:</b> <code>" . $UserStatus->OriginalPrivateID . "</code>\n";
                                break;

                            case BlacklistFlag::ChildAbuse:
                                $Response .= "<b>Blacklist Reason:</b> <code>Child Pornography / Child Abuse</code>\n";
                                break;

                            case BlacklistFlag::Impersonator:
                                $Response .= "<b>Blacklist Reason:</b> <code>Malicious Impersonator</code>\n";
                                break;

                            case BlacklistFlag::PiracySpam:
                                $Response .= "<b>Blacklist Reason:</b> <code>Promotes/Spam Pirated Content</code>\n";
                                break;

                            case BlacklistFlag::PornographicSpam:
                                $Response .= "<b>Blacklist Reason:</b> <code>Promotes/Spam NSFW Content</code>\n";
                                break;

                            case BlacklistFlag::PrivateSpam:
                                $Response .= "<b>Blacklist Reason:</b> <code>Spam / Unwanted Promotion via a unsolicited private message</code>\n";
                                break;

                            case BlacklistFlag::Raid:
                                $Response .= "<b>Blacklist Reason:</b> <code>RAID Initializer / Participator</code>\n";
                                break;

                            case BlacklistFlag::Scam:
                                $Response .= "<b>Blacklist Reason:</b> <code>Scamming</code>\n";
                                break;

                            case BlacklistFlag::Special:
                                $Response .= "<b>Blacklist Reason:</b> <code>Special Reason, consult @IntellivoidSupport</code>\n";
                                break;

                            default:
                                $Response .= "<b>Blacklist Reason:</b> <code>Unknown</code>\n";
                                break;
                        }

                        $Response .= "\n<i>You can find evidence of abuse by searching the Private Telegram ID in @SpamProtectionLogs</i>\n\n";
                        $Response .= "<i>If you think this is a mistake, let us know in @IntellivoidDiscussions</i>";

                        Request::sendMessage([
                            "chat_id" => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            "text" => $Response
                        ]);
                    }
                }
            }

            // Process message text
            $Message = Message::fromArray($this->getMessage()->getRawData());
            if($Message->getText() !== null)
            {
                $MessageObject = Message::fromArray($this->getMessage()->getRawData());
                $ChatSettings = SettingsManager::getChatSettings($ChatClient);

                if($ChatSettings->LogSpamPredictions == false)
                {
                    return null;
                }

                if($MessageObject->isForwarded())
                {
                    if($MessageObject->getForwardedOriginalUser() !== null)
                    {
                        if($MessageObject->getForwardedOriginalUser()->Username == "SpamProtectionBot")
                        {
                            return null;
                        }
                    }
                }

                if($ChatSettings->ForwardProtectionEnabled)
                {
                    if($MessageObject->isForwarded())
                    {
                        if($MessageObject->getForwardedOriginalUser() !== null)
                        {
                            // Define and update forwarder user client
                            $UserObject = User::fromArray($this->getMessage()->getFrom()->getRawData());
                            $UserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($UserObject);
                            if(isset($UserClient->SessionData->Data['user_status']) == false)
                            {
                                $UserStatus = SettingsManager::getUserStatus($UserClient);
                                $UserClient = SettingsManager::updateUserStatus($UserClient, $UserStatus);
                                $TelegramClientManager->getTelegramClientManager()->updateClient($UserClient);
                            }
                        }
                    }
                }

                if($ChatSettings->DetectSpamEnabled)
                {
                    if($UserStatus->IsWhitelisted == false)
                    {
                        $CoffeeHouse = new CoffeeHouse();

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

                        $SpamProtection = new SpamProtection();
                        $MessageLogObject = $SpamProtection->getMessageLogManager()->registerMessage(
                            $MessageObject, $Results->SpamPrediction, $Results->HamPrediction
                        );

                        $UserStatus->GeneralizedSpam = $Results->GeneralizedSpam;
                        $UserStatus->GeneralizedHam = $Results->GeneralizedHam;
                        $UserStatus->GeneralizedID = $Results->GeneralizedID;
                        $UserClient = SettingsManager::updateUserStatus($UserClient, $UserStatus);
                        $TelegramClientManager->getTelegramClientManager()->updateClient($UserClient);

                        if($Results->SpamPrediction > $Results->HamPrediction)
                        {
                            if($ChatSettings->LogSpamPredictions)
                            {
                                self::logDetectedSpam($MessageObject, $MessageLogObject, $UserClient);
                            }

                            self::handleSpam(
                                $MessageObject, $MessageLogObject,
                                $UserClient, $UserStatus, $ChatSettings, $Results);

                            $DeepAnalytics->tally('tg_spam_protection', 'detected_spam', 0);
                            $DeepAnalytics->tally('tg_spam_protection', 'detected_spam', (int)$TelegramClient->getChatId());
                            $DeepAnalytics->tally('tg_spam_protection', 'detected_spam', (int)$TelegramClient->getUserId());
                        }
                        else
                        {
                            $DeepAnalytics->tally('tg_spam_protection', 'detected_ham', 0);
                            $DeepAnalytics->tally('tg_spam_protection', 'detected_ham', (int)$TelegramClient->getChatId());
                            $DeepAnalytics->tally('tg_spam_protection', 'detected_ham', (int)$TelegramClient->getUserId());
                        }
                    }
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
         * @param ChatSettings $chatSettings
         * @param SpamPredictionResults $spamPredictionResults
         * @throws TelegramException
         */
        private static function handleSpam(
            Message $message, MessageLog $messageLog,
            TelegramClient $userClient, UserStatus $userStatus,
            ChatSettings $chatSettings, SpamPredictionResults $spamPredictionResults
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
                                    self::generateDetectionMessage($messageLog, $userClient, $spamPredictionResults) . "\n\n" .
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
                            self::generateDetectionMessage($messageLog, $userClient, $spamPredictionResults) . "\n\n" .
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
                                self::generateDetectionMessage($messageLog, $userClient, $spamPredictionResults) . "\n\n" .
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
                        if($chatSettings->GeneralAlertsEnabled)
                        {
                            Request::sendMessage([
                                "chat_id" => $message->Chat->ID,
                                "parse_mode" => "html",
                                "text" =>
                                    self::generateDetectionMessage($messageLog, $userClient, $spamPredictionResults) . "\n\n" .
                                    "The message has been deleted"
                            ]);
                        }
                    }
                    else
                    {
                        if($chatSettings->GeneralAlertsEnabled)
                        {
                            Request::sendMessage([
                                "chat_id" => $message->Chat->ID,
                                "reply_to_message_id" => $message->MessageID,
                                "parse_mode" => "html",
                                "text" =>
                                    self::generateDetectionMessage($messageLog, $userClient, $spamPredictionResults) . "\n\n" .
                                    "<b>The message cannot be deleted because of insufficient administrator privileges</b>"
                            ]);
                        }

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

                    $Response = self::generateDetectionMessage($messageLog, $userClient, $spamPredictionResults) . "\n\n";

                    if($DeleteResponse->isOk() == false)
                    {
                        $Response .= "<b>The message cannot be deleted because of insufficient administrator privileges</b>\n\n";
                    }

                    if($KickResponse->isOk() == false)
                    {
                        $Response .= "<b>The user cannot be removed because of insufficient administrator privileges</b>\n\n";
                    }

                    if($KickResponse->isOk() == true && $DeleteResponse->isOk() == true)
                    {
                        $Response .= "The message was deleted and the offender was removed from the group";
                    }

                    if($chatSettings->GeneralAlertsEnabled)
                    {
                        Request::sendMessage([
                            "chat_id" => $message->Chat->ID,
                            "parse_mode" => "html",
                            "text" => $Response
                        ]);
                    }

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

                    $Response = self::generateDetectionMessage($messageLog, $userClient, $spamPredictionResults) . "\n\n";

                    if($DeleteResponse->isOk() == false)
                    {
                        $Response .= "<b>The message cannot be deleted because of insufficient administrator privileges</b>\n\n";
                    }

                    if($BanResponse->isOk() == false)
                    {
                        $Response .= "<b>The user cannot be banned because of insufficient administrator privileges</b>\n\n";
                    }

                    if($BanResponse->isOk() == true && $DeleteResponse->isOk() == true)
                    {
                        $Response .= "The message was deleted and the offender was banned from the group";
                    }

                    if($chatSettings->GeneralAlertsEnabled)
                    {
                        Request::sendMessage([
                            "chat_id" => $message->Chat->ID,
                            "parse_mode" => "html",
                            "text" => $Response
                        ]);
                    }

                    break;

                default:
                    if($chatSettings->GeneralAlertsEnabled)
                    {
                        Request::sendMessage([
                            "chat_id" => $message->Chat->ID,
                            "reply_to_message_id" => $message->MessageID,
                            "parse_mode" => "html",
                            "text" =>
                                self::generateDetectionMessage($messageLog, $userClient, $spamPredictionResults) . "\n\n" .
                                "No action was taken because the detection action is not recognized"
                        ]);
                    }
            }

        }

        /**
         * Generates a generic spam detection message
         *
         * @param MessageLog $messageLog
         * @param TelegramClient $userClient
         * @param SpamPredictionResults $spamPredictionResults
         * @return string
         */
        private static function generateDetectionMessage(MessageLog $messageLog, TelegramClient $userClient, SpamPredictionResults $spamPredictionResults): string
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
         * @noinspection DuplicatedCode
         */
        private static function logDetectedSpam(Message $message,  MessageLog $messageLog, TelegramClient $userClient)
        {
            $LogMessage = "#spam_prediction\n\n";
            $LogMessage .= "<b>Private Telegram ID:</b> <code>" . $userClient->PublicID . "</code>\n";
            $LogMessage .= "<b>Prediction Results:</b> <code>" . $messageLog->SpamPrediction . "</code>\n";
            $LogMessage .= "<b>Message Hash:</b> <code>" . $messageLog->MessageHash . "</code>\n";
            $LogMessage .= "<b>Timestamp:</b> <code>" . $messageLog->Timestamp . "</code>";

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
