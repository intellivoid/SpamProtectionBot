<?php

    namespace ZiProto\Type;

    /**
     * Class Map
     * @package ZiProto\Type
     */
    final class Map
    {
        /**
         * @var array
         */
        public $map;

        /**
         * Map constructor.
         * @param array $map
         */
        public function __construct(array $map)
        {
            $this->map = $map;
        }
    }