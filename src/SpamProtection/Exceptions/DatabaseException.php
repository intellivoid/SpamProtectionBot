<?php


    namespace SpamProtection\Exceptions;

    use Exception;

    /**
     * Class DatabaseException
     * @package SpamProtection\Exceptions
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
        private $error;

        /**
         * DatabaseException constructor.
         * @param string $query
         * @param string $error
         */
        public function __construct(string $query, string $error)
        {
            $this->query = $query;
            $this->error = $error;
            parent::__construct('There was an exception with the Database Operation',  0, null);
        }

        /**
         * @return string
         */
        public function getQuery(): string
        {
            return $this->query;
        }

        /**
         * @return string
         */
        public function getError(): string
        {
            return $this->error;
        }

    }