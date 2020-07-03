<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\UserCommands;

    use Exception;
    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\InlineKeyboard;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Exceptions\DatabaseException;
    use SpamProtection\Exceptions\MessageLogNotFoundException;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtection\Utilities\Hashing;
    use SpamProtectionBot;
    use TelegramClientManager\Objects\TelegramClient\Chat;
    use TelegramClientManager\Objects\TelegramClient\User;

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
        protected $description = 'Allows further details about the message hash to be returned';

        /**
         * @var string
         */
        protected $usage = '/msginfo [Message Hash]';

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
         * @throws TelegramException
         * @throws DatabaseException
         * @noinspection DuplicatedCode
         */
        public function execute()
        {
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();

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
                if($this->getMessage()->getReplyToMessage() !== null)
                {
                    if($this->getMessage()->getReplyToMessage()->getForwardFrom() !== null)
                    {
                        $ForwardUserObject = User::fromArray($this->getMessage()->getReplyToMessage()->getForwardFrom()->getRawData());
                        $ForwardUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($ForwardUserObject);
                        if(isset($ForwardUserClient->SessionData->Data["user_status"]) == false)
                        {
                            $ForwardUserStatus = SettingsManager::getUserStatus($ForwardUserClient);
                            $ForwardUserClient = SettingsManager::updateUserStatus($ForwardUserClient, $ForwardUserStatus);
                            $TelegramClientManager->getTelegramClientManager()->updateClient($ForwardUserClient);
                        }
                    }

                    if($this->getMessage()->getReplyToMessage()->getForwardFromChat() !== null)
                    {
                        $ForwardChannelObject = Chat::fromArray($this->getMessage()->getReplyToMessage()->getForwardFromChat()->getRawData());
                        $ForwardChannelClient = $TelegramClientManager->getTelegramClientManager()->registerChat($ForwardChannelObject);
                        if(isset($ForwardChannelClient->SessionData->Data["channel_status"]) == false)
                        {
                            $ForwardChannelStatus = SettingsManager::getChannelStatus($ForwardChannelClient);
                            $ForwardChannelClient = SettingsManager::updateChannelStatus($ForwardChannelClient, $ForwardChannelStatus);
                            $TelegramClientManager->getTelegramClientManager()->updateClient($ForwardChannelClient);
                        }
                    }
                }
            }
            catch(Exception $e)
            {
                $ReferenceID = TgFileLogging::dumpException($e, TELEGRAM_BOT_NAME, $this->name);
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "Oops! Something went wrong! contact someone in @IntellivoidDiscussions\n\n" .
                        "Error Code: <code>" . $ReferenceID . "</code>\n" .
                        "Object: <code>Commands/" . $this->name . ".bin</code>"
                ]);
            }

            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'msginfo_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$TelegramClient->getChatId());
            $DeepAnalytics->tally('tg_spam_protection', 'msginfo_command', (int)$TelegramClient->getChatId());

            if($this->getMessage()->getText(true) !== null)
            {
                if(strlen($this->getMessage()->getText(true)) > 0)
                {
                    $CommandParameters = explode(" ", $this->getMessage()->getText(true));
                    $CommandParameters = array_values(array_filter($CommandParameters, 'strlen'));
                    $TargetMessageParameter = null;

                    if(count($CommandParameters) . 0)
                    {
                        $TargetMessageParameter = $CommandParameters[0];
                        $Results = $this->lookupMessageInfo($TargetMessageParameter);

                        if($Results == null)
                        {
                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "text" => "Unable to resolve the query '$TargetMessageParameter'!"
                            ]);
                        }

                        return $Results;
                    }

                    if($TargetMessageParameter == null)
                    {
                        $TargetMessageParameter = "No Input";
                    }

                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "text" => "Unable to resolve the query '$TargetMessageParameter'!"
                    ]);
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


                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
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
            return Request::sendMessage([
                "chat_id" => $this->getMessage()->getChat()->getId(),
                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                "parse_mode" => "html",
                "text" =>
                    "$error\n\n" .
                    "Usage:\n" .
                    "<b>/msginfo</b> <code>[Message Hash]</code>"
            ]);
        }

    }