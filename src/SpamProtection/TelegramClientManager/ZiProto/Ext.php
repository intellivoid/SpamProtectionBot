<?php
    namespace ZiProto;

    /**
     * Class Ext
     * @package ZiProto
     */
    final class Ext
    {
        /**
         * @var int
         */
        public $type;

        /**
         * @var string
         */
        public $data;

        /**
         * Ext constructor.
         * @param int $type
         * @param string $data
         */
        public function __construct(int $type, string $data)
        {
            $this->type = $type;
            $this->data = $data;
        }
    }