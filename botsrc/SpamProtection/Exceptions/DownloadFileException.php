<?php


    namespace SpamProtection\Exceptions;


    use Exception;

    /**
     * Class DownloadFileException
     * @package SpamProtection\Exceptions
     */
    class DownloadFileException extends Exception
    {
        /**
         * @var string
         */
        private $error_details;
        /**
         * @var string
         */
        private $address;

        /**
         * DownloadFileException constructor.
         * @param string $error_details
         * @param string $address
         */
        public function __construct(string $error_details, string $address)
        {
            parent::__construct("There was an unexpected error while contacting the server interface", 0, null);
            $this->error_details = $error_details;
            $this->address = $address;
        }
    }