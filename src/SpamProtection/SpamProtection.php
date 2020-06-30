<?php

    /** @noinspection PhpUndefinedClassInspection */


    namespace SpamProtection;

    use acm\acm;
    use Exception;
    use mysqli;
    use SpamProtection\Managers\MessageLogManager;
    use TelegramClientManager\TelegramClientManager;

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Abstracts' . DIRECTORY_SEPARATOR . 'BlacklistFlag.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Abstracts' . DIRECTORY_SEPARATOR . 'DetectionAction.php');

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'DatabaseException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'DownloadFileException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'InvalidSearchMethod.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'MessageLogNotFoundException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'UnsupportedMessageException.php');

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'TelegramObjects' . DIRECTORY_SEPARATOR . 'Message.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'TelegramObjects' . DIRECTORY_SEPARATOR . 'PhotoSize.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'ChannelStatus.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'ChatSettings.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'MessageLog.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'UserStatus.php');

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Managers' . DIRECTORY_SEPARATOR . 'MessageLogManager.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Managers' . DIRECTORY_SEPARATOR . 'SettingsManager.php');

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Utilities' . DIRECTORY_SEPARATOR . 'Hashing.php');

    if(class_exists('msqg\msqg') == false)
    {
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'msqg' . DIRECTORY_SEPARATOR . 'msqg.php');
    }

    if(class_exists('ZiProto\ZiProto') == false)
    {
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'ZiProto' . DIRECTORY_SEPARATOR . 'ZiProto.php');
    }

    if(class_exists('TelegramClientManager\TelegramClientManager') == false)
    {
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'TelegramClientManager' . DIRECTORY_SEPARATOR . 'TelegramClientManager.php');
    }

    if(class_exists('acm\acm') == false)
    {
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'acm' . DIRECTORY_SEPARATOR . 'acm.php');
    }

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'AutoConfig.php');


    /**
     * Class SpamProtection
     * @package SpamProtection
     */
    class SpamProtection
    {
        /**
         * @return mysqli
         */
        private $database;

        /**
         * The database configuration
         *
         * @var array
         */
        private $DatabaseConfiguration;

        /**
         * @var acm
         * @noinspection PhpUndefinedClassInspection
         */
        private $acm;

        /**
         * @var MessageLogManager
         */
        private $MessageLogManager;

        /**
         * @var TelegramClientManager
         */
        private $TelegramClientManager;


        /**
         * SpamProtection constructor.
         * @throws Exception
         */
        public function __construct()
        {
            /** @noinspection PhpUndefinedClassInspection */
            $this->acm = new acm(__DIR__, 'SpamProtection');
            $this->DatabaseConfiguration = $this->acm->getConfiguration('Database');
            $this->database = null;

            $this->MessageLogManager = new MessageLogManager($this);
            $this->TelegramClientManager = null;
        }

        /**
         * @return mysqli
         */
        public function getDatabase()
        {
            if($this->database == null)
            {
                $this->database = new mysqli(
                    $this->DatabaseConfiguration['Host'],
                    $this->DatabaseConfiguration['Username'],
                    $this->DatabaseConfiguration['Password'],
                    $this->DatabaseConfiguration['Database'],
                    $this->DatabaseConfiguration['Port']
                );
            }

            return $this->database;
        }

        /**
         * @return MessageLogManager
         * @noinspection PhpUnused
         */
        public function getMessageLogManager(): MessageLogManager
        {
            return $this->MessageLogManager;
        }

        /**
         * @return TelegramClientManager
         * @noinspection PhpUnused
         */
        public function getTelegramClientManager(): TelegramClientManager
        {
            if($this->TelegramClientManager == null)
            {
                $this->TelegramClientManager = new TelegramClientManager();
            }

            return $this->TelegramClientManager;
        }

    }