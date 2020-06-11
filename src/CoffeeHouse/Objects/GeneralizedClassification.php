<?php


    namespace CoffeeHouse\Objects;

    use CoffeeHouse\Exceptions\GeneralizedClassificationLimitException;

    /**
     * Class GeneralizedClassification
     * @package CoffeeHouse\Objects
     */
    class GeneralizedClassification
    {
        /**
         * Unique Internal Database ID for this record
         *
         * @var int
         */
        public $ID;

        /**
         * Unique Public ID
         *
         * @var string
         */
        public $PublicID;

        /**
         * The array of data to be calculate
         *
         * @var array
         */
        public $Data;

        /**
         * The current results calculated from the data
         *
         * @var float|int
         */
        public $Results;

        /**
         * The current size of the generalized classifier
         *
         * @var int
         */
        public $Size;

        /**
         * The current position of the pointer
         *
         * @var int
         */
        public $CurrentPointer;

        /**
         * The Unix Timestamp for when this record was last updated
         *
         * @var int
         */
        public $LastUpdated;

        /**
         * The Unix Timestamp for when this record was created
         *
         * @var int
         */
        public $Created;

        /**
         * Calculates the final results
         *
         * @return float
         */
        public function calculateResults(): float
        {
            $Results = (float)0;

            foreach($this->Data as $datum)
            {
                $Results += $datum;
            }

            $this->Results = ($Results / $this->Size);
            return $this->Results;
        }

        /**
         * Adds a new value to the generalized classifier
         *
         * @param float $value
         * @param bool $overwrite
         * @return float
         * @throws GeneralizedClassificationLimitException
         */
        public function addValue(float $value, bool $overwrite=true): float
        {
            if($this->CurrentPointer == $this->Size)
            {
                if($overwrite == false)
                {
                    throw new GeneralizedClassificationLimitException();
                }

                $this->CurrentPointer = 0;
            }

            $this->Data[$this->CurrentPointer] = $value;
            $this->CurrentPointer += 1;

            return $this->calculateResults();
        }

        /**
         * Returns an array for which represents this object
         *
         * @return array
         */
        public function toArray(): array
        {
            return array(
                'id' => (int)$this->ID,
                'public_id' => $this->PublicID,
                'data' => $this->Data,
                'results' => (float)$this->Results,
                'size' => (int)$this->Size,
                'current_pointer' => (int)$this->CurrentPointer,
                'last_updated' => (int)$this->LastUpdated,
                'created' => (int)$this->Created
            );
        }

        /**
         * Constructs object from array
         *
         * @param array $data
         * @return GeneralizedClassification
         */
        public static function fromArray(array $data): GeneralizedClassification
        {
            $GeneralizedClassification = new GeneralizedClassification();

            if(isset($data['id']))
            {
                $GeneralizedClassification->ID = (int)$data['id'];
            }

            if(isset($data['public_id']))
            {
                $GeneralizedClassification->PublicID = $data['public_id'];
            }

            if(isset($data['data']))
            {
                $GeneralizedClassification->Data = $data['data'];
            }

            if(isset($data['results']))
            {
                $GeneralizedClassification->Results = (float)$data['results'];
            }

            if(isset($data['size']))
            {
                $GeneralizedClassification->Size = (int)$data['size'];
            }

            if(isset($data['current_pointer']))
            {
                $GeneralizedClassification->CurrentPointer = (int)$data['current_pointer'];
            }

            if(isset($data['last_updated']))
            {
                $GeneralizedClassification->LastUpdated = (int)$data['last_updated'];
            }

            if(isset($data['created']))
            {
                $GeneralizedClassification->Created = (int)$data['created'];
            }

            return $GeneralizedClassification;
        }
    }