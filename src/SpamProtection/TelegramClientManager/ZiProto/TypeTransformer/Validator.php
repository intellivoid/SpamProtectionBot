<?php
    namespace ZiProto\TypeTransformer;

    use ZiProto\Packet;

    /**
     * Interface Validator
     * @package ZiProto\TypeTransformer
     */
    interface Validator
    {
        /**
         * @param Packet $packer
         * @param $value
         * @return string
         */
        public function check(Packet $packer, $value) :string;
    }