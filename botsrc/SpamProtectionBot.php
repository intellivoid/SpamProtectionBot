<?php

    /** @noinspection PhpMissingFieldTypeInspection */

    use acm2\acm2;
    use acm2\Objects\Schema;
    use BackgroundWorker\BackgroundWorker;
    use CoffeeHouse\CoffeeHouse;
    use DeepAnalytics\DeepAnalytics;
    use SpamProtection\SpamProtection;
    use TelegramClientManager\TelegramClientManager;
    use VerboseAdventure\VerboseAdventure;

/**
     * Class SpamProtectionBot
     */
    class SpamProtectionBot
    {
        /**
         * The last Unix Timestamp when the worker was invoked
         *
         * @var int
         */
        public static $LastWorkerActivity;

        /**
         * Indicates if this worker is sleeping
         *
         * @var bool
         */
        public static $IsSleeping;

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
         * @var VerboseAdventure
         */
        public static $LogHandler;

        /**
         * Auto configures ACM
         *
         * @return acm2
         */
        public static function autoConfig(): acm2
        {
            $acm = new acm2('SpamProtectionBot');

            $TelegramSchema = new Schema();
            $TelegramSchema->setName('TelegramService');
            $TelegramSchema->setDefinition('BotName', '<BOT NAME HERE>');
            $TelegramSchema->setDefinition('BotToken', '<BOT TOKEN>');
            $TelegramSchema->setDefinition('BotEnabled', true);
            $TelegramSchema->setDefinition('UseTestServers', false);
            $TelegramSchema->setDefinition('EnableCustomServer', true);
            $TelegramSchema->setDefinition('CustomEndpoint', 'http://127.0.0.1:8081');
            $TelegramSchema->setDefinition('CustomDownloadEndpoint', '/file/bot{API_KEY}');
            $TelegramSchema->setDefinition('MainOperators', []);
            $TelegramSchema->setDefinition('LoggingChannel', 'SpamProtectionLogs');
            $TelegramSchema->setDefinition('VerboseLogging', false);

            $acm->defineSchema($TelegramSchema);

            $BackgroundWorkerSchema = new Schema();
            $BackgroundWorkerSchema->setName('BackgroundWorker');
            $BackgroundWorkerSchema->setDefinition('Host', '127.0.0.1');
            $BackgroundWorkerSchema->setDefinition('Port', 4730);
            $BackgroundWorkerSchema->setDefinition('MaxWorkers', 5);
            $acm->defineSchema($BackgroundWorkerSchema);

            $DatabaseSchema = new Schema();
            $DatabaseSchema->setName('Database');
            $DatabaseSchema->setDefinition('Host', '127.0.0.1');
            $DatabaseSchema->setDefinition('Port', 3306);
            $DatabaseSchema->setDefinition('Username', 'root');
            $DatabaseSchema->setDefinition('Password', 'admin');
            $DatabaseSchema->setDefinition('Database', 'telegram');
            $acm->defineSchema($DatabaseSchema);

            $RedisSchema = new Schema();
            $RedisSchema->setName('Redis');
            $RedisSchema->setDefinition('Host', '127.0.0.1');
            $RedisSchema->setDefinition('Port', 6379);
            $RedisSchema->setDefinition('Username', '');
            $RedisSchema->setDefinition('Password', '');
            $RedisSchema->setDefinition('Database', 0);
            $acm->defineSchema($RedisSchema);

            $acm->updateConfiguration();
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
         * Returns the redis configuration
         *
         * @return mixed
         * @throws Exception
         * @noinspection PhpUnused
         */
        public static function getRedisConfiguration()
        {
            return self::autoConfig()->getConfiguration('Redis');
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
        public static function getCoffeeHouse(): CoffeeHouse
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

        /**
         * @return VerboseAdventure
         */
        public static function getLogHandler(): VerboseAdventure
        {
            return self::$LogHandler;
        }

        /**
         * @param VerboseAdventure $LogHandler
         */
        public static function setLogHandler(VerboseAdventure $LogHandler): void
        {
            self::$LogHandler = $LogHandler;
        }

        /**
         * @return int
         */
        public static function getLastWorkerActivity(): int
        {
            return self::$LastWorkerActivity;
        }

        /**
         * @param int $LastWorkerActivity
         * @noinspection PhpUnused
         */
        public static function setLastWorkerActivity(int $LastWorkerActivity): void
        {
            self::$LastWorkerActivity = $LastWorkerActivity;
        }

        /**
         * @return bool
         */
        public static function isSleeping(): bool
        {
            return self::$IsSleeping;
        }

        /**
         * @param bool $IsSleeping
         */
        public static function setIsSleeping(bool $IsSleeping): void
        {
            self::$IsSleeping = $IsSleeping;
        }

        /**
         * Determines if this current worker should save resources by going to sleep or wake up depending on the
         * last activity cycle
         * @noinspection PhpUnused
         */
        public static function processSleepCycle()
        {
            if(time() - self::getLastWorkerActivity() > 60)
            {
                if(self::isSleeping() == false)
                {
                    self::getSpamProtection()->disconnectDatabase();
                    self::getCoffeeHouse()->disconnectDatabase();
                    self::getTelegramClientManager()->disconnectDatabase();
                    self::setIsSleeping(true);
                }
            }
            else
            {
                if(self::isSleeping() == true)
                {
                    self::getSpamProtection()->connectDatabase();
                    self::getCoffeeHouse()->connectDatabase();
                    self::getTelegramClientManager()->connectDatabase();
                    self::setIsSleeping(false);
                }
            }
        }
    }