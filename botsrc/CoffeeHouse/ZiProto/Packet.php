<?php

    namespace ZiProto;

    use function array_values;
    use function chr;
    use function count;
    use function is_array;
    use function is_bool;
    use function is_float;
    use function is_int;
    use function is_string;
    use function pack;
    use function preg_match;
    use function strlen;
    use ZiProto\Abstracts\Regex;
    use ZiProto\Exception\InvalidOptionException;
    use ZiProto\Exception\EncodingFailedException;
    use ZiProto\TypeTransformer\Validator;

    /**
     * Class Packet
     * @package ZiProto
     */
    class Packet
    {
        /**
         * @var bool
         */
        private $isDetectStrBin;

        /**
         * @var bool
         */
        private $isForceStr;

        /**
         * @var bool
         */
        private $isDetectArrMap;

        /**
         * @var bool
         */
        private $isForceArr;

        /**
         * @var bool
         */
        private $isForceFloat32;

        /**
         * @var Validator[]|null
         */
        private $transformers;

        /**
         * @param EncodingOptions|int|null $options
         *
         * @throws InvalidOptionException
         */
        public function __construct($options = null)
        {
            if (null === $options)
            {
                $options = EncodingOptions::fromDefaults();
            }
            elseif (!$options instanceof EncodingOptions)
            {
                $options = EncodingOptions::fromBitmask($options);
            }

            $this->isDetectStrBin = $options->isDetectStrBinMode();
            $this->isForceStr = $options->isForceStrMode();
            $this->isDetectArrMap = $options->isDetectArrMapMode();
            $this->isForceArr = $options->isForceArrMode();
            $this->isForceFloat32 = $options->isForceFloat32Mode();
        }

        /**
         * @param Validator $transformer
         * @return Packet
         */
        public function registerTransformer(Validator $transformer) : self
        {
            $this->transformers[] = $transformer;

            return $this;
        }

        /**
         * @param $value
         * @return false|string
         */
        public function encode($value)
        {
            if (is_int($value))
            {
                return $this->encodeInt($value);
            }

            if (is_string($value))
            {
                if ($this->isForceStr)
                {
                    return $this->encodeStr($value);
                }

                if ($this->isDetectStrBin)
                {
                    return preg_match(Regex::UTF8_REGEX, $value)
                        ? $this->encodeStr($value)
                        : $this->encodeBin($value);
                }

                return $this->encodeBin($value);
            }

            if (is_array($value))
            {
                if ($this->isDetectArrMap)
                {
                    return array_values($value) === $value
                        ? $this->encodeArray($value)
                        : $this->encodeMap($value);
                }

                return $this->isForceArr ? $this->encodeArray($value) : $this->encodeMap($value);
            }

            if (null === $value)
            {
                return "\xc0";
            }

            if (is_bool($value))
            {
                return $value ? "\xc3" : "\xc2";
            }

            if (is_float($value))
            {
                return $this->encodeFloat($value);
            }

            if ($value instanceof Ext)
            {
                return $this->encodeExt($value->type, $value->data);
            }

            if ($this->transformers)
            {
                foreach ($this->transformers as $transformer)
                {
                    if (null !== $encoded = $transformer->check($this, $value))
                    {
                        return $encoded;
                    }
                }
            }

            throw EncodingFailedException::unsupportedType($value);
        }

        /**
         * @return string
         */
        public function encodeNil()
        {
            return "\xc0";
        }

        /**
         * @param $bool
         * @return string
         */
        public function encodeBool($bool)
        {
            return $bool ? "\xc3" : "\xc2";
        }

        /**
         * @param $int
         * @return false|string
         */
        public function encodeInt($int)
        {
            if ($int >= 0)
            {
                if ($int <= 0x7f)
                {
                    return chr($int);
                }

                if ($int <= 0xff)
                {
                    return "\xcc". chr($int);
                }

                if ($int <= 0xffff)
                {
                    return "\xcd". chr($int >> 8). chr($int);
                }

                if ($int <= 0xffffffff)
                {
                    return pack('CN', 0xce, $int);
                }

                return pack('CJ', 0xcf, $int);
            }

            if ($int >= -0x20)
            {
                return chr(0xe0 | $int);
            }

            if ($int >= -0x80)
            {
                return "\xd0". chr($int);
            }

            if ($int >= -0x8000)
            {
                return "\xd1". chr($int >> 8). chr($int);
            }

            if ($int >= -0x80000000)
            {
                return pack('CN', 0xd2, $int);
            }

            return pack('CJ', 0xd3, $int);
        }

        /**
         * @param $float
         * @return string
         */
        public function encodeFloat($float)
        {
            return $this->isForceFloat32
                ? "\xca". pack('G', $float)
                : "\xcb". pack('E', $float);
        }

        /**
         * @param $str
         * @return string
         */
        public function encodeStr($str)
        {
            $length = strlen($str);

            if ($length < 32)
            {
                return chr(0xa0 | $length).$str;
            }

            if ($length <= 0xff)
            {
                return "\xd9". chr($length).$str;
            }

            if ($length <= 0xffff)
            {
                return "\xda". chr($length >> 8). chr($length).$str;
            }

            return pack('CN', 0xdb, $length).$str;
        }

        /**
         * @param $str
         * @return string
         */
        public function encodeBin($str)
        {
            $length = strlen($str);

            if ($length <= 0xff)
            {
                return "\xc4". chr($length).$str;
            }

            if ($length <= 0xffff)
            {
                return "\xc5". chr($length >> 8). chr($length).$str;
            }

            return pack('CN', 0xc6, $length).$str;
        }

        /**
         * @param $array
         * @return false|string
         */
        public function encodeArray($array)
        {
            $data = $this->encodeArrayHeader(count($array));

            foreach ($array as $val)
            {
                $data .= $this->encode($val);
            }

            return $data;
        }

        /**
         * @param $size
         * @return false|string
         */
        public function encodeArrayHeader($size)
        {
            if ($size <= 0xf)
            {
                return chr(0x90 | $size);
            }

            if ($size <= 0xffff)
            {
                return "\xdc". chr($size >> 8). chr($size);
            }

            return pack('CN', 0xdd, $size);
        }

        /**
         * @param $map
         * @return false|string
         */
        public function encodeMap($map)
        {
            $data = $this->encodeMapHeader(count($map));

            if ($this->isForceStr)
            {
                foreach ($map as $key => $val)
                {
                    $data .= is_string($key) ? $this->encodeStr($key) : $this->encodeInt($key);
                    $data .= $this->encode($val);
                }

                return $data;
            }

            if ($this->isDetectStrBin)
            {
                foreach ($map as $key => $val)
                {
                    $data .= is_string($key)
                        ? (preg_match(Regex::UTF8_REGEX, $key) ? $this->encodeStr($key) : $this->encodeBin($key))
                        : $this->encodeInt($key);
                    $data .= $this->encode($val);
                }

                return $data;
            }

            foreach ($map as $key => $val)
            {
                $data .= is_string($key) ? $this->encodeBin($key) : $this->encodeInt($key);
                $data .= $this->encode($val);
            }

            return $data;
        }

        /**
         * @param $size
         * @return false|string
         */
        public function encodeMapHeader($size)
        {
            if ($size <= 0xf)
            {
                return chr(0x80 | $size);
            }

            if ($size <= 0xffff)
            {
                return "\xde". chr($size >> 8). chr($size);
            }

            return pack('CN', 0xdf, $size);
        }

        /**
         * @param $type
         * @param $data
         * @return string
         */
        public function encodeExt($type, $data)
        {
            $length = strlen($data);

            switch ($length)
            {
                case 1: return "\xd4". chr($type).$data;
                case 2: return "\xd5". chr($type).$data;
                case 4: return "\xd6". chr($type).$data;
                case 8: return "\xd7". chr($type).$data;
                case 16: return "\xd8". chr($type).$data;
            }

            if ($length <= 0xff)
            {
                return "\xc7". chr($length). chr($type).$data;
            }

            if ($length <= 0xffff)
            {
                return pack('CnC', 0xc8, $length, $type).$data;
            }

            return pack('CNC', 0xc9, $length, $type).$data;
        }
    }
