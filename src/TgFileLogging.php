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

            $event = "[$current_timestamp]::[$event_type] => $message";
            print($event . PHP_EOL);
            file_put_contents($log_path,  $event . "\n", FILE_APPEND);
        }

        /**
         * Dumps the full exception details into a JSON file
         *
         * @param Exception $exception
         * @param string $name
         * @param string $identifier
         * @return string
         */
        public static function dumpException(Exception $exception, string $name, string $identifier): string
        {
            $DumpResults = array(
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTrace(),
                'trace_string' => $exception->getTraceAsString(),
                'previous_exceptions' => []
            );

            $current_exception = $exception->getPrevious();
            while(true)
            {
                if($current_exception == null)
                {
                    break;
                }

                $DumpResults['previous_exceptions'][] = array(
                    'file' => $current_exception->getFile(),
                    'line' => $current_exception->getLine(),
                    'code' => $current_exception->getCode(),
                    'message' => $current_exception->getMessage(),
                    'trace' => $current_exception->getTrace(),
                    'trace_string' => $current_exception->getTraceAsString()
                );

                $current_exception = $current_exception->getPrevious();
            }

            $DumpResultsJson = json_encode($DumpResults, JSON_PRETTY_PRINT);

            $system_logging_directory = DIRECTORY_SEPARATOR . "var" . DIRECTORY_SEPARATOR . "log" . DIRECTORY_SEPARATOR;
            $main_exception_directory = $system_logging_directory . "telegram_exceptions";

            if(file_exists($main_exception_directory) == false)
            {
                mkdir($main_exception_directory);
            }

            $exception_id = hash('sha256', time() . $name . $identifier . $DumpResultsJson);
            $file_name = strtolower($name) . "_" . strtolower($identifier) . "_" . $exception_id . ".json";
            $log_path = $main_exception_directory . DIRECTORY_SEPARATOR . $file_name;

            file_put_contents($log_path, $DumpResultsJson);

            return strtolower($name) . "_" . strtolower($identifier) . "_" . $exception_id;
        }
    }
