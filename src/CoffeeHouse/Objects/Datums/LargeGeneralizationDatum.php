<?php


    namespace CoffeeHouse\Objects\Datums;

    /**
     * Class LargeGeneralizationDatum
     * @package CoffeeHouse\Objects\Datums
     */
    class LargeGeneralizationDatum
    {
        /**
         * The label of the datum
         *
         * @var string
         */
        public $Label;

        /**
         * The probability of the datum
         *
         * @var int|float
         */
        public $Probability;

        /**
         * Constructs an array from the object
         *
         * @param bool $as_object
         * @return array
         */
        public function toArray(bool $as_object=False): array
        {
            if($as_object)
            {
                return array(
                    "label" => $this->Label,
                    "probability" => (float)$this->Probability
                );
            }

            return array($this->Label, (float)$this->Probability);
        }

        /**
         * Constructs object from array
         *
         * @param array $data
         * @param bool $as_object
         * @return LargeGeneralizationDatum
         */
        public static function fromArray(array $data, bool $as_object=False): LargeGeneralizationDatum
        {
            $LargeGeneralizationDatumObject = new LargeGeneralizationDatum();

            if($as_object)
            {
                if(isset($data["label"]))
                {
                    $LargeGeneralizationDatumObject->Label = $data["label"];
                }

                if(isset($data["probability"]))
                {
                    $LargeGeneralizationDatumObject->Probability = (float)$data["probability"];
                }
            }
            else
            {
                $LargeGeneralizationDatumObject->Label = $data[0];
                $LargeGeneralizationDatumObject->Probability = (float)$data[1];
            }


            return $LargeGeneralizationDatumObject;
        }
    }