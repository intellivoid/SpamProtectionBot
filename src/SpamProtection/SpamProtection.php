<?php


    namespace SpamProtection;

    use acm\acm;
    use Exception;
    use mysqli;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtection\Managers\MessageLogManager;
    use SpamProtection\Managers\TelegramClientManager;
    use SpamProtection\Objects\ChatSettings;

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Abstracts' . DIRECTORY_SEPARATOR . 'DetectionAction.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Abstracts' . DIRECTORY_SEPARATOR . 'TelegramChatType.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Abstracts' . DIRECTORY_SEPARATOR . 'TelegramClientSearchMethod.php');

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'DatabaseException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'InvalidSearchMethod.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'MessageLogNotFoundException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'TelegramClientNotFoundException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'UnsupportedMessageException.php');

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'TelegramClient' . DIRECTORY_SEPARATOR . 'Chat.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'TelegramClient' . DIRECTORY_SEPARATOR . 'SessionData.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'TelegramClient' . DIRECTORY_SEPARATOR . 'User.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'TelegramObjects' . DIRECTORY_SEPARATOR . 'Message.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'ChatSettings.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'MessageLog.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'TelegramClient.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'UserStatus.php');

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Managers' . DIRECTORY_SEPARATOR . 'SettingsManager.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Managers' . DIRECTORY_SEPARATOR . 'MessageLogManager.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Managers' . DIRECTORY_SEPARATOR . 'TelegramClientManager.php');

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Utilities' . DIRECTORY_SEPARATOR . 'Hashing.php');

    if(class_exists('msqg\msqg') == false)
    {
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'msqg' . DIRECTORY_SEPARATOR . 'msqg.php');
    }

    if(class_exists('ZiProto\ZiProto') == false)
    {
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'ZiProto' . DIRECTORY_SEPARATOR . 'ZiProto.php');
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
         * The current database that's selected
         *
         * @var string
         */
        private $CurrentDatabase;

        /**
         * @var acm
         */
        private $acm;

        /**
         * @var TelegramClientManager
         */
        private $TelegramClientManager;

        /**
         * @var SettingsManager
         */
        private $ChatSettingsManager;

        /**
         * @var MessageLogManager
         */
        private $MessageLogManager;

        /**
         * @var SettingsManager
         */
        private $SettingsManager;

        /**
         * SpamProtection constructor.
         * @throws Exception
         */
        public function __construct()
        {
            $this->acm = new acm(__DIR__, 'SpamProtection');
            $this->DatabaseConfiguration = $this->acm->getConfiguration('Database');
            $this->database = null;
            
            $this->TelegramClientManager = new TelegramClientManager($this);
            $this->ChatSettingsManager = new SettingsManager($this);
            $this->MessageLogManager = new MessageLogManager($this);
            $this->SettingsManager = new SettingsManager($this);
        }

        /**
         * @param string $database
         * @return mysqli
         */
        public function getDatabase(string $database="MainDatabase")
        {
            if($this->database == null)
            {
                $this->database = new mysqli(
                    $this->DatabaseConfiguration['Host'],
                    $this->DatabaseConfiguration['Username'],
                    $this->DatabaseConfiguration['Password'],
                    $this->DatabaseConfiguration[$database],
                    $this->DatabaseConfiguration['Port']
                );
                $this->CurrentDatabase = $this->DatabaseConfiguration[$database];
            }

            if($this->CurrentDatabase == $database)
            {
                return $this->database;
            }
            
            $this->database->select_db($this->DatabaseConfiguration[$database]);
            $this->CurrentDatabase = $this->DatabaseConfiguration[$database];
            
            return $this->database;
        }

        /**
         * @return TelegramClientManager
         */
        public function getTelegramClientManager(): TelegramClientManager
        {
            return $this->TelegramClientManager;
        }

        /**
         * @return SettingsManager
         */
        public function getChatSettingsManager(): SettingsManager
        {
            return $this->ChatSettingsManager;
        }

        /**
         * @return MessageLogManager
         */
        public function getMessageLogManager(): MessageLogManager
        {
            return $this->MessageLogManager;
        }

        /**
         * @return SettingsManager
         */
        public function getSettingsManager(): SettingsManager
        {
            return $this->SettingsManager;
        }

    }