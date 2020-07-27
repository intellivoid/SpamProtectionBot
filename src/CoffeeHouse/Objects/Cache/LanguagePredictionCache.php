<?php


    namespace CoffeeHouse\Objects\Cache;


    /**
     * Class LanguagePredictionCache
     * @package CoffeeHouse\Objects\Cache
     */
    class LanguagePredictionCache
    {
        /**
         * Unique Internal Database ID
         *
         * @var int
         */
        public $ID;

        /**
         * SHA256 Hash of the input
         *
         * @var string
         */
        public $Hash;

        /**
         * The results of Deep Learning Text Classification
         *
         * @var {language, probability} array
         */
        public $DLTC_Results;

        /**
         * The results of ContentLanguageDetection
         *
         * @var {language, probability} array
         */
        public $CLD_Results;

        /**
         * The results of LangDetect
         *
         * @var {language, probability} array
         */
        public $LD_Results;

        /**
         * The Unix Timestamp of when this record was last updated
         *
         * @var int
         */
        public $LastUpdated;

        /**
         * The Unix Timestamp of when this record was created
         *
         * @var int
         */
        public $Created;

        /**
         * Returns an array which represents this object
         *
         * @return array
         */
        public function toArray(): array
        {
            return array(
                "id" => (int)$this->ID,
                "hash" => $this->Hash,
                "dltc_results" => $this->DLTC_Results,
                "cld_results" => $this->CLD_Results,
                "ld_results" => $this->LD_Results,
                "last_updated" => (int)$this->LastUpdated,
                "created" => (int)$this->Created
            );
        }

        /**
         * Constructs object from array
         *
         * @param array $data
         * @return LanguagePredictionCache
         */
        public static function fromArray(array $data): LanguagePredictionCache
        {
            $LanguagePredictionCacheObject = new LanguagePredictionCache();

            if(isset($data["id"]))
            {
                $LanguagePredictionCacheObject->ID = (int)$data["id"];
            }

            if(isset($data["hash"]))
            {
                $LanguagePredictionCacheObject->Hash = $data["hash"];
            }

            if(isset($data["dltc_results"]))
            {
                $LanguagePredictionCacheObject->DLTC_Results = $data["dltc_results"];
            }

            if(isset($data["cld_results"]))
            {
                $LanguagePredictionCacheObject->CLD_Results = $data["cld_results"];
            }

            if(isset($data["ld_results"]))
            {
                $LanguagePredictionCacheObject->LD_Results = $data["ld_results"];
            }

            if(isset($data["last_updated"]))
            {
                $LanguagePredictionCacheObject->LastUpdated = (int)$data["last_updated"];
            }

            if(isset($data["created"]))
            {
                $LanguagePredictionCacheObject->Created = (int)$data["created"];
            }

            return $LanguagePredictionCacheObject;
        }
    }