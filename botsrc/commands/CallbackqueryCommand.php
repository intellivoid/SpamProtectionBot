<?php

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use Longman\TelegramBot\Commands\SystemCommand;
    use Longman\TelegramBot\Commands\UserCommands\SettingsCommand;
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

            switch(mb_substr($this->getCallbackQuery()->getData(), 0, 2))
            {
                case "01":
                    $SettingsCommand = new SettingsCommand($this->telegram, $this->update);
                    return $SettingsCommand->handleCallbackQuery($this->getCallbackQuery());

                default:
                    return $this->getCallbackQuery()->answer([
                        "text" => "This query isn't understood, are you using an official client? (Got '" . mb_substr($this->getCallbackQuery()->getData(), 0, 2) . "')",
                        "show_alert" => true
                    ]);
            }
        }
    }