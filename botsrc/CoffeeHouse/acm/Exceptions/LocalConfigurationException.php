<?php


    namespace acm\Exceptions;

    use acm\Abstracts\ExceptionCodes;
    use Exception;

    /**
     * Class LocalConfigurationException
     * @package acm\Exceptions
     */
    class LocalConfigurationException extends Exception
    {
        /**
         * LocalConfigurationException constructor.
         */
        public function __construct()
        {
            parent::__construct('This configuration was not found in the local configuration', ExceptionCodes::LocalConfigurationException, null);
        }
    }