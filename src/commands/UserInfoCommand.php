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
    use SpamProtection\Abstracts\BlacklistFlag;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtection\Utilities\Hashing;
    use TelegramClientManager\Abstracts\SearchMethods\TelegramClientSearchMethod;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;
    use TelegramClientManager\Objects\TelegramClient;
    use TelegramClientManager\TelegramClientManager;

    /**
     * Info command
     *
     * Allows the user to see the current information about requested user, either by
     * a reply to a message or by providing the private Telegram ID or Telegram ID
     */
    class UserInfoCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'User Information Command';

        /**
         * @var string
         */
        protected $description = 'Resolves public information about the user or forwarded content';

        /**
         * @var string
         */
        protected $usage = '/userinfo [None/ID/Private Telegram ID/Username]';

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
         * @throws TelegramException
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @noinspection DuplicatedCode
         */
        public function execute()
        {
            $TelegramClientManager = new TelegramClientManager();

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
                        "Object: <code>Commands/user_info.bin</code>"
                ]);
            }

            $DeepAnalytics = new DeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'userinfo_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$TelegramClient->getChatId());
            $DeepAnalytics->tally('tg_spam_protection', 'userinfo_command', (int)$TelegramClient->getChatId());

            if($this->getMessage()->getReplyToMessage() !== null)
            {
                $TargetUser = TelegramClient\User::fromArray($this->getMessage()->getReplyToMessage()->getFrom()->getRawData());
                $TargetUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($TargetUser);

                if($this->getMessage()->getReplyToMessage()->getForwardFrom() !== null)
                {
                    $ForwardUser = TelegramClient\User::fromArray($this->getMessage()->getReplyToMessage()->getForwardFrom()->getRawData());
                    $ForwardUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($ForwardUser);

                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "parse_mode" => "html",
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "text" =>
                            self::generateUserInfoString($TargetUserClient, "User Information") .
                            "\n\n" .
                            self::generateUserInfoString($ForwardUserClient, "User of Forwarded Message Information")
                    ]);

                }

                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "parse_mode" => "html",
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "text" => self::generateUserInfoString($TargetUserClient)
                ]);
            }

            if($this->getMessage()->getText(true) !== null)
            {
                if(strlen($this->getMessage()->getText(true)) > 0)
                {
                    $CommandParameters = explode(" ", $this->getMessage()->getText(true));
                    $CommandParameters = array_values(array_filter($CommandParameters, 'strlen'));
                    $TargetUserParameter = null;

                    if(count($CommandParameters) > 0)
                    {
                        $TargetUserParameter = $CommandParameters[0];
                        $EstimatedPrivateID = Hashing::telegramClientPublicID((int)$TargetUserParameter, (int)$TargetUserParameter);

                        try
                        {
                            $TargetUserClient = $TelegramClientManager->getTelegramClientManager()->getClient(TelegramClientSearchMethod::byPublicId, $EstimatedPrivateID);

                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "parse_mode" => "html",
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "text" => self::generateUserInfoString($TargetUserClient, "Resolved User ID")
                            ]);
                        }
                        catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                        {
                            unset($telegramClientNotFoundException);
                        }

                        try
                        {
                            $TargetUserClient = $TelegramClientManager->getTelegramClientManager()->getClient(TelegramClientSearchMethod::byPublicId, $TargetUserParameter);

                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "parse_mode" => "html",
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "text" => self::generateUserInfoString($TargetUserClient, "Resolved User Private ID")
                            ]);
                        }
                        catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                        {
                            unset($telegramClientNotFoundException);
                        }

                        try
                        {
                            $TargetUserClient = $TelegramClientManager->getTelegramClientManager()->getClient(
                                TelegramClientSearchMethod::byUsername, str_ireplace("@", "", $TargetUserParameter)
                            );

                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "parse_mode" => "html",
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "text" => self::generateUserInfoString($TargetUserClient, "Resolved Username")
                            ]);
                        }
                        catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                        {
                            unset($telegramClientNotFoundException);
                        }
                    }

                    if($TargetUserParameter == null)
                    {
                        $TargetUserParameter = "No Input";
                    }

                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "text" => "Unable to resolve the query '$TargetUserParameter'!"
                    ]);
                }
            }


            return Request::sendMessage([
                "chat_id" => $this->getMessage()->getChat()->getId(),
                "parse_mode" => "html",
                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                "text" => self::generateUserInfoString($UserClient)
            ]);

        }

        /**
         * Generates a user information string
         *
         * @param TelegramClient $user_client
         * @param string $title
         * @return string
         */
        private static function generateUserInfoString(TelegramClient $user_client, string $title="User Information"): string
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

            $Response .= "   <b>Private ID:</b> <code>" . $user_client->PublicID . "</code>\n";
            $Response .= "   <b>User ID:</b> <code>" . $user_client->User->ID . "</code>\n";

            if($user_client->User->FirstName !== null)
            {
                $Response .= "   <b>First Name:</b> <code>" . self::escapeHTML($user_client->User->FirstName) . "</code>\n";
            }

            if($user_client->User->LastName !== null)
            {
                $Response .= "   <b>Last Name:</b> <code>" . self::escapeHTML($user_client->User->LastName) . "</code>\n";
            }

            if($user_client->User->Username !== null)
            {
                $Response .= "   <b>Username:</b> <code>" . $user_client->User->Username . "</code> (@" . $user_client->User->Username . ")\n";
            }

            $Response .= "   <b>Trust Prediction:</b> <code>" . $UserStatus->GeneralizedHam . "/" . $UserStatus->GeneralizedSpam . "</code>\n";

            if($UserStatus->GeneralizedSpam > 0)
            {
                if($UserStatus->GeneralizedSpam > $UserStatus->GeneralizedHam)
                {
                    $Response .= "   <b>Active Spammer:</b> <code>True</code>\n";
                }
            }

            if($UserStatus->IsWhitelisted)
            {
                $Response .= "   <b>Whitelisted:</b> <code>True</code>\n";
            }

            if($UserStatus->IsBlacklisted)
            {
                $Response .= "   <b>Blacklisted:</b> <code>True</code>\n";

                switch($UserStatus->BlacklistFlag)
                {
                    case BlacklistFlag::None:
                        $Response .= "   <b>Blacklist Reason:</b> <code>None</code>\n";
                        break;

                    case BlacklistFlag::Spam:
                        $Response .= "   <b>Blacklist Reason:</b> <code>Spam / Unwanted Promotion</code>\n";
                        break;

                    case BlacklistFlag::BanEvade:
                        $Response .= "   <b>Blacklist Reason:</b> <code>Ban Evade</code>\n";
                        $Response .= "   <b>Original Private ID:</b> <code>" . $UserStatus->OriginalPrivateID . "</code>\n";
                        break;

                    case BlacklistFlag::ChildAbuse:
                        $Response .= "   <b>Blacklist Reason:</b> <code>Child Pornography / Child Abuse</code>\n";
                        break;

                    case BlacklistFlag::Impersonator:
                        $Response .= "   <b>Blacklist Reason:</b> <code>Malicious Impersonator</code>\n";
                        break;

                    case BlacklistFlag::PiracySpam:
                        $Response .= "   <b>Blacklist Reason:</b> <code>Promotes/Spam Pirated Content</code>\n";
                        break;

                    case BlacklistFlag::PornographicSpam:
                        $Response .= "   <b>Blacklist Reason:</b> <code>Promotes/Spam NSFW Content</code>\n";
                        break;

                    case BlacklistFlag::PrivateSpam:
                        $Response .= "   <b>Blacklist Reason:</b> <code>Spam / Unwanted Promotion via a unsolicited private message</code>\n";
                        break;

                    case BlacklistFlag::Raid:
                        $Response .= "   <b>Blacklist Reason:</b> <code>RAID Initializer / Participator</code>\n";
                        break;

                    case BlacklistFlag::Scam:
                        $Response .= "   <b>Blacklist Reason:</b> <code>Scamming</code>\n";
                        break;

                    case BlacklistFlag::Special:
                        $Response .= "   <b>Blacklist Reason:</b> <code>Special Reason, consult @IntellivoidSupport</code>\n";
                        break;

                    default:
                        $Response .= "   <b>Blacklist Reason:</b> <code>Unknown</code>\n";
                        break;
                }

            }

            if($UserStatus->IsOperator)
            {
                $Response .= "   <b>Operator:</b> <code>True</code>\n";
            }

            if($UserStatus->IsAgent)
            {
                $Response .= "   <b>Spam Detection Agent:</b> <code>True</code>\n";
            }

            if($UserStatus->OperatorNote !== "None")
            {
                $Response .= "\n" . self::escapeHTML($UserStatus->OperatorNote) . "\n";
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