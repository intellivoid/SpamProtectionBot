<?php


    namespace CoffeeHouse\Exceptions;


    use CoffeeHouse\Abstracts\ExceptionCodes;
    use Exception;

    /**
     * Class InvalidServerInterfaceModuleException
     * @package CoffeeHouse\Exceptions
     */
    class InvalidServerInterfaceModuleException extends Exception
    {
        /**
         * InvalidServerInterfaceModuleException constructor.
         */
        public function __construct()
        {
            parent::__construct("The given server interface module is not supported by this version of CoffeeHouse", ExceptionCodes::InvalidServerInterfaceModuleException, null);
        }
    }