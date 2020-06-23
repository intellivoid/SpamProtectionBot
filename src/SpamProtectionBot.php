<?php

    use acm\acm;
    use acm\Objects\Schema;
    use BackgroundWorker\BackgroundWorker;
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
         * @var BackgroundWorker
         */
        public static $BackgroundWorker;

        /**
         * Auto configures ACM
         *
         * @return acm
         */
        public static function autoConfig(): acm
        {
            $acm = new acm(__DIR__, 'SpamProtectionBot');

            $TelegramSchema = new Schema();
            $TelegramSchema->setDefinition('BotName', '<BOT NAME HERE>');
            $TelegramSchema->setDefinition('BotToken', '<BOT TOKEN>');
            $TelegramSchema->setDefinition('BotEnabled', 'true');
            $TelegramSchema->setDefinition('WebHook', 'http://localhost');
            $TelegramSchema->setDefinition('MaxConnections', '100');
            $TelegramSchema->setDefinition('MaxWorkers', '5');
            $acm->defineSchema('TelegramService', $TelegramSchema);

            $BackgroundWorkerSchema = new Schema();
            $BackgroundWorkerSchema->setDefinition('Host', '127.0.0.1');
            $BackgroundWorkerSchema->setDefinition('Port', '4730');
            $acm->defineSchema('BackgroundWorker', $BackgroundWorkerSchema);

            $DatabaseSchema = new Schema();
            $DatabaseSchema->setDefinition('Host', '127.0.0.1');
            $DatabaseSchema->setDefinition('Port', '3306');
            $DatabaseSchema->setDefinition('Username', 'root');
            $DatabaseSchema->setDefinition('Password', 'admin');
            $DatabaseSchema->setDefinition('Database', 'telegram');
            $acm->defineSchema('Database', $DatabaseSchema);

            return $acm;
        }

        /**
         * Returns the Telegram Service configuration
         *
         * @return mixed
         * @throws Exception
         */
        public static function getTelegramConfiguration()
        {
            return self::autoConfig()->getConfiguration('TelegramService');
        }

        /**
         * Returns the database configuration
         *
         * @return mixed
         * @throws Exception
         */
        public static function getDatabaseConfiguration()
        {
            return self::autoConfig()->getConfiguration('Database');
        }

        /**
         * Returns the background worker configuration
         *
         * @return mixed
         * @throws Exception
         */
        public static function getBackgroundWorkerConfiguration()
        {
            return self::autoConfig()->getConfiguration('BackgroundWorker');
        }

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

        /**
         * @return BackgroundWorker
         */
        public static function getBackgroundWorker(): BackgroundWorker
        {
            return self::$BackgroundWorker;
        }
    }