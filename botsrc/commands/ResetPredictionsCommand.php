<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\UserCommands;

    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\Message;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use pop\pop;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtection\Utilities\Hashing;
    use SpamProtectionBot;
    use TelegramClientManager\Abstracts\SearchMethods\TelegramClientSearchMethod;
    use TelegramClientManager\Abstracts\TelegramChatType;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;

    /**
     * Reset Predictions Command
     *
     * Allows an operator or agent to reset the current prediction values for a user, chat or channel.
     */
    class ResetPredictionsCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'ResetPredictions';

        /**
         * @var string
         */
        protected $description = 'Allows an operator or agent to reset the current prediction values for a user, chat or channel.';

        /**
         * @var string
         */
        protected $usage = '/ResetPredictions [ID/PTID/Username]';

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
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramException
         * @throws TelegramClientNotFoundException
         * @noinspection DuplicatedCode
         */
        public function execute()
        {
            // Find clients
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
            $this->WhoisCommand = new WhoisCommand($this->telegram, $this->update);
            $this->WhoisCommand->findClients();

            // Tally DeepAnalytics
            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'reset_predictions_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->WhoisCommand->ChatObject->ID);
            $DeepAnalytics->tally('tg_spam_protection', 'reset_predictions_command', (int)$this->WhoisCommand->ChatObject->ID);

            // Ignore forwarded commands
            if($this->getMessage()->getForwardFrom() !== null || $this->getMessage()->getForwardFromChat())
            {
                return null;
            }

            // Check the permissions
            $UserStatus = SettingsManager::getUserStatus($this->WhoisCommand->UserClient);

            if($UserStatus->IsOperator == false && $UserStatus->IsAgent == false)
            {
                return null;
            }

            if($this->getMessage()->getText(true) !== null && strlen($this->getMessage()->getText(true)) > 0)
            {
                $options = pop::parse($this->getMessage()->getText(true));
                $TargetTelegramParameter = array_values($options)[(count($options)-1)];

                if(is_bool($TargetTelegramParameter))
                {
                    $TargetTelegramParameter = array_keys($options)[(count($options)-1)];
                }


                if($TargetTelegramParameter == null)
                {
                    return self::displayUsage($this->getMessage(), LanguageCommand::localizeChatText($this->WhoisCommand, "Missing target value parameter"));
                }

                $EstimatedPrivateID = Hashing::telegramClientPublicID((int)$TargetTelegramParameter, (int)$TargetTelegramParameter);
                $TargetTelegramClient = null;

                try
                {
                    $TargetTelegramClient = $TelegramClientManager->getTelegramClientManager()->getClient(TelegramClientSearchMethod::byPublicId, $EstimatedPrivateID);
                }
                catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                {
                    unset($telegramClientNotFoundException);
                }

                try
                {
                    $TargetTelegramClient = $TelegramClientManager->getTelegramClientManager()->getClient(
                        TelegramClientSearchMethod::byPublicId, $TargetTelegramParameter
                    );
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
                }
                catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                {
                    unset($telegramClientNotFoundException);
                }

                if($TargetTelegramClient == null)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => str_ireplace("%s", self::escapeHTML($TargetTelegramParameter), LanguageCommand::localizeChatText(
                            $this->WhoisCommand, "Unable to find the client '%s'", ['s']
                        ))
                    ]);
                }

                switch($TargetTelegramClient->Chat->Type)
                {
                    case TelegramChatType::Private:
                        $UserStatus = SettingsManager::getUserStatus($TargetTelegramClient);
                        $UserStatus->LargeLanguageGeneralizedID = null;
                        $UserStatus->GeneralizedLanguage = "Unknown";
                        $UserStatus->GeneralizedLanguageProbability = 0;
                        $UserStatus->LargeSpamGeneralizedID = null;
                        $UserStatus->GeneralizedSpamProbability = 0;
                        $UserStatus->GeneralizedHamProbability = 0;
                        $UserStatus->GeneralizedSpamLabel = "Unknown";
                        $TargetTelegramClient = SettingsManager::updateUserStatus($TargetTelegramClient, $UserStatus);
                        $TelegramClientManager->getTelegramClientManager()->updateClient($TargetTelegramClient);
                        break;

                    case TelegramChatType::SuperGroup:
                    case TelegramChatType::Group:
                        $ChatSettings = SettingsManager::getChatSettings($TargetTelegramClient);
                        $ChatSettings->LargeLanguageGeneralizedID = null;
                        $ChatSettings->GeneralizedLanguage = "Unknown";
                        $ChatSettings->GeneralizedLanguageProbability = 0;
                        $TargetTelegramClient = SettingsManager::updateChatSettings($TargetTelegramClient, $ChatSettings);
                        $TelegramClientManager->getTelegramClientManager()->updateClient($TargetTelegramClient);
                        break;

                    case TelegramChatType::Channel:
                        $ChannelStatus = SettingsManager::getChannelStatus($TargetTelegramClient);
                        $ChannelStatus->LargeLanguageGeneralizedID = null;
                        $ChannelStatus->GeneralizedLanguage = "Unknown";
                        $ChannelStatus->GeneralizedLanguageProbability = 0;
                        $ChannelStatus->LargeSpamGeneralizedID = null;
                        $ChannelStatus->GeneralizedSpamProbability = 0;
                        $ChannelStatus->GeneralizedHamProbability = 0;
                        $ChannelStatus->GeneralizedSpamLabel = "Unknown";
                        $TargetTelegramClient = SettingsManager::updateChannelStatus($TargetTelegramClient, $ChannelStatus);
                        $TelegramClientManager->getTelegramClientManager()->updateClient($TargetTelegramClient);
                        break;

                    default:
                        return Request::sendMessage([
                            "chat_id" => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "This command is not applicable to this entity type")
                        ]);
                }

                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => LanguageCommand::localizeChatText($this->WhoisCommand, "Success, all prediction values has been set back to the default values")
                ]);
            }

            return self::displayUsage($this->getMessage());
        }

        /**
         * Displays the command usage
         *
         * @param Message $message
         * @param string $error
         * @return ServerResponse|null
         * @throws TelegramException
         */
        public function displayUsage(Message $message, string $error="Missing parameter")
        {
            return Request::sendMessage([
                "chat_id" => $message->getChat()->getId(),
                "parse_mode" => "html",
                "reply_to_message_id" => $message->getMessageId(),
                "text" =>
                    "$error\n\n" .
                    LanguageCommand::localizeChatText($this->WhoisCommand, "Usage:") . "\n" .
                    "   <b>/resetpredictions</b> -c [PTID/ID/Username]\n".
                    LanguageCommand::localizeChatText($this->WhoisCommand, "For further instructions, send /help resetpredictions")
            ]);
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