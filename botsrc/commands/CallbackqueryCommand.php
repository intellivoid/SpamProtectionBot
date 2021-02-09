<?php

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use Longman\TelegramBot\Commands\SystemCommand;
    use Longman\TelegramBot\Commands\UserCommands\LanguageCommand;
    use Longman\TelegramBot\Commands\UserCommands\SettingsCommand;
    use Longman\TelegramBot\Commands\UserCommands\WhoisCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Request;

    class CallbackqueryCommand extends SystemCommand
    {
        /**
         * @var string
         */
        protected $name = 'callbackquery';

        /**
         * @var string
         */
        protected $description = 'Handle the callback query';

        /**
         * @var string
         */
        protected $version = '1.0.0';

        /**
         * The whois command used for finding targets
         *
         * @var WhoisCommand|null
         */
        public $WhoisCommand = null;

        /**
         * Main command execution
         *
         * @return ServerResponse
         * @throws \Exception
         */
        public function execute(): ?ServerResponse
        {
            // Callback query data can be fetched and handled accordingly.
            if($this->getCallbackQuery()->getMessage() == null)
                return $this->getCallbackQuery()->answer([
                    "text" => "This message is too old to be processed, please try running your query again.",
                    "show_alert" => true
                ]);

            if($this->WhoisCommand == null)
            {
                $this->WhoisCommand = new WhoisCommand($this->telegram, $this->update);
            }

            $this->WhoisCommand->findCallbackClients($this->getCallbackQuery());


            switch(mb_substr($this->getCallbackQuery()->getData(), 0, 2))
            {
                case "01":
                    $SettingsCommand = new SettingsCommand($this->telegram, $this->update);
                    return $SettingsCommand->handleCallbackQuery($this->getCallbackQuery());

                case "02":
                    Request::deleteMessage([
                        "chat_id" => $this->getCallbackQuery()->getMessage()->getChat()->getId(),
                        "message_id" => $this->getCallbackQuery()->getMessage()->getMessageId()
                    ]);

                    $LanguageCommand = new LanguageCommand($this->telegram, $this->update);
                    return $LanguageCommand->handleUserLanguageChange($this->getCallbackQuery(), $this->WhoisCommand, false);

                case "12":
                    $LanguageCommand = new LanguageCommand($this->telegram, $this->update);
                    return $LanguageCommand->handleUserLanguageChange($this->getCallbackQuery(), $this->WhoisCommand, true);

                case "03": // Close the current language dialog
                    return Request::deleteMessage([
                        "chat_id" => $this->getCallbackQuery()->getMessage()->getChat()->getId(),
                        "message_id" => $this->getCallbackQuery()->getMessage()->getMessageId()
                    ]);

                default:
                    return $this->getCallbackQuery()->answer([
                        "text" => "This query isn't understood, are you using an official client? (Got '" . mb_substr($this->getCallbackQuery()->getData(), 0, 2) . "')",
                        "show_alert" => true
                    ]);
            }
        }
    }