<?php


    namespace CoffeeHouse\Classes;


    /**
     * Class Hashing
     * @package CoffeeHouse\Classes
     */
    class Hashing
    {
        /**
         * Peppers a hash using whirlpool
         *
         * @param string $Data The hash to pepper
         * @param int $Min Minimal amounts of executions
         * @param int $Max Maximum amount of executions
         * @return string
         */
        public static function pepper(string $Data, int $Min = 100, int $Max = 1000): string
        {
            $n = rand($Min, $Max);
            $res = '';
            $Data = hash('whirlpool', $Data);
            for ($i=0,$l=strlen($Data) ; $l ; $l--)
            {
                $i = ($i+$n-1) % $l;
                $res = $res . $Data[$i];
                $Data = ($i ? substr($Data, 0, $i) : '') . ($i < $l-1 ? substr($Data, $i+1) : '');
            }
            return($res);
        }

        /**
         * Creates icognocheck Code
         *
         * @param string $vars
         * @return string
         */
        public static function icognocheckCode(string $vars): string
        {
            $data = substr($vars . '&icognocheck=', 7, 26);
            return(hash('md5', $data));
        }

        /**
         * Creates a foreign session id
         *
         * @param string $language
         * @param int $time
         * @return string
         */
        public static function foreignSessionId(string $language, int $time): string
        {
            $vars_c = hash('sha256', self::pepper($time) . $language);
            return hash('sha256', $vars_c . $time);
        }

        /**
         * Hashes a input using SHA256
         *
         * @param string $input
         * @return string
         */
        public static function input(string $input): string
        {
            return hash('sha256', $input);
        }

        /**
         * Generates a unique classification public ID
         *
         * @param int $timestamp
         * @param int $size
         * @return string
         */
        public static function generalizedClassificationPublicId(int $timestamp, int $size): string
        {
            $time_pepper = self::pepper((string)$timestamp);
            $size_pepper = self::pepper((string)$size);
            $combined = self::pepper($time_pepper . $size_pepper);

            return hash('sha256', $time_pepper . $size_pepper . $combined);
        }
    }