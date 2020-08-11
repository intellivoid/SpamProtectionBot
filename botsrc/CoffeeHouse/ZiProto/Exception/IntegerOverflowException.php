<?php

    namespace ZiProto\Exception;

    use function sprintf;

    /**
     * Class IntegerOverflowException
     * @package ZiProto\Exception
     */
    class IntegerOverflowException extends DecodingFailedException
    {
        /**
         * @var int
         */
        private $value;

        /**
         * IntegerOverflowException constructor.
         * @param int $value
         */
        public function __construct(int $value)
        {
            parent::__construct(sprintf('The value is too big: %u.', $value));
            $this->value = $value;
        }

        /**
         * @return int
         */
        public function getValue() : int
        {
            return $this->value;
        }
    }