<?php

    namespace ZiProto;

    use ZiProto\Exception\DecodingFailedException;
    use ZiProto\Exception\EncodingFailedException;
    use ZiProto\Exception\InvalidOptionException;

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Abstracts' . DIRECTORY_SEPARATOR . 'Options.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Abstracts' . DIRECTORY_SEPARATOR . 'Regex.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception' . DIRECTORY_SEPARATOR . 'DecodingFailedException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception' . DIRECTORY_SEPARATOR . 'EncodingFailedException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception' . DIRECTORY_SEPARATOR . 'InsufficientDataException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception' . DIRECTORY_SEPARATOR . 'IntegerOverflowException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception' . DIRECTORY_SEPARATOR . 'InvalidOptionException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Type' . DIRECTORY_SEPARATOR . 'Binary.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Type' . DIRECTORY_SEPARATOR . 'Map.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'TypeTransformer' . DIRECTORY_SEPARATOR . 'BinaryTransformer.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'TypeTransformer' . DIRECTORY_SEPARATOR . 'Extension.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'TypeTransformer' . DIRECTORY_SEPARATOR . 'MapTransformer.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'TypeTransformer' . DIRECTORY_SEPARATOR . 'Validator.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'BufferStream.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'DecodingOptions.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'EncodingOptions.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Ext.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Packet.php');

    /**
     * ZiProto Class
     *
     * Class ZiProto
     * @package ZiProto
     */
    class ZiProto
    {
        /**
         * @param mixed $value
         * @param EncodingOptions|int|null $options
         *
         * @throws InvalidOptionException
         * @throws EncodingFailedException
         *
         * @return string
         */
        public static function encode($value, $options = null) : string
        {
            return (new Packet($options))->encode($value);
        }

        /**
         * @param string $data
         * @param DecodingOptions|int|null $options
         *
         * @throws InvalidOptionException
         * @throws DecodingFailedException
         *
         * @return mixed
         */
        public static function decode(string $data, $options = null)
        {
            return (new BufferStream($data, $options))->decode();
        }
    }