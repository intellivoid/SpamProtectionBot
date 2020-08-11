<?php /** @noinspection PhpUnused */


    namespace CoffeeHouse\Objects\Results;

    /**
     * Class LanguagePrediction
     * @package CoffeeHouse\Objects\Results
     */
    class LanguagePrediction
    {
        /**
         * The language for the prediction
         *
         * @var string
         */
        public $Language;

        /**
         * The probability of the prediction
         *
         * @var float|int
         */
        public $Probability;

        /**
         * Returns an array which represents this object
         *
         * @param bool $bytes
         * @return array
         */
        public function toArray(bool $bytes=false): array
        {
            if($bytes)
            {
                return array($this->Language, $this->Probability);
            }

            return array(
                "language" => $this->Language,
                "probability" => (float)$this->Probability
            );
        }

        /**
         * Constructs object from array
         *
         * @param array $data
         * @param bool $bytes
         * @return LanguagePrediction
         */
        public static function fromArray(array $data, bool $bytes=false): LanguagePrediction
        {
            $LanguagePrediction = new LanguagePrediction();

            if($bytes)
            {
                $LanguagePrediction->Language = $data[0];
                $LanguagePrediction->Probability = (float)$data[1];
            }
            else
            {
                if(isset($data["language"]))
                {
                    $LanguagePrediction->Language = $data["language"];
                }

                if(isset($data["probability"]))
                {
                    $LanguagePrediction->Probability = (float)$data["probability"];
                }
            }

            return $LanguagePrediction;
        }
    }