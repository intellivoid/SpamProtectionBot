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
    use SpamProtection\Exceptions\DatabaseException;
    use SpamProtection\Exceptions\MessageLogNotFoundException;
    use SpamProtection\Objects\TelegramClient\Chat;
    use SpamProtection\Objects\TelegramClient\User;
    use SpamProtection\SpamProtection;
    use SpamProtection\Utilities\Hashing;

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
        protected $name = 'Message information command';

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
         * @throws DatabaseException
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
                    $ChatSettings = $SpamProtection->getSettingsManager()->getChatSettings($ChatClient);
                    $ChatClient = $SpamProtection->getSettingsManager()->updateChatSettings($ChatClient, $ChatSettings);
                }
                $SpamProtection->getTelegramClientManager()->updateClient($ChatClient);

                // Define and update user client
                $UserClient = $SpamProtection->getTelegramClientManager()->registerUser($UserObject);
                if(isset($UserClient->SessionData->Data["user_status"]) == false)
                {
                    $UserStatus = $SpamProtection->getSettingsManager()->getUserStatus($UserClient);
                    $UserClient = $SpamProtection->getSettingsManager()->updateUserStatus($UserClient, $UserStatus);
                }
                $SpamProtection->getTelegramClientManager()->updateClient($UserClient);

                // Define and update the forwarder if available
                if($this->getMessage()->getForwardFrom() !== null)
                {
                    $ForwardUserObject = User::fromArray($this->getMessage()->getForwardFrom()->getRawData());
                    $ForwardUserClient = $SpamProtection->getTelegramClientManager()->registerUser($ForwardUserObject);
                    if(isset($ForwardUserClient->SessionData->Data["user_status"]) == false)
                    {
                        $ForwardUserStatus = $SpamProtection->getSettingsManager()->getUserStatus($ForwardUserClient);
                        $ForwardUserClient = $SpamProtection->getSettingsManager()->updateUserStatus($ForwardUserClient, $ForwardUserStatus);
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
                        "Object: <code>Commands/msginfo.bin</code>"
                ]);
            }

            $DeepAnalytics = new DeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'msginfo_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$TelegramClient->getChatId());
            $DeepAnalytics->tally('tg_spam_protection', 'msginfo_command', (int)$TelegramClient->getChatId());

            if($this->getMessage()->getText(true) !== null)
            {
                if(strlen($this->getMessage()->getText(true)) > 0)
                {
                    $CommandParameters = explode(" ", $this->getMessage()->getText(true));
                    $CommandParameters = array_filter($CommandParameters, 'strlen');

                    $TargetMessageParameter = $CommandParameters[0];

                    try
                    {
                        $MessageLog = $SpamProtection->getMessageLogManager()->getMessage($TargetMessageParameter);

                        $Response = "<b>Message Hash Lookup</b>\n\n";

                        $UserPrivateID = Hashing::telegramClientPublicID($MessageLog->User->ID, $MessageLog->User->ID);
                        $ChatPrivateID = Hashing::telegramClientPublicID($MessageLog->Chat->ID, $MessageLog->Chat->ID);
                        $Response .= "   <b>Private User ID:</b> <code>" . $UserPrivateID . "</code>\n";
                        $Response .= "   <b>Private Chat ID:</b> <code>" . $ChatPrivateID . "</code>\n";
                        $Response .= "   <b>Message ID:</b> <code>" . $MessageLog->ID . "</code>\n";
                        $Response .= "   <b>Content Hash:</b> <code>" . $MessageLog->ContentHash . "</code>\n";

                        if($MessageLog->Chat->Username !== null)
                        {
                            $Response .= "   <b>Link:</b> <a href=\"https://t.me/" . $MessageLog->Chat->Username . "/" . $MessageLog->MessageID . "\">" . $MessageLog->Chat->Username . "/" . $MessageLog->MessageID . "</a>\n";
                        }

                        if($MessageLog->ForwardFrom->ID !== null)
                        {
                            $ForwardUserPrivateID = Hashing::telegramClientPublicID($MessageLog->ForwardFrom->ID, $MessageLog->ForwardFrom->ID);
                            $Response .= "   <b>Original Sender Private ID (Forwarded):</b> <code>" . $ForwardUserPrivateID . "</code>\n";
                        }

                        if($MessageLog->SpamPrediction > 0 && $MessageLog->HamPrediction > 0)
                        {
                            $Response .= "   <b>Ham Prediction:</b> <code>" . $MessageLog->HamPrediction . "</code>\n";
                            $Response .= "   <b>Spam Prediction:</b> <code>" . $MessageLog->SpamPrediction . "</code>\n";

                            if($MessageLog->SpamPrediction > $MessageLog->HamPrediction)
                            {
                                $Response .= "   <b>Is Spam:</b> <code>True</code>\n";
                            }
                            else
                            {
                                $Response .= "   <b>Is Spam:</b> <code>False</code>\n";
                            }
                        }

                        return Request::sendMessage([
                            "chat_id" => $this->getMessage()->getChat()->getId(),
                            "parse_mode" => "html",
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "text" => $Response
                        ]);
                    }
                    catch(MessageLogNotFoundException $messageLogNotFoundException)
                    {
                        unset($messageLogNotFoundException);
                    }

                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "text" => "Unable to resolve the query '$TargetMessageParameter'!"
                    ]);
                }
            }

            return self::displayUsage($this->getMessage(), "Missing message hash parameter");

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
                    "   <b>/msginfo</b> <code>[Message Hash]</code>"
            ]);
        }
    }