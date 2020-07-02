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
    use TelegramClientManager\Objects\TelegramClient\Chat;
    use TelegramClientManager\Objects\TelegramClient\User;

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
        protected $name = 'blacklist';

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

            // Is it a reply to message?
            if($this->getMessage()->getReplyToMessage() !== null)
            {
                $TargetUser = TelegramClient\User::fromArray($this->getMessage()->getReplyToMessage()->getFrom()->getRawData());
                $TargetUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($TargetUser);

                $CommandParameters = explode(" ", $this->getMessage()->getText(true));
                $CommandParameters = array_values(array_filter($CommandParameters, 'strlen'));

                // Check if there's parameters
                if(count($CommandParameters) == 0)
                {
                    return self::displayUsage($this->getMessage(), "Missing blacklist parameter/forward option");
                }
                else
                {
                    // If to target the forwarder
                    if(strtolower($CommandParameters[0]) == "-f")
                    {
                        // If the message is from a user
                        if($this->getMessage()->getReplyToMessage()->getForwardFrom() !== null)
                        {
                            $TargetForwardUser = TelegramClient\User::fromArray($this->getMessage()->getReplyToMessage()->getForwardFrom()->getRawData());
                            $TargetForwardUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($TargetForwardUser);

                            // If missing the blacklist parameter
                            if(count($CommandParameters) < 2)
                            {
                                return self::displayUsage($this->getMessage(), "Missing blacklist parameter");
                            }

                            // If it contains the original user ID
                            if(count($CommandParameters) == 3)
                            {
                                return self::blacklistTarget(
                                    $TargetForwardUserClient, $UserClient,
                                    $CommandParameters[1], $CommandParameters[2]
                                );
                            }
                            else
                            {
                                return self::blacklistTarget($TargetForwardUserClient, $UserClient, $CommandParameters[1]);
                            }
                        }
                        // If the message is from a channel
                        elseif($this->getMessage()->getReplyToMessage()->getForwardFromChat() !== null)
                        {
                            $TargetChannel = Chat::fromArray($this->getMessage()->getReplyToMessage()->getForwardFromChat()->getRawData());
                            $TargetForwardChannelClient = $TelegramClientManager->getTelegramClientManager()->registerChat($TargetChannel);

                            // If missing the blacklist parameter
                            if(count($CommandParameters) < 2)
                            {
                                return self::displayUsage($this->getMessage(), "Missing blacklist parameter");
                            }

                            // If it contains the original user ID
                            if(count($CommandParameters) == 3)
                            {
                                return self::blacklistTarget(
                                    $TargetForwardChannelClient, $UserClient,
                                    $CommandParameters[1], $CommandParameters[2]
                                );
                            }
                            else
                            {
                                return self::blacklistTarget($TargetForwardChannelClient, $UserClient, $CommandParameters[1]);
                            }
                        }
                        else
                        {
                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "text" => "Unable to get the target user/channel from the forwarded message"
                            ]);
                        }
                    }
                    // Target the user the operator replied to
                    else
                    {
                        if(count($CommandParameters) == 1)
                        {
                            return self::blacklistTarget($TargetUserClient, $UserClient, $CommandParameters[0]);
                        }
                        else
                        {
                            return self::blacklistTarget($TargetUserClient, $UserClient, $CommandParameters[0], $CommandParameters[1]);
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
                    $EstimatedPrivateID = Hashing::telegramClientPublicID((int)$TargetUserParameter, (int)$TargetUserParameter);

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
                            return self::blacklistTarget($TargetUserClient, $UserClient, $CommandParameters[1]);
                        }
                        else
                        {
                            return self::blacklistTarget($TargetUserClient, $UserClient, $CommandParameters[1], $CommandParameters[2]);
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
                            return self::blacklistTarget($TargetUserClient, $UserClient, $CommandParameters[1]);
                        }
                        else
                        {
                            return self::blacklistTarget($TargetUserClient, $UserClient, $CommandParameters[1], $CommandParameters[2]);
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
                            return self::blacklistTarget($TargetUserClient, $UserClient, $CommandParameters[1]);
                        }
                        else
                        {
                            return self::blacklistTarget($TargetUserClient, $UserClient, $CommandParameters[1], $CommandParameters[2]);
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
                    "   <b>/blacklist</b> (In reply to forwarded content/channel content) -f <code>[Blacklist Flag]</code>\n" .
                    "   <b>/blacklist</b> <code>[Private Telegram ID]</code> <code>[Blacklist Flag]</code>\n" .
                    "   <b>/blacklist</b> <code>[User/Channel ID]</code> <code>[Blacklist Flag]</code>\n" .
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
         * Determines the target type and applies the appropiate blacklist flag to the client
         *
         * @param TelegramClient $targetClient
         * @param TelegramClient $operatorClient
         * @param string $blacklistFlag
         * @param string|null $originalPrivateID
         * @return ServerResponse|null
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         */
        public function blacklistTarget(TelegramClient $targetClient, TelegramClient $operatorClient, string $blacklistFlag, string $originalPrivateID=null)
        {
            switch($targetClient->Chat->Type)
            {
                case TelegramChatType::Group:
                case TelegramChatType::SuperGroup:
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "You can't blacklist a chat"
                    ]);

                case TelegramChatType::Private:
                    return self::blacklistUser($targetClient, $operatorClient, $blacklistFlag, $originalPrivateID);

                case TelegramChatType::Channel:
                    return self::blacklistChannel($targetClient, $operatorClient, $blacklistFlag);

                default:
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "The entity type '" .  $targetClient->Chat->Type . "' cannot be blacklisted"
                    ]);

            }
        }

        /**
         * Blacklists a user
         *
         * @param TelegramClient $targetUserClient
         * @param TelegramClient $operatorClient
         * @param string $blacklistFlag
         * @param string|null $originalPrivateID
         * @return ServerResponse
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @noinspection DuplicatedCode
         */
        public function blacklistUser(TelegramClient $targetUserClient, TelegramClient $operatorClient, string $blacklistFlag, string $originalPrivateID=null)
        {
            if($targetUserClient->Chat->Type !== TelegramChatType::Private)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => "This operation is not applicable to this entity."
                ]);
            }

            $UserStatus = SettingsManager::getUserStatus($targetUserClient);
            $OriginalUserStatus = SettingsManager::getUserStatus($targetUserClient);

            if($UserStatus->IsOperator)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => "You can't blacklist an operator"
                ]);
            }

            if($UserStatus->IsAgent)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => "You can't blacklist an agent"
                ]);
            }

            if($UserStatus->IsWhitelisted)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => "You can't blacklist a user who's whitelisted"
                ]);
            }

            if($UserStatus->IsBlacklisted)
            {
                if($UserStatus->BlacklistFlag == $blacklistFlag)
                {
                    if($UserStatus->BlacklistFlag == BlacklistFlag::BanEvade)
                    {
                        if($UserStatus->OriginalPrivateID == $originalPrivateID)
                        {
                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "text" =>
                                    "This user is already blacklisted for ban evade with the same ".
                                    "Original Private ID, you can only update this user's blacklist if the ".
                                    "Original Private ID is different."
                            ]);
                        }
                    }
                    else
                    {
                        return Request::sendMessage([
                            "chat_id" => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            "text" => "This user is already blacklisted with the same flag."
                        ]);
                    }
                }
            }

            switch(str_ireplace('X', 'x', strtoupper($blacklistFlag)))
            {
                case BlacklistFlag::Special:
                    if($operatorClient->User->Username !== "IntellivoidSupport")
                    {

                        return Request::sendMessage([
                            "chat_id" => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            "text" => "Only IntellivoidSupport can blacklist using the flag 0xSP"
                        ]);
                    }

                    $UserStatus->IsBlacklisted = true;
                    $UserStatus->BlacklistFlag = BlacklistFlag::Special;
                    $UserStatus->OriginalPrivateID = null;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                case BlacklistFlag::Spam:
                    $UserStatus->IsBlacklisted = true;
                    $UserStatus->BlacklistFlag = BlacklistFlag::Spam;
                    $UserStatus->OriginalPrivateID = null;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                case BlacklistFlag::Scam:
                    $UserStatus->IsBlacklisted = true;
                    $UserStatus->BlacklistFlag = BlacklistFlag::Scam;
                    $UserStatus->OriginalPrivateID = null;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                case BlacklistFlag::Raid:
                    $UserStatus->IsBlacklisted = true;
                    $UserStatus->BlacklistFlag = BlacklistFlag::Raid;
                    $UserStatus->OriginalPrivateID = null;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                case BlacklistFlag::PrivateSpam:
                    $UserStatus->IsBlacklisted = true;
                    $UserStatus->BlacklistFlag = BlacklistFlag::PrivateSpam;
                    $UserStatus->OriginalPrivateID = null;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                case BlacklistFlag::PornographicSpam:
                    $UserStatus->IsBlacklisted = true;
                    $UserStatus->BlacklistFlag = BlacklistFlag::PornographicSpam;
                    $UserStatus->OriginalPrivateID = null;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                case BlacklistFlag::PiracySpam:
                    $UserStatus->IsBlacklisted = true;
                    $UserStatus->BlacklistFlag = BlacklistFlag::PiracySpam;
                    $UserStatus->OriginalPrivateID = null;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                case BlacklistFlag::Impersonator:
                    $UserStatus->IsBlacklisted = true;
                    $UserStatus->BlacklistFlag = BlacklistFlag::Impersonator;
                    $UserStatus->OriginalPrivateID = null;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                case BlacklistFlag::ChildAbuse:
                    $UserStatus->IsBlacklisted = true;
                    $UserStatus->BlacklistFlag = BlacklistFlag::ChildAbuse;
                    $UserStatus->OriginalPrivateID = null;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                case BlacklistFlag::BanEvade:
                    if($originalPrivateID == null)
                    {
                        return Request::sendMessage([
                            "chat_id" => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            "text" => "This blacklist flag requires an additional parameter 'Private Telegram ID'"
                        ]);
                    }

                    try
                    {
                        SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->getClient(TelegramClientSearchMethod::byPublicId, $originalPrivateID);
                    }
                    catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                    {
                        unset($telegramClientNotFoundException);
                        return Request::sendMessage([
                            "chat_id" => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            "text" => "The given private ID does not exist"
                        ]);
                    }

                    $UserStatus->IsBlacklisted = true;
                    $UserStatus->BlacklistFlag = BlacklistFlag::BanEvade;
                    $UserStatus->OriginalPrivateID = $originalPrivateID;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                case BlacklistFlag::None:
                    $UserStatus->IsBlacklisted = false;
                    $UserStatus->BlacklistFlag = BlacklistFlag::None;
                    $UserStatus->OriginalPrivateID = null;
                    $targetUserClient = SettingsManager::updateUserStatus($targetUserClient, $UserStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetUserClient);
                    break;

                default:
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "Invalid blacklist flag <code>" . str_ireplace('X', 'x', strtoupper($blacklistFlag)) . "</code>"
                    ]);
            }

            if($UserStatus->BlacklistFlag == BlacklistFlag::None)
            {
                Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => "The user <code>" . $targetUserClient->PublicID . "</code> blacklist flag has been removed"
                ]);
                return self::logAction(
                    $targetUserClient, $operatorClient,
                    true, false, $OriginalUserStatus->BlacklistFlag
                );
            }

            if($OriginalUserStatus->IsBlacklisted)
            {
                Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
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
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
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

        /**
         * Blacklists a channel
         *
         * @param TelegramClient $targetChannelClient
         * @param TelegramClient $operatorClient
         * @param string $blacklistFlag
         * @return ServerResponse
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @noinspection DuplicatedCode
         */
        public function blacklistChannel(TelegramClient $targetChannelClient, TelegramClient $operatorClient, string $blacklistFlag)
        {
            if($targetChannelClient->Chat->Type !== TelegramChatType::Channel)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => "This operation is not applicable to this entity."
                ]);
            }

            $ChannelStatus = SettingsManager::getChannelStatus($targetChannelClient);
            $OriginalChannelStatus = SettingsManager::getChannelStatus($targetChannelClient);

            if($ChannelStatus->IsWhitelisted)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => "You can't blacklist this channel since it's whitelisted"
                ]);
            }

            if($ChannelStatus->IsOfficial)
            {
                Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => "Notice! This channel is considered to be official by Intellivoid, nothing is stopping you from blacklisting it."
                ]);
            }

            if($ChannelStatus->IsBlacklisted)
            {
                if($ChannelStatus->BlacklistFlag == $blacklistFlag)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "This channel is already blacklisted with the same flag."
                    ]);
                }
            }

            switch(str_ireplace('X', 'x', strtoupper($blacklistFlag)))
            {
                case BlacklistFlag::Special:
                    if($operatorClient->User->Username !== "IntellivoidSupport")
                    {

                        return Request::sendMessage([
                            "chat_id" => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            "text" => "Only IntellivoidSupport can blacklist using the flag 0xSP"
                        ]);
                    }

                    $ChannelStatus->IsBlacklisted = true;
                    $ChannelStatus->BlacklistFlag = BlacklistFlag::Special;
                    $targetChannelClient = SettingsManager::updateChannelStatus($targetChannelClient, $ChannelStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetChannelClient);
                    break;

                case BlacklistFlag::Spam:
                    $ChannelStatus->IsBlacklisted = true;
                    $ChannelStatus->BlacklistFlag = BlacklistFlag::Spam;
                    $targetChannelClient = SettingsManager::updateChannelStatus($targetChannelClient, $ChannelStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetChannelClient);
                    break;

                case BlacklistFlag::Scam:
                    $ChannelStatus->IsBlacklisted = true;
                    $ChannelStatus->BlacklistFlag = BlacklistFlag::Scam;
                    $targetChannelClient = SettingsManager::updateChannelStatus($targetChannelClient, $ChannelStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetChannelClient);
                    break;

                case BlacklistFlag::Raid:
                    $ChannelStatus->IsBlacklisted = true;
                    $ChannelStatus->BlacklistFlag = BlacklistFlag::Raid;
                    $targetChannelClient = SettingsManager::updateChannelStatus($targetChannelClient, $ChannelStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetChannelClient);
                    break;

                case BlacklistFlag::PrivateSpam:
                    $ChannelStatus->IsBlacklisted = true;
                    $ChannelStatus->BlacklistFlag = BlacklistFlag::PrivateSpam;
                    $targetChannelClient = SettingsManager::updateChannelStatus($targetChannelClient, $ChannelStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetChannelClient);
                    break;

                case BlacklistFlag::PornographicSpam:
                    $ChannelStatus->IsBlacklisted = true;
                    $ChannelStatus->BlacklistFlag = BlacklistFlag::PornographicSpam;
                    $targetChannelClient = SettingsManager::updateChannelStatus($targetChannelClient, $ChannelStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetChannelClient);
                    break;

                case BlacklistFlag::PiracySpam:
                    $ChannelStatus->IsBlacklisted = true;
                    $ChannelStatus->BlacklistFlag = BlacklistFlag::PiracySpam;
                    $targetChannelClient = SettingsManager::updateChannelStatus($targetChannelClient, $ChannelStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetChannelClient);
                    break;

                case BlacklistFlag::Impersonator:
                    $ChannelStatus->IsBlacklisted = true;
                    $ChannelStatus->BlacklistFlag = BlacklistFlag::Impersonator;
                    $targetChannelClient = SettingsManager::updateChannelStatus($targetChannelClient, $ChannelStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetChannelClient);
                    break;

                case BlacklistFlag::ChildAbuse:
                    $ChannelStatus->IsBlacklisted = true;
                    $ChannelStatus->BlacklistFlag = BlacklistFlag::ChildAbuse;
                    $targetChannelClient = SettingsManager::updateChannelStatus($targetChannelClient, $ChannelStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetChannelClient);
                    break;

                case BlacklistFlag::BanEvade:
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "You can't blacklist a channel for ban evade."
                    ]);

                case BlacklistFlag::None:
                    $ChannelStatus->IsBlacklisted = false;
                    $ChannelStatus->BlacklistFlag = BlacklistFlag::None;
                    $targetChannelClient = SettingsManager::updateChannelStatus($targetChannelClient, $ChannelStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($targetChannelClient);
                    break;

                default:
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "text" => "Invalid blacklist flag <code>" . str_ireplace('X', 'x', strtoupper($blacklistFlag)) . "</code>"
                    ]);
            }

            if($ChannelStatus->BlacklistFlag == BlacklistFlag::None)
            {
                Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => "The channel <code>" . $targetChannelClient->PublicID . "</code> blacklist flag has been removed"
                ]);
                return self::logAction(
                    $targetChannelClient, $operatorClient,
                    true, false, $OriginalChannelStatus->BlacklistFlag
                );
            }

            if($OriginalChannelStatus->IsBlacklisted)
            {
                Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "The channel <code>" . $targetChannelClient->PublicID . "</code> blacklist flag has been updated from " .
                        "<code>" . str_ireplace('X', 'x', strtoupper($OriginalChannelStatus->BlacklistFlag)) . "</code> to ".
                        "<code>" . str_ireplace('X', 'x', strtoupper($ChannelStatus->BlacklistFlag)) . "</code>"
                ]);
                return self::logAction(
                    $targetChannelClient, $operatorClient,
                    false, true, $OriginalChannelStatus->BlacklistFlag
                );
            }
            else
            {
                Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "The channel <code>" . $targetChannelClient->PublicID . "</code> has been blacklisted with the flag " .
                        "<code>" . str_ireplace('X', 'x', strtoupper($ChannelStatus->BlacklistFlag)) . "</code>"
                ]);
                return self::logAction(
                    $targetChannelClient, $operatorClient,
                    false, false
                );
            }
        }

    }