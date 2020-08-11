<?php


    namespace TelegramClientManager\Exceptions;

    use Exception;

    /**
     * Class InvalidSearchMethod
     * @package TelegramClientManager\Exceptions
     */
    class InvalidSearchMethod extends Exception
    {
        /**
         * InvalidSearchMethod constructor.
         */
        public function __construct()
        {
            parent::__construct("The given search method is invalid for this method", 0, null);
        }
    }