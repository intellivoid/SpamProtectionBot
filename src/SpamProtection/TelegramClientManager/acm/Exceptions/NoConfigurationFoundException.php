<?php


    namespace acm\Exceptions;


    use acm\Abstracts\ExceptionCodes;
    use Exception;

    /**
     * Class NoConfigurationFoundException
     * @package acm\Exceptions
     */
    class NoConfigurationFoundException extends Exception
    {
        /**
         * NoConfigurationFoundException constructor.
         */
        public function __construct()
        {
            parent::__construct('No local / master configuration found', ExceptionCodes::NoConfigurationFoundException, null);
        }

    }