<?php


    namespace SpamProtection\Exceptions;


    use Exception;
    use Throwable;

    /**
     * Class TemporaryVerificationCodeExpiredException
     * @package SpamProtection\Exceptions
     */
    class TemporaryVerificationCodeExpiredException extends Exception
    {
        /**
         * TemporaryVerificationCodeExpiredException constructor.
         * @param string $message
         * @param int $code
         * @param Throwable|null $previous
         */
        public function __construct($message = "", $code = 0, Throwable $previous = null)
        {
            parent::__construct($message, $code, $previous);
        }
    }