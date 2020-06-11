<?php


    namespace CoffeeHouse\Objects\Cache;


    /**
     * Class SpamDetectionCache
     * @package CoffeeHouse\Objects\Cache
     */
    class SpamPredictionCache
    {
        /**
         * Unique internal databsae ID for this cache record
         *
         * @var int
         */
        public $ID;

        /**
         * The hash of the contents
         *
         * @var string
         */
        public $Hash;

        /**
         * The calculation for the ham calculation
         *
         * @var float
         */
        public $HamCalculation;

        /**
         * The calculation for the spam detection
         *
         * @var float
         */
        public $SpamCalculation;

        /**
         * The Unix Timestamp of when this cache record was last updated
         *
         * @var int
         */
        public $LastUpdated;

        /**
         * The Unix Timestamp of when this cache record was created
         *
         * @var int
         */
        public $Created;

        /**
         * Returns an array representing this object's structure and data
         *
         * @return array
         */
        public function toArray(): array
        {
            return array(
                'id' => (int)$this->ID,
                'hash' => $this->Hash,
                'ham_calculation' => (float)$this->HamCalculation,
                'spam_calculation' => (float)$this->SpamCalculation,
                'last_updated' => (int)$this->LastUpdated,
                'created' => (int)$this->Created
            );
        }

        /**
         * Constructs object from array data
         *
         * @param array $data
         * @return SpamPredictionCache
         */
        public static function fromArray(array $data): SpamPredictionCache
        {
            $SpamDetectionCacheObject = new SpamPredictionCache();

            if(isset($data['id']))
            {
                $SpamDetectionCacheObject->ID = (int)$data['id'];
            }

            if(isset($data['hash']))
            {
                $SpamDetectionCacheObject->Hash = $data['hash'];
            }

            if(isset($data['ham_calculation']))
            {
                $SpamDetectionCacheObject->HamCalculation = (float)$data['ham_calculation'];
            }

            if(isset($data['spam_calculation']))
            {
                $SpamDetectionCacheObject->SpamCalculation = (float)$data['spam_calculation'];
            }

            if(isset($data['last_updated']))
            {
                $SpamDetectionCacheObject->LastUpdated = (int)$data['last_updated'];
            }

            if(isset($data['created']))
            {
                $SpamDetectionCacheObject->Created = (int)$data['created'];
            }

            return $SpamDetectionCacheObject;
        }
    }