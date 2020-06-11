<?php


    namespace CoffeeHouse\Objects;


    /**
     * Class BotThought
     * @package CoffeeHouse\Objects
     */
    class BotThought
    {

        /**
         * @var string
         */
        private $input;

        /**
         * @var string
         */
        private $output;

        /**
         * @var array
         */
        private $session;

        /**
         * BotThought constructor.
         * @param string $input
         * @param string $output
         * @param array $session
         */
        public function __construct(string $input, string $output, array $session)
        {

            $this->input = $input;
            $this->output = $output;
            $this->session = $session;
        }

        /**
         * @return string
         */
        public function getInput(): string
        {
            return $this->input;
        }

        /**
         * @return string
         */
        public function getOutput(): string
        {
            return $this->output;
        }

        /**
         * @return array
         */
        public function getSession(): array
        {
            return $this->session;
        }

        /**
         * @param array $session
         */
        public function setSession(array $session): void
        {
            $this->session = $session;
        }
    }