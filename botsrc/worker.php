<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection DuplicatedCode */

    /**
     * worker.php is the code that the worker will execute whenever a job passed on from the main
     * bot. Starting the CLI will restart the workers that are already running in the background
     */

    use BackgroundWorker\BackgroundWorker;
    use CoffeeHouse\CoffeeHouse;
    use DeepAnalytics\DeepAnalytics;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Entities\Update;
    use SpamProtection\SpamProtection;
    use TelegramClientManager\TelegramClientManager;

    /** @noinspection PhpIncludeInspection */
    require("ppm");

    // Import all required auto loaders
    if(defined("PPM") == false)
    {
        /** @noinspection PhpIncludeInspection */
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'BackgroundWorker' . DIRECTORY_SEPARATOR . 'BackgroundWorker.php');
        /** @noinspection PhpIncludeInspection */
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'CoffeeHouse' . DIRECTORY_SEPARATOR . 'CoffeeHouse.php');
        /** @noinspection PhpIncludeInspection */
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'SpamProtection' . DIRECTORY_SEPARATOR . 'SpamProtection.php');

        if(class_exists('DeepAnalytics\DeepAnalytics') == false)
        {
            /** @noinspection PhpIncludeInspection */
            include_once(__DIR__ . DIRECTORY_SEPARATOR . 'DeepAnalytics' . DIRECTORY_SEPARATOR . 'DeepAnalytics.php');
        }
    }
    else
    {
        \ppm\ppm::import("net.intellivoid.acm");
        \ppm\ppm::import("net.intellivoid.background_worker");
        \ppm\ppm::import("net.intellivoid.coffeehouse");
        \ppm\ppm::import("net.intellivoid.deepanalytics");
        \ppm\ppm::import("net.intellivoid.spam_protection");
    }

    $current_directory = getcwd();

    if(file_exists($current_directory . DIRECTORY_SEPARATOR . "vendor") == false)
    {
        $current_directory = __DIR__;
    }

    if(file_exists($current_directory . DIRECTORY_SEPARATOR . "vendor") == false)
    {
        print("Cannot find vendor directory" . PHP_EOL);
        exit(255);
    }

    require($current_directory . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');

    if(class_exists("SpamProtectionBot") == false)
    {
        include_once($current_directory . DIRECTORY_SEPARATOR . 'SpamProtectionBot.php');
    }

    if(class_exists("TgFileLogging") == false)
    {
        include_once($current_directory . DIRECTORY_SEPARATOR . 'TgFileLogging.php');
    }

    // Load all required configurations

    /** @noinspection PhpUnhandledExceptionInspection */
    $TelegramServiceConfiguration = SpamProtectionBot::getTelegramConfiguration();

    /** @noinspection PhpUnhandledExceptionInspection */
    $DatabaseConfiguration = SpamProtectionBot::getDatabaseConfiguration();

    /** @noinspection PhpUnhandledExceptionInspection */
    $BackgroundWorkerConfiguration = SpamProtectionBot::getBackgroundWorkerConfiguration();

    // Define and create the Telegram Bot instance (SQL)

    define("TELEGRAM_BOT_NAME", $TelegramServiceConfiguration['BotName'], false);

    if(strtolower($TelegramServiceConfiguration['BotName']) == 'true')
    {
        define("TELEGRAM_BOT_ENABLED", true);
    }
    else
    {
        define("TELEGRAM_BOT_ENABLED", false);
    }

    try
    {
        $telegram = new Longman\TelegramBot\Telegram(
            $TelegramServiceConfiguration['BotToken'],
            $TelegramServiceConfiguration['BotName']
        );
        $telegram->addCommandsPaths([__DIR__ . DIRECTORY_SEPARATOR . 'commands']);
    }
    catch (Longman\TelegramBot\Exception\TelegramException $e)
    {
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
            "Telegram Exception Raised: " . $e->getMessage()
        );
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
            "Line: " . $e->getLine()
        );
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
            "File: " . $e->getFile()
        );
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
            "Trace: " . json_encode($e->getTrace())
        );
        exit(255);
    }

    try
    {
        $telegram->enableMySql(array(
            'host' => $DatabaseConfiguration['Host'],
            'port' => $DatabaseConfiguration['Port'],
            'user' => $DatabaseConfiguration['Username'],
            'password' => $DatabaseConfiguration['Password'],
            'database' => $DatabaseConfiguration['Database'],
        ));
    }
    catch(Exception $e)
    {
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
            "Telegram Database Exception Raised: " . $e->getMessage()
        );
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
            "Line: " . $e->getLine()
        );
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
            "File: " . $e->getFile()
        );
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
            "Trace: " . json_encode($e->getTrace())
        );
        exit(255);
    }

    // Start the worker instance

    TgFileLogging::writeLog(TgFileLogging::INFO, TELEGRAM_BOT_NAME . "_worker",
        "Starting worker"
    );

    SpamProtectionBot::$DeepAnalytics = new DeepAnalytics();

    // Create the database connections
    SpamProtectionBot::$TelegramClientManager = new TelegramClientManager();
    if(SpamProtectionBot::$TelegramClientManager->getDatabase()->connect_error)
    {
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
            "Failed to initialize TelegramClientManager, " .
            SpamProtectionBot::$TelegramClientManager->getDatabase()->connect_error
        );

        exit(255);
    }

    SpamProtectionBot::$SpamProtection = new SpamProtection();
    if(SpamProtectionBot::$SpamProtection->getDatabase()->connect_error)
    {
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
            "Failed to initialize SpamProtection, " .
            SpamProtectionBot::$SpamProtection->getDatabase()->connect_error
        );

        exit(255);
    }

    SpamProtectionBot::$CoffeeHouse = new CoffeeHouse();
    if(SpamProtectionBot::$CoffeeHouse->getDatabase()->connect_error)
    {
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
            "Failed to initialize CoffeeHouse, " .
            SpamProtectionBot::$CoffeeHouse->getDatabase()->connect_error
        );

        exit(255);
    }

    try
    {
        $BackgroundWorker = new BackgroundWorker();
        $BackgroundWorker->getWorker()->addServer(
            $BackgroundWorkerConfiguration["Host"],
            (int)$BackgroundWorkerConfiguration["Port"]
        );
    }
    catch(Exception $e)
    {
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
            "BackgroundWorker Exception Raised: " . $e->getMessage()
        );
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
            "Line: " . $e->getLine()
        );
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
            "File: " . $e->getFile()
        );
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
            "Trace: " . json_encode($e->getTrace())
        );
        TgFileLogging::writeLog(TgFileLogging::WARNING, TELEGRAM_BOT_NAME . "_worker",
            "Make sure Gearman is running!"
        );
        exit(255);
    }

    // Define the function "process_batch" to process a batch of Updates from Telegram in the background
    $BackgroundWorker->getWorker()->getGearmanWorker()->addFunction("process_batch", function(GearmanJob $job) use ($telegram)
    {
        try
        {
            $ServerResponse = new ServerResponse(json_decode($job->workload(), true), TELEGRAM_BOT_NAME);
            $UpdateCount = count($ServerResponse->getResult());

            if($UpdateCount > 0)
            {
                if($UpdateCount == 1)
                {
                    TgFileLogging::writeLog(TgFileLogging::INFO, TELEGRAM_BOT_NAME . "_worker",
                        "Processing one update"
                    );
                }
                else
                {
                    TgFileLogging::writeLog(TgFileLogging::INFO, TELEGRAM_BOT_NAME . "_worker",
                        "Processing batch of $UpdateCount updates"
                    );
                }

                /** @var Update $result */
                foreach ($ServerResponse->getResult() as $result)
                {
                    try
                    {
                        TgFileLogging::writeLog(TgFileLogging::INFO, TELEGRAM_BOT_NAME . "_worker",
                            "Processing update " . $result->getUpdateId()
                        );
                        $telegram->processUpdate($result);
                    }
                    catch(Exception $e)
                    {
                        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
                            "Exception Raised: " . $e->getMessage()
                        );
                        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
                            "Line: " . $e->getLine()
                        );
                        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
                            "File: " . $e->getFile()
                        );
                        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
                            "Trace: " . json_encode($e->getTrace())
                        );
                    }
                }
            }
        }
        catch(Exception $e)
        {
            TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
                "Worker Exception Raised: " . $e->getMessage()
            );
            TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
                "Line: " . $e->getLine()
            );
            TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
                "File: " . $e->getFile()
            );
            TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
                "Trace: " . json_encode($e->getTrace())
            );
        }

    });

    // Start working

    TgFileLogging::writeLog(TgFileLogging::INFO, TELEGRAM_BOT_NAME . "_worker",
        "Worker started successfully"
    );

    while(true)
    {
        try
        {
            $BackgroundWorker->getWorker()->work();
        }
        catch(Exception $e)
        {
            TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
                "Worker Exception Raised: " . $e->getMessage()
            );
            TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
                "Line: " . $e->getLine()
            );
            TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
                "File: " . $e->getFile()
            );
            TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_worker",
                "Trace: " . json_encode($e->getTrace())
            );
        }
    }
