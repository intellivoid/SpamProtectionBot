<?php

    /** @noinspection PhpMissingFieldTypeInspection */
    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\UserCommands;

    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Abstracts\TelegramUserStatus;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtection\Objects\TelegramObjects\ChatMember;
    use SpamProtectionBot;
    use TelegramClientManager\Abstracts\TelegramChatType;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;
    use TelegramClientManager\Objects\TelegramClient;
    use VerboseAdventure\Abstracts\EventType;

    /**
     * Rest Cache Command
     *
     * Allows a user to reset the cache of a group after x amount of time.
     */
    class ResetCacheCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'ResetCache';

        /**
         * @var string
         */
        protected $description = 'Allows a user to reset the cache of a group after x amount of time';

        /**
         * @var string
         */
        protected $usage = '/ResetCache';

        /**
         * @var string
         */
        protected $version = '1.0.0';

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
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramException
         * @throws TelegramClientNotFoundException
         * @noinspection DuplicatedCode
         */
        public function execute(): ServerResponse
        {
            // Find clients
            $this->WhoisCommand = new WhoisCommand($this->telegram, $this->update);
            $this->WhoisCommand->findClients();

            // Tally DeepAnalytics
            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'reset_cache', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->WhoisCommand->ChatObject->ID);
            $DeepAnalytics->tally('tg_spam_protection', 'reset_cache', (int)$this->WhoisCommand->ChatObject->ID);

            // Ignore forwarded commands
            if($this->getMessage()->getForwardFrom() !== null || $this->getMessage()->getForwardFromChat())
            {
                return Request::emptyResponse();
            }

            $ChatClient = $this->WhoisCommand->ChatClient;
            $ChatSettings = SettingsManager::getChatSettings($ChatClient);

            try
            {
                // Update the admin cache if outdated
                /** @noinspection DuplicatedCode */
                if($ChatClient->Chat->Type == TelegramChatType::Group || $ChatClient->Chat->Type == TelegramChatType::SuperGroup)
                {
                    if(((int)time() - $ChatSettings->AdminCacheLastUpdated) > 120)
                    {
                        $Results = Request::getChatAdministrators(["chat_id" => $ChatClient->Chat->ID]);

                        if($Results->isOk())
                        {
                            /** @var array $ChatMembersResponse */
                            $ChatMembersResponse = $Results->getRawData()["result"];
                            $ChatSettings->Administrators = array();
                            $ChatSettings->AdminCacheLastUpdated = (int)time();

                            foreach($ChatMembersResponse as $chatMember)
                            {
                                $ChatSettings->Administrators[] = ChatMember::fromArray($chatMember);
                            }

                            $this->WhoisCommand->ChatClient = SettingsManager::updateChatSettings($ChatClient, $ChatSettings);
                            SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($ChatClient);

                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Success, cache values has been reset. " . count($ChatSettings->Administrators) . " administrator(s) found.")
                            ]);
                        }
                        else
                        {
                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "There was an error while trying to request the admin list from Telegram.")
                            ]);
                        }
                    }
                    else
                    {
                        $TimeLeft = abs((time() - $ChatSettings->AdminCacheLastUpdated) - 120);

                        return Request::sendMessage([
                            "chat_id" => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "The cache has been updated recently. Try again in $TimeLeft second(s)")
                        ]);
                    }
                }
            }
            catch(Exception $e)
            {
                SpamProtectionBot::getLogHandler()->log(EventType::WARNING, "There was an error while trying to process handleMessage (AdminCacheUpdate)", "handleMessage");
                SpamProtectionBot::getLogHandler()->logException($e, "handleMessage");
            }

            return Request::sendMessage([
                "chat_id" => $this->getMessage()->getChat()->getId(),
                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                "parse_mode" => "html",
                "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "There was an unknown issue while trying to update the admin cache.")
            ]);
        }

        /**
         * Attempts to update the admin cache if it's outadted
         *
         * @param WhoisCommand $whoisCommand
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         */
        public function updateCache(WhoisCommand $whoisCommand)
        {
            $ChatClient = $whoisCommand->ChatClient;
            if($ChatClient == null)
                $ChatClient = $whoisCommand->CallbackQueryChatClient;

            if($ChatClient == null)
                return;

            $ChatSettings = SettingsManager::getChatSettings($ChatClient);

            try
            {
                // Update the admin cache if outdated
                /** @noinspection DuplicatedCode */
                if($ChatClient->Chat->Type == TelegramChatType::Group || $ChatClient->Chat->Type == TelegramChatType::SuperGroup)
                {
                    if(((int)time() - $ChatSettings->AdminCacheLastUpdated) > 120)
                    {
                        $Results = Request::getChatAdministrators(["chat_id" => $ChatClient->Chat->ID]);

                        if($Results->isOk())
                        {
                            /** @var array $ChatMembersResponse */
                            $ChatMembersResponse = $Results->getRawData()["result"];
                            $ChatSettings->Administrators = array();
                            $ChatSettings->AdminCacheLastUpdated = (int)time();

                            foreach($ChatMembersResponse as $chatMember)
                            {
                                $ChatSettings->Administrators[] = ChatMember::fromArray($chatMember);
                            }

                            $whoisCommand->ChatClient = SettingsManager::updateChatSettings($ChatClient, $ChatSettings);
                            SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($ChatClient);
                        }
                    }
                }
            }
            catch(Exception $e)
            {
                SpamProtectionBot::getLogHandler()->log(EventType::WARNING, "There was an error while trying to process handleMessage (AdminCacheUpdate)", "handleMessage");
                SpamProtectionBot::getLogHandler()->logException($e, "handleMessage");
            }
        }

        /**
         * Determines if the given client is an administrator or not
         *
         * @param WhoisCommand $whoisCommand
         * @param TelegramClient $telegramClient
         * @return bool
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         */
        public function isAdmin(WhoisCommand $whoisCommand, TelegramClient $telegramClient): bool
        {
            $this->updateCache($whoisCommand);

            $ChatClient = $whoisCommand->ChatClient;
            if($ChatClient == null)
                $ChatClient = $whoisCommand->CallbackQueryChatClient;

            if($ChatClient == null)
                return false;

            $ChatSettings = SettingsManager::getChatSettings($ChatClient);

            foreach($ChatSettings->Administrators as $chatMember)
            {
                if($chatMember->User->ID == $telegramClient->User->ID)
                {
                    if($chatMember->Status == TelegramUserStatus::Administrator)
                    {
                        return True;
                    }

                    if($chatMember->Status == TelegramUserStatus::Creator)
                    {
                        return True;
                    }
                }
            }

            // Anonymous bot.
            if($telegramClient->User->ID == 1087968824)
            {
                return True;
            }

            return false;
        }
    }