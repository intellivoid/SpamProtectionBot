<?php


    namespace CoffeeHouse;

    use acm\acm;
    use CoffeeHouse\Classes\ServerInterface;
    use CoffeeHouse\Managers\ChatDialogsManager;
    use CoffeeHouse\Managers\ForeignSessionsManager;
    use CoffeeHouse\Managers\GeneralizedClassificationManager;
    use CoffeeHouse\Managers\SpamPredictionCacheManager;
    use CoffeeHouse\Managers\UserSubscriptionManager;
    use CoffeeHouse\NaturalLanguageProcessing\SpamPrediction;
    use DeepAnalytics\DeepAnalytics;
    use Exception;
    use mysqli;

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Abstracts' . DIRECTORY_SEPARATOR . 'ExceptionCodes.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Abstracts' . DIRECTORY_SEPARATOR . 'ForeignSessionSearchMethod.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Abstracts' . DIRECTORY_SEPARATOR . 'GeneralizedClassificationSearchMethod.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Abstracts' . DIRECTORY_SEPARATOR . 'ServerInterfaceModule.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Abstracts' . DIRECTORY_SEPARATOR . 'UserSubscriptionSearchMethod.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Abstracts' . DIRECTORY_SEPARATOR . 'UserSubscriptionStatus.php');

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Bots' . DIRECTORY_SEPARATOR . 'Cleverbot.php');

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Classes' . DIRECTORY_SEPARATOR . 'Hashing.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Classes' . DIRECTORY_SEPARATOR . 'ServerInterface.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Classes' . DIRECTORY_SEPARATOR . 'StringDistance.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Classes' . DIRECTORY_SEPARATOR . 'Utilities.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Classes' . DIRECTORY_SEPARATOR . 'Validation.php');

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'BotSessionException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'DatabaseException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'ForeignSessionNotFoundException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'GeneralizedClassificationLimitException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'GeneralizedClassificationNotFoundException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'InvalidMessageException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'InvalidSearchMethodException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'InvalidServerInterfaceModuleException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'ServerInterfaceException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'SpamPredictionCacheNotFoundException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'UserSubscriptionNotFoundException.php');

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Managers' . DIRECTORY_SEPARATOR . 'ChatDialogsManager.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Managers' . DIRECTORY_SEPARATOR . 'ForeignSessionsManager.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Managers' . DIRECTORY_SEPARATOR . 'GeneralizedClassificationManager.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Managers' . DIRECTORY_SEPARATOR . 'SpamPredictionCacheManager.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Managers' . DIRECTORY_SEPARATOR . 'UserSubscriptionManager.php');

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'NaturalLanguageProcessing' . DIRECTORY_SEPARATOR . 'SpamPrediction.php');

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'Cache' . DIRECTORY_SEPARATOR . 'SpamPredictionCache.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'Results' . DIRECTORY_SEPARATOR . 'SpamPredictionResults.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'BotThought.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'ForeignSession.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'GeneralizedClassification.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'HttpResponse.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'ServerInterfaceConnection.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'UserSubscription.php');

    if(class_exists('ZiProto\ZiProto') == false)
    {
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'ZiProto' . DIRECTORY_SEPARATOR . 'ZiProto.php');
    }

    if(class_exists('DeepAnalytics\DeepAnalytics') == false)
    {
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'DeepAnalytics' . DIRECTORY_SEPARATOR . 'DeepAnalytics.php');
    }

    if(class_exists('msqg\msqg') == false)
    {
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'msqg' . DIRECTORY_SEPARATOR . 'msqg.php');
    }

    if(class_exists('acm\acm') == false)
    {
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'acm' . DIRECTORY_SEPARATOR . 'acm.php');
    }

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'AutoConfig.php');

    /**
     * Class CoffeeHouse
     * @package CoffeeHouse
     */
    class CoffeeHouse
    {
        /**
         * @var mysqli
         */
        private $database;

        /**
         * @var mixed
         */
        private $DatabaseConfiguration;

        /**
         * @var acm
         */
        private $acm;

        /**
         * @var ForeignSessionsManager
         */
        private $ForeignSessionsManager;

        /**
         * @var ChatDialogsManager
         */
        private $ChatDialogsManager;

        /**
         * @var UserSubscriptionManager
         */
        private $UserSubscriptionManager;

        /**
         * @var DeepAnalytics
         */
        private $DeepAnalytics;

        /**
         * @var mixed
         */
        private $ServerConfiguration;

        /**
         * @var ServerInterface
         */
        private $ServerInterface;

        /**
         * @var SpamPrediction
         */
        private $SpamPrediction;

        /**
         * @var SpamPredictionCacheManager
         */
        private $SpamPredictionCacheManager;

        /**
         * @var GeneralizedClassificationManager
         */
        private $GeneralizedClassificationManager;

        /**
         * CoffeeHouse constructor.
         * @throws Exception
         */
        public function __construct()
        {
            $this->acm = new acm(__DIR__, 'CoffeeHouse');
            $this->DatabaseConfiguration = $this->acm->getConfiguration('Database');
            $this->ServerConfiguration = $this->acm->getConfiguration('CoffeeHouseServer');
            $this->database = null;

            $this->ForeignSessionsManager = new ForeignSessionsManager($this);
            $this->ChatDialogsManager = new ChatDialogsManager($this);
            $this->UserSubscriptionManager = new UserSubscriptionManager($this);
            $this->SpamPredictionCacheManager = new SpamPredictionCacheManager($this);
            $this->GeneralizedClassificationManager = new GeneralizedClassificationManager($this);
            $this->ServerInterface = new ServerInterface($this);
            $this->SpamPrediction = new SpamPrediction($this);
            $this->DeepAnalytics = new DeepAnalytics();
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
                    $this->DatabaseConfiguration['Name'],
                    $this->DatabaseConfiguration['Port']
                );
            }

            return $this->database;
        }

        /**
         * @return ForeignSessionsManager
         */
        public function getForeignSessionsManager(): ForeignSessionsManager
        {
            return $this->ForeignSessionsManager;
        }

        /**
         * @return ChatDialogsManager
         */
        public function getChatDialogsManager(): ChatDialogsManager
        {
            return $this->ChatDialogsManager;
        }


        /**
         * @return mixed
         */
        public function getDatabaseConfiguration()
        {
            return $this->DatabaseConfiguration;
        }

        /**
         * @return UserSubscriptionManager
         */
        public function getUserSubscriptionManager(): UserSubscriptionManager
        {
            return $this->UserSubscriptionManager;
        }

        /**
         * @return DeepAnalytics
         */
        public function getDeepAnalytics(): DeepAnalytics
        {
            return $this->DeepAnalytics;
        }

        /**
         * @return mixed
         */
        public function getServerConfiguration()
        {
            return $this->ServerConfiguration;
        }

        /**
         * @return ServerInterface
         */
        public function getServerInterface(): ServerInterface
        {
            return $this->ServerInterface;
        }

        /**
         * @return SpamPredictionCacheManager
         */
        public function getSpamPredictionCacheManager(): SpamPredictionCacheManager
        {
            return $this->SpamPredictionCacheManager;
        }

        /**
         * @return SpamPrediction
         */
        public function getSpamPrediction(): SpamPrediction
        {
            return $this->SpamPrediction;
        }

        /**
         * @return GeneralizedClassificationManager
         */
        public function getGeneralizedClassificationManager(): GeneralizedClassificationManager
        {
            return $this->GeneralizedClassificationManager;
        }

    }