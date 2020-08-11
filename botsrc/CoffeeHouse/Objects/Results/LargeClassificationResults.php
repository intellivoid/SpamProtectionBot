<?php /** @noinspection PhpUnused */


    namespace CoffeeHouse\Objects\Results;


    use CoffeeHouse\Objects\LargeGeneralization;
    use CoffeeHouse\Objects\Results\LargeClassificationResults\Probabilities;

    /**
     * Class LargeClassificationResults
     * @package CoffeeHouse\Objects\Results
     */
    class LargeClassificationResults
    {
        /**
         * The Public ID of the generalization results
         *
         * @var string
         */
        public $PublicID;

        /**
         * @var LargeGeneralization[]
         */
        public $LargeGeneralizations;

        /**
         * @var Probabilities[]
         */
        public $CombinedProbabilities;

        /**
         * @var string
         */
        public $TopLabel;

        /**
         * @var float|int
         */
        public $TopProbability;

        /**
         * LargeClassificationResults constructor.
         */
        public function __construct()
        {
            $this->LargeGeneralizations = array();
        }

        /**
         * Combines the results of all the large generalization results
         *
         * @return array|Probabilities[]
         */
        public function combineProbabilities(): array
        {
            $WorkingData = array();

            foreach($this->LargeGeneralizations as $largeGeneralization)
            {
                foreach($largeGeneralization->Data as $generalizationDatum)
                {
                    if(isset($WorkingData[$generalizationDatum->Label]) == false)
                    {
                        $WorkingData[$generalizationDatum->Label] = new Probabilities();
                        $WorkingData[$generalizationDatum->Label]->Label = $generalizationDatum->Label;
                    }

                    $WorkingData[$generalizationDatum->Label]->add($generalizationDatum->Probability);
                }
            }

            $SortedResults = array();
            for ($i = 0; $i < count($WorkingData); $i++)
            {
                $LargestProbability = null;
                $CurrentSelection = null;

                foreach($WorkingData as $prediction)
                {
                    if(isset($SortedResults[$prediction->Label]) == false)
                    {
                        if($prediction->CalculatedProbability > $LargestProbability)
                        {
                            $LargestProbability = $prediction->CalculatedProbability;
                            $CurrentSelection = $prediction;
                        }
                    }
                }

                $SortedResults[$CurrentSelection->Label] = $CurrentSelection;
            }

            $this->CombinedProbabilities = array();
            foreach($SortedResults as $result)
            {
                $this->CombinedProbabilities[] = $result;
            }

            return $this->CombinedProbabilities;
        }

        /**
         * Updates the Top Results properties
         *
         * @return Probabilities
         */
        public function updateTopK(): Probabilities
        {
            $this->TopProbability = $this->CombinedProbabilities[0]->CalculatedProbability;
            $this->TopLabel = $this->CombinedProbabilities[0]->Label;
            return $this->CombinedProbabilities[0];
        }

        /**
         * Updates the public ID
         *
         * @return string
         */
        public function updatePublicID(): string
        {
            $this->PublicID = $this->LargeGeneralizations[0]->PublicID;
            return $this->PublicID;
        }

        /**
         * Returns an array which represents this object
         *
         * @return array
         */
        public function toArray(): array
        {
            $large_generalization_data = array();

            foreach($this->LargeGeneralizations as $largeGeneralization)
            {
                $large_generalization_data[] = $largeGeneralization->toArray();
            }

            return array(
                "large_generalizations" => $large_generalization_data,
                "top_label" => $this->TopLabel,
                "top_probability" => $this->TopProbability
            );
        }

        /**
         * Constructs object from array
         *
         * @param array $data
         * @return LargeClassificationResults
         */
        public static function fromArray(array $data): LargeClassificationResults
        {
            $LargeClassificationResultsObject = new LargeClassificationResults();

            if(isset($data["large_generalizations"]))
            {
                foreach($data["large_generalizations"] as $datum)
                {
                    $LargeClassificationResultsObject[] = LargeGeneralization::fromArray($datum);
                }
            }

            if(isset($data["top_label"]))
            {
                $LargeClassificationResultsObject->TopLabel = $data["top_label"];
            }

            if(isset($data["top_probability"]))
            {
                $LargeClassificationResultsObject->TopProbability = (float)$data["top_probability"];
            }

            return $LargeClassificationResultsObject;
        }
    }