<?php

    namespace ZiProto\Exception;

    use function strlen;

    /**
     * Class InsufficientDataException
     * @package ZiProto\Exception
     */
    class InsufficientDataException extends DecodingFailedException
    {
        /**
         * @param string $buffer
         * @param int $offset
         * @param int $expectedLength
         * @return InsufficientDataException
         */
        public static function unexpectedLength(string $buffer, int $offset, int $expectedLength) : self
        {
            $actualLength = strlen($buffer) - $offset;
            $message = "Not enough data to unpack: expected $expectedLength, got $actualLength.";
            return new self($message);
        }
    }