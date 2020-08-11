<?php

    namespace ZiProto\TypeTransformer;

    use ZiProto\Packet;
    use ZiProto\Type\Binary;

    /**
     * Class BinaryTransformer
     * @package ZiProto\TypeTransformer
     */
    abstract class BinaryTransformer
    {
        /**
         * @param Packet $packer
         * @param $value
         * @return string
         */
        public function pack(Packet $packer, $value): string
        {
            if ($value instanceof Binary)
            {
                return $packer->encodeBin($value->data);
            }
            else
            {
                return null;
            }
        }
    }