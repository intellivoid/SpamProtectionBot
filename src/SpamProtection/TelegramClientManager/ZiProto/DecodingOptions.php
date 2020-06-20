<?php
    namespace ZiProto;

    use ZiProto\Exception\InvalidOptionException;
    use ZiProto\Abstracts\Options;

    /**
     * Class DecodingOptions
     * @package ZiProto
     */
    final class DecodingOptions
    {

        /**
         * @var
         */
        private $bigIntMode;

        /**
         * DecodingOptions constructor.
         */
        private function __construct() {}

        /**
         * @return DecodingOptions
         */
        public static function fromDefaults() : self
        {
            $self = new self();
            $self->bigIntMode = Options::BIGINT_AS_STR;

            return $self;
        }

        /**
         * @param int $bitmask
         * @return DecodingOptions
         */
        public static function fromBitmask(int $bitmask) : self
        {
            $self = new self();

            $self->bigIntMode = self::getSingleOption('bigint', $bitmask,
                Options::BIGINT_AS_STR |
                Options::BIGINT_AS_GMP |
                Options::BIGINT_AS_EXCEPTION
            ) ?: Options::BIGINT_AS_STR;

            return $self;
        }

        /**
         * @return bool
         */
        public function isBigIntAsStrMode() : bool
        {
            return Options::BIGINT_AS_STR === $this->bigIntMode;
        }

        /**
         * @return bool
         */
        public function isBigIntAsGmpMode() : bool
        {
            return Options::BIGINT_AS_GMP === $this->bigIntMode;
        }

        /**
         * @param string $name
         * @param int $bitmask
         * @param int $validBitmask
         * @return int
         */
        private static function getSingleOption(string $name, int $bitmask, int $validBitmask) : int
        {
            $option = $bitmask & $validBitmask;
            if ($option === ($option & -$option))
            {
                return $option;
            }

            static $map = [
                Options::BIGINT_AS_STR => 'BIGINT_AS_STR',
                Options::BIGINT_AS_GMP => 'BIGINT_AS_GMP',
                Options::BIGINT_AS_EXCEPTION => 'BIGINT_AS_EXCEPTION',
            ];

            $validOptions = [];
            for ($i = $validBitmask & -$validBitmask; $i <= $validBitmask; $i <<= 1)
            {
                $validOptions[] = __CLASS__.'::'.$map[$i];
            }

            throw InvalidOptionException::outOfRange($name, $validOptions);
        }
    }
