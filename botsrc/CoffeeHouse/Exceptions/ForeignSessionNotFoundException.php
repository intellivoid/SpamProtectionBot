<?php


    namespace CoffeeHouse\Exceptions;


    use CoffeeHouse\Abstracts\ExceptionCodes;
    use Exception;

    /**
     * Class ForeignSessionNotFoundException
     * @package CoffeeHouse\Exceptions
     */
    class ForeignSessionNotFoundException extends Exception
    {
        /**
         * ForeignSessionNotFoundException constructor.
         */
        public function __construct()
        {
            parent::__construct('The foreign session was not found in the database', ExceptionCodes::ForeignSessionNotFoundException, null);
        }
    }