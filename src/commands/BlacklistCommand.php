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
    use SpamProtection\Abstracts\BlacklistFlag;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtection\Utilities\Hashing;
    use SpamProtectionBot;
    use TelegramClientManager\Abstracts\SearchMethods\TelegramClientSearchMethod;
    use TelegramClientManager\Abstracts\TelegramChatType;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;
    use TelegramClientManager\Objects\TelegramClient;
    use TelegramClientManager\TelegramClientManager;

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
        protected $name = 'Blacklist user command';

        /**
         * @var string
         */
        protected $description = 'Allows the operator with sufficient permissions to blacklist a user';

        /**
         * @var string
         */
        protected $usage = '/blacklist [Reply/ID/Private Telegram ID/Username]';

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
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @noinspection DuplicatedCode
         */
        public function execute()
        {
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();

            $ChatObject = TelegramClient\Chat::fromArray($this->getMessage()->getChat()->getRawData());
            $UserObject = TelegramClient\User::fromArray($this->getMessage()->getFrom()->getRawData());

            try
            {
                $TelegramClient = $TelegramClientManager->getTelegramClientManager()->registerClient(
                    $ChatObject, $UserObject);

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
                    $ForwardUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser(
                        $ForwardUserObject);
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
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "Oops! Something went wrong! contact someone in @IntellivoidDiscussions\n\n" .
                        "Error Code: <code>" . $e->getCode() . "</code>\n" .
                        "Object: <code>Commands/blacklist.bin</code>"
                ]);
            }

            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'blacklist_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$TelegramClient->getChatId());
            $DeepAnalytics->tally('tg_spam_protection', 'blacklist_command', (int)$TelegramClient->getChatId());

            $UserStatus = SettingsManager::getUserStatus($UserClient);
            if($UserStatus->IsOperator == false)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => "This command can only be used by an operator!"
                ]);
            }

            if($this->getMessage()->getReplyToMessage() !== null)
            {
                $TargetUser = TelegramClient\User::fromArray($this->getMessage()->getReplyToMessage()->getFrom()->getRawData());
                $TargetUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($TargetUser);

                $CommandParameters = explode(" ", $this->getMessage()->getText(true));
                $CommandParameters = array_values(array_filter($CommandParameters, 'strlen'));

                if(count($CommandParameters) == 0)
                {
                    return self::displayUsage($this->getMessage(), "Missing blacklist parameter/forward option");
                }
                else
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
                            $TargetForwardUser = TelegramClient\User::fromArray(
                                $this->getMessage()->getReplyToMessage()->getForwardFrom()->getRawData());
                            $TargetForwardUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser(
                                $TargetForwardUser);

                            // If missing the blacklist parameter
                            if(count($CommandParameters) < 2)
                            {
                                return self::displayUsage($this->getMessage(), "Missing blacklist parameter");
                            }

                            // If it contains the original user ID
                            if(count($CommandParameters) == 3)
                            {
                                return self::blacklistUser(
                                    $TelegramClientManager, $TargetForwardUserClient, $UserClient, $this->getMessage(),
                                    $CommandParameters[1], $CommandParameters[2]
                                );
                            }
                            else
                            {
                                return self::blacklistUser(
                                    $TelegramClientManager, $TargetForwardUserClient, $UserClient, $this->getMessage(),
                                    $CommandParameters[1]
                                );
                            }
                        }
                    }
                    // Target the user the operator replied to
                    else
                    {
                        if(count($CommandParameters) == 1)
                        {
                            return self::blacklistUser(
                                $TelegramClientManager, $TargetUserClient, $UserClient, $this->getMessage(),
                                $CommandParameters[0]
                            );
                        }
                        else
                        {
                            return self::blacklistUser(
                                $TelegramClientManager, $TargetUserClient, $UserClient, $this->getMessage(),
                                $CommandParameters[0], $CommandParameters[1]
                            );
                        }
                    }
                }
            }

            if($this->getMessage()->getText(true) !== null)
            {
                if(strlen($this->getMessage()->getText(true)) > 0)
                {
                    $CommandParameters = explode(" ", $this->getMessage()->getText(true));
                    $CommandParameters = array_values(array_filter($CommandParameters, 'strlen'));

                    $TargetUserParameter = $CommandParameters[0];
                    $EstimatedPrivateID = Hashing::telegramClientPublicID(
                        (int)$TargetUserParameter, (int)$TargetUserParameter);

                    try
                    {
                        $TargetUserClient = $TelegramClientManager->getTelegramClientManager()->getClient(
                            TelegramClientSearchMethod::byPublicId, $EstimatedPrivateID);

                        // Target user via ID
                        if(count($CommandParameters) < 2)
                        {
                            return self::displayUsage($this->getMessage(), "Missing blacklist parameter");
                        }
                        elseif(count($CommandParameters) == 2)
                        {
                            return self::blacklistUser(
                                $TelegramClientManager, $TargetUserClient, $UserClient, $this->getMessage(),
                                $CommandParameters[1]
                            );
                        }
                        else
                        {
                            return self::blacklistUser(
                                $TelegramClientManager, $TargetUserClient, $UserClient, $this->getMessage(),
                                $CommandParameters[1], $CommandParameters[2]
                            );
                        }
                    }
                    catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                    {
                        unset($telegramClientNotFoundException);
                    }

                    try
                    {
                        $TargetUserClient = $TelegramClientManager->getTelegramClientManager()->getClient(
                            TelegramClientSearchMethod::byPublicId, $TargetUserParameter);

                        // Target user via Private ID
                        if(count($CommandParameters) < 2)
                        {
                            return self::displayUsage($this->getMessage(), "Missing blacklist parameter");
                        }
                        elseif(count($CommandParameters) == 2)
                        {
                            return self::blacklistUser(
                                $TelegramClientManager, $TargetUserClient, $UserClient, $this->getMessage(),
                                $CommandParameters[1]
                            );
                        }
                        else
                        {
                            return self::blacklistUser(
                                $TelegramClientManager, $TargetUserClient, $UserClient, $this->getMessage(),
                                $CommandParameters[1], $CommandParameters[2]
                            );
                        }
                    }
                    catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                    {
                        unset($telegramClientNotFoundException);
                    }


                    try
                    {
                        $TargetUserClient = $TelegramClientManager->getTelegramClientManager()->getClient(
                            TelegramClientSearchMethod::byUsername,
                            str_ireplace("@", "", $TargetUserParameter)
                        );

                        // Target user via Username
                        if(count($CommandParameters) < 2)
                        {
                            return self::displayUsage($this->getMessage(), "Missing blacklist parameter");
                        }
                        elseif(count($CommandParameters) == 2)
                        {
                            return self::blacklistUser(
                                $TelegramClientManager, $TargetUserClient, $UserClient, $this->getMessage(),
                                $CommandParameters[1]
                            );
                        }
                        else
                        {
                            return self::blacklistUser(
                                $TelegramClientManager, $TargetUserClient, $UserClient, $this->getMessage(),
                                $CommandParameters[1], $CommandParameters[2]
                            );
                        }
                    }
                    catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                    {
                        unset($telegramClientNotFoundException);
                    }

                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "text" => "Unable to resolve the query '$TargetUserParameter'!"
                    ]);
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
        public static function displayUsage(Message $message, string $error="Missing parameter")
        {
            return Request::sendMessage([
                "chat_id" => $message->getChat()->getId(),
                "parse_mode" => "html",
                "reply_to_message_id" => $message->getMessageId(),
                "text" =>
                    "$error\n\n" .
                    "Usage:\n" .
                    "   <b>/blacklist</b> (In reply to target user) <code>[Blacklist Flag]</code>\n" .
                    "   <b>/blacklist</b> (In reply to forwarded content) -f <code>[Blacklist Flag]</code>\n" .
                    "   <b>/blacklist</b> <code>[Private Telegram ID]</code> <code>[Blacklist Flag]</code>\n" .
                    "   <b>/blacklist</b> <code>[User ID]</code> <code>[Blacklist Flag]</code>\n" .
                    "   <b>/blacklist</b> <code>[Username]</code> <code>[Blacklist Flag]</code>\n\n" .
                    "For further instructions, refer to the operator manual"
            ]);
        }

        /**
         * Takes a blacklist flag and converts it into a user-readable message
         *
         * @param string $flag
         * @return string
         */
        private static function blacklistFlagToReason(string $flag): string
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

                default:
                    return "Unknown";
            }
        }

        /**
         * Logs a blacklist action
         *
         * @param TelegramClient $targetUserClient
         * @param TelegramClient $operatorUserClient
         * @param bool $removal
         * @param bool $update
         * @param string|null $previous_flag
         * @return ServerResponse
         * @throws TelegramException
         */
        private static function logAction(TelegramClient $targetUserClient, TelegramClient $operatorUserClient, bool $removal=false, bool $update=false, string $previous_flag=null)
        {
            $TargetUserStatus = SettingsManager::getUserStatus($targetUserClient);

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

            $LogMessage .= "<b>Private Telegram ID:</b> <code>" . $targetUserClient->PublicID . "</code>\n";
            $LogMessage .= "<b>Operator PTID:</b> <code>" . $operatorUserClient->PublicID . "</code>\n";

            if($removal)
            {
                $LogMessage .= "\n<i>The previous blacklist flag</i> <code>$previous_flag</code> <i>has been lifted</i>";
            }
            elseif($update)
            {$LogMessage .= "<b>Previous Flag:</b> <code>" . $previous_flag . "</code> (" . self::blacklistFlagToReason($previous_flag) . ")\n";
                $LogMessage .= "<b>New Flag:</b> <code>" . $TargetUserStatus->BlacklistFlag . "</code> (" . self::blacklistFlagToReason($TargetUserStatus->BlacklistFlag) . ")\n";
            }
            else
            {
                $LogMessage .= "<b>Blacklist Flag:</b> <code>" . $TargetUserStatus->BlacklistFlag . "</code> (" . self::blacklistFlagToReason($TargetUserStatus->BlacklistFlag) . ")\n";
            }

            return Request::sendMessage([
                "chat_id" => "@SpamProtectionLogs",
                "disable_web_page_preview" => true,
                "disable_notification" => true,
                "parse_mode" => "html",
                "text" => $LogMessage
            ]);
        }

        /**
         * Blacklists a user
         *
         * @param TelegramClientManager $telegramClientManager
         * @param TelegramClient $targetUserClient
         * @param TelegramClient $operatorClient
         * @param Message $message
         * @param string $blacklistFlag
         * @param string|null $originalPrivateID
         * @return ServerResponse
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramException
         */
        public static function blacklistUser(TelegramClientManager $telegramClientManager, TelegramClient $targetUserClient, TelegramClient $operatorClient, Message $message, string $blacklistFlag, string $originalPrivateID=null)
        {
            if($targetUserClient->Chat->Type !== TelegramChatType::Private)
            {
                return Request::sendMessage([
                    "chat_id" => $message->getChat()->getId(),
                    "parse_mode" => "html",
                    "reply_to_message_id" => $message->getMessageId(),
                    "text" => "This operation is not applicable to this user."
                ]);
            }

            $UserStatus = SettingsManager::getUserStatus($targetUserClient);
            $OriginalUserStatus = SettingsManager::getUserStatus($targetUserClient);

            if($UserStatus->IsOperator)
            {
                return Request::sendMessage([
                    "chat_id" => $message->getChat()->getId(),
                    "parse_mode" => "html",
                    "reply_to_message_id" => $message->getMessageId(),
                    "text" => "You can't blacklist an operator"
                ]);
            }

            if($UserStatus->IsAgent)
            {
                return Request::sendMessage([
                    "chat_id" => $message->getChat()->getId(),
                    "parse_mode" => "html",
                    "reply_to_message_id" => $message->getMessageId(),
                    "text" => "You can't blacklist an agent"
                ]);
            }

            if($UserStatus->IsWhitelisted)
            {
                return Request::sendMessage([
                    "chat_id" => $message->getChat()->getId(),
                    "parse_mode" => "html",
                    "reply_to_message_id" => $message->getMessageId(),
                    "text" => "You can't blacklist a user who's whitelisted"
                ]);
            }

            switch(str_ireplace('X', 'x', strtoupper($blacklistFlag)))
            {
                case BlacklistFlag::Special:
                    if($operatorClient->User->Username !== "IntellivoidSupport")
                    {

                        return Request::sendMessage([
                            "chat_id" => $message->getChat()->getId(),
                            "parse_mode" => "html",
                            "reply_to_message_id" => $message->getMessageId(),
                            "text" => "Only IntellivoidSupport can blacklist using the flag 0xSP"
                        ]);
                    }

                    $UserStatus->IsBlacklisted = true;
                    $UserStatus->BlacklistFlag = BlacklistFlag::Special;
                    $UserStatus->OriginalPrivateID = null;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    $telegramClientManager->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                case BlacklistFlag::Spam:
                    $UserStatus->IsBlacklisted = true;
                    $UserStatus->BlacklistFlag = BlacklistFlag::Spam;
                    $UserStatus->OriginalPrivateID = null;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    $telegramClientManager->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                case BlacklistFlag::Scam:
                    $UserStatus->IsBlacklisted = true;
                    $UserStatus->BlacklistFlag = BlacklistFlag::Scam;
                    $UserStatus->OriginalPrivateID = null;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    $telegramClientManager->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                case BlacklistFlag::Raid:
                    $UserStatus->IsBlacklisted = true;
                    $UserStatus->BlacklistFlag = BlacklistFlag::Raid;
                    $UserStatus->OriginalPrivateID = null;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    $telegramClientManager->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                case BlacklistFlag::PrivateSpam:
                    $UserStatus->IsBlacklisted = true;
                    $UserStatus->BlacklistFlag = BlacklistFlag::PrivateSpam;
                    $UserStatus->OriginalPrivateID = null;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    $telegramClientManager->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                case BlacklistFlag::PornographicSpam:
                    $UserStatus->IsBlacklisted = true;
                    $UserStatus->BlacklistFlag = BlacklistFlag::PornographicSpam;
                    $UserStatus->OriginalPrivateID = null;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    $telegramClientManager->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                case BlacklistFlag::PiracySpam:
                    $UserStatus->IsBlacklisted = true;
                    $UserStatus->BlacklistFlag = BlacklistFlag::PiracySpam;
                    $UserStatus->OriginalPrivateID = null;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    $telegramClientManager->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                case BlacklistFlag::Impersonator:
                    $UserStatus->IsBlacklisted = true;
                    $UserStatus->BlacklistFlag = BlacklistFlag::Impersonator;
                    $UserStatus->OriginalPrivateID = null;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    $telegramClientManager->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                case BlacklistFlag::ChildAbuse:
                    $UserStatus->IsBlacklisted = true;
                    $UserStatus->BlacklistFlag = BlacklistFlag::ChildAbuse;
                    $UserStatus->OriginalPrivateID = null;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    $telegramClientManager->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                case BlacklistFlag::BanEvade:
                    if($originalPrivateID == null)
                    {

                        return Request::sendMessage([
                            "chat_id" => $message->getChat()->getId(),
                            "parse_mode" => "html",
                            "reply_to_message_id" => $message->getMessageId(),
                            "text" => "This blacklist flag requires an additional parameter 'Private Telegram ID'"
                        ]);
                    }

                    try
                    {
                        $telegramClientManager->getTelegramClientManager()->getClient(TelegramClientSearchMethod::byPublicId, $originalPrivateID);
                    }
                    catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                    {
                        unset($telegramClientNotFoundException);
                        return Request::sendMessage([
                            "chat_id" => $message->getChat()->getId(),
                            "parse_mode" => "html",
                            "reply_to_message_id" => $message->getMessageId(),
                            "text" => "The given private ID does not exist"
                        ]);
                    }

                    $UserStatus->IsBlacklisted = true;
                    $UserStatus->BlacklistFlag = BlacklistFlag::BanEvade;
                    $UserStatus->OriginalPrivateID = $originalPrivateID;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    $telegramClientManager->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                case BlacklistFlag::None:
                    $UserStatus->IsBlacklisted = false;
                    $UserStatus->BlacklistFlag = BlacklistFlag::None;
                    $UserStatus->OriginalPrivateID = null;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    $telegramClientManager->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                default:
                    return Request::sendMessage([
                        "chat_id" => $message->getChat()->getId(),
                        "parse_mode" => "html",
                        "reply_to_message_id" => $message->getMessageId(),
                        "text" => "Invalid blacklist flag <code>" . str_ireplace('X', 'x', strtoupper($blacklistFlag)) . "</code>"
                    ]);
            }


            if($UserStatus->BlacklistFlag == BlacklistFlag::None)
            {
                Request::sendMessage([
                    "chat_id" => $message->getChat()->getId(),
                    "parse_mode" => "html",
                    "reply_to_message_id" => $message->getMessageId(),
                    "text" =>
                        "The user <code>" . $targetUserClient->PublicID . "</code> blacklist flag has been removed"
                ]);
                return self::logAction(
                    $targetUserClient, $operatorClient,
                    true, false, $OriginalUserStatus->BlacklistFlag
                );
            }

            if($OriginalUserStatus->IsBlacklisted)
            {
                Request::sendMessage([
                    "chat_id" => $message->getChat()->getId(),
                    "parse_mode" => "html",
                    "reply_to_message_id" => $message->getMessageId(),
                    "text" =>
                        "The user <code>" . $targetUserClient->PublicID . "</code> blacklist flag has been updated from " .
                        "<code>" . str_ireplace('X', 'x', strtoupper($OriginalUserStatus->BlacklistFlag)) . "</code> to ".
                        "<code>" . str_ireplace('X', 'x', strtoupper($UserStatus->BlacklistFlag)) . "</code>"
                ]);
                return self::logAction(
                    $targetUserClient, $operatorClient,
                    false, true, $OriginalUserStatus->BlacklistFlag
                );
            }
            else
            {
                Request::sendMessage([
                    "chat_id" => $message->getChat()->getId(),
                    "parse_mode" => "html",
                    "reply_to_message_id" => $message->getMessageId(),
                    "text" =>
                        "The user <code>" . $targetUserClient->PublicID . "</code> has been blacklisted with the flag " .
                        "<code>" . str_ireplace('X', 'x', strtoupper($UserStatus->BlacklistFlag)) . "</code>"
                ]);
                return self::logAction(
                    $targetUserClient, $operatorClient,
                    false, false
                );
            }

        }

    }