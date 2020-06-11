<?php

    namespace MongoDB;

    use IteratorAggregate;
    use stdClass;
    use Traversable;
    use function call_user_func;

    /**
     * Result class for mapReduce command results.
     *
     * This class allows for iteration of mapReduce results irrespective of the
     * output method (e.g. inline, collection) via the IteratorAggregate interface.
     * It also provides access to command statistics.
     *
     * @api
     * @see \MongoDB\Collection::mapReduce()
     * @see https://docs.mongodb.com/manual/reference/command/mapReduce/
     */
    class MapReduceResult implements IteratorAggregate
    {
        /** @var callable */
        private $getIterator;

        /** @var integer */
        private $executionTimeMS;

        /** @var array */
        private $counts;

        /** @var array */
        private $timing;

        /**
         * @internal
         * @param callable $getIterator Callback that returns a Traversable for mapReduce results
         * @param stdClass $result      Result document from the mapReduce command
         */
        public function __construct(callable $getIterator, stdClass $result)
        {
            $this->getIterator = $getIterator;
            $this->executionTimeMS = (integer) $result->timeMillis;
            $this->counts = (array) $result->counts;
            $this->timing = isset($result->timing) ? (array) $result->timing : [];
        }

        /**
         * Returns various count statistics from the mapReduce command.
         *
         * @return array
         */
        public function getCounts()
        {
            return $this->counts;
        }

        /**
         * Return the command execution time in milliseconds.
         *
         * @return integer
         */
        public function getExecutionTimeMS()
        {
            return (integer) $this->executionTimeMS;
        }

        /**
         * Return the mapReduce results as a Traversable.
         *
         * @see http://php.net/iteratoraggregate.getiterator
         * @return Traversable
         */
        public function getIterator()
        {
            return call_user_func($this->getIterator);
        }

        /**
         * Returns various timing statistics from the mapReduce command.
         *
         * Note: timing statistics are only available if the mapReduce command's
         * "verbose" option was true; otherwise, an empty array will be returned.
         *
         * @return array
         */
        public function getTiming()
        {
            return $this->timing;
        }
    }
