<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\UserCommands;

    use Exception;
    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Exceptions\InvalidSearchMethodException;
    use SpamProtection\Exceptions\NoPoolCurrentlyActiveExceptions;
    use SpamProtection\Exceptions\ReportBuildAlreadyInProgressException;
    use SpamProtection\Exceptions\VotingPoolAlreadyCompletedException;
    use SpamProtection\Exceptions\VotingPoolCurrentlyActiveException;
    use SpamProtectionBot;
    use TelegramClientManager\Abstracts\SearchMethods\TelegramClientSearchMethod;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;
    use VerboseAdventure\Abstracts\EventType;

    /**
     * Reset Predictions Command
     *
     * Allows an operator or agent to reset the current prediction values for a user, chat or channel.
     */
    class FinalVerdictCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'FinalVerdictCommand';

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
         * @throws InvalidSearchMethodException
         * @throws NoPoolCurrentlyActiveExceptions
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @throws VotingPoolCurrentlyActiveException
         * @throws \SpamProtection\Exceptions\DatabaseException
         * @throws ReportBuildAlreadyInProgressException
         * @throws VotingPoolAlreadyCompletedException
         * @noinspection DuplicatedCode
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
            $DeepAnalytics->tally('tg_spam_protection', 'final_verdict_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->WhoisCommand->ChatObject->ID);
            $DeepAnalytics->tally('tg_spam_protection', 'final_verdict_command', (int)$this->WhoisCommand->ChatObject->ID);

            // Ignore forwarded commands
            if($this->getMessage()->getForwardFrom() !== null || $this->getMessage()->getForwardFromChat())
            {
                return Request::emptyResponse();
            }

            if(!in_array($this->WhoisCommand->UserClient->User->ID, MAIN_OPERATOR_IDS, true))
            {
                return Request::emptyResponse();
            }

            Request::sendMessage([
                "chat_id" => $this->getMessage()->getChat()->getId(),
                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                "text" => "Processing final verdict, this may take a while."
            ]);

            $this->processFinalVerdict();

            return Request::sendMessage([
                "chat_id" => $this->getMessage()->getChat()->getId(),
                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                "text" => "Completed"
            ]);
        }

        /**
         * Processes the final verdict and privately sends the dataset to the main operator
         *
         * @return ServerResponse
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws InvalidSearchMethodException
         * @throws NoPoolCurrentlyActiveExceptions
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @throws VotingPoolCurrentlyActiveException
         * @throws \SpamProtection\Exceptions\DatabaseException
         * @throws ReportBuildAlreadyInProgressException
         * @throws VotingPoolAlreadyCompletedException
         */
        public function processFinalVerdict(): ServerResponse
        {
            $VotesDueRecord = SpamProtectionBot::getSpamProtection()->getVotesDueManager()->getCurrentPool(false);

            // Finalize the verdict and get the results!
            $FinalResults = SpamProtectionBot::getSpamProtection()->getVotesDueManager()->finalizeResults(
                $VotesDueRecord, SpamProtectionBot::getTelegramClientManager()
            );

            if($FinalResults == null)
            {
                return Request::emptyResponse();
            }

            $ShowContributors = true;
            $TopContributors = "Top " . count($FinalResults->TopUsers) . " contributors\n\n";

            if(count($FinalResults->TopUsers) > 0)
            {
                $CurrentCount = 1;
                foreach($FinalResults->TopUsers as $user_id => $reputation_points_gained)
                {
                    $UserClient = SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->getClient(
                        TelegramClientSearchMethod::byId, $user_id
                    );

                    $TopContributors .= " <b>$CurrentCount</b>. <i>" . WhoisCommand::generatePrivateMention($UserClient) . "</i> (<code>+" . $reputation_points_gained . "</code>)\n";
                    $CurrentCount += 1;
                }
            }
            else
            {
                $ShowContributors = false;
            }

            // Calculate the accuracy
            $Accuracy = ($FinalResults->SpamCount / ($FinalResults->SpamCount + $FinalResults->HamCount)) * 100;

            $LogMessage = "#final_verdict\n\n";
            $LogMessage .= "<b>Voting Pool ID:</b> <code>" . hash("crc32b", $VotesDueRecord->ID) . "</code>\n";
            $LogMessage .= "<b>Voting Pool Size:</b> <code>" . $FinalResults->VotingRecordsCount . "</code>\n";
            $LogMessage .= "<b>User Votes:</b> <code>" . ($FinalResults->UserYayVotes + $FinalResults->UserNayVotes + $FinalResults->TieVotes) . "</code>\n";
            $LogMessage .= "<b>CPU Votes:</b> <code>" . ($FinalResults->CpuYayVotes + $FinalResults->CpuNayVotes) . "</code>\n";
            $LogMessage .= "<b>Tied Votes:</b> <code>" . ($FinalResults->TieVotes) . "</code>\n";
            $LogMessage .= "<b>Correct Predictions:</b> <code>" . ($FinalResults->SpamCount) . "</code>\n";
            $LogMessage .= "<b>Incorrect Predictions:</b> <code>" . ($FinalResults->HamCount) . "</code>\n";
            $LogMessage .= "<b>Failed Counts:</b> <code>" . ($FinalResults->VotingRecordsFailureCount) . "</code>\n";
            $LogMessage .= "<b>Model Accuracy:</b> <code>" . $Accuracy . "%</code>\n\n";

            if($ShowContributors)
            {
                $LogMessage .= $TopContributors . "\n";
                $LogMessage .= "Reputation points has been distributed among the contributors";
            }

            $LogMessageResults = Request::sendMessage([
                "chat_id" => "@" . LOG_CHANNEL,
                "disable_web_page_preview" => true,
                "disable_notification" => true,
                "parse_mode" => "html",
                "text" => $LogMessage
            ]);

            try
            {
                foreach (MAIN_OPERATOR_IDS as $MainOperatorID)
                {
                    $MainOperator = SpamProtectionBot::getTelegramClientManager()->getTelegramClientManager()->getClient(
                        TelegramClientSearchMethod::byUserId, $MainOperatorID
                    );

                    if($FinalResults->HamDatasetPath !== null)
                    {
                        Request::sendDocument([
                            "chat_id" => $MainOperator->Chat->ID,
                            "document" => $FinalResults->HamDatasetPath,
                            "caption" => hash("crc32b", $VotesDueRecord->ID)
                        ]);
                    }

                    if($FinalResults->SpamDatasetPath !== null)
                    {
                        Request::sendDocument([
                            "chat_id" => $MainOperator->Chat->ID,
                            "document" => $FinalResults->SpamDatasetPath,
                            "caption" => hash("crc32b", $VotesDueRecord->ID)
                        ]);
                    }
                }

                if($FinalResults->SpamDatasetPath !== null)
                {
                    unlink($FinalResults->SpamDatasetPath);
                }

                if($FinalResults->HamDatasetPath !== null)
                {
                    unlink($FinalResults->HamDatasetPath);
                }
            }
            catch(Exception $e)
            {
                SpamProtectionBot::getLogHandler()->log(EventType::WARNING, "There was an error while trying to process the final verdict data dump", "processFinalVerdict");
                SpamProtectionBot::getLogHandler()->logException($e, "processFinalVerdict");
            }

            return $LogMessageResults;
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