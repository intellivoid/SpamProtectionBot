<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\UserCommands;

    use DeepAnalytics\DeepAnalytics;
    use Exception;
    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Abstracts\DetectionAction;
    use SpamProtection\Abstracts\TelegramChatType;
    use SpamProtection\Abstracts\TelegramClientSearchMethod;
    use SpamProtection\Exceptions\DatabaseException;
    use SpamProtection\Exceptions\InvalidSearchMethod;
    use SpamProtection\Exceptions\TelegramClientNotFoundException;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtection\Objects\TelegramClient;
    use SpamProtection\Objects\TelegramClient\Chat;
    use SpamProtection\Objects\TelegramClient\User;
    use SpamProtection\SpamProtection;
    use SpamProtection\Utilities\Hashing;

    /**
     * Chat Info command
     *
     * Allows the user to resolve chat information and the current configuration set to the chat
     */
    class ChatInfoCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'Chat Information Command';

        /**
         * @var string
         */
        protected $description = 'Returns information about the current chat or requested chat and it\'s properties';

        /**
         * @var string
         */
        protected $usage = '/chatinfo [None/ID/Private Telegram ID/Username]';

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
         * @throws TelegramException
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
                    $ChatClient =  SettingsManager::updateChatSettings($ChatClient, $ChatSettings);
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
                //$SpamProtection->getDatabase()->close();
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "Oops! Something went wrong! contact someone in @IntellivoidDiscussions\n\n" .
                        "Error Code: <code>" . $e->getCode() . "</code>\n" .
                        "Object: <code>Commands/chat_info.bin</code>"
                ]);
            }

            /** @noinspection PhpUndefinedClassInspection */
            $DeepAnalytics = new DeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'chat_info_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$TelegramClient->getChatId());
            $DeepAnalytics->tally('tg_spam_protection', 'chat_info_command', (int)$TelegramClient->getChatId());

            if($this->getMessage()->getText(true) !== null)
            {
                if(strlen($this->getMessage()->getText(true)) > 0)
                {
                    $CommandParameters = explode(" ", $this->getMessage()->getText(true));
                    $CommandParameters = array_values(array_filter($CommandParameters, 'strlen'));

                    if(count($CommandParameters) > 0)
                    {
                        $TargetChatParameter = $CommandParameters[0];
                        $EstimatedPrivateID = Hashing::telegramClientPublicID((int)$TargetChatParameter, (int)$TargetChatParameter);

                        try
                        {
                            $TargetChatClient = $SpamProtection->getTelegramClientManager()->getClient(TelegramClientSearchMethod::byPublicId, $EstimatedPrivateID);
                            $SpamProtection->getTelegramClientManager()->updateClient($TargetChatClient);
                            //$SpamProtection->getDatabase()->close();

                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "parse_mode" => "html",
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "text" => self::generateChatInfoString($TargetChatClient, "Resolved Chat ID")
                            ]);
                        }
                        catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                        {
                            unset($telegramClientNotFoundException);
                        }

                        try
                        {
                            $TargetChatClient = $SpamProtection->getTelegramClientManager()->getClient(TelegramClientSearchMethod::byPublicId, $TargetChatParameter);
                            $SpamProtection->getTelegramClientManager()->updateClient($TargetChatClient);
                            //$SpamProtection->getDatabase()->close();

                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "parse_mode" => "html",
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "text" => self::generateChatInfoString($TargetChatClient, "Resolved Chat ID")
                            ]);
                        }
                        catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                        {
                            unset($telegramClientNotFoundException);
                        }

                        try
                        {
                            $TargetChatClient = $SpamProtection->getTelegramClientManager()->getClient(
                                TelegramClientSearchMethod::byUsername, str_ireplace("@", "", $TargetChatParameter)
                            );
                            $SpamProtection->getTelegramClientManager()->updateClient($TargetChatClient);
                            //$SpamProtection->getDatabase()->close();

                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "parse_mode" => "html",
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "text" => self::generateChatInfoString($TargetChatClient, "Resolved Chat Username")
                            ]);
                        }
                        catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                        {
                            unset($telegramClientNotFoundException);
                        }
                    }

                    //$SpamProtection->getDatabase()->close();
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "text" => "Unable to resolve the query '" . self::escapeHTML($TargetChatParameter) . "'!"
                    ]);
                }
            }

            //$SpamProtection->getDatabase()->close();
            return Request::sendMessage([
                "chat_id" => $this->getMessage()->getChat()->getId(),
                "parse_mode" => "html",
                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                "text" => self::generateChatInfoString($ChatClient)
            ]);
        }

        /**
         * Generates a user information string
         *
         * @param TelegramClient $chat_client
         * @param string $title
         * @return string
         */
        private static function generateChatInfoString(TelegramClient $chat_client, string $title="Chat Information"): string
        {
            if($chat_client->Chat->Type == TelegramChatType::Private)
            {
                return "This command does not support users/private chats";
            }

            if($chat_client->Chat->Type == TelegramChatType::Channel)
            {
                return "This command does not support channels";
            }

            $ChatSettings = SettingsManager::getChatSettings($chat_client);
            $RequiresExtraNewline = false;
            $Response = "<b>$title</b>\n\n";

            if($ChatSettings->ForwardProtectionEnabled)
            {
                $RequiresExtraNewline = true;
                $Response .= "\u{1F6E1} This chat has forward protection enabled\n";
            }

            if($RequiresExtraNewline)
            {
                $Response .= "\n";
            }

            $Response .= "   <b>Private ID:</b> <code>" . $chat_client->PublicID . "</code>\n";
            $Response .= "   <b>Chat ID:</b> <code>" . $chat_client->Chat->ID . "</code>\n";
            $Response .= "   <b>Chat Type:</b> <code>" . $chat_client->Chat->Type . "</code>\n";
            $Response .= "   <b>Chat Title:</b> <code>" . self::escapeHTML($chat_client->Chat->Title) . "</code>\n";

            if($chat_client->Chat->Username !== null)
            {
                $Response .= "   <b>Chat Username:</b> <code>" . $chat_client->Chat->Username . "</code> (@" . $chat_client->Chat->Username . ")\n";
            }

            if($ChatSettings->ForwardProtectionEnabled)
            {
                $Response .= "   <b>Forward Protection Enabled:</b> <code>True</code>\n";
            }

            if($ChatSettings->DetectSpamEnabled)
            {
                $Response .= "   <b>Spam Detection Enabled:</b> <code>True</code>\n";

                switch($ChatSettings->DetectSpamAction)
                {
                    case DetectionAction::Nothing:
                        $Response .= "   <b>Spam Detection Action:</b> <code>Nothing</code>\n";
                        break;

                    case DetectionAction::DeleteMessage:
                        $Response .= "   <b>Spam Detection Action:</b> <code>Delete Content</code>\n";
                        break;

                    case DetectionAction::KickOffender:
                        $Response .= "   <b>Spam Detection Action:</b> <code>Remove Offender</code>\n";
                        break;

                    case DetectionAction::BanOffender:
                        $Response .= "   <b>Spam Detection Action:</b> <code>Permanently Ban Offender</code>\n";
                        break;
                }

                //if($ChatSettings->LogSpamPredictions)
                //{
                //    $Response .= "   <b>Log Spam Detections:</b> <code>True</code>\n";
                //}
                //else
                //{
                //    $Response .= "   <b>Log Spam Detections:</b> <code>False</code>\n";
                //}
            }
            else
            {
                $Response .= "   <b>Spam Detection Enabled:</b> <code>False</code>\n";
            }

            if($ChatSettings->GeneralAlertsEnabled)
            {
                $Response .= "   <b>General Alerts Enabled:</b> <code>True</code>\n";
            }

            if($ChatSettings->BlacklistProtectionEnabled)
            {
                $Response .= "   <b>Blacklist Protection Enabled:</b> <code>True</code>\n";
            }
            else
            {
                $Response .= "   <b>Blacklist Protection Enabled:</b> <code>False</code>\n";
            }

            if($ChatSettings->ActiveSpammerAlertEnabled)
            {
                $Response .= "   <b>Active Spammer Alert Enabled:</b> <code>True</code>\n";
            }
            else
            {
                $Response .= "   <b>Active Spammer Alert Enabled:</b> <code>False</code>\n";
            }

            return $Response;
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