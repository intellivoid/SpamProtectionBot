<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use Exception;
    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Commands\UserCommands\WhoisCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtectionBot;
    use TelegramClientManager\Abstracts\TelegramChatType;

    /**
     * Help command
     *
     * Gets executed when a user first starts using the bot.
     */
    class HelpCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'help';

        /**
         * @var string
         */
        protected $description = 'Displays the help menu for the general usage of the bot';

        /**
         * @var string
         */
        protected $usage = '/help';

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
            // Find clients
            $this->WhoisCommand = new WhoisCommand($this->telegram, $this->update);
            $this->WhoisCommand->findClients();

            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'help_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->WhoisCommand->ChatObject->ID);
            $DeepAnalytics->tally('tg_spam_protection', 'help_command', (int)$this->WhoisCommand->ChatObject->ID);

            // Ignore forwarded commands
            if($this->getMessage()->getForwardFrom() !== null || $this->getMessage()->getForwardFromChat())
            {
                return null;
            }

            if($this->getMessage()->getChat()->getType() !== TelegramChatType::Private)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => "This command can only be used in private!"
                ]);
            }

            $CommandParameters = explode(" ", $this->getMessage()->getText(true));
            $CommandParameters = array_values(array_filter($CommandParameters, 'strlen'));

            if(count($CommandParameters) > 0)
            {
                $HelpRequestDoc = str_ireplace("/", "", strtolower($CommandParameters[0]));
                $HelpRequestDocSafe = str_ireplace('/', '_', $HelpRequestDoc);
                $HelpRequestDocSafe = str_ireplace('\\', '_', $HelpRequestDocSafe);
                $HelpDocumentsPath = __DIR__ . DIRECTORY_SEPARATOR . "help_docs";
                $HelpDocument = $HelpDocumentsPath . DIRECTORY_SEPARATOR . $HelpRequestDocSafe . ".html";

                if(file_exists($HelpDocument) == false)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "There is not help available for <code>" . $HelpRequestDoc . "</code>"
                    ]);
                }
                else
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "disable_web_page_preview" => true,
                        "text" => file_get_contents($HelpDocument)
                    ]);
                }
            }
            else
            {
                $HelpDocumentsPath = __DIR__ . DIRECTORY_SEPARATOR . "help_docs";
                $HelpDocument = $HelpDocumentsPath . DIRECTORY_SEPARATOR . "help.html";

                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "disable_web_page_preview" => true,
                    "text" => file_get_contents($HelpDocument)
                ]);
            }
        }
    }