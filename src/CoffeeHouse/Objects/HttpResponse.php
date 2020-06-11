<?php


    namespace CoffeeHouse\Objects;

    /**
     * Class HttpResponse
     * @package CoffeeHouse\Objects
     */
    class HttpResponse
    {
        /**
         * Array of cookies that has been set
         *
         * @var array
         */
        public $cookies;

        /**
         * The body response
         *
         * @var mixed
         */
        public $response;

        /**
         * HttpResponse constructor.
         * @param $cookies
         * @param $response
         */
        public function __construct($cookies, $response)
        {
            $this->cookies = $cookies;
            $this->response = $response;
        }

    }