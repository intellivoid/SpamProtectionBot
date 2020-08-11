<?php


    namespace DeepAnalytics\Objects;


    use DeepAnalytics\Utilities;
    use InvalidArgumentException;

    /**
     * Class MonthlyData
     * @package DeepAnalytics\Objects
     */
    class MonthlyData
    {
        /**
         * @var string
         *
         * Unique internal database ID for this record
         */
        public $ID;

        /**
         * @var string
         */
        public $ReferenceID;

        /**
         * The name of this analytical counter
         *
         * @var string
         */
        public $Name;

        /**
         * The date that this da
         *
         * @var Date
         */
        public $Date;

        /**
         * Unique stamp for this record
         *
         * @var string
         */
        public $Stamp;

        /**
         * The monthly data
         *
         * @var array
         */
        public $Data;

        /**
         * The total combined from data
         *
         * @var int
         */
        public $Total;

        /**
         * The Unix Timestamp when this record was last updated
         *
         * @var int
         */
        public $LastUpdated;

        /**
         * The Unix Timestamp when this record was created
         *
         * @var int
         */
        public $Created;

        /**
         * HourlyData constructor.
         * @param int|null $year
         * @param int|null $month
         */
        public function __construct(int $year=null, int $month=null)
        {
            $this->Date = new Date();

            if(is_null($year) == false)
            {
                $this->Date->Year = $year;
            }

            if(is_null($month) == false)
            {
                $this->Date->Month = $month;
            }

            $this->Stamp = $this->Date->getMonthStamp();
            $this->Data = Utilities::generateMonthArray($this->Date->Month, $this->Date->Year);

            $this->Created = (int)time();
            $this->LastUpdated = (int)$this->Created;
        }

        /**
         * Tallies the monthly rate
         *
         * @param int $amount
         * @param int|null $day
         */
        public function tally(int $amount=1, int $day=null)
        {
            if($amount < 0)
            {
                $amount = 0;
            }

            if($amount == 0)
            {
                return;
            }

            if(is_null($day))
            {
                $day = (int)date('j');
                $this->Data[(int)$day - 1] += $amount;
            }
            else
            {
                if(isset($this->Data[(int)$day]) == false)
                {
                    throw new InvalidArgumentException("The given day must be a value between 1 and " . (count($this->Data) +1));
                }

                $this->Data[(int)$day - 1] += $amount;
            }

            $this->Total = Utilities::calculateTotal($this->Data);
        }

        /**
         * Returns an array which represents this object
         *
         * @return array
         */
        public function toArray(): array
        {
            return array(
                'id' => $this->ID,
                'reference_id' => (int)$this->ReferenceID,
                'name' => $this->Name,
                'date' => $this->Date->toArray(false),
                'stamp' => $this->Stamp,
                'data' => $this->Data,
                'total' => (int)$this->Total,
                'last_updated' => (int)$this->LastUpdated,
                'created' => (int)$this->Created
            );
        }

        /**
         * Constructs object from array
         *
         * @param array $data
         * @return MonthlyData
         */
        public static function fromArray(array $data): MonthlyData
        {
            $MonthlyDataObject = new MonthlyData();

            if(isset($data['id']))
            {
                if(is_null($data['id']) == false)
                {
                    $MonthlyDataObject->ID = $data['id'];
                }
            }

            if(isset($data['_id']))
            {
                if(is_null($data['_id']) == false)
                {
                    $MonthlyDataObject->ID = (string)$data['_id'];
                }
            }

            if(isset($data['reference_id']))
            {
                $MonthlyDataObject->ReferenceID = $data['reference_id'];
            }

            if(isset($data['name']))
            {
                $MonthlyDataObject->Name = $data['name'];
            }

            if(isset($data['date']))
            {
                $MonthlyDataObject->Date = Date::fromArray($data['date']);
            }

            if(isset($data['stamp']))
            {
                $MonthlyDataObject->Stamp = $data['stamp'];
            }

            if(isset($data['data']))
            {
                $MonthlyDataObject->Data = $data['data'];
            }

            if(isset($data['total']))
            {
                $MonthlyDataObject->Total = (int)$data['total'];
            }

            if(isset($data['last_updated']))
            {
                $MonthlyDataObject->LastUpdated = (int)$data['last_updated'];
            }

            if(isset($data['created']))
            {
                $MonthlyDataObject->Created = (int)$data['created'];
            }

            return $MonthlyDataObject;
        }

        /**
         * Returns the data, optionally formatted.
         *
         * @param bool $formatted
         * @return array
         */
        public function getData(bool $formatted=true): array
        {
            if($formatted)
            {
                $Results = array();

                foreach($this->Data as $key => $value)
                {
                    $key += 1;
                    if($key < 10)
                    {
                        $key = "0$key";
                    }

                    $Results[(string)$key] = $value;
                }

                return $Results;
            }

            return $this->Data;
        }
    }