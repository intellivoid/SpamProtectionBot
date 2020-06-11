<?php


    namespace CoffeeHouse\Exceptions;


    use CoffeeHouse\Abstracts\ExceptionCodes;
    use Exception;

    /**
     * Class GeneralizedClassificationLimitException
     * @package CoffeeHouse\Exceptions
     */
    class GeneralizedClassificationLimitException extends Exception
    {
        /**
         * GeneralizedClassificationLimitException constructor.
         */
        public function __construct()
        {
            parent::__construct("The generalized classifier has reached it's size limit, use the overwrite option to reset the current pointer", ExceptionCodes::GeneralizedClassificationLimitException, null);
        }
    }