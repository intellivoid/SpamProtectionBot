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
            $DeepAnalytics->tally('tg_spam_protection', 'predictions_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->WhoisCommand->ChatObject->ID);
            $DeepAnalytics->tally('tg_spam_protection', 'predictions_command', (int)$this->WhoisCommand->ChatObject->ID);

            // Ignore forwarded commands
            if($this->getMessage()->getForwardFrom() !== null || $this->getMessage()->getForwardFromChat())
            {
                return null;
            }

            if($this->getMessage()->getReplyToMessage() !== null)
            {
                if($this->getMessage()->getReplyToMessage()->getText(false) !== null && strlen($this->getMessage()->getReplyToMessage()->getText(false)) > 0)
                {
                    /** @var Message $Message */
                    $Message = Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "parse_mode" => "html",
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "text" => "Processing message"
                    ]);

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

                        $ReturnMessage .= "LangDetect CLD: " . $LangResults->CLD_Results[0]->Language . " (" . ($LangResults->CLD_Results[0]->Probability * 100) . ")\n";
                        $ReturnMessage .= "LangDetect LD: " . $LangResults->LD_Results[0]->Language . " (" . ($LangResults->LD_Results[0]->Probability * 100) . ")\n";
                        $ReturnMessage .= "LangDetect DLTC: " . $LangResults->DLTC_Results[0]->Language . " (" . ($LangResults->DLTC_Results[0]->Probability * 100) . ")\n";
                        $ReturnMessage .= "LangDetect Combined: " . $CombinedResults[0]->Language . " (" . ($CombinedResults[0]->Probability * 100) . ")\n\n";
                    }

                    if($SpamResults !== null)
                    {
                        $ReturnMessage .= "SpamDetect Ham: " . ($SpamResults->HamPrediction * 100) . "\n";
                        $ReturnMessage .= "SpamDetect Spam: " . ($SpamResults->SpamPrediction * 100) . "\n";
                        $ReturnMessage .= "SpamDetect IsSpam: " . $SpamResults->isSpam();
                    }

                    return Request::editMessageText([
                        "chat_id" => $Message->getChat()->getId(),
                        "message_id" => $Message->getMessageId(),
                        "parse_mode" => "html",
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
                    "Usage:\n" .
                    "   <b>/predictions</b> [Reply to message]\n".
                    "For further instructions, send /help predictions"
            ]);
        }
    }