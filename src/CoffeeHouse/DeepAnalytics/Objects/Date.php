<?php


    namespace DeepAnalytics\Objects;


    use DeepAnalytics\Utilities;

    class Date
    {
        /**
         * @var int|null
         */
        public $Day;

        /**
         * @var int|null
         */
        public $Month;

        /**
         * @var int|null
         */
        public $Year;

        /**
         * Date constructor.
         */
        public function __construct()
        {
            $this->Year = (int)date('Y');
            $this->Month = (int)date('n');
            $this->Day = (int)date('j');
        }

        /**
         * Returns the stamp that represents the monthly stamp
         *
         * @return string
         */
        public function getMonthStamp(): string
        {
            return Utilities::generateMonthStamp($this->Year, $this->Month);
        }

        /**
         * Returns the stamp that represents the full date including the day1
         *
         * @return string
         */
        public function getDayStamp(): string
        {
            return Utilities::generateHourlyStamp($this->Year, $this->Month, $this->Day);
        }

        /**
         * Returns an array which represents the object's structure
         *
         * @param bool $include_day
         * @return array
         */
        public function toArray(bool $include_day=true): array
        {
            $results = array(
                'year' => $this->Year,
                'month' => $this->Month
            );

            if($include_day)
            {
                $results['day'] = $this->Day;
            }

            return $results;
        }

        /**
         * Constructs object from array
         *
         * @param array $data
         * @return Date
         */
        public static function fromArray(array $data): Date
        {
            $DateObject = new Date();

            if(isset($data['year']))
            {
                $DateObject->Year = $data['year'];
            }

            if(isset($data['month']))
            {
                $DateObject->Month = $data['month'];
            }

            if(isset($data['day']))
            {
                $DateObject->Day = $data['day'];
            }

            return $DateObject;
        }
    }