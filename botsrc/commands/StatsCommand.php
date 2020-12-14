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
    use pop\pop;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtectionBot;
    use TelegramClientManager\Abstracts\TelegramChatType;
    use TelegramClientManager\Objects\TelegramClient;
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
        protected $version = '2.0.0';

        /**
         * @var bool
         */
        protected $private_only = false;

        /**
         * The whois command used for finding targets
         *
         * @var WhoisCommand|null
         */
        public $WhoisCommand = null;

        /**
         * Command execute method
         *
         * @return ServerResponse
         * @throws TelegramException
         * @noinspection DuplicatedCode
         */
        public function execute()
        {
            $this->WhoisCommand = new WhoisCommand($this->telegram, $this->update);

            try
            {
                $this->WhoisCommand->findClients();
            }
            catch(Exception $e)
            {
                $ReferenceID = SpamProtectionBot::getLogHandler()->logException($e, "Worker");
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
            $DeepAnalytics->tally('tg_spam_protection', 'stats_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->WhoisCommand->ChatClient->getChatId());
            $DeepAnalytics->tally('tg_spam_protection', 'stats_command', (int)$this->WhoisCommand->ChatClient->getChatId());

            // Ignore forwarded commands
            if($this->getMessage()->getForwardFrom() !== null || $this->getMessage()->getForwardFromChat())
            {
                return null;
            }

            // Parse the options
            if($this->getMessage()->getText(true) !== null && strlen($this->getMessage()->getText(true)) > 0)
            {
                $options = pop::parse($this->getMessage()->getText(true));

                if(isset($options["info"]))
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "parse_mode" => "html",
                        "reply_to_message_id" =>$this->getMessage()->getMessageId(),
                        "text" =>
                            $this->name . " (v" . $this->version . ")\n" .
                            " Usage: <code>" . $this->usage . "</code>\n\n" .
                            "<i>" . $this->description . "</i>"
                    ]);
                }
            }

            if($this->WhoisCommand->UserClient->User->Username !== MAIN_OPERATOR_USERNAME)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => "This command can only be used by @" . MAIN_OPERATOR_USERNAME
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
                "active_spammers" => 0,
                "whitelisted_channels" => 0,
                "blacklisted_channels" => 0,
                "official_channels" => 0,
                "spam_channels" => 0,
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

                        if($UserStatus->GeneralizedSpam > 0)
                        {
                            if($UserStatus->GeneralizedSpam > $UserStatus->GeneralizedHam)
                            {
                                $Results["active_spammers"] += 1;
                            }
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

                        if($ChannelStatus->GeneralizedSpam > 0)
                        {
                            if($ChannelStatus->GeneralizedSpam > $ChannelStatus->GeneralizedHam)
                            {
                                $Results["spam_channels"] += 1;
                            }
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
                    "Active Spammers: <code>" . number_format($Results["active_spammers"]) . "</code>\n".
                    "Whitelisted Channels: <code>" . number_format($Results["whitelisted_channels"]) . "</code>\n".
                    "Blacklisted Channels: <code>" . number_format($Results["blacklisted_channels"]) . "</code>\n".
                    "Official Channels: <code>" . number_format($Results["official_channels"]) . "</code>\n".
                    "Spam Channels: <code>" . number_format($Results["spam_channels"]) . "</code>\n".
                    "Verified Chats: <code>" . number_format($Results["verified_chats"]) . "</code>\n".
                    "Verified Accounts: <code>" . number_format($Results["verified_accounts"]) . "</code>\n"
            ]);
        }
    }