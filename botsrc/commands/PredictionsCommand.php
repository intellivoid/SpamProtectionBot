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
    use SpamProtection\Managers\SettingsManager;
    use SpamProtectionBot;

    /**
     * Predictions Command
     *
     * Allows anyone to see the predictions of the replied to message
     */
    class PredictionsCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'PredictionsCommand';

        /**
         * @var string
         */
        protected $description = 'Allows a user to get the predictions of the replied message';

        /**
         * @var string
         */
        protected $usage = '/predictions [REPLY TO MESSAGE]';

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
         * @noinspection DuplicatedCode
         * @throws TelegramException
         */
        public function execute(): ServerResponse
        {
            // Find clients
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
            $this->WhoisCommand = new WhoisCommand($this->telegram, $this->update);
            $this->WhoisCommand->findClients();

            // Tally DeepAnalytics
            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'predictions_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->WhoisCommand->ChatObject->ID);
            $DeepAnalytics->tally('tg_spam_protection', 'predictions_command', (int)$this->WhoisCommand->ChatObject->ID);

            // Ignore forwarded commands
            if($this->getMessage()->getForwardFrom() !== null || $this->getMessage()->getForwardFromChat())
            {
                return Request::emptyResponse();
            }

            $UserStatus = SettingsManager::getUserStatus($this->WhoisCommand->UserClient);
            if($UserStatus->IsOperator == false && $UserStatus->IsAgent == false)
            {
                return Request::emptyResponse();
            }

            if($this->getMessage()->getReplyToMessage() !== null)
            {
                if($this->getMessage()->getReplyToMessage()->getText(false) !== null && strlen($this->getMessage()->getReplyToMessage()->getText(false)) > 0)
                {
                    $CoffeeHouse = SpamProtectionBot::getCoffeeHouse();

                    try
                    {
                        $LangResults = $CoffeeHouse->getLanguagePrediction()->predict(
                            $this->getMessage()->getReplyToMessage()->getText(false),
                            true, true, true, true
                        );
                    }
                    catch(Exception $e)
                    {
                        $LangResults = null;
                    }

                    try
                    {
                        $SpamResults = $CoffeeHouse->getSpamPrediction()->predict(
                            $this->getMessage()->getReplyToMessage()->getText(false), false
                        );
                    }
                    catch(Exception $e)
                    {
                        $SpamResults = null;
                    }

                    $ReturnMessage = "Value Predictions\n\n";

                    if($LangResults !== null)
                    {
                        $CombinedResults = $LangResults->combineResults();

                        $ReturnMessage .= "LangDetect CLD: " . strtoupper($LangResults->CLD_Results[0]->Language) . " (<code>" . ($LangResults->CLD_Results[0]->Probability * 100) . "</code>)\n";
                        $ReturnMessage .= "LangDetect LD: " . strtoupper($LangResults->LD_Results[0]->Language) . " (<code>" . ($LangResults->LD_Results[0]->Probability * 100) . "</code>)\n";
                        $ReturnMessage .= "LangDetect DLTC: " . strtoupper($LangResults->DLTC_Results[0]->Language) . " (<code>" . ($LangResults->DLTC_Results[0]->Probability * 100) . "</code>)\n";
                        $ReturnMessage .= "LangDetect Combined: " . strtoupper($CombinedResults[0]->Language) . " (<code>" . ($CombinedResults[0]->Probability * 100) . "</code>)\n\n";
                    }

                    if($SpamResults !== null)
                    {
                        $IsSpam = "False";

                        if($SpamResults->isSpam())
                        {
                            $IsSpam = "True";
                        }

                        $ReturnMessage .= "SpamDetect Ham: <code>" . ($SpamResults->HamPrediction * 100) . "</code>\n";
                        $ReturnMessage .= "SpamDetect Spam:<code> " . ($SpamResults->SpamPrediction * 100) . "</code>\n";
                        $ReturnMessage .= "SpamDetect IsSpam: <code>" . $IsSpam . "</code>";
                    }

                    /** @var Message $Message */
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "parse_mode" => "html",
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "text" => $ReturnMessage
                    ]);
                }
            }


            return self::displayUsage($this->getMessage());
        }

        /**
         * Displays the command usage
         *
         * @param Message $message
         * @param string $error
         * @return ServerResponse
         * @throws TelegramException
         */
        public function displayUsage(Message $message, string $error="Missing parameter"): ServerResponse
        {
            return Request::sendMessage([
                "chat_id" => $message->getChat()->getId(),
                "parse_mode" => "html",
                "reply_to_message_id" => $message->getMessageId(),
                "text" =>
                    "$error\n\n" .
                    "Usage:\n" .
                    "   <b>/predictions</b> [Reply to message]\n".
                    "For further instructions, send /help predictions"
            ]);
        }
    }