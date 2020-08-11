<?php


    namespace CoffeeHouse\Exceptions;


    use CoffeeHouse\Abstracts\ExceptionCodes;
    use Exception;

    /**
     * Class DatabaseException
     * @package CoffeeHouse\Exceptions
     */
    class DatabaseException extends Exception
    {

        /**
         * @var string
         */
        private $DatabaseError;

        /**
         * DatabaseException constructor.
         * @param string $DatabaseError
         */
        public function __construct(string $DatabaseError)
        {
            parent::__construct(
                sprintf('There was an internal Database error'),
                ExceptionCodes::DatabaseException
            );
            $this->DatabaseError = $DatabaseError;
        }

        /**
         * @return string
         */
        public function getDatabaseError(): string
        {
            return($this->DatabaseError);
        }
    }