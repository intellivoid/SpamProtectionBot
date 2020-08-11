<?php


    namespace CoffeeHouse\Exceptions;


    use CoffeeHouse\Abstracts\ExceptionCodes;
    use Exception;

    /**
     * Class ServerInterfaceException
     * @package CoffeeHouse\Exceptions
     */
    class ServerInterfaceException extends Exception
    {
        /**
         * Error details returned from the client
         *
         * @var string
         */
        private $error_details;

        /**
         * The address the request was sent to
         *
         * @var string
         */
        private $address;

        /**
         * The parameters sent
         *
         * @var array
         */
        private $parameters;

        /**
         * ServerInterfaceException constructor.
         * @param string $error_details
         * @param string $address
         * @param array $parameters
         */
        public function __construct(string $error_details, string $address, array $parameters)
        {
            parent::__construct("There was an unexpected error while contacting the server interface", ExceptionCodes::ServerInterfaceException, null);
            $this->error_details = $error_details;
            $this->address = $address;
            $this->parameters = $parameters;
        }
    }