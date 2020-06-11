<?php


    namespace DeepAnalytics\Exceptions;

    use Exception;
    use Throwable;

    /**
     * Class DataNotFoundException
     * @package DeepAnalytics\Exceptions
     */
    class DataNotFoundException extends Exception
    {
        /**
         * DataNotFoundException constructor.
         * @param string $message
         * @param int $code
         * @param Throwable|null $previous
         */
        public function __construct($message = "", $code = 0, Throwable $previous = null)
        {
            parent::__construct($message, $code, $previous);
        }
    }