<?php


    namespace DeepAnalytics\Objects;

    use DeepAnalytics\Utilities;
    use InvalidArgumentException;

    /**
     * Class HourlyData
     * @package DeepAnalytics\Objects
     */
    class HourlyData
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
         * Unique month stamp for this record
         *
         * @var string
         */
        public $MonthStamp;

        /**
         * The 24 hour timeline
         *
         * @var array
         */
        public $Data;

        /**
         * The total amount calculated from all the data
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
         * @param null $day
         */
        public function __construct(int $year=null, int $month=null, $day=null)
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

            if(is_null($day) == false)
            {
                $this->Date->Day = $day;
            }

            $this->Stamp = $this->Date->getDayStamp();
            $this->MonthStamp = $this->Date->getMonthStamp();
            $this->Data = Utilities::generateHourArray();

            $this->Created = (int)time();
            $this->LastUpdated = (int)$this->Created;
        }

        /**
         * Tallies the hourly rate
         *
         * @param int $amount
         * @param int|null $hour
         */
        public function tally(int $amount=1, int $hour=null)
        {
            if($amount < 0)
            {
                $amount = 0;
            }

            if($amount == 0)
            {
                return;
            }

            if(is_null($hour))
            {
                $CurrentHour = (int)date('G');
                $this->Data[$CurrentHour] += $amount;
            }
            else
            {
                if(isset($this->Data[$hour]) == false)
                {
                    throw new InvalidArgumentException("The given hour must be a value between a 24 hour period");
                }

                $this->Data[$hour] += $amount;
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
                'date' => $this->Date->toArray(true),
                'stamp' => $this->Stamp,
                'month_stamp' => $this->MonthStamp,
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
         * @return HourlyData
         */
        public static function fromArray(array $data): HourlyData
        {
            $HourlyDataObject = new HourlyData();

            /** @noinspection DuplicatedCode */
            if(isset($data['id']))
            {
                if(is_null($data['id']) == false)
                {
                    $HourlyDataObject->ID = $data['id'];
                }
            }

            if(isset($data['_id']))
            {
                if(is_null($data['_id']) == false)
                {
                    $HourlyDataObject->ID = (string)$data['_id'];
                }
            }

            if(isset($data['reference_id']))
            {
                $HourlyDataObject->ReferenceID = $data['reference_id'];
            }

            if(isset($data['name']))
            {
                $HourlyDataObject->Name = $data['name'];
            }

            if(isset($data['date']))
            {
                $HourlyDataObject->Date = Date::fromArray($data['date']);
            }

            if(isset($data['stamp']))
            {
                $HourlyDataObject->Stamp = $data['stamp'];
            }

            if(isset($data['month_stamp']))
            {
                $HourlyDataObject->MonthStamp = $data['month_stamp'];
            }

            if(isset($data['data']))
            {
                $HourlyDataObject->Data = $data['data'];
            }

            if(isset($data['total']))
            {
                $HourlyDataObject->Total = (int)$data['total'];
            }

            if(isset($data['last_updated']))
            {
                $HourlyDataObject->LastUpdated = (int)$data['last_updated'];
            }

            if(isset($data['created']))
            {
                $HourlyDataObject->Created = (int)$data['created'];
            }

            return $HourlyDataObject;
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
                    $Results["$key:00"] = $value;
                }

                return $Results;
            }

            return $this->Data;
        }
    }