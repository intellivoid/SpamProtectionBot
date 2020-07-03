<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use CoffeeHouse\Objects\Results\SpamPredictionResults;
    use Exception;
    use Longman\TelegramBot\Commands\SystemCommand;
    use Longman\TelegramBot\Entities\InlineKeyboard;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Abstracts\BlacklistFlag;
    use SpamProtection\Abstracts\DetectionAction;
    use SpamProtection\Abstracts\TelegramUserStatus;
    use SpamProtection\Exceptions\DatabaseException;
    use SpamProtection\Exceptions\MessageLogNotFoundException;
    use SpamProtection\Exceptions\UnsupportedMessageException;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtection\Objects\ChannelStatus;
    use SpamProtection\Objects\ChatSettings;
    use SpamProtection\Objects\MessageLog;
    use SpamProtection\Objects\TelegramObjects\ChatMember;
    use SpamProtection\Objects\TelegramObjects\Message;
    use SpamProtection\Objects\UserStatus;
    use SpamProtectionBot;
    use TelegramClientManager\Abstracts\TelegramChatType;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;
    use TelegramClientManager\Objects\TelegramClient;

    /**
     * Generic Command
     *
     * Gets executed when a user sends a generic message
     */
    class GenericmessageCommand extends SystemCommand
    {

        /**
         * @var string
         */
        protected $name = 'generic_message';

        /**
         * @var string
         */
        protected $description = 'Handles spam detection in a group chat';

        /**
         * @var bool
         */
        protected $private_only = false;

        /**
         * @var string
         */
        protected $version = '1.0.1';

        /**
         * Executes the generic message command
         *
         * @return ServerResponse|null
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws MessageLogNotFoundException
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @throws UnsupportedMessageException
         * @throws \TelegramClientManager\Exceptions\DatabaseException
         * @noinspection DuplicatedCode
         */
        public function execute()
        {
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();

            $ChatObject = TelegramClient\Chat::fromArray($this->getMessage()->getChat()->getRawData());
            $UserObject = TelegramClient\User::fromArray($this->getMessage()->getFrom()->getRawData());
            $ForwardChannelObject = null;

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
                TgFileLogging::dumpException($e, TELEGRAM_BOT_NAME, $this->name);
                return null;
            }

            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$TelegramClient->getChatId());

            // If it's a private chat, ignore it.
            if($ChatObject->Type == TelegramChatType::Private)
            {
                return null;
            }

            // Obtain the User Stats and Chat Settings
            $UserStatus = SettingsManager::getUserStatus($UserClient);
            $ChatSettings = SettingsManager::getChatSettings($ChatClient);

            // Ban the user from the chat if the chat has blacklist protection enabled
            // and the user is blacklisted.
            $this->handleBlacklistedUser($ChatSettings, $UserStatus, $UserClient, $ChatClient);

            // Remove the message if it came from a blacklisted channel
            if($this->getMessage()->getForwardFromChat() !== null)
            {
                $ForwardChannelClient = $TelegramClientManager->getTelegramClientManager()->registerChat($ForwardChannelObject);
                $ForwardChannelStatus = SettingsManager::getChannelStatus($ForwardChannelClient);
                $this->handleBlacklistedChannel($ChatSettings, $ForwardChannelStatus, $ForwardChannelClient, $UserClient, $ChatClient);
            }

            // Handles the message to detect if it's spam or not
            $this->handleMessage($ChatClient, $UserClient, $TelegramClient);

            return null;
        }

        /**
         * @param TelegramClient $chatClient
         * @param TelegramClient $userClient
         * @param TelegramClient $telegramClient
         * @return null
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws MessageLogNotFoundException
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @throws UnsupportedMessageException
         * @throws \TelegramClientManager\Exceptions\DatabaseException
         */
        public function handleMessage(TelegramClient $chatClient, TelegramClient $userClient, TelegramClient $telegramClient)
        {
            // Process message text
            $Message = Message::fromArray($this->getMessage()->getRawData());
            $ChatObject = TelegramClient\Chat::fromArray($this->getMessage()->getChat()->getRawData());

            // Is the message empty?
            if($Message->getText() !== null)
            {
                $MessageObject = Message::fromArray($this->getMessage()->getRawData());
                $ChatSettings = SettingsManager::getChatSettings($chatClient);

                // Does the chat allow logging?
                if($ChatSettings->LogSpamPredictions == false)
                {
                    return;
                }

                // Is the message from the same bot?
                if($Message->ForwardFrom !== null)
                {
                    if($Message->ForwardFrom->Username == TELEGRAM_BOT_NAME)
                    {
                        return;
                    }
                }

                $TargetUserClient = $userClient;
                $TargetChannelClient = null;

                // Does the chat have forward protection enabled?
                if($ChatSettings->ForwardProtectionEnabled)
                {
                    if($this->getMessage()->getForwardFrom() !== null)
                    {
                        // Define and update forwarder user client
                        $ForwardUserObject = TelegramClient\User::fromArray($this->getMessage()->getForwardFrom()->getRawData());
                        $ForwardUserClient = SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->registerUser($ForwardUserObject);
                        if(isset($UserClient->SessionData->Data['user_status']) == false)
                        {
                            $ForwardUserStatus = SettingsManager::getUserStatus($ForwardUserClient);
                            $ForwardUserClient = SettingsManager::updateUserStatus($ForwardUserClient, $ForwardUserStatus);
                            SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($ForwardUserClient);
                        }

                        $TargetUserClient = $ForwardUserClient;
                    }

                    /** @noinspection DuplicatedCode */
                    if($this->getMessage()->getForwardFromChat() !== null)
                    {
                        // Define and update forwarder user client
                        $ChannelObject = TelegramClient\Chat::fromArray($this->getMessage()->getForwardFromChat()->getRawData());
                        $ChannelClient = SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->registerChat($ChannelObject);
                        if(isset($ChannelClient->SessionData->Data['channel_status']) == false)
                        {
                            $ChannelStatus = SettingsManager::getChannelStatus($ChannelClient);
                            $ChannelClient = SettingsManager::updateChannelStatus($ChannelClient, $ChannelStatus);
                            SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($ChannelClient);
                        }

                        $TargetChannelClient = $ChannelClient;
                    }
                }
                else
                {
                    /** @noinspection DuplicatedCode */
                    if($this->getMessage()->getForwardFromChat() !== null)
                    {
                        // Define and update forwarder user client
                        $ChannelObject = TelegramClient\Chat::fromArray($this->getMessage()->getForwardFromChat()->getRawData());
                        $ChannelClient = SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->registerChat($ChannelObject);
                        if(isset($ChannelClient->SessionData->Data['channel_status']) == false)
                        {
                            $ChannelStatus = SettingsManager::getChannelStatus($ChannelClient);
                            $ChannelClient = SettingsManager::updateChannelStatus($ChannelClient, $ChannelStatus);
                            SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($ChannelClient);
                        }

                        $TargetChannelClient = $ChannelClient;
                    }
                }

                // Update the admin cache if outdated
                /** @noinspection DuplicatedCode */
                if($ChatObject->Type == TelegramChatType::Group || $ChatObject->Type == TelegramChatType::SuperGroup)
                {
                    if(((int)time() - $ChatSettings->AdminCacheLastUpdated) > 600)
                    {
                        $Results = Request::getChatAdministrators(["chat_id" => $ChatObject->ID]);

                        if($Results->isOk())
                        {
                            /** @var array $ChatMembersResponse */
                            $ChatMembersResponse = $Results->getRawData()["result"];
                            $ChatSettings->Administrators = array();

                            foreach($ChatMembersResponse as $chatMember)
                            {
                                $ChatSettings->Administrators[] = ChatMember::fromArray($chatMember);
                            }

                            $ChatClient = SettingsManager::updateChatSettings($chatClient, $ChatSettings);
                            SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($ChatClient);
                        }
                    }
                }

                if($ChatSettings->DetectSpamEnabled)
                {
                    $TargetUserStatus = SettingsManager::getUserStatus($TargetUserClient);

                    // Check if the channel is whitelisted
                    if($TargetChannelClient !== null)
                    {
                        $TargetChannelStatus = SettingsManager::getChannelStatus($TargetChannelClient);

                        if($TargetChannelStatus->IsWhitelisted)
                        {
                            return;
                        }
                    }

                    // Check if the user is whitelisted
                    if($TargetUserStatus->IsWhitelisted)
                    {
                        return;
                    }

                    $CoffeeHouse = SpamProtectionBot::getCoffeeHouse();

                    try
                    {
                        if($TargetUserStatus->GeneralizedID == null || $TargetUserStatus->GeneralizedID == "None")
                        {
                            $Results = $CoffeeHouse->getSpamPrediction()->predict($Message->getText(), true);
                        }
                        else
                        {
                            $Results = $CoffeeHouse->getSpamPrediction()->predict($Message->getText(), true, $TargetUserStatus->GeneralizedID);
                        }
                    }
                    catch(Exception $exception)
                    {
                        return;
                    }

                    try
                    {
                        if($TargetChannelClient !== null)
                        {
                            $TargetChannelStatus = SettingsManager::getChannelStatus($TargetChannelClient);

                            if($TargetChannelStatus->GeneralizedID == null || $TargetChannelStatus->GeneralizedID == "None")
                            {
                                $ChannelResults = $CoffeeHouse->getSpamPrediction()->predict($Message->getText(), true);
                            }
                            else
                            {
                                $ChannelResults = $CoffeeHouse->getSpamPrediction()->predict($Message->getText(), true, $TargetChannelStatus->GeneralizedID);
                            }

                            $TargetChannelStatus->GeneralizedSpam = $ChannelResults->GeneralizedSpam;
                            $TargetChannelStatus->GeneralizedHam = $ChannelResults->GeneralizedHam;
                            $TargetChannelStatus->GeneralizedID = $ChannelResults->GeneralizedID;
                            $TargetChannelClient = SettingsManager::updateChannelStatus($TargetChannelClient, $TargetChannelStatus);
                            SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($TargetChannelClient);
                        }
                    }
                    catch(Exception $exception)
                    {
                        // Ignore the exception for channels
                        unset($exception);
                    }

                    $SpamProtection = SpamProtectionBot::getSpamProtection();
                    $MessageLogObject = $SpamProtection->getMessageLogManager()->registerMessage($MessageObject, $Results->SpamPrediction, $Results->HamPrediction);

                    // Update the target user's trust prediction
                    $TargetUserStatus->GeneralizedSpam = $Results->GeneralizedSpam;
                    $TargetUserStatus->GeneralizedHam = $Results->GeneralizedHam;
                    $TargetUserStatus->GeneralizedID = $Results->GeneralizedID;
                    $TargetUserClient = SettingsManager::updateUserStatus($TargetUserClient, $TargetUserStatus);
                    SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($TargetUserClient);

                    // Determine if the user is an admin or creator
                    $IsAdmin = false;
                    foreach($ChatSettings->Administrators as $chatMember)
                    {
                        if($chatMember->User->ID == $TargetUserClient->User->ID)
                        {
                            if($chatMember->Status == TelegramUserStatus::Administrator || $chatMember->Status == TelegramUserStatus::Creator)
                            {
                                $IsAdmin = true;
                            }
                        }
                    }

                    // If the user isn't an admin or creator, then it's probably a random spammer.
                    if($IsAdmin == false)
                    {
                        if($Results->SpamPrediction > $Results->HamPrediction)
                        {
                            $LoggedReferenceLink = null;

                            if($ChatSettings->LogSpamPredictions)
                            {
                                $LoggedReferenceLink = self::logDetectedSpam($MessageObject, $MessageLogObject, $TargetUserClient, $TargetChannelClient);
                            }

                            self::handleSpam($MessageObject, $MessageLogObject, $TargetUserClient, $TargetUserStatus, $ChatSettings, $Results, $LoggedReferenceLink);

                            SpamProtectionBot::getDeepAnalytics()->tally('tg_spam_protection', 'detected_spam', 0);
                            SpamProtectionBot::getDeepAnalytics()->tally('tg_spam_protection', 'detected_spam', (int)$telegramClient->getChatId());
                            SpamProtectionBot::getDeepAnalytics()->tally('tg_spam_protection', 'detected_spam', (int)$telegramClient->getUserId());

                            return;
                        }
                        else
                        {
                            SpamProtectionBot::getDeepAnalytics()->tally('tg_spam_protection', 'detected_ham', 0);
                            SpamProtectionBot::getDeepAnalytics()->tally('tg_spam_protection', 'detected_ham', (int)$telegramClient->getChatId());
                            SpamProtectionBot::getDeepAnalytics()->tally('tg_spam_protection', 'detected_ham', (int)$telegramClient->getUserId());

                            return;
                        }
                    }
                }
            }

            return;
        }

        /**
         * Handles a blacklisted channel message
         *
         * @param ChatSettings $chatSettings
         * @param ChannelStatus $channelStatus
         * @param TelegramClient $channelClient
         * @param TelegramClient $userClient
         * @param TelegramClient $chatClient
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @throws \TelegramClientManager\Exceptions\DatabaseException
         * @noinspection DuplicatedCode
         */
        public function handleBlacklistedChannel(ChatSettings $chatSettings, ChannelStatus $channelStatus, TelegramClient $channelClient, TelegramClient $userClient, TelegramClient $chatClient)
        {
            if($channelStatus->IsWhitelisted)
            {
                return;
            }

            if($chatSettings->BlacklistProtectionEnabled)
            {
                if($channelStatus->IsBlacklisted)
                {
                    $ChatObject = TelegramClient\Chat::fromArray($this->getMessage()->getChat()->getRawData());

                    // Update the admin cache if it's outdated
                    /** @noinspection DuplicatedCode */
                    if($ChatObject->Type == TelegramChatType::Group || $ChatObject->Type == TelegramChatType::SuperGroup)
                    {
                        if(((int)time() - $chatSettings->AdminCacheLastUpdated) > 600)
                        {
                            $Results = Request::getChatAdministrators(["chat_id" => $ChatObject->ID]);

                            if($Results->isOk())
                            {
                                /** @var array $ChatMembersResponse */
                                $ChatMembersResponse = $Results->getRawData()["result"];
                                $chatSettings->Administrators = array();

                                foreach($ChatMembersResponse as $chatMember)
                                {
                                    $chatSettings->Administrators[] = ChatMember::fromArray($chatMember);
                                }

                                $chatClient = SettingsManager::updateChatSettings($chatClient, $chatSettings);
                                SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($chatClient);
                            }
                        }
                    }

                    $IsAdmin = false;
                    foreach($chatSettings->Administrators as $chatMember)
                    {
                        if($chatMember->User->ID == $userClient->User->ID)
                        {
                            if($chatMember->Status == TelegramUserStatus::Administrator || $chatMember->Status == TelegramUserStatus::Creator)
                            {
                                $IsAdmin = true;
                            }
                        }
                    }

                    if($IsAdmin)
                    {
                        $Response = Request::deleteMessage([
                            "chat_id" => $this->getMessage()->getChat()->getId(),
                            "message_id" => $this->getMessage()->getMessageId()
                        ]);

                        if($Response->isOk() == false)
                        {
                            /** @noinspection DuplicatedCode */
                            if($userClient->User->Username == null)
                            {
                                if($userClient->User->LastName == null)
                                {
                                    $Mention = "<a href=\"tg://user?id=" . $userClient->User->ID . "\">" . self::escapeHTML($userClient->User->FirstName) . "</a>";
                                }
                                else
                                {
                                    $Mention = "<a href=\"tg://user?id=" . $userClient->User->ID . "\">" . self::escapeHTML($userClient->User->FirstName . " " . $userClient->User->LastName) . "</a>";
                                }
                            }
                            else
                            {
                                $Mention = "@" . $userClient->User->Username;
                            }

                            $Response = $Mention . " has forwarded a message from a channel that has been blacklisted, the message has been deleted.\n\n";
                            $Response .= "<b>Channel PID:</b> <code>" . $channelClient->PublicID . "</code>\n";

                            switch($channelStatus->BlacklistFlag)
                            {
                                case BlacklistFlag::None:
                                    $Response .= "<b>Blacklist Reason:</b> <code>None</code>\n";
                                    break;

                                case BlacklistFlag::Spam:
                                    $Response .= "<b>Blacklist Reason:</b> <code>Spam / Unwanted Promotion</code>\n";
                                    break;

                                case BlacklistFlag::BanEvade:
                                    $Response .= "<b>Blacklist Reason:</b> <code>Ban Evade</code>\n";
                                    $Response .= "<b>Original Private ID:</b> (Not applicable to a channel)\n";
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

                            $Response .= "\n<i>You can find evidence of abuse by searching the Private Telegram ID (PID) in @SpamProtectionLogs</i>";

                            Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "parse_mode" => "html",
                                "reply_markup" => new InlineKeyboard(
                                    [
                                        ["text" => "Logs", "url" => "https://t.me/SpamProtectionLogs"],
                                        ["text" => "Channel Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $channelClient->User->ID],
                                        ["text" => "User Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $userClient->User->ID],
                                    ],
                                    [
                                        ["text" => "Report Problem", "url" => "https://t.me/SpamProtectionSupport"]
                                    ]
                                ),
                                "text" => $Response
                            ]);
                        }
                    }


                }
            }
        }

        /**
         * Handles a blacklisted user from the chat
         *
         * @param ChatSettings $chatSettings
         * @param UserStatus $userStatus
         * @param TelegramClient $userClient
         * @param TelegramClient $chatClient
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @throws \TelegramClientManager\Exceptions\DatabaseException
         * @noinspection DuplicatedCode
         */
        public function handleBlacklistedUser(ChatSettings $chatSettings, UserStatus $userStatus, TelegramClient $userClient, TelegramClient $chatClient)
        {
            if($userStatus->IsWhitelisted)
            {
                return;
            }

            if($chatSettings->BlacklistProtectionEnabled)
            {
                if($userStatus->IsBlacklisted)
                {
                    $ChatObject = TelegramClient\Chat::fromArray($this->getMessage()->getChat()->getRawData());
                    // Update the admin cache if it's outdated
                    /** @noinspection DuplicatedCode */
                    if($ChatObject->Type == TelegramChatType::Group || $ChatObject->Type == TelegramChatType::SuperGroup)
                    {
                        if(((int)time() - $chatSettings->AdminCacheLastUpdated) > 600)
                        {
                            $Results = Request::getChatAdministrators(["chat_id" => $ChatObject->ID]);

                            if($Results->isOk())
                            {
                                /** @var array $ChatMembersResponse */
                                $ChatMembersResponse = $Results->getRawData()["result"];
                                $chatSettings->Administrators = array();

                                foreach($ChatMembersResponse as $chatMember)
                                {
                                    $chatSettings->Administrators[] = ChatMember::fromArray($chatMember);
                                }

                                $chatClient = SettingsManager::updateChatSettings($chatClient, $chatSettings);
                                SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($chatClient);
                            }
                        }
                    }

                    $IsAdmin = false;
                    foreach($chatSettings->Administrators as $chatMember)
                    {
                        if($chatMember->User->ID == $userClient->User->ID)
                        {
                            if($chatMember->Status == TelegramUserStatus::Administrator || $chatMember->Status == TelegramUserStatus::Creator)
                            {
                                $IsAdmin = true;
                            }
                        }
                    }

                    if($IsAdmin == false)
                    {
                        $BanResponse = Request::kickChatMember([
                            "chat_id" => $this->getMessage()->getChat()->getId(),
                            "user_id" => $userClient->User->ID,
                            "until_date" => 0
                        ]);

                        if($BanResponse->isOk())
                        {
                            /** @noinspection DuplicatedCode */
                            if($userClient->User->Username == null)
                            {
                                if($userClient->User->LastName == null)
                                {
                                    $Mention = "<a href=\"tg://user?id=" . $userClient->User->ID . "\">" . self::escapeHTML($userClient->User->FirstName) . "</a>";
                                }
                                else
                                {
                                    $Mention = "<a href=\"tg://user?id=" . $userClient->User->ID . "\">" . self::escapeHTML($userClient->User->FirstName . " " . $userClient->User->LastName) . "</a>";
                                }
                            }
                            else
                            {
                                $Mention = "@" . $userClient->User->Username;
                            }

                            $Response = "$Mention has been banned because they've been blacklisted!\n\n";
                            $Response .= "<b>Private Telegram ID:</b> <code>" . $userClient->PublicID . "</code>\n";

                            switch($userStatus->BlacklistFlag)
                            {
                                case BlacklistFlag::None:
                                    $Response .= "<b>Blacklist Reason:</b> <code>None</code>\n";
                                    break;

                                case BlacklistFlag::Spam:
                                    $Response .= "<b>Blacklist Reason:</b> <code>Spam / Unwanted Promotion</code>\n";
                                    break;

                                case BlacklistFlag::BanEvade:
                                    $Response .= "<b>Blacklist Reason:</b> <code>Ban Evade</code>\n";
                                    $Response .= "<b>Original Private ID:</b> <code>" . $userStatus->OriginalPrivateID . "</code>\n";
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

                            $Response .= "\n<i>You can find evidence of abuse by searching the Private Telegram ID in @SpamProtectionLogs</i>\n\n";
                            $Response .= "<i>If you think this is a mistake, let us know in @IntellivoidDiscussions</i>";

                            Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "text" => $Response
                            ]);
                        }
                    }
                }
            }
        }

        /**
         * Handles the detected spam configured by the group administrator
         *
         * @param Message $message
         * @param MessageLog $messageLog
         * @param TelegramClient $userClient
         * @param UserStatus $userStatus
         * @param ChatSettings $chatSettings
         * @param SpamPredictionResults $spamPredictionResults
         * @param string|null $logLink
         * @throws TelegramException
         */
        private static function handleSpam(
            Message $message, MessageLog $messageLog,
            TelegramClient $userClient, UserStatus $userStatus,
            ChatSettings $chatSettings, SpamPredictionResults $spamPredictionResults, $logLink
        )
        {
            if($logLink !== null)
            {
                $UseInlineKeyboard = true;
                $InlineKeyboard = new InlineKeyboard([
                    [
                        "text" => "View Message",
                        "url" => $logLink
                    ],
                    [
                        "text" => "View User",
                        "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $userClient->User->ID
                    ]
                ]);
            }
            else
            {
                $UseInlineKeyboard = true;
                $InlineKeyboard = new InlineKeyboard([
                    [
                        "text" => "View User",
                        "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $userClient->User->ID
                    ]
                ]);
            }

            if($chatSettings->ForwardProtectionEnabled)
            {
                if($message->isForwarded())
                {
                    if($message->getForwardedOriginalUser() !== null)
                    {
                        if($chatSettings->GeneralAlertsEnabled)
                        {
                            $ResponseMessage = [
                                "chat_id" => $message->Chat->ID,
                                "reply_to_message_id" => $message->MessageID,
                                "parse_mode" => "html",
                                "text" =>
                                    self::generateDetectionMessage($messageLog, $userClient, $spamPredictionResults) . "\n\n" .
                                    "No action will be taken since this group has Forward Protection Enabled"
                            ];

                            if($UseInlineKeyboard)
                            {
                                $ResponseMessage["reply_markup"] = $InlineKeyboard;
                            }

                            Request::sendMessage($ResponseMessage);
                        }

                        return;
                    }
                }
            }

            if($userStatus->IsWhitelisted)
            {
                return;
            }

            switch($chatSettings->DetectSpamAction)
            {
                case DetectionAction::Nothing:
                    if($chatSettings->GeneralAlertsEnabled)
                    {
                        $ResponseMessage = [
                            "chat_id" => $message->Chat->ID,
                            "reply_to_message_id" => $message->MessageID,
                            "parse_mode" => "html",
                            "text" =>
                                self::generateDetectionMessage($messageLog, $userClient, $spamPredictionResults) . "\n\n" .
                                "No action will be taken since the the current detection rule in this group is to do nothing"
                        ];

                        if($UseInlineKeyboard)
                        {
                            $ResponseMessage["reply_markup"] = $InlineKeyboard;
                        }

                        Request::sendMessage($ResponseMessage);
                    }
                    break;

                case DetectionAction::DeleteMessage:
                    $Response = Request::deleteMessage([
                        "chat_id" => $message->Chat->ID,
                        "message_id" => $message->MessageID
                    ]);
                    if($Response->isOk())
                    {
                        if($chatSettings->GeneralAlertsEnabled)
                        {
                            $ResponseMessage = [
                                "chat_id" => $message->Chat->ID,
                                "parse_mode" => "html",
                                "text" =>
                                    self::generateDetectionMessage($messageLog, $userClient, $spamPredictionResults) . "\n\n" .
                                    "The message has been deleted"
                            ];

                            if($UseInlineKeyboard)
                            {
                                $ResponseMessage["reply_markup"] = $InlineKeyboard;
                            }

                            Request::sendMessage($ResponseMessage);
                        }
                    }
                    else
                    {
                        if($chatSettings->GeneralAlertsEnabled)
                        {
                            $ResponseMessage = [
                                "chat_id" => $message->Chat->ID,
                                "reply_to_message_id" => $message->MessageID,
                                "parse_mode" => "html",
                                "text" =>
                                    self::generateDetectionMessage($messageLog, $userClient, $spamPredictionResults) . "\n\n" .
                                    "<b>The message cannot be deleted because of insufficient administrator privileges</b>"
                            ];

                            if($UseInlineKeyboard)
                            {
                                $ResponseMessage["reply_markup"] = $InlineKeyboard;
                            }

                            Request::sendMessage($ResponseMessage);
                        }

                    }
                    break;

                case DetectionAction::KickOffender:
                    $DeleteResponse = Request::deleteMessage([
                        "chat_id" => $message->Chat->ID,
                        "message_id" => $message->MessageID
                    ]);

                    $KickResponse = Request::kickChatMember([
                        "chat_id" => $message->Chat->ID,
                        "user_id" => $userClient->User->ID,
                        "until_date" => (int)time() + 60
                    ]);

                    $Response = self::generateDetectionMessage($messageLog, $userClient, $spamPredictionResults) . "\n\n";

                    if($DeleteResponse->isOk() == false)
                    {
                        $Response .= "<b>The message cannot be deleted because of insufficient administrator privileges</b>\n\n";
                    }

                    if($KickResponse->isOk() == false)
                    {
                        $Response .= "<b>The user cannot be removed because of insufficient administrator privileges</b>\n\n";
                    }

                    if($KickResponse->isOk() == true && $DeleteResponse->isOk() == true)
                    {
                        $Response .= "The message was deleted and the offender was removed from the group";
                    }

                    if($chatSettings->GeneralAlertsEnabled)
                    {
                        $ResponseMessage = [
                            "chat_id" => $message->Chat->ID,
                            "parse_mode" => "html",
                            "text" => $Response
                        ];

                        if($UseInlineKeyboard)
                        {
                            $ResponseMessage["reply_markup"] = $InlineKeyboard;
                        }

                        Request::sendMessage($ResponseMessage);
                    }

                    break;

                case DetectionAction::BanOffender:
                    $DeleteResponse = Request::deleteMessage([
                        "chat_id" => $message->Chat->ID,
                        "message_id" => $message->MessageID
                    ]);

                    $BanResponse = Request::kickChatMember([
                        "chat_id" => $message->Chat->ID,
                        "user_id" => $userClient->User->ID,
                        "until_date" => 0
                    ]);

                    $Response = self::generateDetectionMessage($messageLog, $userClient, $spamPredictionResults) . "\n\n";

                    if($DeleteResponse->isOk() == false)
                    {
                        $Response .= "<b>The message cannot be deleted because of insufficient administrator privileges</b>\n\n";
                    }

                    if($BanResponse->isOk() == false)
                    {
                        $Response .= "<b>The user cannot be banned because of insufficient administrator privileges</b>\n\n";
                    }

                    if($BanResponse->isOk() == true && $DeleteResponse->isOk() == true)
                    {
                        $Response .= "The message was deleted and the offender was banned from the group";
                    }

                    if($chatSettings->GeneralAlertsEnabled)
                    {
                        $ResponseMessage = [
                            "chat_id" => $message->Chat->ID,
                            "parse_mode" => "html",
                            "text" => $Response
                        ];

                        if($UseInlineKeyboard)
                        {
                            $ResponseMessage["reply_markup"] = $InlineKeyboard;
                        }

                        Request::sendMessage($ResponseMessage);
                    }

                    break;

                default:
                    if($chatSettings->GeneralAlertsEnabled)
                    {
                        $ResponseMessage = [
                            "chat_id" => $message->Chat->ID,
                            "reply_to_message_id" => $message->MessageID,
                            "parse_mode" => "html",
                            "text" =>
                                self::generateDetectionMessage($messageLog, $userClient, $spamPredictionResults) . "\n\n" .
                                "No action was taken because the detection action is not recognized"
                        ];

                        if($UseInlineKeyboard)
                        {
                            $ResponseMessage["reply_markup"] = $InlineKeyboard;
                        }

                        Request::sendMessage($ResponseMessage);
                    }
            }

        }

        /**
         * Generates a generic spam detection message
         *
         * @param MessageLog $messageLog
         * @param TelegramClient $userClient
         * @param SpamPredictionResults $spamPredictionResults
         * @return string
         */
        private static function generateDetectionMessage(MessageLog $messageLog, TelegramClient $userClient, SpamPredictionResults $spamPredictionResults): string
        {
            /** @noinspection DuplicatedCode */
            if($userClient->User->Username == null)
            {
                if($userClient->User->LastName == null)
                {
                    $Mention = "<a href=\"tg://user?id=" . $userClient->User->ID . "\">" . self::escapeHTML($userClient->User->FirstName) . "</a>";
                }
                else
                {
                    $Mention = "<a href=\"tg://user?id=" . $userClient->User->ID . "\">" . self::escapeHTML($userClient->User->FirstName . " " . $userClient->User->LastName) . "</a>";
                }
            }
            else
            {
                $Mention = "@" . $userClient->User->Username;
            }

            $Response = "\u{26A0} <b>SPAM DETECTED</b> \u{26A0}\n\n";
            $Response .= "<b>User:</b> $Mention (<code>" . $userClient->PublicID . "</code>)\n";
            $Response .= "<b>Message Hash:</b> <code>" . $messageLog->MessageHash . "</code>\n";
            $Response .= "<b>Spam Probability:</b> <code>" . $spamPredictionResults->SpamPrediction . "%</code>";

            return $Response;
        }

        /**
         * Logs detected spam to the public channel
         *
         * @param Message $message
         * @param MessageLog $messageLog
         * @param TelegramClient $userClient
         * @param TelegramClient|null $channelClient
         * @return string|null
         * @throws TelegramException
         * @noinspection DuplicatedCode
         */
        private static function logDetectedSpam(Message $message,  MessageLog $messageLog, TelegramClient $userClient, $channelClient=null)
        {
            $LogMessage = "#spam_prediction\n\n";

            $LogMessage .= "<b>Private Telegram ID:</b> <code>" . $userClient->PublicID . "</code>\n";
            if($channelClient !== null)
            {
                $LogMessage .= "<b>Channel PTID:</b> <code>" . $channelClient->PublicID . "</code>\n";
            }
            $LogMessage .= "<b>Prediction Results:</b> <code>" . $messageLog->SpamPrediction . "</code>\n";
            $LogMessage .= "<b>Message Hash:</b> <code>" . $messageLog->MessageHash . "</code>\n";
            $LogMessage .= "<b>Timestamp:</b> <code>" . $messageLog->Timestamp . "</code>";

            $LogMessageWithContent = $LogMessage . "\n\n<i>===== CONTENT =====</i>\n\n" . self::escapeHTML($message->getText());
            if(strlen($LogMessageWithContent) > 4096)
            {
                $LogMessage .= "\n\nThe content is too large to be shown\n";
            }
            else
            {
                $LogMessage = $LogMessageWithContent;
            }

            $Response = Request::sendMessage([
                "chat_id" => "570787098",
                "disable_web_page_preview" => true,
                "disable_notification" => true,
                "parse_mode" => "html",
                "text" => $LogMessage
            ]);

            if($Response->isOk() == false)
            {
                return null;
            }

            /** @var \Longman\TelegramBot\Entities\Message $LoggedMessage */
            $LoggedMessage = $Response->getResult();

            return "https://t.me/SpamProtectionLogs/" . $LoggedMessage->getMessageId();
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
