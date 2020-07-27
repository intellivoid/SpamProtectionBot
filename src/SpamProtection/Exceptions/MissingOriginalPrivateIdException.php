<?php


    namespace SpamProtection\Exceptions;


    use Exception;
    use Throwable;

    /**
     * Class MissingOriginalPrivateIdException
     * @package SpamProtection\Exceptions
     */
    class MissingOriginalPrivateIdException extends Exception
    {
        /**
         * MissingOriginalPrivateIdException constructor.
         * @param string $message
         * @param int $code
         * @param Throwable|null $previous
         */
        public function __construct($message = "", $code = 0, Throwable $previous = null)
        {
            parent::__construct($message, $code, $previous);
        }
    }