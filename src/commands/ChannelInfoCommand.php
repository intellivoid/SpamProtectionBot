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

    /**
     * Channel Info command
     *
     * Allows the user to resolve channel information and the current configuration set to the channel
     */
    class ChannelInfoCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'Channel Information Command';

        /**
         * @var string
         */
        protected $description = 'Returns information about a channel and it\'s properties';

        /**
         * @var string
         */
        protected $usage = '/channelinfo [ID/Private Telegram ID/Username]';

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
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramException
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
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "Oops! Something went wrong! contact someone in @IntellivoidDiscussions\n\n" .
                        "Error Code: <code>" . $e->getCode() . "</code>\n" .
                        "Object: <code>Commands/channel_info.bin</code>"
                ]);
            }

            /** @noinspection PhpUndefinedClassInspection */
            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'channel_info_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$TelegramClient->getChatId());
            $DeepAnalytics->tally('tg_spam_protection', 'channel_info_command', (int)$TelegramClient->getChatId());

            if($this->getMessage()->getText(true) !== null)
            {
                if(strlen($this->getMessage()->getText(true)) > 0)
                {
                    $CommandParameters = explode(" ", $this->getMessage()->getText(true));
                    $CommandParameters = array_values(array_filter($CommandParameters, 'strlen'));
                    $TargetChannelParameter = null;

                    if(count($CommandParameters) > 0)
                    {
                        $TargetChannelParameter = $CommandParameters[0];
                        $EstimatedPrivateID = Hashing::telegramClientPublicID((int)$TargetChannelParameter, (int)$TargetChannelParameter);

                        try
                        {
                            $TargetChatClient = $TelegramClientManager->getTelegramClientManager()->getClient(TelegramClientSearchMethod::byPublicId, $EstimatedPrivateID);

                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "parse_mode" => "html",
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "text" => self::generateChannelString($TargetChatClient, "Resolved Channel ID")
                            ]);
                        }
                        catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                        {
                            unset($telegramClientNotFoundException);
                        }

                        try
                        {
                            $TargetChatClient = $TelegramClientManager->getTelegramClientManager()->getClient(TelegramClientSearchMethod::byPublicId, $TargetChannelParameter);

                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "parse_mode" => "html",
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "text" => self::generateChannelString($TargetChatClient, "Resolved Channel Private ID")
                            ]);
                        }
                        catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                        {
                            unset($telegramClientNotFoundException);
                        }

                        try
                        {
                            $TargetChatClient = $TelegramClientManager->getTelegramClientManager()->getClient(
                                TelegramClientSearchMethod::byUsername,
                                str_ireplace("@", "", $TargetChannelParameter)
                            );

                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "parse_mode" => "html",
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "text" => self::generateChannelString($TargetChatClient, "Resolved Channel Username")
                            ]);
                        }
                        catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                        {
                            unset($telegramClientNotFoundException);
                        }
                    }

                    if($TargetChannelParameter == null)
                    {
                        $TargetChannelParameter = "No Input";
                    }

                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "text" => "Unable to resolve the query '" . self::escapeHTML($TargetChannelParameter) . "'!"
                    ]);
                }
            }

            return self::displayUsage($this->getMessage());
        }

        /**
         * Displays the help usage of the command
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
                    "   <b>/channelinfo</b> <code>[ID/Private Telegram ID/Username]</code>"
            ]);
        }

        /**
         * Generates a user information string
         *
         * @param TelegramClient $channel_client
         * @param string $title
         * @return string
         * @noinspection DuplicatedCode
         */
        private static function generateChannelString(TelegramClient $channel_client, string $title="Channel Information"): string
        {
            if($channel_client->Chat->Type == TelegramChatType::Private)
            {
                return "This command does not support users/private chats";
            }

            if($channel_client->Chat->Type == TelegramChatType::SuperGroup)
            {
                return "This command does not support super groups";
            }

            if($channel_client->Chat->Type == TelegramChatType::Group)
            {
                return "This command does not support groups";
            }

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

            if($RequiresExtraNewline)
            {
                $Response .= "\n";
            }

            $Response .= "   <b>Private ID:</b> <code>" . $channel_client->PublicID . "</code>\n";
            $Response .= "   <b>Channel ID:</b> <code>" . $channel_client->Chat->ID . "</code>\n";
            $Response .= "   <b>Channel Title:</b> <code>" . self::escapeHTML($channel_client->Chat->Title) . "</code>\n";

            if($channel_client->Chat->Username !== null)
            {
                $Response .= "   <b>Channel Username:</b> <code>" . $channel_client->Chat->Username . "</code> (@" . $channel_client->Chat->Username . ")\n";
            }

            if($ChannelStatus->IsBlacklisted)
            {
                $Response .= "   <b>Blacklisted:</b> <code>True</code>\n";

                switch($ChannelStatus->BlacklistFlag)
                {
                    case BlacklistFlag::None:
                        $Response .= "   <b>Blacklist Reason:</b> <code>None</code>\n";
                        break;

                    case BlacklistFlag::Spam:
                        $Response .= "   <b>Blacklist Reason:</b> <code>Spam / Unwanted Promotion</code>\n";
                        break;

                    case BlacklistFlag::BanEvade:
                        $Response .= "   <b>Blacklist Reason:</b> <code>Ban Evade</code>\n";
                        $Response .= "   <b>Original Private ID:</b> Not applicable to channels\n";
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