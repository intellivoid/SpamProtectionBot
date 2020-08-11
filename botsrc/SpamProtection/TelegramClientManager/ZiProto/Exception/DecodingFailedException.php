<?php

    namespace ZiProto\Exception;

    use RuntimeException;
    use function sprintf;

    /**
     * Class DecodingFailedException
     * @package ZiProto\Exception
     */
    class DecodingFailedException extends RuntimeException
    {
        /**
         * @param int $code
         * @return DecodingFailedException
         */
        public static function unknownCode(int $code) : self
        {
            return new self(sprintf('Unknown code: 0x%x.', $code));
        }

        /**
         * @param int $code
         * @param string $type
         * @return DecodingFailedException
         */
        public static function unexpectedCode(int $code, string $type) : self
        {
            return new self(sprintf('Unexpected %s code: 0x%x.', $type, $code));
        }
    }