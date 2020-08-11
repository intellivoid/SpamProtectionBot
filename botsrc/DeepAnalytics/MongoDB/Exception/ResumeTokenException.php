<?php


    namespace MongoDB\Exception;

    use function gettype;
    use function sprintf;

    /**
     * Class ResumeTokenException
     * @package MongoDB\Exception
     */
    class ResumeTokenException extends RuntimeException
    {
        /**
         * Thrown when a resume token has an invalid type.
         *
         * @param mixed $value Actual value (used to derive the type)
         * @return self
         */
        public static function invalidType($value)
        {
            return new static(sprintf('Expected resume token to have type "array or object" but found "%s"', gettype($value)));
        }

        /**
         * Thrown when a resume token is not found in a change document.
         *
         * @return self
         */
        public static function notFound()
        {
            return new static('Resume token not found in change document');
        }
    }
