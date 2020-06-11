<?php

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use DeepAnalytics\DeepAnalytics;
    use Exception;
    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Abstracts\BlacklistFlag;
    use SpamProtection\Objects\TelegramClient;
    use SpamProtection\Objects\TelegramClient\Chat;
    use SpamProtection\Objects\TelegramClient\User;
    use SpamProtection\Objects\UserStatus;
    use SpamProtection\SpamProtection;

    /**
     * Info command
     *
     * Allows the user to see the current information about requested user, either by
     * a reply to a message or by providing the private Telegram ID or Telegram ID
     */
    class InfoCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'info';

        /**
         * @var string
         */
        protected $description = 'User Information Command';

        /**
         * @var string
         */
        protected $usage = '/info';

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
                    $SpamProtection->getTelegramClientManager()->updateClient($ChatClient);
                }

                // Define and update user client
                $UserClient = $SpamProtection->getTelegramClientManager()->registerUser($UserObject);
                if(isset($UserClient->SessionData->Data['user_status']) == false)
                {
                    $UserStatus = $SpamProtection->getSettingsManager()->getUserStatus($UserClient);
                    $UserClient = $SpamProtection->getSettingsManager()->updateUserStatus($UserClient, $UserStatus);
                    $SpamProtection->getTelegramClientManager()->updateClient($UserClient);
                }
            }
            catch(Exception $e)
            {
                $data = [
                    'chat_id' => $this->getMessage()->getChat()->getId(),
                    'text' =>
                        "Oops! Something went wrong! contact someone in @IntellivoidDev"
                ];

                return Request::sendMessage($data);
            }

            $DeepAnalytics = new DeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$TelegramClient->getChatId());

            if($this->getMessage()->getReplyToMessage() !== null)
            {
                $TargetUser = User::fromArray($this->getMessage()->getReplyToMessage()->getFrom()->getRawData());
                $TargetUserClient = $SpamProtection->getTelegramClientManager()->registerUser($TargetUser);

                if($this->getMessage()->getReplyToMessage()->getForwardFrom() !== null)
                {
                    $ForwardUser = User::fromArray($this->getMessage()->getReplyToMessage()->getForwardFrom()->getRawData());
                    $ForwardUserClient = $SpamProtection->getTelegramClientManager()->registerUser($ForwardUser);

                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "parse_mode" => "html",
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "text" =>
                            self::generateUserInfoString($SpamProtection, $TargetUserClient, "User Information") .
                            "\n\n" .
                            self::generateUserInfoString($SpamProtection, $ForwardUserClient, "User of Forwarded Message Information")
                    ]);

                }

                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "parse_mode" => "html",
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "text" => self::generateUserInfoString($SpamProtection, $TargetUserClient)
                ]);
            }

            return Request::sendMessage([
                "chat_id" => $this->getMessage()->getChat()->getId(),
                "parse_mode" => "html",
                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                "text" => self::generateUserInfoString($SpamProtection, $UserClient)
            ]);

        }

        /**
         * Generates a user information string
         *
         * @param SpamProtection $spamProtection
         * @param TelegramClient $user_client
         * @param string $title
         * @return string
         */
        private static function generateUserInfoString(SpamProtection $spamProtection, TelegramClient $user_client, string $title="User Information"): string
        {
            $UserStatus = $spamProtection->getSettingsManager()->getUserStatus($user_client);
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
                $Response .= "\u{2705} This user's Telegram is verified by Intellivoid Accounts\n";
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

            if($RequiresExtraNewline)
            {
                $Response .= "\n";
            }

            $Response .= "   <b>Private ID:</b> <code>" . $user_client->PublicID . "</code>\n";
            $Response .= "   <b>User ID:</b> <code>" . $user_client->User->ID . "</code>\n";

            if($user_client->User->FirstName == null)
            {
                $Response .= "   <b>First Name:</b> Empty\n";
            }
            else
            {
                $Response .= "   <b>First Name:</b> <code>" . $user_client->User->FirstName . "</code>\n";
            }

            if($user_client->User->LastName == null)
            {
                $Response .= "   <b>Last Name:</b> Empty\n";
            }
            else
            {
                $Response .= "   <b>Last Name:</b> <code>" . $user_client->User->LastName . "</code>\n";
            }

            if($user_client->User->Username == null)
            {
                $Response .= "   <b>Username:</b> Empty\n";
            }
            else
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

            return $Response;
        }
    }