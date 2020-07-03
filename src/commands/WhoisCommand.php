<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\UserCommands;

    use Exception;
    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Abstracts\BlacklistFlag;
    use SpamProtection\Abstracts\DetectionAction;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtection\Utilities\Hashing;
    use SpamProtectionBot;
    use TelegramClientManager\Abstracts\SearchMethods\TelegramClientSearchMethod;
    use TelegramClientManager\Abstracts\TelegramChatType;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;
    use TelegramClientManager\Objects\TelegramClient;
    use TgFileLogging;

    /**
     * Info command
     *
     * Allows the user to see the current information about requested user, either by
     * a reply to a message or by providing the private Telegram ID or Telegram ID
     */
    class WhoisCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'whois';

        /**
         * @var string
         */
        protected $description = 'Resolves information about the target object';

        /**
         * @var string
         */
        protected $usage = '/whois [None/Reply/ID/Private Telegram ID/Username]';

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
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
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
            $DeepAnalytics->tally('tg_spam_protection', 'whois_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$TelegramClient->getChatId());
            $DeepAnalytics->tally('tg_spam_protection', 'whois_command', (int)$TelegramClient->getChatId());

            if($this->getMessage()->getReplyToMessage() !== null)
            {
                $TargetUser = TelegramClient\User::fromArray($this->getMessage()->getReplyToMessage()->getFrom()->getRawData());
                $TargetTelegramClient = $TelegramClientManager->getTelegramClientManager()->registerUser($TargetUser);

                if($this->getMessage()->getReplyToMessage()->getForwardFromChat() !== null)
                {
                    $ForwardChannel = TelegramClient\Chat::fromArray($this->getMessage()->getReplyToMessage()->getForwardFromChat()->getRawData());
                    $ForwardChannelClient = $TelegramClientManager->getTelegramClientManager()->registerChat($ForwardChannel);

                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "parse_mode" => "html",
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "text" =>
                            self::resolveTarget($TargetTelegramClient, false, "None", false) .
                            "\n\n" .
                            self::resolveTarget($ForwardChannelClient, false, "None", true)
                    ]);
                }

                if($this->getMessage()->getReplyToMessage()->getForwardFrom() !== null)
                {
                    $ForwardUser = TelegramClient\User::fromArray($this->getMessage()->getReplyToMessage()->getForwardFrom()->getRawData());
                    $ForwardUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($ForwardUser);

                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "parse_mode" => "html",
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "text" =>
                            self::resolveTarget($TargetTelegramClient, false, "None", false) .
                            "\n\n" .
                            self::resolveTarget($ForwardUserClient, false, "None", true)
                    ]);
                }

                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "parse_mode" => "html",
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "text" => self::resolveTarget($TargetTelegramClient, false, "None", false)
                ]);
            }

            if($this->getMessage()->getText(true) !== null)
            {
                if(strlen($this->getMessage()->getText(true)) > 0)
                {
                    $CommandParameters = explode(" ", $this->getMessage()->getText(true));
                    $CommandParameters = array_values(array_filter($CommandParameters, 'strlen'));
                    $TargetTelegramParameter = null;

                    if(count($CommandParameters) > 0)
                    {
                        $TargetTelegramParameter = $CommandParameters[0];
                        $EstimatedPrivateID = Hashing::telegramClientPublicID((int)$TargetTelegramParameter, (int)$TargetTelegramParameter);

                        if($TargetTelegramParameter == "-c")
                        {
                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "parse_mode" => "html",
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "text" =>
                                    self::resolveTarget($UserClient, false, "None", false) .
                                    "\n\n" .
                                    self::resolveTarget($ChatClient, false, "None", false)
                            ]);
                        }

                        try
                        {
                            $TargetTelegramClient = $TelegramClientManager->getTelegramClientManager()->getClient(TelegramClientSearchMethod::byPublicId, $EstimatedPrivateID);

                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "parse_mode" => "html",
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "text" => self::resolveTarget($TargetTelegramClient, true, "ID", false)
                            ]);
                        }
                        catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                        {
                            unset($telegramClientNotFoundException);
                        }

                        try
                        {
                            $TargetTelegramClient = $TelegramClientManager->getTelegramClientManager()->getClient(TelegramClientSearchMethod::byPublicId, $TargetTelegramParameter);

                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "parse_mode" => "html",
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "text" => self::resolveTarget($TargetTelegramClient, true, "Private ID", false)
                            ]);
                        }
                        catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                        {
                            unset($telegramClientNotFoundException);
                        }

                        try
                        {
                            $TargetTelegramClient = $TelegramClientManager->getTelegramClientManager()->getClient(
                                TelegramClientSearchMethod::byUsername, str_ireplace("@", "", $TargetTelegramParameter)
                            );

                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "parse_mode" => "html",
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "text" => self::resolveTarget($TargetTelegramClient, true, "Username", false)
                            ]);
                        }
                        catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                        {
                            unset($telegramClientNotFoundException);
                        }
                    }

                    if($TargetTelegramParameter == null)
                    {
                        $TargetTelegramParameter = "No Input";
                    }

                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "text" => "Unable to resolve the query '$TargetTelegramParameter'!"
                    ]);
                }
            }

            return Request::sendMessage([
                "chat_id" => $this->getMessage()->getChat()->getId(),
                "parse_mode" => "html",
                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                "text" => self::resolveTarget($UserClient, false, "None", false)
            ]);
        }

        /**
         * Resolves the client target and returns the generated information about the target
         *
         * @param TelegramClient $target_client
         * @param bool $is_resolved
         * @param string $resolved_type
         * @param bool $is_forwarded
         * @return string
         */
        public function resolveTarget(TelegramClient $target_client, bool $is_resolved=false, string $resolved_type="Private ID", bool $is_forwarded=false): string
        {
            switch($target_client->Chat->Type)
            {
                case TelegramChatType::SuperGroup:
                case TelegramChatType::Group:
                    if($is_resolved)
                    {
                        return $this->generateChatInfoString($target_client, "Resolved Chat " . $resolved_type);
                    }

                    if($is_forwarded)
                    {
                        return $this->generateChatInfoString($target_client, "Forwarded Chat");
                    }

                    return $this->generateChatInfoString($target_client, "Chat Information");

                case TelegramChatType::Channel:
                    if($is_resolved)
                    {
                        return $this->generateChannelInfoString($target_client, "Resolved Channel " . $resolved_type);
                    }

                    if($is_forwarded)
                    {
                        return $this->generateChannelInfoString($target_client, "Forwarded Channel");
                    }

                    return $this->generateChannelInfoString($target_client, "Channel Information");

                case TelegramChatType::Private:
                    if($is_resolved)
                    {
                        return $this->generateUserInfoString($target_client, "Resolved User " . $resolved_type);
                    }

                    if($is_forwarded)
                    {
                        return $this->generateUserInfoString($target_client, "Original Sender");
                    }

                    return $this->generateUserInfoString($target_client, "User Information");

                default:
                    return $this->generateGenericInfoString($target_client, "Resolved Information");
            }
        }

        /**
         * If the client is neither a user, group, super group or channel then it's generic information
         *
         * @param TelegramClient $client
         * @param string $title
         * @return string
         */
        private function generateGenericInfoString(TelegramClient $client, string $title="Resolved Information"): string
        {
            $Response = "<b>$title</b>\n\n";

            $Response .= "<b>Private ID:</b> <code>" . $client->PublicID . "</code>\n";
            $Response .= "<b>User ID:</b> <code>" . $client->User->ID . "</code>\n";
            $Response .= "<b>Chat ID:</b> <code>" . $client->Chat->ID . "</code>\n";

            if($client->User->FirstName !== null)
            {
                $Response .= "<b>User First Name:</b> <code>" . self::escapeHTML($client->User->FirstName) . "</code>\n";
            }

            if($client->User->LastName !== null)
            {
                $Response .= "<b>User Last Name:</b> <code>" . self::escapeHTML($client->User->LastName) . "</code>\n";
            }

            if($client->User->Username !== null)
            {
                $Response .= "<b>User Username:</b> <code>" . $client->User->Username . "</code> (@" . $client->User->Username . ")\n";
            }

            if($client->User->IsBot)
            {
                $Response .= "<b>Is Bot:</b> <code>True</code>\n";
            }

            if($client->Chat->Type !== null)
            {
                $Response .= "<b>Chat Type:</b> <code>" . self::escapeHTML($client->Chat->Type) . "</code>\n";
            }

            if($client->Chat->Username !== null)
            {
                $Response .= "<b>Chat Username:</b> <code>" . self::escapeHTML($client->Chat->Username) . "</code>\n";
            }

            if($client->Chat->Title !== null)
            {
                $Response .= "<b>Chat Title:</b> <code>" . self::escapeHTML($client->Chat->Title) . "</code>\n";
            }

            return $Response;
        }

        /**
         * Generates a user information string
         *
         * @param TelegramClient $user_client
         * @param string $title
         * @return string
         * @noinspection DuplicatedCode
         */
        private function generateUserInfoString(TelegramClient $user_client, string $title="User Information"): string
        {
            $UserStatus = SettingsManager::getUserStatus($user_client);
            $RequiresExtraNewline = false;
            $Response = "<b>$title</b>\n\n";

            if($user_client->User->Username == "IntellivoidSupport")
            {
                $RequiresExtraNewline = true;
                $Response .= "\u{2705} This user is the main operator\n";
            }

            if($user_client->AccountID !== 0)
            {
                $RequiresExtraNewline = true;
                $Response .= "\u{2705} This user's Telegram account is verified by Intellivoid Accounts\n";
            }

            if($UserStatus->GeneralizedSpam > 0)
            {
                if($UserStatus->GeneralizedSpam > $UserStatus->GeneralizedHam)
                {
                    $RequiresExtraNewline = true;
                    $Response .= "\u{26A0} <b>This user may be an active spammer</b>\n";
                }
            }

            if($UserStatus->IsBlacklisted)
            {
                $RequiresExtraNewline = true;
                $Response .= "\u{26A0} <b>This user is blacklisted!</b>\n";
            }

            if($UserStatus->IsAgent)
            {
                $RequiresExtraNewline = true;
                $Response .= "\u{1F46E} This user is an agent who actively reports spam automatically\n";
            }

            if($UserStatus->IsOperator)
            {
                $RequiresExtraNewline = true;
                $Response .= "\u{1F46E} This user is an operator who can blacklist users\n";
            }

            if($RequiresExtraNewline)
            {
                $Response .= "\n";
            }

            $Response .= "<b>Private ID:</b> <code>" . $user_client->PublicID . "</code>\n";
            $Response .= "<b>User ID:</b> <code>" . $user_client->User->ID . "</code>\n";

            if($user_client->User->FirstName !== null)
            {
                $Response .= "<b>First Name:</b> <code>" . self::escapeHTML($user_client->User->FirstName) . "</code>\n";
            }

            if($user_client->User->LastName !== null)
            {
                $Response .= "<b>Last Name:</b> <code>" . self::escapeHTML($user_client->User->LastName) . "</code>\n";
            }

            if($user_client->User->Username !== null)
            {
                $Response .= "<b>Username:</b> <code>" . $user_client->User->Username . "</code> (@" . $user_client->User->Username . ")\n";
            }

            if($user_client->User->IsBot == false)
            {
                $Response .= "<b>Trust Prediction:</b> <code>" . $UserStatus->GeneralizedHam . "/" . $UserStatus->GeneralizedSpam . "</code>\n";
            }

            if($UserStatus->GeneralizedSpam > 0)
            {
                if($UserStatus->GeneralizedSpam > $UserStatus->GeneralizedHam)
                {
                    $Response .= "<b>Active Spammer:</b> <code>True</code>\n";
                }
            }

            if($UserStatus->IsWhitelisted)
            {
                $Response .= "<b>Whitelisted:</b> <code>True</code>\n";
            }

            if($UserStatus->IsBlacklisted)
            {
                $Response .= "<b>Blacklisted:</b> <code>True</code>\n";

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

            }

            if($UserStatus->IsOperator)
            {
                $Response .= "<b>Operator:</b> <code>True</code>\n";
            }

            if($UserStatus->IsAgent)
            {
                $Response .= "<b>Spam Detection Agent:</b> <code>True</code>\n";
            }

            $Response .=  "<b>User Link:</b> <a href=\"tg://user?id=" . $user_client->User->ID . "\">tg://user?id=" . $user_client->User->ID . "</a>";

            if($UserStatus->OperatorNote !== "None")
            {
                $Response .= "\n" . self::escapeHTML($UserStatus->OperatorNote) . "\n";
            }

            return $Response;
        }

        /**
         * Generates a user information string
         *
         * @param TelegramClient $chat_client
         * @param string $title
         * @return string
         * @noinspection DuplicatedCode
         */
        private function generateChatInfoString(TelegramClient $chat_client, string $title="Chat Information"): string
        {
            $ChatSettings = SettingsManager::getChatSettings($chat_client);
            $RequiresExtraNewline = false;
            $Response = "<b>$title</b>\n\n";

            if($ChatSettings->ForwardProtectionEnabled)
            {
                $RequiresExtraNewline = true;
                $Response .= "\u{1F6E1} This chat has forward protection enabled\n";
            }

            if($ChatSettings->IsVerified)
            {
                $RequiresExtraNewline = true;
                $Response .= "\u{2705} This chat is verified by Intellivoid Technologies\n";
            }

            if($RequiresExtraNewline)
            {
                $Response .= "\n";
            }

            $Response .= "<b>Private ID:</b> <code>" . $chat_client->PublicID . "</code>\n";
            $Response .= "<b>Chat ID:</b> <code>" . $chat_client->Chat->ID . "</code>\n";
            $Response .= "<b>Chat Type:</b> <code>" . $chat_client->Chat->Type . "</code>\n";
            $Response .= "<b>Chat Title:</b> <code>" . self::escapeHTML($chat_client->Chat->Title) . "</code>\n";

            if($chat_client->Chat->Username !== null)
            {
                $Response .= "<b>Chat Username:</b> <code>" . $chat_client->Chat->Username . "</code> (@" . $chat_client->Chat->Username . ")\n";
            }

            if($ChatSettings->ForwardProtectionEnabled)
            {
                $Response .= "<b>Forward Protection Enabled:</b> <code>True</code>\n";
            }

            if($ChatSettings->DetectSpamEnabled)
            {
                $Response .= "<b>Spam Detection Enabled:</b> <code>True</code>\n";

                switch($ChatSettings->DetectSpamAction)
                {
                    case DetectionAction::Nothing:
                        $Response .= "<b>Spam Detection Action:</b> <code>Nothing</code>\n";
                        break;

                    case DetectionAction::DeleteMessage:
                        $Response .= "<b>Spam Detection Action:</b> <code>Delete Content</code>\n";
                        break;

                    case DetectionAction::KickOffender:
                        $Response .= "<b>Spam Detection Action:</b> <code>Remove Offender</code>\n";
                        break;

                    case DetectionAction::BanOffender:
                        $Response .= "<b>Spam Detection Action:</b> <code>Permanently Ban Offender</code>\n";
                        break;
                }
            }
            else
            {
                $Response .= "<b>Spam Detection Enabled:</b> <code>False</code>\n";
            }

            if($ChatSettings->GeneralAlertsEnabled)
            {
                $Response .= "<b>General Alerts Enabled:</b> <code>True</code>\n";
            }

            if($ChatSettings->BlacklistProtectionEnabled)
            {
                $Response .= "<b>Blacklist Protection Enabled:</b> <code>True</code>\n";
            }
            else
            {
                $Response .= "<b>Blacklist Protection Enabled:</b> <code>False</code>\n";
            }

            if($ChatSettings->ActiveSpammerAlertEnabled)
            {
                $Response .= "<b>Active Spammer Alert Enabled:</b> <code>True</code>\n";
            }
            else
            {
                $Response .= "<b>Active Spammer Alert Enabled:</b> <code>False</code>\n";
            }

            if($ChatSettings->DeleteOlderMessages)
            {
                $Response .= "<b>Delete Older Messages:</b> <code>True</code>\n";
            }
            else
            {
                $Response .= "<b>Delete Older Messages:</b> <code>False</code>\n";
            }

            return $Response;
        }

        /**
         * Generates a user information string
         *
         * @param TelegramClient $channel_client
         * @param string $title
         * @return string
         * @noinspection DuplicatedCode
         */
        private function generateChannelInfoString(TelegramClient $channel_client, string $title="Channel Information"): string
        {
            $ChannelStatus = SettingsManager::getChannelStatus($channel_client);
            $RequiresExtraNewline = false;
            $Response = "<b>$title</b>\n\n";

            if($ChannelStatus->IsOfficial)
            {
                $RequiresExtraNewline = true;
                $Response .= "\u{2705} This channel is official\n";
            }

            if($ChannelStatus->IsBlacklisted)
            {
                $RequiresExtraNewline = true;
                $Response .= "\u{26A0} This channel is blacklisted\n";
            }

            if($ChannelStatus->IsWhitelisted)
            {
                $RequiresExtraNewline = true;
                $Response .= "\u{1F530} This channel is whitelisted\n";
            }

            if($ChannelStatus->GeneralizedSpam > 0)
            {
                if($ChannelStatus->GeneralizedSpam > $ChannelStatus->GeneralizedHam)
                {
                    $RequiresExtraNewline = true;
                    $Response .= "\u{26A0} <b>This channel may be promoting spam!</b>\n";
                }
            }

            if($RequiresExtraNewline)
            {
                $Response .= "\n";
            }

            $Response .= "<b>Private ID:</b> <code>" . $channel_client->PublicID . "</code>\n";
            $Response .= "<b>Channel ID:</b> <code>" . $channel_client->Chat->ID . "</code>\n";
            $Response .= "<b>Channel Title:</b> <code>" . self::escapeHTML($channel_client->Chat->Title) . "</code>\n";

            if($channel_client->Chat->Username !== null)
            {
                $Response .= "<b>Channel Username:</b> <code>" . $channel_client->Chat->Username . "</code> (@" . $channel_client->Chat->Username . ")\n";
            }

            if($ChannelStatus->IsBlacklisted)
            {
                $Response .= "<b>Blacklisted:</b> <code>True</code>\n";

                switch($ChannelStatus->BlacklistFlag)
                {
                    case BlacklistFlag::None:
                        $Response .= "<b>Blacklist Reason:</b> <code>None</code>\n";
                        break;

                    case BlacklistFlag::Spam:
                        $Response .= "<b>Blacklist Reason:</b> <code>Spam / Unwanted Promotion</code>\n";
                        break;

                    case BlacklistFlag::BanEvade:
                        $Response .= "<b>Blacklist Reason:</b> <code>Ban Evade</code>\n";
                        $Response .= "<b>Original Private ID:</b> Not applicable to channels\n";
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