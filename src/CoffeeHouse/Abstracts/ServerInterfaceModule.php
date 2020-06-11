<?php


    namespace CoffeeHouse\Abstracts;


    /**
     * The supported modules that runs on CoffeeHouse-Server
     *
     * Class ServerInterfaceModule
     * @package CoffeeHouse\Abstracts
     */
    abstract class ServerInterfaceModule
    {
        /**
         * Spam Detection Module of CoffeeHouse-Server
         */
        const SpamPrediction = "SPAM_PREDICTION";
    }