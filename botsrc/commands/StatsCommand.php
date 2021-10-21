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
    use SpamProtection\Abstracts\BlacklistFlag;
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
        protected $version = '3.0.0';

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
        public function execute(): ServerResponse
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
                return Request::emptyResponse();
            }

            if(!in_array($this->WhoisCommand->UserClient->User->ID, MAIN_OPERATOR_IDS, true))
            {
                return Request::emptyResponse();
            }

            $MessageResponse = Request::sendMessage([
                "chat_id" => $this->getMessage()->getChat()->getId(),
                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Loading results")
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
                "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Calculating results")
            ]);


            $Results = array(
                "chats" => 0,
                "users" => 0,
                "channels" => 0,
                "operators" => 0,
                "agents" => 0,
                "whitelisted_users" => 0,
                "blacklisted_users" => 0,
                "potential_spammers" => 0,
                "whitelisted_channels" => 0,
                "blacklisted_channels" => 0,
                "official_channels" => 0,
                "spam_channels" => 0,
                "verified_chats" => 0,
                "verified_accounts" => 0,
                "blacklist_flags" => []
            );

            foreach(BlacklistFlag::All as $item)
            {
                $Results["blacklist_flags"][$item] = 0;
            }

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
                            $Results["blacklist_flags"][$UserStatus->BlacklistFlag] += 1;
                        }

                        if($CurrentClient->AccountID > 0)
                        {
                            $Results["verified_accounts"] += 1;
                        }

                        if($UserStatus->GeneralizedSpamProbability > 0)
                        {
                            if($UserStatus->GeneralizedSpamProbability > $UserStatus->GeneralizedHamProbability)
                            {
                                $Results["potential_spammers"] += 1;
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

                        if($ChannelStatus->GeneralizedSpamProbability > 0)
                        {
                            if($ChannelStatus->GeneralizedSpamProbability > $ChannelStatus->GeneralizedHamProbability)
                            {
                                $Results["spam_channels"] += 1;
                            }
                        }
                    }
                }
            }

            $QueryResults->free();

            str_ireplace("%s",
                "<code>" . number_format($Results["users"]) . "</code>",
                LanguageCommand::localizeChatText($this->WhoisCommand, "Users: %s", ['s']
            )) . "\n";

            return Request::editMessageText([
                "chat_id" => $Message->getChat()->getId(),
                "message_id" => $Message->getMessageId(),
                "parse_mode" => "html",
                "text" =>
                    str_ireplace("%s", "<code>" . number_format($Results["chats"]) . "</code>", LanguageCommand::localizeChatText($this->WhoisCommand, "Chats: %s", ['s'])) . "\n".
                    str_ireplace("%s", "<code>" . number_format($Results["users"]) . "</code>", LanguageCommand::localizeChatText($this->WhoisCommand, "Users: %s", ['s'])) . "\n".
                    str_ireplace("%s", "<code>" . number_format($Results["channels"]) . "</code>", LanguageCommand::localizeChatText($this->WhoisCommand, "Channels: %s", ['s'])) . "\n".
                    str_ireplace("%s", "<code>" . number_format($Results["operators"]) . "</code>", LanguageCommand::localizeChatText($this->WhoisCommand, "Operators: %s", ['s'])) . "\n".
                    str_ireplace("%s", "<code>" . number_format($Results["agents"]) . "</code>", LanguageCommand::localizeChatText($this->WhoisCommand, "Agents: %s", ['s'])) . "\n".
                    str_ireplace("%s", "<code>" . number_format($Results["whitelisted_users"]) . "</code>", LanguageCommand::localizeChatText($this->WhoisCommand, "Whitelisted Users: %s", ['s'])) . "\n".
                    str_ireplace("%s", "<code>" . number_format($Results["blacklisted_users"]) . "</code>", LanguageCommand::localizeChatText($this->WhoisCommand, "Blacklisted Users: %s", ['s'])) . "\n".
                    str_ireplace("%s", "<code>" . number_format($Results["potential_spammers"]) . "</code>", LanguageCommand::localizeChatText($this->WhoisCommand, "Potential Spammers: %s", ['s'])) . "\n".
                    str_ireplace("%s", "<code>" . number_format($Results["whitelisted_channels"]) . "</code>", LanguageCommand::localizeChatText($this->WhoisCommand, "Whitelisted Channels: %s", ['s'])) . "\n".
                    str_ireplace("%s", "<code>" . number_format($Results["blacklisted_channels"]) . "</code>", LanguageCommand::localizeChatText($this->WhoisCommand, "Blacklisted Channels: %s", ['s'])) . "\n".
                    str_ireplace("%s", "<code>" . number_format($Results["official_channels"]) . "</code>", LanguageCommand::localizeChatText($this->WhoisCommand, "Official Channels: %s", ['s'])) . "\n".
                    str_ireplace("%s", "<code>" . number_format($Results["spam_channels"]) . "</code>", LanguageCommand::localizeChatText($this->WhoisCommand, "Spam Channels: %s", ['s'])) . "\n".
                    str_ireplace("%s", "<code>" . number_format($Results["verified_chats"]) . "</code>", LanguageCommand::localizeChatText($this->WhoisCommand, "Verified Chats: %s", ['s'])) . "\n".
                    str_ireplace("%s", "<code>" . number_format($Results["verified_accounts"]) . "</code>", LanguageCommand::localizeChatText($this->WhoisCommand, "Verified Accounts: %s", ['s'])) . "\n\n".
                    str_ireplace("%s", "<code>" . json_encode($Results["blacklist_flags"], JSON_PRETTY_PRINT) . "</code>", LanguageCommand::localizeChatText($this->WhoisCommand, "Blacklist Flags\n%s", ['s'])) . "\n"
            ]);
        }
    }
