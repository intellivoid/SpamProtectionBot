<?php


    namespace CoffeeHouse\Exceptions;


    use CoffeeHouse\Abstracts\ExceptionCodes;
    use Exception;

    /**
     * Class InvalidMessageException
     * @package CoffeeHouse\Exceptions
     */
    class InvalidMessageException extends Exception
    {
        /**
         * InvalidMessageException constructor.
         */
        public function __construct()
        {
            parent::__construct('The given message cannot be greater than 5000 characters', ExceptionCodes::InvalidMessageException, null);
        }
    }