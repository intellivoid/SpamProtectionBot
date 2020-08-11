<?php

    /** @noinspection PhpUnused */

    namespace CoffeeHouse\Objects;


    use CoffeeHouse\Objects\Datums\LargeGeneralizationDatum;

    /**
     * Class LargeGeneralization
     * @package CoffeeHouse\Objects
     */
    class LargeGeneralization
    {
        /**
         * Unique Internal Database ID
         *
         * @var int
         */
        public $ID;

        /**
         * The unique Public ID that's not unique
         *
         * @var string
         */
        public $PublicID;

        /**
         * The label of the top result
         *
         * @var string
         */
        public $TopLabel;

        /**
         * The probability of the top result
         *
         * @var float|int
         */
        public $TopProbability;

        /**
         * @var LargeGeneralizationDatum[]
         */
        public $Data;

        /**
         * Unix Timestamp of when this row was created
         *
         * @var int
         */
        public $Created;

        /**
         * LargeGeneralization constructor.
         */
        public function __construct()
        {
            $this->Data = array();
        }

        /**
         * Calculates the Top K result and returns the datum
         *
         * @return LargeGeneralizationDatum
         */
        public function calculateTopK(): LargeGeneralizationDatum
        {
            $LargestProbability = null;
            $CurrentSelection = null;

            foreach($this->Data as $datum)
            {
                if($datum->Probability > $LargestProbability)
                {
                    $LargestProbability = $datum->Probability;
                    $CurrentSelection = $datum;
                }
            }

            return $CurrentSelection;
        }

        /**
         * Updates the top K Result.
         *
         * @return bool
         */
        public function updateTopK(): bool
        {
            $TopDatum = $this->calculateTopK();

            $this->TopLabel = $TopDatum->Label;
            $this->TopProbability = $TopDatum->Probability;

            return True;
        }

        /**
         * Adds new data to the generalization model
         *
         * @param string $label
         * @param float $probability
         * @return bool
         */
        public function add(string $label, float $probability): bool
        {
            $overwrite = false;
            $selected_index = 0;

            foreach($this->Data as $datum)
            {
                if($datum->Label == $label)
                {
                    $overwrite = true;
                    break;
                }

                $selected_index += 1;
            }

            $datum = new LargeGeneralizationDatum();
            $datum->Label = $label;
            $datum->Probability = (float)$probability;

            if($overwrite)
            {
                $this->Data[$selected_index] = $datum;
                $this->updateTopK();
                return true;
            }

            $this->Data[] = $datum;
            $this->updateTopK();
            return false;
        }

        /**
         * Returns an array that represents this object
         *
         * @return array
         */
        public function toArray(): array
        {
            $data_array = array();

            foreach($this->Data as $datum)
            {
                $data_array[] = $datum->toArray();
            }

            return array(
                "id" => (int)$this->ID,
                "public_id" => $this->PublicID,
                "top_label" => $this->TopLabel,
                "top_probability" => (float)$this->TopProbability,
                "data" => $data_array,
                "created" => (int)$this->Created
            );
        }

        /**
         * Constructs object from array
         *
         * @param array $data
         * @return LargeGeneralization
         */
        public static function fromArray(array $data): LargeGeneralization
        {
            $LargeGeneralizationObject = new LargeGeneralization();

            if(isset($data["id"]))
            {
                $LargeGeneralizationObject->ID = (int)$data["id"];
            }

            if(isset($data["public_id"]))
            {
                $LargeGeneralizationObject->PublicID = $data["public_id"];
            }

            if(isset($data["top_label"]))
            {
                $LargeGeneralizationObject->TopLabel = $data["top_label"];
            }

            if(isset($data["top_probability"]))
            {
                $LargeGeneralizationObject->TopProbability = (float)$data["top_probability"];
            }

            if(isset($data["data"]))
            {
                foreach($data["data"] as $datum)
                {
                    $LargeGeneralizationObject->add($datum[0], $datum[1]);
                }
            }

            if(isset($data["created"]))
            {
                $LargeGeneralizationObject->Created = (int)$data["created"];
            }

            return $LargeGeneralizationObject;
        }
    }