<?php

    namespace acm\Objects;

    class Schema
    {
        /**
         * @var array
         */
        private $structure;

        /**
         * @param string $name
         * @param mixed $default_value
         */
        public function setDefinition(string $name, $default_value)
        {
            $this->structure[$name] = $default_value;
        }

        /**
         * Returns structure as array
         *
         * @return array
         */
        public function toArray(): array
        {
            return $this->structure;
        }
    }