<?php


    namespace CoffeeHouse\Classes;


    /**
     * Class Validation
     * @package CoffeeHouse\Classes
     */
    class Validation
    {
        /**
         * Determines if the message is valid or not
         *
         * @param string $input
         * @return bool
         */
        public static function message(string $input): bool
        {
            if(strlen($input) > 5000)
            {
                return false;
            }

            return true;
        }
    }