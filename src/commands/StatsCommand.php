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
    use msqg\QueryBuilder;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtectionBot;
    use TelegramClientManager\Abstracts\TelegramChatType;
    use TelegramClientManager\Objects\TelegramClient;
    use TelegramClientManager\Objects\TelegramClient\Chat;
    use TelegramClientManager\Objects\TelegramClient\User;
    use ZiProto\ZiProto;

    /**
     * Stat command
     *
     * Gets the current statics of the bot (SLOW)
     */
    class StatsCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'stats';

        /**
         * @var string
         */
        protected $description = 'Returns the current statics of the bot';

        /**
         * @var string
         */
        protected $usage = '/stats';

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
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "Oops! Something went wrong! contact someone in @IntellivoidDiscussions\n\n" .
                        "Error Code: <code>" . $e->getCode() . "</code>\n" .
                        "Object: <code>Commands/stats.bin</code>"
                ]);
            }

            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'stats_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$TelegramClient->getChatId());
            $DeepAnalytics->tally('tg_spam_protection', 'staats_command', (int)$TelegramClient->getChatId());

            if($UserClient->User->Username !== "IntellivoidSupport")
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => "This command can only be used by @IntellivoidSupport"
                ]);
            }

            $MessageResponse = Request::sendMessage([
                "chat_id" => $this->getMessage()->getChat()->getId(),
                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                "text" => "Loading results"
            ]);
            /** @var Message $Message */
            $Message = $MessageResponse->getResult();

            $Query = QueryBuilder::select("telegram_clients", array(
                'account_id',
                'user',
                'chat',
                'session_data',
                'chat_id',
                'user_id'
            ));
            $QueryResults = SpamProtectionBot::getTelegramClientManager()->getDatabase()->query($Query);

            Request::editMessageText([
                "chat_id" => $Message->getChat()->getId(),
                "message_id" => $Message->getMessageId(),
                "text" => "Calculating results"
            ]);

            $Results = array(
                "chats" => 0,
                "users" => 0,
                "channels" => 0,
                "operators" => 0,
                "agents" => 0,
                "whitelisted_users" => 0,
                "blacklisted_users" => 0,
                "whitelisted_channels" => 0,
                "blacklisted_channels" => 0,
                "official_channels" => 0,
                "verified_chats" => 0,
                "verified_accounts" => 0
            );

            while($row = $QueryResults->fetch_assoc())
            {
                $row['session_data'] = ZiProto::decode($row['session_data']);
                $row['user'] = ZiProto::decode($row['user']);
                $row['chat'] = ZiProto::decode($row['chat']);
                $CurrentClient = TelegramClient::fromArray($row);

                if($CurrentClient->User->ID == $CurrentClient->Chat->ID)
                {
                    if($CurrentClient->Chat->Type == TelegramChatType::Group || $CurrentClient->Chat->Type == TelegramChatType::SuperGroup)
                    {
                        $Results["chats"] += 1;

                        $ChatSettings = SettingsManager::getChatSettings($CurrentClient);

                        if($ChatSettings->IsVerified)
                        {
                            $Results["verified_chats"] += 1;
                        }
                    }


                    if($CurrentClient->Chat->Type == TelegramChatType::Private)
                    {
                        $Results["users"] += 1;

                        $UserStatus = SettingsManager::getUserStatus($CurrentClient);
                        if($UserStatus->IsOperator)
                        {
                            $Results["operators"] += 1;
                        }

                        if($UserStatus->IsAgent)
                        {
                            $Results["agents"] += 1;
                        }

                        if($UserStatus->IsWhitelisted)
                        {
                            $Results["whitelisted_users"] += 1;
                        }

                        if($UserStatus->IsBlacklisted)
                        {
                            $Results["blacklisted_users"] += 1;
                        }

                        if($CurrentClient->AccountID > 0)
                        {
                            $Results["verified_accounts"] += 1;
                        }
                    }

                    if($CurrentClient->Chat->Type == TelegramChatType::Channel)
                    {
                        $Results["channels"] += 1;

                        $ChannelStatus = SettingsManager::getChannelStatus($CurrentClient);

                        if($ChannelStatus->IsBlacklisted)
                        {
                            $Results["blacklisted_channels"] += 1;
                        }

                        if($ChannelStatus->IsWhitelisted)
                        {
                            $Results["whitelisted_channels"] += 1;
                        }

                        if($ChannelStatus->IsOfficial)
                        {
                            $Results["official_channels"] += 1;
                        }
                    }
                }
            }

            $QueryResults->free();

            return Request::editMessageText([
                "chat_id" => $Message->getChat()->getId(),
                "message_id" => $Message->getMessageId(),
                "parse_mode" => "html",
                "text" =>
                    "Chats: <code>" . number_format($Results["chats"]) . "</code>\n".
                    "Users: <code>" . number_format($Results["users"]) . "</code>\n".
                    "Channels: <code>" . number_format($Results["channels"]) . "</code>\n".
                    "Operators: <code>" . number_format($Results["operators"]) . "</code>\n".
                    "Agents: <code>" . number_format($Results["agents"]) . "</code>\n".
                    "Whitelisted Users: <code>" . number_format($Results["whitelisted_users"]) . "</code>\n".
                    "Blacklisted Users: <code>" . number_format($Results["blacklisted_users"]) . "</code>\n".
                    "Whitelisted Channels: <code>" . number_format($Results["whitelisted_channels"]) . "</code>\n".
                    "Blacklisted Channels: <code>" . number_format($Results["blacklisted_channels"]) . "</code>\n".
                    "Official Channels: <code>" . number_format($Results["official_channels"]) . "</code>\n".
                    "Verified Chats: <code>" . number_format($Results["verified_chats"]) . "</code>\n".
                    "Verified Accounts: <code>" . number_format($Results["verified_accounts"]) . "</code>\n"
            ]);
        }
    }