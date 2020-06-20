<?php


    namespace TelegramClientManager\Exceptions;

    use Exception;

    /**
     * Class TelegramClientNotFoundException
     * @package TelegramClientManager\Exceptions
     */
    class TelegramClientNotFoundException extends Exception
    {
        /**
         * TelegramClientNotFoundException constructor.
         */
        public function __construct()
        {
            parent::__construct("The Telegram Client was not found in the database", 0, null);
        }
    }