<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\UserCommands;

    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;

    /**
     * Log message command alias
     */
    class LCommand extends UserCommand
    {
        /**
         * @var bool
         */
        protected $private_only = false;

        /**
         * Command execute method
         *
         * @return ServerResponse|null
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @noinspection DuplicatedCode
         */
        public function execute()
        {
            $LogCommand = new LogCommand($this->telegram, $this->update);
            return $LogCommand->execute();
        }

    }