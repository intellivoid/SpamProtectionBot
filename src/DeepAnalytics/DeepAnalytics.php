<?php /** @noinspection PhpUnused */


    namespace DeepAnalytics;

    use acm\acm;
    use DeepAnalytics\Exceptions\DataNotFoundException;
    use DeepAnalytics\Objects\HourlyData;
    use DeepAnalytics\Objects\MonthlyData;
    use Exception;
    use MongoDB\BSON\ObjectId;
    use MongoDB\Client;
    use MongoDB\Database;
    use MongoDB\Driver\Exception\BulkWriteException;
    use MongoDB\Model\BSONDocument;

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'DataNotFoundException.php');

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'Date.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'HourlyData.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'MonthlyData.php');

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Utilities.php');

    if(class_exists('MongoDB\Client') == false)
    {
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'MongoDB' . DIRECTORY_SEPARATOR . 'MongoDB.php');
    }

    if(class_exists('acm\acm') == false)
    {
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'acm' . DIRECTORY_SEPARATOR . 'acm.php');
    }

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'AutoConfig.php');

    /**
     * Class DeepAnalytics
     * @package DeepAnalytics
     */
    class DeepAnalytics
    {
        /**
         * @var acm
         */
        private $acm;

        /**
         * @var mixed
         */
        private $DatabaseConfiguration;

        /**
         * @var Client
         */
        private $MongoDB_Client;

        /**
         * @var Database
         */
        private $Database;

        /**
         * DeepAnalytics constructor.
         * @throws Exception
         */
        public function __construct()
        {
            $this->acm = new acm(__DIR__, 'deep_analytics');
            $this->DatabaseConfiguration = $this->acm->getConfiguration('MongoDB');

            $this->MongoDB_Client = new Client(
                "mongodb://" . $this->DatabaseConfiguration['Host'] . ":" . $this->DatabaseConfiguration['Port'],
                array(
                    "username" => $this->DatabaseConfiguration['Username'],
                    "password" => $this->DatabaseConfiguration['Password']
                )
            );

            $this->Database = $this->MongoDB_Client->selectDatabase($this->DatabaseConfiguration['Database']);
        }

        /**
         * Tallies both hourly and monthly and returns an array with 'hourly' and 'monthly'
         *
         * @param string $collection
         * @param string $name
         * @param int|null $reference_id
         * @param int $amount
         * @param int|null $year
         * @param int|null $month
         * @param int|null $day
         * @return array
         */
        public function tally(string $collection, string $name, int $reference_id=null, int $amount=1,
                              int $year=null, int $month=null, int $day=null): array
        {
            $Results = array();

            $Results['hourly'] = $this->tallyHourly($collection, $name, $reference_id, $amount, $year, $month, $day);
            $Results['monthly'] = $this->tallyMonthly($collection, $name, $reference_id, $amount, $year, $month);

            return $Results;
        }

        /**
         * Tallies an hourly rating
         *
         * @param string $collection
         * @param string $name
         * @param int $reference_id
         * @param int $amount
         * @param int|null $year
         * @param int|null $month
         * @param int|null $day
         * @param bool $throw_dup
         * @return HourlyData
         */
        public function tallyHourly(string $collection, string $name, int $reference_id=null, int $amount=1,
                                    int $year=null, int $month=null, int $day=null, bool $throw_dup=false): HourlyData
        {
            if(is_null($reference_id))
            {
                $reference_id = 0;
            }

            $HourlyData = new HourlyData($year, $month, $day);
            $HourlyData->ReferenceID = $reference_id;
            $HourlyData->Name = $name;

            $Collection = $this->Database->selectCollection($collection . '_hourly');
            $Document = null;

            $Document = $Collection->findOne([
                "stamp" => $HourlyData->Stamp,
                "name" => $name,
                "reference_id" => $reference_id
            ]);

            if(is_null($Document))
            {
                if(is_null($reference_id))
                {
                    $reference_id = 0;
                }

                $Collection->createIndex(
                    [
                        "stamp" => 1,
                        "name" => 1,
                        "reference_id" => 1
                    ],
                    [
                        "unique" => true
                    ]
                );

                $HourlyData->tally($amount);
                $HourlyDataDocument = $HourlyData->toArray();
                unset($HourlyDataDocument["id"]);

                try
                {
                    $Document = $Collection->insertOne($HourlyDataDocument);
                }
                catch(BulkWriteException $bulkWriteException)
                {
                    // Handle duplicate error
                    if($bulkWriteException->getCode() == 11000)
                    {
                        if($throw_dup)
                        {
                            throw $bulkWriteException;
                        }

                        return $this->tallyHourly(
                            $collection, $name, $reference_id, $amount=1,
                            $year, $month, $day, true
                        );
                    }
                }

                $HourlyData->ID = (string)$Document->getInsertedId();
            }
            else
            {
                $HourlyData = Utilities::BSONDocumentToHourlyData($Document);

                $HourlyData->tally($amount);
                $HourlyData->LastUpdated = (int)time();
                $HourlyDataDocument = $HourlyData->toArray();
                unset($HourlyDataDocument["id"]);

                $Collection->updateOne(
                    ['_id' => new ObjectID($HourlyData->ID)],
                    ['$set' => $HourlyDataDocument]
                );
            }

            return $HourlyData;
        }

        /**
         * Returns a range of hourly data
         *
         * @param string $collection
         * @param string $name
         * @param int|null $reference_id
         * @param int $limit
         * @return array
         * @noinspection DuplicatedCode
         */
        public function getHourlyDataRange(string $collection, string $name, int $reference_id=null, $limit=100): array
        {
            $Collection = $this->Database->selectCollection($collection . '_hourly');

            if(is_null($reference_id))
            {
                $reference_id = 0;
            }

            $Cursor = $Collection->find(
                [
                    'name' => $name,
                    'reference_id' => $reference_id
                ],
                [
                    'projection' => [
                        '_id' => 1,
                        'stamp' => 1,
                        'date' => 1
                    ],
                    'sort' => [
                        'created' => -1
                    ],
                    'limit' => $limit
                ]
            );

            $Results = [];

            /** @var BSONDocument $document */
            foreach($Cursor as $document)
            {
                $DocumentArray = (array)$document->jsonSerialize();
                $DateArray = (array)$DocumentArray['date']->jsonSerialize();

                $Results[$DocumentArray['stamp']] = array(
                    'id' => (string)$DocumentArray['_id'],
                    'date' => $DateArray
                );
            }

            return $Results;
        }

        /**
         * Gets Hourly Data by content pointers
         *
         * @param string $collection
         * @param string $name
         * @param int|null $reference_id
         * @param bool $throw_exception
         * @param int|null $year
         * @param int|null $month
         * @param int|null $day
         * @return HourlyData
         * @throws DataNotFoundException
         */
        public function getHourlyData(string $collection, string $name, int $reference_id=null, bool $throw_exception=true,
                                      int $year=null, int $month=null, int $day=null): HourlyData
        {
            $Collection = $this->Database->selectCollection($collection . '_hourly');
            $DateObject = Utilities::constructDate($year, $month, $day);

            if(is_null($reference_id))
            {
                $reference_id = 0;
            }

            $Document = $Collection->findOne([
                "stamp" => $DateObject->getDayStamp(),
                "name" => $name,
                "reference_id" => $reference_id
            ]);

            if(is_null($Document))
            {
                if($throw_exception)
                {
                    throw new DataNotFoundException("The requested hourly rating data was not found.");
                }

                $HourlyData = new HourlyData($year, $month, $day);
                $HourlyData->ID = null;
                $HourlyData->ReferenceID = $reference_id;
                $HourlyData->Name = $name;

                return $HourlyData;
            }

            return Utilities::BSONDocumentToHourlyData($Document);
        }

        /**
         * Finds hourly data by ID
         *
         * @param string $collection
         * @param string $id
         * @param bool $throw_exception
         * @return HourlyData
         * @throws DataNotFoundException
         */
        public function getHourlyDataById(string $collection, string $id, bool $throw_exception=true): HourlyData
        {
            $Collection = $this->Database->selectCollection($collection . '_hourly');

            $Document = $Collection->findOne([
                "_id" => new ObjectId($id)
            ]);

            if(is_null($Document))
            {
                if($throw_exception)
                {
                    throw new DataNotFoundException("The requested hourly rating data was not found.");
                }

                $HourlyData = new HourlyData();
                $HourlyData->ID = null;
                $HourlyData->ReferenceID = null;
                $HourlyData->Name = null;

                return $HourlyData;
            }

            return Utilities::BSONDocumentToHourlyData($Document);
        }

        /**
         * Tallies a monthly rating
         *
         * @param string $collection
         * @param string $name
         * @param int $reference_id
         * @param int $amount
         * @param int|null $year
         * @param int|null $month
         * @param bool $throw_dup
         * @return MonthlyData
         */
        public function tallyMonthly(string $collection, string $name, int $reference_id=null, int $amount=1,
                                    int $year=null, int $month=null, bool $throw_dup=false): MonthlyData
        {
            if(is_null($reference_id))
            {
                $reference_id = 0;
            }

            $MonthlyData = new MonthlyData($year, $month);
            $MonthlyData->ReferenceID = $reference_id;
            $MonthlyData->Name = $name;

            $Collection = $this->Database->selectCollection($collection . '_monthly');
            $Document = null;

            $Document = $Collection->findOne([
                "stamp" => $MonthlyData->Stamp,
                "name" => $name,
                "reference_id" => $reference_id
            ]);

            if(is_null($Document))
            {
                if(is_null($reference_id))
                {
                    $reference_id = 0;
                }

                $Collection->createIndex(
                    [
                        "stamp" => 1,
                        "name" => 1,
                        "reference_id" => 1
                    ],
                    [
                        "unique" => true
                    ]
                );

                $MonthlyData->tally($amount);
                $MonthlyDataDocument = $MonthlyData->toArray();
                unset($MonthlyDataDocument["id"]);

                try
                {
                    $Document = $Collection->insertOne($MonthlyDataDocument);
                }
                catch(BulkWriteException $bulkWriteException)
                {
                    // Handle duplicate error
                    if($bulkWriteException->getCode() == 11000)
                    {
                        if($throw_dup)
                        {
                            throw $bulkWriteException;
                        }

                        return $this->tallyMonthly(
                            $collection, $name, $reference_id, $amount=1,
                            $year, $month, true
                        );
                    }
                }

                $MonthlyData->ID = (string)$Document->getInsertedId();
            }
            else
            {
                $MonthlyData = Utilities::BSONDocumentToMonthlyData($Document);

                $MonthlyData->tally($amount);
                $MonthlyData->LastUpdated = (int)time();
                $MonthlyDataDocument = $MonthlyData->toArray();
                unset($MonthlyDataDocument["id"]);

                $Collection->updateOne(
                    ['_id' => new ObjectID($MonthlyData->ID)],
                    ['$set' => $MonthlyDataDocument]
                );
            }

            return $MonthlyData;
        }

        /**
         * Gets the data range for monthly data.
         *
         * @param string $collection
         * @param string $name
         * @param int|null $reference_id
         * @param int $limit
         * @return array
         * @noinspection DuplicatedCode
         */
        public function getMonthlyDataRange(string $collection, string $name, int $reference_id=null, $limit=100): array
        {
            $Collection = $this->Database->selectCollection($collection . '_monthly');

            if(is_null($reference_id))
            {
                $reference_id = 0;
            }

            $Cursor = $Collection->find(
                [
                    'name' => $name,
                    'reference_id' => $reference_id
                ],
                [
                    'projection' => [
                        '_id' => 1,
                        'stamp' => 1,
                        'date' => 1
                    ],
                    'sort' => [
                        'created' => -1
                    ],
                    'limit' => $limit
                ]
            );

            $Results = [];

            /** @var BSONDocument $document */
            foreach($Cursor as $document)
            {
                $DocumentArray = (array)$document->jsonSerialize();
                $DateArray = (array)$DocumentArray['date']->jsonSerialize();

                $Results[$DocumentArray['stamp']] = array(
                    'id' => (string)$DocumentArray['_id'],
                    'date' => $DateArray
                );
            }

            return $Results;
        }

        /**
         * Returns the monthly data by content pointers
         *
         * @param string $collection
         * @param string $name
         * @param int|null $reference_id
         * @param bool $throw_exception
         * @param int|null $year
         * @param int|null $month
         * @return MonthlyData
         * @throws DataNotFoundException
         */
        public function getMonthlyData(string $collection, string $name, int $reference_id=null, bool $throw_exception=true,
                                      int $year=null, int $month=null): MonthlyData
        {
            if(is_null($reference_id))
            {
                $reference_id = 0;
            }

            $Collection = $this->Database->selectCollection($collection . '_monthly');
            $DateObject = Utilities::constructDate($year, $month);

            $Document = $Collection->findOne([
                "stamp" => $DateObject->getMonthStamp(),
                "name" => $name,
                "reference_id" => $reference_id
            ]);

            if(is_null($Document))
            {
                if($throw_exception)
                {
                    throw new DataNotFoundException("The requested monthly rating data was not found.");
                }

                $MonthlyData = new MonthlyData($year, $month);
                $MonthlyData->ReferenceID = $reference_id;
                $MonthlyData->Name = $name;
                $MonthlyData->ID = null;

                return $MonthlyData;
            }

            return Utilities::BSONDocumentToMonthlyData($Document);
        }

        /**
         * Returns monthly data by ID
         *
         * @param string $collection
         * @param string $id
         * @param bool $throw_exception
         * @return MonthlyData
         * @throws DataNotFoundException
         */
        public function getMonthlyDataById(string $collection, string $id, bool $throw_exception=true): MonthlyData
        {
            $Collection = $this->Database->selectCollection($collection . '_monthly');

            $Document = $Collection->findOne([
                "_id" => new ObjectId($id)
            ]);

            if(is_null($Document))
            {
                if($throw_exception)
                {
                    throw new DataNotFoundException("The requested monthly rating data was not found.");
                }

                $MonthlyData = new MonthlyData();
                $MonthlyData->ReferenceID = null;
                $MonthlyData->Name = null;
                $MonthlyData->ID = null;

                return $MonthlyData;
            }

            return Utilities::BSONDocumentToMonthlyData($Document);
        }
    }