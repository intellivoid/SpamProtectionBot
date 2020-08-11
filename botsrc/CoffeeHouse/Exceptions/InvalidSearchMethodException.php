<?php


    namespace CoffeeHouse\Exceptions;

    use CoffeeHouse\Abstracts\ExceptionCodes;
    use Exception;

    /**
     * Class InvalidSearchMethodException
     * @package CoffeeHouse\Exceptions
     */
    class InvalidSearchMethodException extends Exception
    {
        /**
         * InvalidSearchMethodException constructor.
         */
        public function __construct()
        {
            parent::__construct("The given search method is invalid for this function", ExceptionCodes::InvalidSearchMethodException, null);
        }
    }