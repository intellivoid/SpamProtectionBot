<?php
    namespace ZiProto;

    use function gmp_init;
    use function ord;
    use function sprintf;
    use function substr;
    use function unpack;
    use ZiProto\Exception\InsufficientDataException;
    use ZiProto\Exception\IntegerOverflowException;
    use ZiProto\Exception\DecodingFailedException;
    use ZiProto\Exception\InvalidOptionException;
    use ZiProto\TypeTransformer\Extension;

    /**
     * Class BufferStream
     * @package ZiProto
     */
    class BufferStream
    {
        /**
         * @var string
         */
        private $buffer;

        /**
         * @var int
         */
        private $offset = 0;

        /**
         * @var bool
         */
        private $isBigIntAsStr;

        /**
         * @var bool
         */
        private $isBigIntAsGmp;

        /**
         * @var Extension[]|null
         */
        private $transformers;

        /**
         * @param string $buffer
         * @param DecodingOptions|int|null $options
         *
         * @throws InvalidOptionException
         */
        public function __construct(string $buffer = '', $options = null)
        {
            if (null === $options)
            {
                $options = DecodingOptions::fromDefaults();
            }
            elseif (!$options instanceof EncodingOptions)
            {
                $options = DecodingOptions::fromBitmask($options);
            }

            $this->isBigIntAsStr = $options->isBigIntAsStrMode();
            $this->isBigIntAsGmp = $options->isBigIntAsGmpMode();
            $this->buffer = $buffer;
        }

        /**
         * @param Extension $transformer
         * @return BufferStream
         */
        public function registerTransformer(Extension $transformer) : self
        {
            $this->transformers[$transformer->getType()] = $transformer;
            return $this;
        }

        /**
         * @param string $data
         * @return BufferStream
         */
        public function append(string $data) : self
        {
            $this->buffer .= $data;
            return $this;
        }

        /**
         * @param string $buffer
         * @return BufferStream
         */
        public function reset(string $buffer = '') : self
        {
            $this->buffer = $buffer;
            $this->offset = 0;
            return $this;
        }

        /**
         * Clone Method
         */
        public function __clone()
        {
            $this->buffer = '';
            $this->offset = 0;
        }

        /**
         * @return array
         */
        public function trydecode() : array
        {
            $data = [];
            $offset = $this->offset;

            try
            {
                do
                {
                    $data[] = $this->decode();
                    $offset = $this->offset;
                } while (isset($this->buffer[$this->offset]));
            }
            catch (InsufficientDataException $e)
            {
                $this->offset = $offset;
            }

            if ($this->offset)
            {
                $this->buffer = isset($this->buffer[$this->offset]) ? substr($this->buffer, $this->offset) : '';
                $this->offset = 0;
            }

            return $data;
        }

        /**
         * @return array|bool|int|mixed|resource|string|Ext|null
         */
        public function decode()
        {
            if (!isset($this->buffer[$this->offset]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, 1);
            }

            $c = ord($this->buffer[$this->offset]);
            ++$this->offset;

            // fixint
            if ($c <= 0x7f)
            {
                return $c;
            }

            // fixstr
            if ($c >= 0xa0 && $c <= 0xbf)
            {
                return ($c & 0x1f) ? $this->decodeStrData($c & 0x1f) : '';
            }

            // fixarray
            if ($c >= 0x90 && $c <= 0x9f)
            {
                return ($c & 0xf) ? $this->decodeArrayData($c & 0xf) : [];
            }

            // fixmap
            if ($c >= 0x80 && $c <= 0x8f)
            {
                return ($c & 0xf) ? $this->decodeMapData($c & 0xf) : [];
            }

            // negfixint
            if ($c >= 0xe0)
            {
                return $c - 0x100;
            }

            switch ($c)
            {
                case 0xc0: return null;
                case 0xc2: return false;
                case 0xc3: return true;

                // bin
                case 0xc4: return $this->decodeStrData($this->decodeUint8());
                case 0xc5: return $this->decodeStrData($this->decodeUint16());
                case 0xc6: return $this->decodeStrData($this->decodeUint32());

                // float
                case 0xca: return $this->decodeFloat32();
                case 0xcb: return $this->decodeFloat64();

                // uint
                case 0xcc: return $this->decodeUint8();
                case 0xcd: return $this->decodeUint16();
                case 0xce: return $this->decodeUint32();
                case 0xcf: return $this->decodeUint64();

                // int
                case 0xd0: return $this->decodeInt8();
                case 0xd1: return $this->decodeInt16();
                case 0xd2: return $this->decodeInt32();
                case 0xd3: return $this->decodeInt64();

                // str
                case 0xd9: return $this->decodeStrData($this->decodeUint8());
                case 0xda: return $this->decodeStrData($this->decodeUint16());
                case 0xdb: return $this->decodeStrData($this->decodeUint32());

                // array
                case 0xdc: return $this->decodeArrayData($this->decodeUint16());
                case 0xdd: return $this->decodeArrayData($this->decodeUint32());

                // map
                case 0xde: return $this->decodeMapData($this->decodeUint16());
                case 0xdf: return $this->decodeMapData($this->decodeUint32());

                // ext
                case 0xd4: return $this->decodeExtData(1);
                case 0xd5: return $this->decodeExtData(2);
                case 0xd6: return $this->decodeExtData(4);
                case 0xd7: return $this->decodeExtData(8);
                case 0xd8: return $this->decodeExtData(16);
                case 0xc7: return $this->decodeExtData($this->decodeUint8());
                case 0xc8: return $this->decodeExtData($this->decodeUint16());
                case 0xc9: return $this->decodeExtData($this->decodeUint32());
            }

            throw DecodingFailedException::unknownCode($c);
        }

        /**
         * @return null
         */
        public function decodeNil()
        {
            if (!isset($this->buffer[$this->offset]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, 1);
            }

            if ("\xc0" === $this->buffer[$this->offset])
            {
                ++$this->offset;
                return null;
            }

            throw DecodingFailedException::unexpectedCode(ord($this->buffer[$this->offset++]), 'nil');
        }

        /**
         * @return bool
         */
        public function decodeBool()
        {
            if (!isset($this->buffer[$this->offset]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, 1);
            }

            $c = ord($this->buffer[$this->offset]);
            ++$this->offset;

            if (0xc2 === $c)
            {
                return false;
            }

            if (0xc3 === $c)
            {
                return true;
            }

            throw DecodingFailedException::unexpectedCode($c, 'bool');
        }

        /**
         * @return int|resource|string
         */
        public function decodeInt()
        {
            if (!isset($this->buffer[$this->offset]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, 1);
            }

            $c = ord($this->buffer[$this->offset]);
            ++$this->offset;

            // fixint
            if ($c <= 0x7f)
            {
                return $c;
            }

            // negfixint
            if ($c >= 0xe0)
            {
                return $c - 0x100;
            }

            switch ($c)
            {
                // uint
                case 0xcc: return $this->decodeUint8();
                case 0xcd: return $this->decodeUint16();
                case 0xce: return $this->decodeUint32();
                case 0xcf: return $this->decodeUint64();

                // int
                case 0xd0: return $this->decodeInt8();
                case 0xd1: return $this->decodeInt16();
                case 0xd2: return $this->decodeInt32();
                case 0xd3: return $this->decodeInt64();
            }

            throw DecodingFailedException::unexpectedCode($c, 'int');
        }

        /**
         * @return mixed
         */
        public function decodeFloat()
        {
            if (!isset($this->buffer[$this->offset]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, 1);
            }

            $c = ord($this->buffer[$this->offset]);
            ++$this->offset;

            if (0xcb === $c)
            {
                return $this->decodeFloat64();
            }

            if (0xca === $c)
            {
                return $this->decodeFloat32();
            }

            throw DecodingFailedException::unexpectedCode($c, 'float');
        }

        /**
         * @return bool|string
         */
        public function decodeStr()
        {
            if (!isset($this->buffer[$this->offset]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, 1);
            }

            $c = ord($this->buffer[$this->offset]);
            ++$this->offset;

            if ($c >= 0xa0 && $c <= 0xbf)
            {
                return ($c & 0x1f) ? $this->decodeStrData($c & 0x1f) : '';
            }

            if (0xd9 === $c)
            {
                return $this->decodeStrData($this->decodeUint8());
            }

            if (0xda === $c)
            {
                return $this->decodeStrData($this->decodeUint16());
            }

            if (0xdb === $c)
            {
                return $this->decodeStrData($this->decodeUint32());
            }

            throw DecodingFailedException::unexpectedCode($c, 'str');
        }

        /**
         * @return bool|string
         */
        public function decodeBin()
        {
            if (!isset($this->buffer[$this->offset]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, 1);
            }

            $c = ord($this->buffer[$this->offset]);
            ++$this->offset;

            if (0xc4 === $c)
            {
                return $this->decodeStrData($this->decodeUint8());
            }

            if (0xc5 === $c)
            {
                return $this->decodeStrData($this->decodeUint16());
            }

            if (0xc6 === $c)
            {
                return $this->decodeStrData($this->decodeUint32());
            }

            throw DecodingFailedException::unexpectedCode($c, 'bin');
        }

        /**
         * @return array
         */
        public function decodeArray()
        {
            $size = $this->decodeArrayHeader();
            $array = [];

            while ($size--)
            {
                $array[] = $this->decode();
            }

            return $array;
        }

        /**
         * @return int
         */
        public function decodeArrayHeader()
        {
            if (!isset($this->buffer[$this->offset]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, 1);
            }

            $c = ord($this->buffer[$this->offset]);
            ++$this->offset;

            if ($c >= 0x90 && $c <= 0x9f)
            {
                return $c & 0xf;
            }

            if (0xdc === $c)
            {
                return $this->decodeUint16();
            }

            if (0xdd === $c)
            {
                return $this->decodeUint32();
            }

            throw DecodingFailedException::unexpectedCode($c, 'array header');
        }

        /**
         * @return array
         */
        public function decodeMap()
        {
            $size = $this->decodeMapHeader();
            $map = [];

            while ($size--)
            {
                $map[$this->decode()] = $this->decode();
            }

            return $map;
        }

        /**
         * @return int
         */
        public function decodeMapHeader()
        {
            if (!isset($this->buffer[$this->offset]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, 1);
            }

            $c = ord($this->buffer[$this->offset]);
            ++$this->offset;

            if ($c >= 0x80 && $c <= 0x8f)
            {
                return $c & 0xf;
            }

            if (0xde === $c)
            {
                return $this->decodeUint16();
            }

            if (0xdf === $c)
            {
                return $this->decodeUint32();
            }

            throw DecodingFailedException::unexpectedCode($c, 'map header');
        }

        /**
         * @return mixed|Ext
         */
        public function decodeExt()
        {
            if (!isset($this->buffer[$this->offset]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, 1);
            }

            $c = ord($this->buffer[$this->offset]);
            ++$this->offset;

            switch ($c)
            {
                case 0xd4: return $this->decodeExtData(1);
                case 0xd5: return $this->decodeExtData(2);
                case 0xd6: return $this->decodeExtData(4);
                case 0xd7: return $this->decodeExtData(8);
                case 0xd8: return $this->decodeExtData(16);
                case 0xc7: return $this->decodeExtData($this->decodeUint8());
                case 0xc8: return $this->decodeExtData($this->decodeUint16());
                case 0xc9: return $this->decodeExtData($this->decodeUint32());
            }

            throw DecodingFailedException::unexpectedCode($c, 'ext header');
        }

        /**
         * @return int
         */
        private function decodeUint8()
        {
            if (!isset($this->buffer[$this->offset]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, 1);
            }

            return ord($this->buffer[$this->offset++]);
        }

        /**
         * @return int
         */
        private function decodeUint16()
        {
            if (!isset($this->buffer[$this->offset + 1]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, 2);
            }

            $hi = ord($this->buffer[$this->offset]);
            $lo = ord($this->buffer[++$this->offset]);
            ++$this->offset;

            return $hi << 8 | $lo;
        }

        /**
         * @return mixed
         */
        private function decodeUint32()
        {
            if (!isset($this->buffer[$this->offset + 3]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, 4);
            }

            $num = unpack('N', $this->buffer, $this->offset)[1];
            $this->offset += 4;

            return $num;
        }

        /**
         * @return resource|string
         */
        private function decodeUint64()
        {
            if (!isset($this->buffer[$this->offset + 7]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, 8);
            }

            $num = unpack('J', $this->buffer, $this->offset)[1];
            $this->offset += 8;

            return $num < 0 ? $this->handleIntOverflow($num) : $num;
        }

        /**
         * @return int
         */
        private function decodeInt8()
        {
            if (!isset($this->buffer[$this->offset]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, 1);
            }

            $num = ord($this->buffer[$this->offset]);
            ++$this->offset;

            return $num > 0x7f ? $num - 0x100 : $num;
        }

        /**
         * @return int
         */
        private function decodeInt16()
        {
            if (!isset($this->buffer[$this->offset + 1]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, 2);
            }

            $hi = ord($this->buffer[$this->offset]);
            $lo = ord($this->buffer[++$this->offset]);
            ++$this->offset;

            return $hi > 0x7f ? $hi << 8 | $lo - 0x10000 : $hi << 8 | $lo;
        }

        /**
         * @return int
         */
        private function decodeInt32()
        {
            if (!isset($this->buffer[$this->offset + 3]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, 4);
            }

            $num = unpack('N', $this->buffer, $this->offset)[1];
            $this->offset += 4;

            return $num > 0x7fffffff ? $num - 0x100000000 : $num;
        }

        /**
         * @return mixed
         */
        private function decodeInt64()
        {
            if (!isset($this->buffer[$this->offset + 7]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, 8);
            }

            $num = unpack('J', $this->buffer, $this->offset)[1];
            $this->offset += 8;

            return $num;
        }

        /**
         * @return mixed
         */
        private function decodeFloat32()
        {
            if (!isset($this->buffer[$this->offset + 3]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, 4);
            }

            $num = unpack('G', $this->buffer, $this->offset)[1];
            $this->offset += 4;

            return $num;
        }

        /**
         * @return mixed
         */
        private function decodeFloat64()
        {
            if (!isset($this->buffer[$this->offset + 7]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, 8);
            }

            $num = unpack('E', $this->buffer, $this->offset)[1];
            $this->offset += 8;

            return $num;
        }

        /**
         * @param $length
         * @return bool|string
         */
        private function decodeStrData($length)
        {
            if (!isset($this->buffer[$this->offset + $length - 1]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, $length);
            }

            $str = substr($this->buffer, $this->offset, $length);
            $this->offset += $length;

            return $str;
        }

        /**
         * @param $size
         * @return array
         */
        private function decodeArrayData($size)
        {
            $array = [];

            while ($size--)
            {
                $array[] = $this->decode();
            }

            return $array;
        }

        /**
         * @param $size
         * @return array
         */
        private function decodeMapData($size)
        {
            $map = [];

            while ($size--)
            {
                $map[$this->decode()] = $this->decode();
            }

            return $map;
        }

        /**
         * @param $length
         * @return mixed|Ext
         */
        private function decodeExtData($length)
        {
            if (!isset($this->buffer[$this->offset + $length - 1]))
            {
                throw InsufficientDataException::unexpectedLength($this->buffer, $this->offset, $length);
            }

            // int8
            $num = ord($this->buffer[$this->offset]);
            ++$this->offset;
            $type = $num > 0x7f ? $num - 0x100 : $num;

            if (isset($this->transformers[$type]))
            {
                return $this->transformers[$type]->decode($this, $length);
            }

            $data = substr($this->buffer, $this->offset, $length);
            $this->offset += $length;

            return new Ext($type, $data);
        }

        /**
         * @param $value
         * @return resource|string
         */
        private function handleIntOverflow($value)
        {
            if ($this->isBigIntAsStr)
            {
                return sprintf('%u', $value);
            }

            if ($this->isBigIntAsGmp)
            {
                return gmp_init(sprintf('%u', $value));
            }

            throw new IntegerOverflowException($value);
        }
    }