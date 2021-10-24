<?php

    /** @noinspection PhpMissingFieldTypeInspection */
    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use CoffeeHouse\Abstracts\LargeGeneralizedClassificationSearchMethod;
    use CoffeeHouse\Exceptions\CoffeeHouseUtilsNotReadyException;
    use CoffeeHouse\Exceptions\InvalidServerInterfaceModuleException;
    use CoffeeHouse\Exceptions\NoResultsFoundException;
    use CoffeeHouse\Exceptions\NsfwClassificationException;
    use CoffeeHouse\Exceptions\UnsupportedImageTypeException;
    use CoffeeHouse\Objects\Results\SpamPredictionResults;
    use Exception;
    use Longman\TelegramBot\Commands\SystemCommand;
    use Longman\TelegramBot\Commands\UserCommands\BlacklistCommand;
    use Longman\TelegramBot\Commands\UserCommands\FinalVerdictCommand;
    use Longman\TelegramBot\Commands\UserCommands\LanguageCommand;
    use Longman\TelegramBot\Commands\UserCommands\WhoisCommand;
    use Longman\TelegramBot\Entities\InlineKeyboard;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Abstracts\BlacklistFlag;
    use SpamProtection\Abstracts\DetectionAction;
    use SpamProtection\Abstracts\TelegramUserStatus;
    use SpamProtection\Abstracts\VotesDueRecordStatus;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtection\Objects\ChannelStatus;
    use SpamProtection\Objects\ChatSettings;
    use SpamProtection\Objects\MessageLog;
    use SpamProtection\Objects\TelegramObjects\ChatMember;
    use SpamProtection\Objects\TelegramObjects\Message;
    use SpamProtection\Objects\TelegramObjects\PhotoSize;
    use SpamProtection\Objects\UserStatus;
    use SpamProtectionBot;
    use TelegramClientManager\Abstracts\TelegramChatType;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;
    use TelegramClientManager\Objects\TelegramClient;
    use VerboseAdventure\Abstracts\EventType;
    use TmpFile\TmpFile;

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
        protected $version = '2.0.3';

        /**
         * The whois command used for finding targets
         *
         * @var WhoisCommand|null
         */
        public $WhoisCommand = null;

        /**
         * Executes the generic message command
         *
         * @return ServerResponse
         * @throws CoffeeHouseUtilsNotReadyException
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws InvalidServerInterfaceModuleException
         * @throws NsfwClassificationException
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @throws \CoffeeHouse\Exceptions\DatabaseException
         * @noinspection DuplicatedCode
         * @noinspection PhpMissingReturnTypeInspection
         */
        public function execute(): ServerResponse
        {
            // Find all clients
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
            $this->WhoisCommand = new WhoisCommand($this->telegram, $this->update);
            $this->WhoisCommand->findClients();

            // Tally analytics
            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->WhoisCommand->ChatObject->ID);

            // If it's a private chat, ignore it.
            if($this->WhoisCommand->ChatObject->Type == TelegramChatType::Private)
            {
                return Request::emptyResponse();
            }

            $this->handleMessageSpeed();
            $this->handleFinalVerdict();

            // Obtain the User Stats and Chat Settings
            $UserStatus = SettingsManager::getUserStatus($this->WhoisCommand->UserClient);
            $ChatSettings = SettingsManager::getChatSettings($this->WhoisCommand->ChatClient);

            // Ban the user from the chat if the chat has blacklist protection enabled
            // and the user is blacklisted.
            if($this->handleBlacklistedUser($ChatSettings, $UserStatus, $this->WhoisCommand->UserClient, $this->WhoisCommand->ChatClient))
            {
                // No need to continue any further if the user got banned
                $this->handleLanguageDetection();
                return Request::emptyResponse();
            }

            // Ban the user from the chat if the chat has potential spammer protection enabled
            // and the user is a potential spammer.
            if($this->handlePotentialSpammer($ChatSettings, $UserStatus, $this->WhoisCommand->UserClient, $this->WhoisCommand->ChatClient))
            {
                // No need to continue any further if the user got banned
                $this->handleLanguageDetection();
                return Request::emptyResponse();
            }

            // Remove the message if it came from a blacklisted channel
            if($this->getMessage()->getForwardFromChat() !== null)
            {
                $ForwardChannelClient = $TelegramClientManager->getTelegramClientManager()->registerChat($this->WhoisCommand->ForwardChannelObject);
                $ForwardChannelStatus = SettingsManager::getChannelStatus($ForwardChannelClient);
                if($this->handleBlacklistedChannel($ChatSettings, $ForwardChannelStatus, $ForwardChannelClient, $this->WhoisCommand->UserClient, $this->WhoisCommand->ChatClient))
                {
                    // No need to continue any further if the channel message got deleted
                    $this->handleLanguageDetection();
                    return Request::emptyResponse();
                }
            }

            // Handles the message to detect if it's spam or not
            $this->handleMessage($this->WhoisCommand->ChatClient, $this->WhoisCommand->UserClient, $this->WhoisCommand->DirectClient);
            $this->handleNsfwFilter($this->WhoisCommand->ChatClient, $this->WhoisCommand->UserClient);
            $this->handleLanguageDetection();
            return Request::emptyResponse();
        }

        /**
         * Handles the final verdict
         */
        public function handleFinalVerdict()
        {
            $VotesDueRecord = SpamProtectionBot::getSpamProtection()->getVotesDueManager()->getCurrentPool(false);
            if(time() >= $VotesDueRecord->DueTimestamp && $VotesDueRecord->Status == VotesDueRecordStatus::CollectingData)
            {
                SpamProtectionBot::getLogHandler()->log(EventType::INFO, "Running final verdict event", "handleFinalVerdict");
                $FinalVerdictCommand = new FinalVerdictCommand($this->telegram, $this->update);

                try
                {
                    $FinalVerdictCommand->processFinalVerdict();

                    SpamProtectionBot::getLogHandler()->log(EventType::INFO, "Final Verdict processed!", "handleFinalVerdict");
                }
                catch(Exception $e)
                {
                    SpamProtectionBot::getLogHandler()->log(EventType::WARNING, "There was an error while trying to handle the final verdict event", "handleFinalVerdict");
                    SpamProtectionBot::getLogHandler()->logException($e, "handleFinalVerdict");
                }
            }
        }

        /**
         * Handles the message speed for the user and chat
         */
        public function handleMessageSpeed()
        {
            if($this->getMessage() == null) return;

            try
            {
                $UserStatus = SettingsManager::getUserStatus($this->WhoisCommand->UserClient);
                $UserStatus->trackMessageSpeed($this->getMessage()->getDate());
                $this->WhoisCommand->UserClient = SettingsManager::updateUserStatus($this->WhoisCommand->UserClient, $UserStatus);
                SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($this->WhoisCommand->UserClient);
            }
            catch(Exception $e)
            {
                SpamProtectionBot::getLogHandler()->log(EventType::WARNING, "There was an error while trying to calculate the messages per minute (user)", "handleMessageSpeed");
                SpamProtectionBot::getLogHandler()->logException($e, "handleMessageSpeed");
            }

            try
            {
                $ChatSettings = SettingsManager::getChatSettings($this->WhoisCommand->ChatClient);
                $ChatSettings->trackMessageSpeed($this->getMessage()->getDate());
                $this->WhoisCommand->ChatClient = SettingsManager::updateChatSettings($this->WhoisCommand->ChatClient, $ChatSettings);
                SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($this->WhoisCommand->ChatClient);
            }
            catch(Exception $e)
            {
                SpamProtectionBot::getLogHandler()->log(EventType::WARNING, "There was an error while trying to calculate the messages per minute (chat)", "handleMessageSpeed");
                SpamProtectionBot::getLogHandler()->logException($e, "handleMessageSpeed");
            }
        }

        /**
         * Handles language prediction
         *
         * @return bool
         * @noinspection DuplicatedCode
         */
        public function handleLanguageDetection(): bool
        {
            $CoffeeHouse = SpamProtectionBot::getCoffeeHouse();

            if($this->getMessage()->getText(true) !== null && strlen($this->getMessage()->getText(true)) > 0)
            {
                // Is the message from the same bot?
                if($this->getMessage()->getForwardFrom() !== null)
                {
                    if($this->getMessage()->getForwardFrom()->getUsername() == TELEGRAM_BOT_NAME)
                    {
                        return false;
                    }
                }

                try
                {
                    $Results = $CoffeeHouse->getLanguagePrediction()->predict($this->getMessage()->getText(true), true, true, true, true);
                }
                catch(Exception $e)
                {
                    return false;
                }

                try
                {
                    // Predict the language of the chat
                    if($this->WhoisCommand->ChatClient !== null)
                    {
                        $TargetChatStatus = SettingsManager::getChatSettings($this->WhoisCommand->ChatClient);
                        $GeneralizedChat = null;

                        if($TargetChatStatus->LargeLanguageGeneralizedID == null)
                        {
                            $GeneralizedChat = $CoffeeHouse->getLargeGeneralizedClassificationManager()->create(35);
                        }
                        else
                        {
                            try
                            {
                                $GeneralizedChat = $CoffeeHouse->getLargeGeneralizedClassificationManager()->get(
                                    LargeGeneralizedClassificationSearchMethod::byPublicID, $TargetChatStatus->LargeLanguageGeneralizedID
                                );
                            }
                            catch(NoResultsFoundException $e)
                            {
                                $GeneralizedChat = $CoffeeHouse->getLargeGeneralizedClassificationManager()->create(35);
                            }
                        }

                        /** @noinspection DuplicatedCode */
                        $GeneralizedChat = $CoffeeHouse->getLanguagePrediction()->generalize($GeneralizedChat, $Results);

                        // Update the target user's language prediction
                        $TargetChatStatus->LargeLanguageGeneralizedID = $GeneralizedChat->PublicID;
                        $TargetChatStatus->GeneralizedLanguage = $GeneralizedChat->TopLabel;
                        $TargetChatStatus->GeneralizedLanguageProbability = $GeneralizedChat->TopProbability;
                        $this->WhoisCommand->ChatClient = SettingsManager::updateChatSettings($this->WhoisCommand->ChatClient, $TargetChatStatus);
                        SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($this->WhoisCommand->ChatClient);
                    }
                }
                catch(Exception $e)
                {
                    SpamProtectionBot::getLogHandler()->log(EventType::WARNING, "There was an error while trying to process languageDetection (Chat)", "handleLanguageDetection");
                    SpamProtectionBot::getLogHandler()->logException($e, "handleLanguageDetection");
                }

                try
                {
                    // Predict the language for the forwarded user client
                    if($this->WhoisCommand->ForwardUserClient !== null)
                    {
                        $TargetForwardUserStatus = SettingsManager::getUserStatus($this->WhoisCommand->ForwardUserClient);
                        $GeneralizedForward = null;

                        if($TargetForwardUserStatus->LargeLanguageGeneralizedID == null)
                        {
                            $GeneralizedForward = $CoffeeHouse->getLargeGeneralizedClassificationManager()->create(25);
                        }
                        else
                        {
                            try
                            {
                                $GeneralizedForward = $CoffeeHouse->getLargeGeneralizedClassificationManager()->get(
                                    LargeGeneralizedClassificationSearchMethod::byPublicID, $TargetForwardUserStatus->LargeLanguageGeneralizedID
                                );
                            }
                            catch(NoResultsFoundException $e)
                            {
                                $GeneralizedForward = $CoffeeHouse->getLargeGeneralizedClassificationManager()->create(25);
                            }
                        }

                        /** @noinspection DuplicatedCode */
                        $GeneralizedForward = $CoffeeHouse->getLanguagePrediction()->generalize($GeneralizedForward, $Results);

                        // Update the target user's language prediction
                        $TargetForwardUserStatus->LargeLanguageGeneralizedID = $GeneralizedForward->PublicID;
                        $TargetForwardUserStatus->GeneralizedLanguage = $GeneralizedForward->TopLabel;
                        $TargetForwardUserStatus->GeneralizedLanguageProbability = $GeneralizedForward->TopProbability;
                        $this->WhoisCommand->ForwardUserClient = SettingsManager::updateUserStatus($this->WhoisCommand->ForwardUserClient, $TargetForwardUserStatus);
                        SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($this->WhoisCommand->ForwardUserClient);
                    }
                }
                catch(Exception $e)
                {
                    SpamProtectionBot::getLogHandler()->log(EventType::WARNING, "There was an error while trying to process languageDetection (ForwardUser)", "handleLanguageDetection");
                    SpamProtectionBot::getLogHandler()->logException($e, "handleLanguageDetection");
                }

                try
                {
                    // Predict the language for the forwarded channel client
                    if($this->WhoisCommand->ForwardChannelClient !== null)
                    {
                        $TargetForwardChannelStatus = SettingsManager::getChannelStatus($this->WhoisCommand->ForwardChannelClient);
                        $GeneralizedChannelForward = null;

                        if($TargetForwardChannelStatus->LargeLanguageGeneralizedID == null)
                        {
                            $GeneralizedChannelForward = $CoffeeHouse->getLargeGeneralizedClassificationManager()->create(35);
                        }
                        else
                        {
                            try
                            {
                                $GeneralizedChannelForward = $CoffeeHouse->getLargeGeneralizedClassificationManager()->get(
                                    LargeGeneralizedClassificationSearchMethod::byPublicID, $TargetForwardChannelStatus->LargeLanguageGeneralizedID
                                );
                            }
                            catch(NoResultsFoundException $e)
                            {
                                $GeneralizedChannelForward = $CoffeeHouse->getLargeGeneralizedClassificationManager()->create(35);
                            }
                        }

                        /** @noinspection DuplicatedCode */
                        $GeneralizedChannelForward = $CoffeeHouse->getLanguagePrediction()->generalize($GeneralizedChannelForward, $Results);

                        // Update the target user's language prediction
                        $TargetForwardChannelStatus->LargeLanguageGeneralizedID = $GeneralizedChannelForward->PublicID;
                        $TargetForwardChannelStatus->GeneralizedLanguage = $GeneralizedChannelForward->TopLabel;
                        $TargetForwardChannelStatus->GeneralizedLanguageProbability = $GeneralizedChannelForward->TopProbability;
                        $this->WhoisCommand->ForwardChannelClient = SettingsManager::updateChannelStatus($this->WhoisCommand->ForwardChannelClient, $TargetForwardChannelStatus);
                        SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($this->WhoisCommand->ForwardChannelClient);
                    }
                }
                catch(Exception $e)
                {
                    SpamProtectionBot::getLogHandler()->log(EventType::WARNING, "There was an error while trying to process languageDetection (ForwardChannel)", "handleLanguageDetection");
                    SpamProtectionBot::getLogHandler()->logException($e, "handleLanguageDetection");
                }

                try
                {
                    if($this->WhoisCommand->ForwardUserClient !== null && $this->WhoisCommand->ForwardChannelClient !== null)
                    {
                        $TargetUserStatus = SettingsManager::getUserStatus($this->WhoisCommand->UserClient);
                        $Generalized = null;

                        if($TargetUserStatus->LargeLanguageGeneralizedID == null)
                        {
                            $Generalized = $CoffeeHouse->getLargeGeneralizedClassificationManager()->create(25);
                        }
                        else
                        {
                            try
                            {
                                $Generalized = $CoffeeHouse->getLargeGeneralizedClassificationManager()->get(
                                    LargeGeneralizedClassificationSearchMethod::byPublicID, $TargetUserStatus->LargeLanguageGeneralizedID
                                );
                            }
                            catch(NoResultsFoundException $e)
                            {
                                $Generalized = $CoffeeHouse->getLargeGeneralizedClassificationManager()->create(25);
                            }
                        }

                        /** @noinspection DuplicatedCode */
                        $Generalized = $CoffeeHouse->getLanguagePrediction()->generalize($Generalized, $Results);

                        // Update the target user's language prediction
                        $TargetUserStatus->LargeLanguageGeneralizedID = $Generalized->PublicID;
                        $TargetUserStatus->GeneralizedLanguage = $Generalized->TopLabel;
                        $TargetUserStatus->GeneralizedLanguageProbability = $Generalized->TopProbability;
                        $this->WhoisCommand->UserClient = SettingsManager::updateUserStatus($this->WhoisCommand->UserClient, $TargetUserStatus);
                        SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($this->WhoisCommand->UserClient);
                    }
                }
                catch(Exception $e)
                {
                    SpamProtectionBot::getLogHandler()->log(EventType::WARNING, "There was an error while trying to process languageDetection (User)", "handleLanguageDetection");
                    SpamProtectionBot::getLogHandler()->logException($e, "handleLanguageDetection");
                }
            }

            return true;
        }

        /**
         * Handles the message deletion in chat
         *
         * @param \Longman\TelegramBot\Entities\Message $sentMessage
         * @param TelegramClient $chatClient
         * @return bool
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws DatabaseException
         */
        public function handleMessageDeletion(\Longman\TelegramBot\Entities\Message $sentMessage, TelegramClient $chatClient): bool
        {
            if($chatClient->Chat->Type == TelegramChatType::Private || $chatClient->Chat->Type == TelegramChatType::Channel)
            {
                return false;
            }

            $ChatSettings = SettingsManager::getChatSettings($chatClient);

            if($ChatSettings->DeleteOlderMessages)
            {
                if($ChatSettings->LastMessageID !== null)
                {
                    Request::deleteMessage([
                        "chat_id" => $chatClient->Chat->ID,
                        "message_id" => $ChatSettings->LastMessageID
                    ]);
                }

                $ChatSettings->LastMessageID = $sentMessage->getMessageId();
                $chatClient = SettingsManager::updateChatSettings($chatClient, $ChatSettings);

                SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($chatClient);

                return true;
            }

            return false;
        }

        /**
         * @param TelegramClient $chatClient
         * @param TelegramClient $userClient
         * @return bool
         * @throws CoffeeHouseUtilsNotReadyException
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws InvalidServerInterfaceModuleException
         * @throws NsfwClassificationException
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @throws \CoffeeHouse\Exceptions\DatabaseException
         * @noinspection DuplicatedCode
         */
        public function handleNsfwFilter(TelegramCLient $chatClient, TelegramClient $userClient): bool
        {
            if($this->getMessage()->getPhoto() == null || count($this->getMessage()->getPhoto()) == 0)
                return false;

            $ChatSettings = SettingsManager::getChatSettings($chatClient);
            $UserStatus = SettingsManager::getUserStatus($userClient);

            if($ChatSettings->NsfwFilterEnabled == false)
                return false; // Save processing power!

            if($UserStatus->IsWhitelisted)
                return false;

            $Message = Message::fromArray($this->getMessage()->getRawData());

            // Determine if the user is an admin or creator
            $IsAdmin = false;
            foreach($ChatSettings->Administrators as $chatMember)
            {
                if($chatMember->User->ID == $userClient->User->ID)
                {
                    if($chatMember->Status == TelegramUserStatus::Administrator || $chatMember->Status == TelegramUserStatus::Creator)
                    {
                        $IsAdmin = true;
                    }
                }
            }

            if($this->WhoisCommand->UserClient->User->Username == "GroupAnonymousBot" && $this->WhoisCommand->UserClient->User->IsBot)
            {
                $IsAdmin = true;
            }

            if($IsAdmin) return false;

            if($Message->Photo !== null)
            {
                // Determine the largest photo
                /** @var PhotoSize $LargestPhoto */
                $LargestPhoto = null;

                foreach($Message->Photo as $photoSize)
                {
                    if($LargestPhoto == null)
                    {
                        $LargestPhoto = $photoSize;
                    }
                    else
                    {
                        if($LargestPhoto->FileSize > $photoSize->FileSize)
                        {
                            $LargestPhoto = $photoSize;
                        }
                    }
                }

                $CoffeeHouse = SpamProtectionBot::getCoffeeHouse();
                $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();

                // $DownloadURI = Request::downloadFileLocation(Request::getFile(["file_id" => $LargestPhoto->FileID])->getResult());
                # TODO: This will break on the self-hosted bot server!
                $File = Request::getFile(["file_id" => $LargestPhoto->FileID])->getResult();
                $TelegramServiceConfiguration = SpamProtectionBot::getTelegramConfiguration();
                $URL = "https://api.telegram.org/file/bot" . $TelegramServiceConfiguration['BotToken'] . "/" . $File->getFilePath();

                $DownloadURI = $URL;
                $ImageContent = file_get_contents($DownloadURI);

                if($ImageContent == false) return false;

                try
                {
                    $Results = $CoffeeHouse->getNsfwClassification()->classifyImage($ImageContent);
                }
                catch(UnsupportedImageTypeException $e)
                {
                    return false;
                }

                $LargestPhoto->UnsafePrediction = $Results->UnsafePrediction;
                $LargestPhoto->SafePrediction = $Results->SafePrediction;

                if($Results->IsNSFW)
                {
                    $DeepAnalytics->tally('tg_spam_protection', 'unsafe_content', 0);
                    $DeepAnalytics->tally('tg_spam_protection', 'unsafe_content', (int)$chatClient->Chat->ID);
                }
                else
                {
                    $DeepAnalytics->tally('tg_spam_protection', 'safe_content', 0);
                    $DeepAnalytics->tally('tg_spam_protection', 'safe_content', (int)$chatClient->Chat->ID);
                }

                if($Results->IsNSFW)
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

                    $Response = "\u{26A0} <b>NSFW CONTENT DETECTED</b> \u{26A0}\n\n";
                    $Response .= "<b>User:</b> $Mention (<code>" . $userClient->PublicID . "</code>)\n";
                    $Response .= "<b>Content Hash:</b> <code>" . $Results->ContentHash . "</code>\n";
                    $Response .= "<b>Unsafe Probability:</b> <code>" . ($Results->UnsafePrediction * 100) . "%</code>\n\n";
                    $ReplyToMessage = true;

                    switch($ChatSettings->NsfwDetectionAction)
                    {
                        case DetectionAction::DeleteMessage:
                            $DeletionResponse = Request::deleteMessage([
                                "chat_id" => $Message->Chat->ID,
                                "message_id" => $Message->MessageID
                            ]);

                            if($DeletionResponse->isOk())
                            {
                                $ReplyToMessage = false;
                                $Response .= "The message has been deleted";
                            }
                            else
                            {
                                $Response .=  "<b>The message cannot be deleted because of insufficient administrator privileges</b>";
                            }
                            break;

                        case DetectionAction::KickOffender:
                            $DeleteResponse = Request::deleteMessage([
                                "chat_id" => $Message->Chat->ID,
                                "message_id" => $Message->MessageID
                            ]);

                            $KickResponse = Request::kickChatMember([
                                "chat_id" => $Message->Chat->ID,
                                "user_id" => $userClient->User->ID,
                                "until_date" => (int)time() + 60
                            ]);

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

                            $DeletionResponse = Request::deleteMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "message_id" => $this->getMessage()->getMessageId()
                            ]);

                            if($DeletionResponse->isOk())
                            {
                                $ReplyToMessage = false;
                                $Response .= "\n\nThe message has been deleted";
                            }
                            else
                            {
                                $Response .=  "\n\n<b>The message cannot be deleted because of insufficient administrator privileges</b>";
                            }
                            break;

                        case DetectionAction::BanOffender:
                            $DeleteResponse = Request::deleteMessage([
                                "chat_id" => $Message->Chat->ID,
                                "message_id" => $Message->MessageID
                            ]);

                            $BanResponse = Request::kickChatMember([
                                "chat_id" => $Message->Chat->ID,
                                "user_id" => $userClient->User->ID,
                                "until_date" => 0
                            ]);

                            if($DeleteResponse->isOk() == false)
                            {
                                $Response .= "<b>The message cannot be deleted because of insufficient administrator privileges</b>\n\n";
                            }
                            else
                            {
                                $ReplyToMessage = false;
                            }

                            if($BanResponse->isOk() == false)
                            {
                                $Response .= "<b>The user cannot be banned because of insufficient administrator privileges</b>\n\n";
                            }

                            if($BanResponse->isOk() == true && $DeleteResponse->isOk() == true)
                            {
                                $Response .= "The message was deleted and the offender was banned from the group";
                            }
                            break;

                        case DetectionAction::Nothing:
                            $Response .= "No action will be taken since the current detection rule in this group is to do nothing";
                            break;
                    }

                    $RequestResults = null;

                    if($ReplyToMessage)
                    {
                        if($ChatSettings->GeneralAlertsEnabled)
                            $RequestResults = Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "text" => $Response
                            ]);
                    }
                    else
                    {
                        if($ChatSettings->GeneralAlertsEnabled)
                            $RequestResults = Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "parse_mode" => "html",
                                "text" => $Response
                            ]);
                    }

                    if($ChatSettings->GeneralAlertsEnabled)
                    {
                        if($RequestResults !== null)
                        {
                            if($RequestResults->isOk())
                                $this->handleMessageDeletion($RequestResults->getResult(), $chatClient);
                        }
                    }
                }

                return true;
            }

            return false;
        }

        /**
         * @param TelegramClient $chatClient
         * @param TelegramClient $userClient
         * @param TelegramClient $telegramClient
         * @return bool
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @throws DatabaseException
         * @noinspection DuplicatedCode
         */
        public function handleMessage(TelegramClient $chatClient, TelegramClient $userClient, TelegramClient $telegramClient): bool
        {
            // Process message text
            $Message = Message::fromArray($this->getMessage()->getRawData());
            $ChatObject = TelegramClient\Chat::fromArray($this->getMessage()->getChat()->getRawData());

            // Is the message empty?
            if($Message->getText() !== null)
            {
                $MessageObject = Message::fromArray($this->getMessage()->getRawData());
                $ChatSettings = SettingsManager::getChatSettings($chatClient);

                // Is the message from the same bot?
                if($Message->ForwardFrom !== null)
                {
                    if($Message->ForwardFrom->Username == TELEGRAM_BOT_NAME)
                    {
                        return false;
                    }
                }

                $TargetUserClient = $userClient;
                $TargetChannelClient = null;

                // Does the chat have forward protection enabled?
                try
                {
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
                        elseif($this->getMessage()->getForwardFromChat() !== null)
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
                        else
                        {
                            // Returns false since forward protection is enabled, the users shouldn't be punished for
                            // forwarding spam that isn't theirs
                            return false;
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
                }
                catch(Exception $e)
                {
                    SpamProtectionBot::getLogHandler()->log(EventType::ERROR, "There was an error while trying to process handleMessage (ForwardProtectionEnabled)", "handleMessage");
                    SpamProtectionBot::getLogHandler()->logException($e, "handleMessage");

                    return false;
                }

                try
                {
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
                                $ChatSettings->AdminCacheLastUpdated = (int)time();

                                foreach($ChatMembersResponse as $chatMember)
                                {
                                    $ChatSettings->Administrators[] = ChatMember::fromArray($chatMember);
                                }

                                $ChatClient = SettingsManager::updateChatSettings($chatClient, $ChatSettings);
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

                // Check if spam detection is enabled
                if($ChatSettings->DetectSpamEnabled)
                {
                    $TargetUserStatus = SettingsManager::getUserStatus($TargetUserClient);

                    // Check if the channel is whitelisted
                    if($TargetChannelClient !== null)
                    {
                        $TargetChannelStatus = SettingsManager::getChannelStatus($TargetChannelClient);

                        if($TargetChannelStatus->IsWhitelisted)
                        {
                            return false;
                        }
                    }

                    // Check if the user is whitelisted
                    if($TargetUserStatus->IsWhitelisted)
                    {
                        return false;
                    }

                    $CoffeeHouse = SpamProtectionBot::getCoffeeHouse();

                    try
                    {
                        // Predict the spam results
                        $Results = $CoffeeHouse->getSpamPrediction()->predict($Message->getText(), false);

                        if($Results == null)
                            return false;
                    }
                    catch(Exception $e)
                    {
                        SpamProtectionBot::getLogHandler()->log(EventType::WARNING, "There was an error while trying to process handleMessage (Prediction)", "handleMessage");
                        SpamProtectionBot::getLogHandler()->logException($e, "handleMessage");

                        // Return false since the rest cannot run without the results
                        return false;
                    }


                    try
                    {
                        if($TargetUserStatus->LargeSpamGeneralizedID == null)
                        {
                            /** @noinspection PhpRedundantOptionalArgumentInspection */
                            $Generalized = $CoffeeHouse->getLargeGeneralizedClassificationManager()->create(50);
                        }
                        else
                        {
                            try
                            {
                                $Generalized = $CoffeeHouse->getLargeGeneralizedClassificationManager()->get(
                                    LargeGeneralizedClassificationSearchMethod::byID, $TargetUserStatus->LargeSpamGeneralizedID
                                );
                            }
                            catch(NoResultsFoundException $e)
                            {
                                /** @noinspection PhpRedundantOptionalArgumentInspection */
                                $Generalized = $CoffeeHouse->getLargeGeneralizedClassificationManager()->create(50);
                            }
                        }

                        /**
                         * This part has been updated to use the new LargeGeneralization method in oppose to using
                         * the two-label generalization system
                         */

                        if($Generalized == null)
                        {
                            /** @noinspection PhpRedundantOptionalArgumentInspection */
                            $Generalized = $CoffeeHouse->getLargeGeneralizedClassificationManager()->create(50);
                        }

                        /** @noinspection DuplicatedCode */
                        $Generalized = $CoffeeHouse->getSpamPrediction()->largeGeneralize($Generalized, $Results);

                        $TargetUserStatus->LargeSpamGeneralizedID = $Generalized->ID;
                        $TargetUserStatus->GeneralizedSpamLabel = $Generalized->TopLabel;

                        foreach($Generalized->Probabilities as $probability)
                        {
                            if($probability !== null)
                                switch($probability->Label)
                                {
                                    case "ham":
                                        $TargetUserStatus->GeneralizedHamProbability = $probability->CalculatedProbability;
                                        break;

                                    case "spam":
                                        $TargetUserStatus->GeneralizedSpamProbability = $probability->CalculatedProbability;
                                        break;
                                }
                        }

                        $TargetUserClient = SettingsManager::updateUserStatus($TargetUserClient, $TargetUserStatus);
                        SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($TargetUserClient);
                    }
                    catch(Exception $exception)
                    {
                        return false;
                    }

                    try
                    {
                        if($TargetChannelClient !== null)
                        {
                            $TargetChannelStatus = SettingsManager::getChannelStatus($TargetChannelClient);

                            if($TargetChannelStatus->LargeSpamGeneralizedID == null)
                            {
                                /** @noinspection PhpRedundantOptionalArgumentInspection */
                                $Generalized = $CoffeeHouse->getLargeGeneralizedClassificationManager()->create(50);
                            }
                            else
                            {
                                try
                                {
                                    $Generalized = $CoffeeHouse->getLargeGeneralizedClassificationManager()->get(
                                        LargeGeneralizedClassificationSearchMethod::byID, $TargetChannelStatus->LargeSpamGeneralizedID
                                    );
                                }
                                catch(NoResultsFoundException $e)
                                {
                                    /** @noinspection PhpRedundantOptionalArgumentInspection */
                                    $Generalized = $CoffeeHouse->getLargeGeneralizedClassificationManager()->create(50);
                                }
                            }

                            if($Generalized == null)
                            {
                                /** @noinspection PhpRedundantOptionalArgumentInspection */
                                $Generalized = $CoffeeHouse->getLargeGeneralizedClassificationManager()->create(50);
                            }

                            /** @noinspection DuplicatedCode */
                            $Generalized = $CoffeeHouse->getSpamPrediction()->largeGeneralize($Generalized, $Results);

                            $TargetChannelStatus->LargeSpamGeneralizedID = $Generalized->ID;
                            $TargetChannelStatus->GeneralizedSpamLabel = $Generalized->TopLabel;

                            foreach($Generalized->Probabilities as $probability)
                            {
                                switch($probability->Label)
                                {
                                    case "ham":
                                        $TargetChannelStatus->GeneralizedHamProbability = $probability->CalculatedProbability;
                                        break;

                                    case "spam":
                                        $TargetChannelStatus->GeneralizedSpamProbability = $probability->CalculatedProbability;
                                        break;
                                }
                            }

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

                    try
                    {
                        $MessageLogObject = $SpamProtection->getMessageLogManager()->registerMessage($MessageObject, $Results->SpamPrediction, $Results->HamPrediction);
                    }
                    catch(Exception $exception)
                    {
                        unset($exception);
                        return false;
                    }

                    // Update the target user's trust prediction
                    // WARNING: This action was already performed above, omitting the functionality.
                    /**
                        $TargetUserStatus->GeneralizedSpam = $Results->GeneralizedSpam;
                        $TargetUserStatus->GeneralizedHam = $Results->GeneralizedHam;
                        $TargetUserStatus->GeneralizedID = $Results->GeneralizedID;
                        $TargetUserClient = SettingsManager::updateUserStatus($TargetUserClient, $TargetUserStatus);
                        SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->updateClient($TargetUserClient);
                     **/

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

                    if($this->WhoisCommand->UserClient->User->Username == "GroupAnonymousBot" && $this->WhoisCommand->UserClient->User->IsBot)
                    {
                        $IsAdmin = true;
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

                            self::handleSpam($MessageObject, $MessageLogObject, $TargetUserClient, $TargetUserStatus, $ChatSettings, $Results, $chatClient, $LoggedReferenceLink);

                            SpamProtectionBot::getDeepAnalytics()->tally('tg_spam_protection', 'detected_spam', 0);
                            SpamProtectionBot::getDeepAnalytics()->tally('tg_spam_protection', 'detected_spam', (int)$telegramClient->getChatId());
                            SpamProtectionBot::getDeepAnalytics()->tally('tg_spam_protection', 'detected_spam', (int)$telegramClient->getUserId());
                            return true;
                        }
                        else
                        {
                            SpamProtectionBot::getDeepAnalytics()->tally('tg_spam_protection', 'detected_ham', 0);
                            SpamProtectionBot::getDeepAnalytics()->tally('tg_spam_protection', 'detected_ham', (int)$telegramClient->getChatId());
                            SpamProtectionBot::getDeepAnalytics()->tally('tg_spam_protection', 'detected_ham', (int)$telegramClient->getUserId());
                            return false;
                        }
                    }
                }
            }

            return false;
        }

        /**
         * Handles a blacklisted channel message
         *
         * @param ChatSettings $chatSettings
         * @param ChannelStatus $channelStatus
         * @param TelegramClient $channelClient
         * @param TelegramClient $userClient
         * @param TelegramClient $chatClient
         * @return bool
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @throws DatabaseException
         * @noinspection DuplicatedCode
         */
        public function handleBlacklistedChannel(ChatSettings $chatSettings, ChannelStatus $channelStatus, TelegramClient $channelClient, TelegramClient $userClient, TelegramClient $chatClient): bool
        {
            if($channelStatus->IsWhitelisted)
            {
                return false;
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
                                $chatSettings->AdminCacheLastUpdated = (int)time();

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

                    if($this->WhoisCommand->UserClient->User->Username == "GroupAnonymousBot" && $this->WhoisCommand->UserClient->User->IsBot)
                    {
                        $IsAdmin = true;
                    }

                    if($IsAdmin == false)
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
                                case BlacklistFlag::BanEvade:
                                    $Response .= "<b>Blacklist Reason:</b> <code>Ban Evade</code>\n";
                                    $Response .= "<b>Original Private ID:</b> (Not applicable to a channel)\n";
                                    break;

                                default:
                                    $Response .= "<b>Blacklist Reason:</b> <code>" . BlacklistCommand::blacklistFlagToReason($channelStatus->BlacklistFlag) . "</code>\n";
                                    break;
                            }

                            $Response .= "<i>You can find evidence of abuse by searching the Private Telegram ID in @" . LOG_CHANNEL . " else ";
                            $Response .= "If you believe that this is was a mistake then let us know in @SpamProtectionSupport</i>";

                            $MessageServerResponse = Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "parse_mode" => "html",
                                "reply_markup" => new InlineKeyboard(
                                    [
                                        ["text" => "Logs", "url" => "https://t.me/" . LOG_CHANNEL],
                                        ["text" => "Channel Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $channelClient->User->ID],
                                        ["text" => "User Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $userClient->User->ID],
                                    ],
                                    [
                                        ["text" => "Report Problem", "url" => "https://t.me/SpamProtectionSupport"]
                                    ]
                                ),
                                "text" => $Response
                            ]);

                            if($MessageServerResponse->isOk())
                            {
                                $this->handleMessageDeletion($MessageServerResponse->getResult(), $chatClient);
                            }

                            return true;
                        }
                    }
                }
            }

            return false;
        }

        /**
         * Handles a blacklisted user from the chat
         *
         * @param ChatSettings $chatSettings
         * @param UserStatus $userStatus
         * @param TelegramClient $userClient
         * @param TelegramClient $chatClient
         * @return bool
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @throws DatabaseException
         * @noinspection DuplicatedCode
         */
        public function handleBlacklistedUser(ChatSettings $chatSettings, UserStatus $userStatus, TelegramClient $userClient, TelegramClient $chatClient): bool
        {
            if($userStatus->IsWhitelisted)
            {
                return false;
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
                                $chatSettings->AdminCacheLastUpdated = (int)time();

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

                    if($this->WhoisCommand->UserClient->User->Username == "GroupAnonymousBot" && $this->WhoisCommand->UserClient->User->IsBot)
                    {
                        $IsAdmin = true;
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
                            $Response = str_ireplace("%s", WhoisCommand::generateMention($userClient), LanguageCommand::localizeChatText(
                                $this->WhoisCommand, "%s has been banned because they've been blacklisted!", ['s'], true
                            )) . "\n\n";

                            $Response .= str_ireplace("%s", "<code>" . $userClient->PublicID . "</code>", LanguageCommand::localizeChatText(
                                    $this->WhoisCommand, "Private Telegram ID: %s", ['s'], true
                                )) . "\n";

                            switch($userStatus->BlacklistFlag)
                            {
                                case BlacklistFlag::BanEvade:
                                    $Response .= str_ireplace("%s", "<code>" . LanguageCommand::localizeChatText($this->WhoisCommand, "Ban Evade") . "</code>", LanguageCommand::localizeChatText(
                                            $this->WhoisCommand, "Blacklist Reason: %s", ['s'], true
                                        )) . "\n";
                                    $Response .= str_ireplace("%s", "<code>" . $userStatus->OriginalPrivateID . "</code>", LanguageCommand::localizeChatText(
                                            $this->WhoisCommand, "Original Private ID: %s", ['s'], true
                                        )) . "\n";
                                    break;

                                default:
                                    $Response .= str_ireplace("%s", "<code>" . LanguageCommand::localizeChatText($this->WhoisCommand, $userStatus->BlacklistFlag) . "</code>", LanguageCommand::localizeChatText(
                                            $this->WhoisCommand, "Blacklist Reason: %s", ['s'], true
                                        )) . "\n";
                                    break;
                            }

                            $NoticeText = LanguageCommand::localizeChatText(
                                    $this->WhoisCommand,
                                    "You can find evidence of abuse by searching the Private Telegram ID in %s else " .
                                    "if you believe that this is a mistake then let us know in %b",
                                    ['s', 'b'], true
                                );

                            $NoticeText = str_ireplace("%s", "@" . LOG_CHANNEL, $NoticeText);
                            $NoticeText = str_ireplace("%b", "@SpamProtectionSupport", $NoticeText);
                            $Response .= "<i>$NoticeText</i>";

                            Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "reply_markup" => new InlineKeyboard(
                                    [
                                        ["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Logs", [], true), "url" => "https://t.me/" . LOG_CHANNEL],
                                        ["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "User Info", [], true), "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $userClient->User->ID],
                                        ["text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Report Problem", [], true), "url" => "https://t.me/SpamProtectionSupport"]
                                    ]
                                ),
                                "text" => $Response
                            ]);

                            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
                            $DeepAnalytics->tally('tg_spam_protection', 'banned_blacklisted', 0);
                            $DeepAnalytics->tally('tg_spam_protection', 'banned_blacklisted', (int)$this->WhoisCommand->ChatObject->ID);

                            return true;
                        }
                    }
                }
            }
            return false;
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
         * @param TelegramClient $chatClient
         * @param string|null $logLink
         * @return bool
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @throws DatabaseException
         * @noinspection DuplicatedCode
         */
        public function handleSpam(
            Message $message, MessageLog $messageLog,
            TelegramClient $userClient, UserStatus $userStatus,
            ChatSettings $chatSettings, SpamPredictionResults $spamPredictionResults, TelegramClient $chatClient,
            ?string $logLink
        ): bool
        {
            if($logLink !== null)
            {
                $UseInlineKeyboard = true;
                $InlineKeyboard = new InlineKeyboard([
                    [
                        "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "View Message", [], true),
                        "url" => $logLink
                    ],
                    [
                        "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "View User", [], true),
                        "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $userClient->User->ID
                    ]
                ]);
            }
            else
            {
                $UseInlineKeyboard = true;
                $InlineKeyboard = new InlineKeyboard([
                    [
                        "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "User Info", [], true),
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
                                    self::generateDetectionMessage($this->WhoisCommand, $messageLog, $userClient, $spamPredictionResults) . "\n\n" .
                                    LanguageCommand::localizeChatText($this->WhoisCommand, "No action will be taken since this group has Forward Protection Enabled", [], true)
                            ];

                            if($UseInlineKeyboard)
                            {
                                $ResponseMessage["reply_markup"] = $InlineKeyboard;
                            }

                            $MessageServerResponse = Request::sendMessage($ResponseMessage);

                            if($MessageServerResponse->isOk())
                            {
                                $this->handleMessageDeletion($MessageServerResponse->getResult(), $chatClient);
                            }
                        }

                        return false;
                    }
                }
            }

            if($userStatus->IsWhitelisted)
            {
                return false;
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
                                self::generateDetectionMessage($this->WhoisCommand, $messageLog, $userClient, $spamPredictionResults) . "\n\n" .
                                LanguageCommand::localizeChatText($this->WhoisCommand, "No action will be taken since the current detection rule in this group is to do nothing", [], true)
                        ];

                        if($UseInlineKeyboard)
                        {
                            $ResponseMessage["reply_markup"] = $InlineKeyboard;
                        }

                        $MessageServerResponse = Request::sendMessage($ResponseMessage);

                        if($MessageServerResponse->isOk())
                        {
                            $this->handleMessageDeletion($MessageServerResponse->getResult(), $chatClient);
                        }
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
                                    self::generateDetectionMessage($this->WhoisCommand, $messageLog, $userClient, $spamPredictionResults) . "\n\n" .
                                    LanguageCommand::localizeChatText($this->WhoisCommand, "The message has been deleted", [], true)
                            ];

                            if($UseInlineKeyboard)
                            {
                                $ResponseMessage["reply_markup"] = $InlineKeyboard;
                            }

                            $MessageServerResponse = Request::sendMessage($ResponseMessage);

                            if($MessageServerResponse->isOk())
                            {
                                $this->handleMessageDeletion($MessageServerResponse->getResult(), $chatClient);
                            }
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
                                    self::generateDetectionMessage($this->WhoisCommand, $messageLog, $userClient, $spamPredictionResults) . "\n\n" .
                                    "<b>" . LanguageCommand::localizeChatText($this->WhoisCommand, "The message cannot be deleted because of insufficient administrator privileges", [], true) . "</b>"
                            ];

                            /** @noinspection PhpExpressionAlwaysConstantInspection */
                            if($UseInlineKeyboard)
                            {
                                $ResponseMessage["reply_markup"] = $InlineKeyboard;
                            }

                            $MessageServerResponse = Request::sendMessage($ResponseMessage);

                            if($MessageServerResponse->isOk())
                            {
                                $this->handleMessageDeletion($MessageServerResponse->getResult(), $chatClient);
                            }
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

                    $Response = self::generateDetectionMessage($this->WhoisCommand, $messageLog, $userClient, $spamPredictionResults) . "\n\n";

                    if($DeleteResponse->isOk() == false)
                    {
                        $Response .= "<b>" . LanguageCommand::localizeChatText($this->WhoisCommand, "The message cannot be deleted because of insufficient administrator privileges", [], true) . "</b>\n\n";
                    }

                    if($KickResponse->isOk() == false)
                    {
                        $Response .= "<b>" . LanguageCommand::localizeChatText($this->WhoisCommand, "The user cannot be removed because of insufficient administrator privileges", [], true) . "</b>\n\n";
                    }

                    if($KickResponse->isOk() == true && $DeleteResponse->isOk() == true)
                    {
                        $Response .= LanguageCommand::localizeChatText($this->WhoisCommand, "The message was deleted and the offender was removed from the group", [], true);
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

                        $MessageServerResponse = Request::sendMessage($ResponseMessage);

                        if($MessageServerResponse->isOk())
                        {
                            $this->handleMessageDeletion($MessageServerResponse->getResult(), $chatClient);
                        }
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

                    $Response = self::generateDetectionMessage($this->WhoisCommand, $messageLog, $userClient, $spamPredictionResults) . "\n\n";

                    if($DeleteResponse->isOk() == false)
                    {
                        $Response .= "<b>" . LanguageCommand::localizeChatText($this->WhoisCommand, "The message cannot be deleted because of insufficient administrator privileges", [], true) . "</b>\n\n";
                    }

                    if($BanResponse->isOk() == false)
                    {
                        $Response .= "<b>" . LanguageCommand::localizeChatText($this->WhoisCommand, "The user cannot be banned because of insufficient administrator privileges", [], true) . "</b>\n\n";
                    }

                    if($BanResponse->isOk() == true && $DeleteResponse->isOk() == true)
                    {
                        $Response .= LanguageCommand::localizeChatText($this->WhoisCommand, "The message was deleted and the offender was banned from the group", [], true);
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

                        $MessageServerResponse = Request::sendMessage($ResponseMessage);

                        if($MessageServerResponse->isOk())
                        {
                            $this->handleMessageDeletion($MessageServerResponse->getResult(), $chatClient);
                        }
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
                                self::generateDetectionMessage($this->WhoisCommand, $messageLog, $userClient, $spamPredictionResults) . "\n\n" .
                                LanguageCommand::localizeChatText($this->WhoisCommand, "No action was taken because the detection action is not recognized", [], true)
                        ];

                        if($UseInlineKeyboard)
                        {
                            $ResponseMessage["reply_markup"] = $InlineKeyboard;
                        }

                        $MessageServerResponse = Request::sendMessage($ResponseMessage);

                        if($MessageServerResponse->isOk())
                        {
                            $this->handleMessageDeletion($MessageServerResponse->getResult(), $chatClient);
                        }
                    }
                    break;
            }

            return true;
        }

        /**
         * Generates a generic spam detection message
         *
         * @param WhoisCommand $whoisCommand
         * @param MessageLog $messageLog
         * @param TelegramClient $userClient
         * @param SpamPredictionResults $spamPredictionResults
         * @return string
         */
        private static function generateDetectionMessage(WhoisCommand $whoisCommand, MessageLog $messageLog, TelegramClient $userClient, SpamPredictionResults $spamPredictionResults): string
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

            $Response = "\u{26A0} <b>" . LanguageCommand::localizeChatText($whoisCommand, "SPAM DETECTED", [], true) . "</b> \u{26A0}\n\n";

            $Response .= str_ireplace(
                "%s", "$Mention (<code>" . $userClient->PublicID . "</code>)\n",
                LanguageCommand::localizeChatText($whoisCommand, "User: %s", ['s'], true)
            );
            $Response .= str_ireplace(
                "%s", "<code>" . $messageLog->MessageHash . "</code>\n",
                LanguageCommand::localizeChatText($whoisCommand, "Message Hash: %s", ['s'], true)
            );
            $Response .= str_ireplace(
                "%s", "<code>" . ($spamPredictionResults->SpamPrediction * 100) . "%</code>\n",
                LanguageCommand::localizeChatText($whoisCommand, "Spam Probability: %s", ['s'], true)
            );

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
        private static function logDetectedSpam(Message $message,  MessageLog $messageLog, TelegramClient $userClient, $channelClient=null): ?string
        {
            // Attempt to create a voting pool
            $VotingPool = null;
            $VotingPoll = null;

            try
            {
                $VotingPool = SpamProtectionBot::getSpamProtection()->getVotesDueManager()->getCurrentPool(false);
                $VotingPoll = SpamProtectionBot::getSpamProtection()->getPredictionVotesManager()->createNewVote(
                    $messageLog, $message, $VotingPool
                );
                $VotingPool->Records->addRecord($VotingPoll);
                SpamProtectionBot::getSpamProtection()->getVotesDueManager()->updatePool($VotingPool);
            }
            catch(Exception $e)
            {
                SpamProtectionBot::getLogHandler()->log(EventType::WARNING, "There was an error while trying to create a voting pool", "logDetectedSpam");
                SpamProtectionBot::getLogHandler()->logException($e, "logDetectedSpam");
            }

            $TmpFile = null;

            $LogMessage = "#spam_prediction\n\n";

            $LogMessage .= "<b>Private Telegram ID:</b> <code>" . $userClient->PublicID . "</code>\n";
            if($channelClient !== null)
            {
                $LogMessage .= "<b>Channel PTID:</b> <code>" . $channelClient->PublicID . "</code>\n";
            }

            $LogMessage .= "<b>Prediction Results:</b> <code>" . ($messageLog->SpamPrediction * 100) . "</code>\n";
            $LogMessage .= "<b>Message Hash:</b> <code>" . $messageLog->MessageHash . "</code>\n";
            $LogMessage .= "<b>Timestamp:</b> <code>" . $messageLog->Timestamp . "</code>";

            $LogMessageWithContent = $LogMessage . "\n\n<i>===== CONTENT =====</i>\n\n" . self::escapeHTML($message->getText());
            if(strlen($LogMessageWithContent) > 4096)
            {
                $LogMessage .= "\n\nSee the attached file\n";
                $TmpFile = new TmpFile($message->getText(), '.txt', 'msg_content_');
                // $LogMessage .= "\n\nThe content is too large to be shown\n";
            }
            else
            {
                $LogMessage = $LogMessageWithContent;
            }

            if($channelClient !== null)
            {
                if($VotingPoll == null)
                {
                    $InlineKeyboard = new InlineKeyboard(
                        [
                            ["text" => "User Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $messageLog->User->ID],
                            ["text" => "Chat Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $messageLog->Chat->ID],
                            ["text" => "Channel Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $messageLog->ForwardFromChat->ID],
                        ]
                    );
                }
                else
                {
                    $InlineKeyboard = new InlineKeyboard(
                        [
                            ["text" => "User Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $messageLog->User->ID],
                            ["text" => "Chat Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $messageLog->Chat->ID],
                            ["text" => "Channel Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $messageLog->ForwardFromChat->ID],
                        ],
                        [
                            ["text" => "\u{2714} Correct (0)", "callback_data" => "0501" . $VotingPoll->ID],
                            ["text" => "\u{274C} Incorrect (0)", "callback_data" => "0500" . $VotingPoll->ID]
                        ]
                    );
                }
            }
            else
            {
                if($VotingPoll == null)
                {
                    $InlineKeyboard = new InlineKeyboard(
                        [
                            ["text" => "User Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $messageLog->User->ID],
                            ["text" => "Chat Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $messageLog->Chat->ID]
                        ]
                    );
                }
                else
                {
                    $InlineKeyboard = new InlineKeyboard(
                        [
                            ["text" => "User Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $messageLog->User->ID],
                            ["text" => "Chat Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $messageLog->Chat->ID]
                        ],
                        [
                            ["text" => "\u{2714} Correct (0)", "callback_data" => "0501" . $VotingPoll->ID],
                            ["text" => "\u{274C} Incorrect (0)", "callback_data" => "0500" . $VotingPoll->ID]
                        ]
                    );
                }

            }

            $Response = null;

            if ($TmpFile !== null)
            {
                $Response = Request::sendDocument([
                    "chat_id" => "@" . LOG_CHANNEL,
                    "disable_notification" => true,
                    "reply_markup" => $InlineKeyboard,
                    "parse_mode" => "html",
                    "caption" => $LogMessage,
                    "document" => Request::encodeFile($TmpFile->getFileName())
                ]);
            }
            else
            {
                $Response = Request::sendMessage([
                    "chat_id" => "@" . LOG_CHANNEL,
                    "disable_web_page_preview" => true,
                    "disable_notification" => true,
                    "parse_mode" => "html",
                    "reply_markup" => $InlineKeyboard,
                    "text" => $LogMessage
                ]);
            }

            if($Response->isOk() == false)
            {
                return null;
            }

            /** @var \Longman\TelegramBot\Entities\Message $LoggedMessage */
            $LoggedMessage = $Response->getResult();

            return "https://t.me/" . LOG_CHANNEL . "/" . $LoggedMessage->getMessageId();
        }

        /**
         * Escapes problematic characters for HTML content
         *
         * @param string $input
         * @return string
         */
        private static function escapeHTML(string $input): string
        {
            return htmlspecialchars($input, ENT_COMPAT);
        }
    }
