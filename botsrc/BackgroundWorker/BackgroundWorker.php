<?php


    namespace BackgroundWorker;

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Client.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Supervisor.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Worker.php');

    /**
     * Class BackgroundWorker
     * @package BackgroundWorker
     */
    class BackgroundWorker
    {
        /**
         * @var Worker
         */
        private $Worker;

        /**
         * @var Client
         */
        private $Client;

        /**
         * @var Supervisor
         */
        private $Supervisor;

        /**
         * BackgroundWorker constructor.
         */
        public function __construct()
        {
            $this->Worker = new Worker();
            $this->Client = new Client();
            $this->Supervisor = new Supervisor($this);
        }

        /**
         * @return Worker
         */
        public function getWorker(): Worker
        {
            return $this->Worker;
        }

        /**
         * @return Client
         */
        public function getClient(): Client
        {
            return $this->Client;
        }

        /**
         * @return Supervisor
         */
        public function getSupervisor(): Supervisor
        {
            return $this->Supervisor;
        }
    }