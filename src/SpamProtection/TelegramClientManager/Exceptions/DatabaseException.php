<?php


    namespace TelegramClientManager\Exceptions;

    use Exception;

    /**
     * Class DatabaseException
     * @package TelegramClientManager\Exceptions
     */
    class DatabaseException extends Exception
    {
        /**
         * @var string
         */
        private $query;
        /**
         * @var string
         */
        private $database_error;

        /**
         * DatabaseException constructor.
         * @param string $query
         * @param string $database_error
         */
        public function __construct(string $query, string $database_error)
        {
            parent::__construct("There was a unexpected Database Error", 0, null);
            $this->query = $query;
            $this->database_error = $database_error;
        }
    }