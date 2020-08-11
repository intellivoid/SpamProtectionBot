<?php


    namespace BackgroundWorker;


    use GearmanWorker;

    /**
     * Class Worker
     * @package BackgroundWorker
     */
    class Worker
    {
        /**
         * @var GearmanWorker
         */
        private $GearmanWorker;

        /**
         * Worker constructor.
         */
        public function __construct()
        {
            $this->GearmanWorker = null;
        }

        /**
         * Adds one or more job servers to this worker. These go into a list of servers
         * that can be used to run jobs. No socket I/O happens here.
         *
         * @param string $host
         * @param int $port
         */
        public function addServer(string $host="127.0.0.1", int $port=4730)
        {
            $this->getGearmanWorker()->addServer($host, $port);
        }

        /**
         * @return GearmanWorker
         */
        public function getGearmanWorker(): GearmanWorker
        {
            if($this->GearmanWorker == null)
            {
                $this->GearmanWorker = new GearmanWorker();
            }

            return $this->GearmanWorker;
        }

        /**
         * Starts the worker
         */
        public function work()
        {
            /** @noinspection PhpStatementHasEmptyBodyInspection */
            while($this->getGearmanWorker()->work());
        }
    }