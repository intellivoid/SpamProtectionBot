<?php

    /** @noinspection PhpUnused */


    /**
     * Class TgFileLogging
     */
    class TgFileLogging
    {
        /**
         * Information event type
         */
        const INFO = "INFO";

        /**
         * Warning event type
         */
        const WARNING = "WARNING";

        /**
         * Error event type
         */
        const ERROR = "ERROR";

        /**
         * Debugging event type
         */
        const DEBUG = "DEBUG";

        /**
         * Writes to a log file
         *
         * @param string $event_type
         * @param string $name
         * @param string $message
         * @noinspection PhpUnused
         */
        public static function writeLog(string $event_type, string $name, string $message)
        {
            $system_logging_directory = DIRECTORY_SEPARATOR . "var" . DIRECTORY_SEPARATOR . "log" . DIRECTORY_SEPARATOR;
            $main_logging_directory = $system_logging_directory . "telegram_bots";

            if(file_exists($main_logging_directory) == false)
            {
                mkdir($main_logging_directory);
            }

            $log_path = $main_logging_directory . DIRECTORY_SEPARATOR . strtolower($name) . ".log";
            $current_timestamp = date('Y-m-d H:i:s', time());

            file_put_contents($log_path,  "[$current_timestamp]::[$event_type] => $message\n", FILE_APPEND);
        }
    }
