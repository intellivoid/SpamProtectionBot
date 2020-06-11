<?php


    namespace CoffeeHouse\Exceptions;


    use CoffeeHouse\Abstracts\ExceptionCodes;
    use Exception;

    /**
     * Class UserSubscriptionNotFoundException
     * @package CoffeeHouse\Exceptions
     */
    class UserSubscriptionNotFoundException extends Exception
    {
        /**
         * UserSubscriptionNotFoundException constructor.
         */
        public function __construct()
        {
            parent::__construct("The user subscription was not found in the database", ExceptionCodes::UserSubscriptionNotFoundException, null);
        }


    }