<?php


    namespace BackgroundWorker;


    use GearmanClient;

    /**
     * Class Client
     * @package BackgroundWorker
     */
    class Client
    {
        /**
         * @var GearmanClient
         */
        private $GearmanClient;

        /**
         * Client constructor.
         */
        public function __construct()
        {
            $this->GearmanClient = null;
        }

        /**
         * Adds a job server to a list of servers that can be used to run a task. No socket
         * I/O happens here; the server is simply added to the list.
         *
         * @param string $host
         * @param int $port
         */
        public function addServer(string $host="127.0.0.1", int $port=4730)
        {
            $this->getGearmanClient()->addServer($host, $port);
        }

        /**
         * @return GearmanClient
         */
        public function getGearmanClient(): GearmanClient
        {
            if($this->GearmanClient == null)
            {
                $this->GearmanClient = new GearmanClient();
            }

            return $this->GearmanClient;
        }
    }