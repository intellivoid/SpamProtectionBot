<?php

    use CoffeeHouse\CoffeeHouse;
    use DeepAnalytics\DeepAnalytics;
    use SpamProtection\SpamProtection;
    use TelegramClientManager\TelegramClientManager;

    /**
     * Class SpamProtectionBot
     */
    class SpamProtectionBot
    {
        /**
         * @var TelegramClientManager
         */
        public static $TelegramClientManager;

        /**
         * @var SpamProtection
         */
        public static $SpamProtection;

        /**
         * @var DeepAnalytics
         */
        public static $DeepAnalytics;

        /**
         * @var CoffeeHouse
         */
        public static $CoffeeHouse;

        /**
         * @return TelegramClientManager
         */
        public static function getTelegramClientManager(): TelegramClientManager
        {
            return self::$TelegramClientManager;
        }

        /**
         * @return SpamProtection
         */
        public static function getSpamProtection(): SpamProtection
        {
            return self::$SpamProtection;
        }

        /**
         * @return DeepAnalytics
         */
        public static function getDeepAnalytics(): DeepAnalytics
        {
            return self::$DeepAnalytics;
        }

        /**
         * @return CoffeeHouse
         */
        public static function getCoffeeHouse()
        {
            return self::$CoffeeHouse;
        }
    }