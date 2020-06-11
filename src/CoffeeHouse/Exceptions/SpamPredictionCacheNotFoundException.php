<?php


    namespace CoffeeHouse\Exceptions;


    use CoffeeHouse\Abstracts\ExceptionCodes;
    use Exception;

    /**
     * Class SpamPredictionCacheNotFoundException
     * @package CoffeeHouse\Exceptions
     */
    class SpamPredictionCacheNotFoundException extends Exception
    {
        /**
         * SpamPredictionCacheNotFoundException constructor.
         */
        public function __construct()
        {
            parent::__construct("The spam prediction cache record was not found", ExceptionCodes::SpamPredictionCacheNotFoundException, null);
        }
    }