<?php


    namespace CoffeeHouse\Objects\Results;


    /**
     * Class SpamPredictionResults
     * @package CoffeeHouse\Objects\Results
     */
    class SpamPredictionResults
    {
        /**
         * The prediction percentage of the content being spam
         *
         * @var float
         */
        public $SpamPrediction;

        /**
         * The generalized prediction of spam if generalization is used
         *
         * @var float|null
         */
        public $GeneralizedSpam;

        /**
         * The prediction percentage of the content not being spam
         *
         * @var float
         */
        public $HamPrediction;

        /**
         * The generalized prediction of ham if generalization is used
         *
         * @var float|null
         */
        public $GeneralizedHam;

        /**
         * The ID of the generalization results
         *
         * @var string|null
         */
        public $GeneralizedID;

        /**
         * Returns true if the results predict that the results are spam
         *
         * @return bool
         */
        public function isSpam(): bool
        {
            if($this->SpamPrediction > $this->HamPrediction)
            {
                return true;
            }

            return false;
        }

        /**
         * Returns true if the generalized results predict that most of the given data is spam
         *
         * @return bool
         */
        public function isGeneralizedSpam(): bool
        {
            if($this->GeneralizedSpam > $this->GeneralizedHam)
            {
                return true;
            }

            return false;
        }

        /**
         * Returns the array structure of this object
         *
         * @return array
         */
        public function toArray(): array
        {
            return array(
                'spam' => $this->SpamPrediction,
                'spam_generalized' => $this->GeneralizedSpam,
                'ham' => $this->HamPrediction,
                'ham_generalized' => $this->GeneralizedHam,
                'generalized_id' => $this->GeneralizedID
            );
        }

        /**
         * Constructs object from array
         *
         * @param array $data
         * @return SpamPredictionResults
         */
        public static function fromArray(array $data): SpamPredictionResults
        {
            $SpamPredictionResultsObject = new SpamPredictionResults();

            if(isset($data['spam']))
            {
                $SpamPredictionResultsObject->SpamPrediction = (float)$data['spam'];
            }

            if(isset($data['spam_generalized']))
            {
                $SpamPredictionResultsObject->GeneralizedSpam = (float)$data['spam_generalized'];
            }

            if(isset($data['ham']))
            {
                $SpamPredictionResultsObject->HamPrediction = (float)$data['ham'];
            }

            if(isset($data['ham_generalized']))
            {
                $SpamPredictionResultsObject->GeneralizedHam = (float)$data['ham_generalized'];
            }

            if(isset($data['generalized_id']))
            {
                $SpamPredictionResultsObject->GeneralizedID = $data['generalized_id'];
            }

            return $SpamPredictionResultsObject;
        }
    }