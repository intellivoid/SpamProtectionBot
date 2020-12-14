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
use ppm\ppm;
use SpamProtection\SpamProtection;
    use TelegramClientManager\TelegramClientManager;
use VerboseAdventure\Abstracts\EventType;
use VerboseAdventure\VerboseAdventure;

// Import all required auto loaders
    /** @noinspection PhpIncludeInspection */
    require("ppm");

    /** @noinspection PhpUnhandledExceptionInspection */
    ppm::import("net.intellivoid.acm");
    /** @noinspection PhpUnhandledExceptionInspection */
    ppm::import("net.intellivoid.background_worker");
    /** @noinspection PhpUnhandledExceptionInspection */
    ppm::import("net.intellivoid.coffeehouse");
    /** @noinspection PhpUnhandledExceptionInspection */
    ppm::import("net.intellivoid.deepanalytics");
    /** @noinspection PhpUnhandledExceptionInspection */
    ppm::import("net.intellivoid.telegram_client_manager");
    /** @noinspection PhpUnhandledExceptionInspection */
    ppm::import("net.intellivoid.spam_protection");
    /** @noinspection PhpUnhandledExceptionInspection */
    ppm::import("net.intellivoid.pop");
    /** @noinspection PhpUnhandledExceptionInspection */
    ppm::import("net.intellivoid.msqg");
    /** @noinspection PhpUnhandledExceptionInspection */
    ppm::import("net.intellivoid.ziproto");
    /** @noinspection PhpUnhandledExceptionInspection */
    ppm::import("net.intellivoid.verbose_adventure");

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

    // Load all required configurations

    /** @noinspection PhpUnhandledExceptionInspection */
    $TelegramServiceConfiguration = SpamProtectionBot::getTelegramConfiguration();

    /** @noinspection PhpUnhandledExceptionInspection */
    $DatabaseConfiguration = SpamProtectionBot::getDatabaseConfiguration();

    /** @noinspection PhpUnhandledExceptionInspection */
    $BackgroundWorkerConfiguration = SpamProtectionBot::getBackgroundWorkerConfiguration();

    // Define and create the Telegram Bot instance (SQL)

    define("TELEGRAM_BOT_NAME", $TelegramServiceConfiguration['BotName']);
    define("LOG_CHANNEL", "SpamProtectionLogs");
    define("MAIN_OPERATOR_USERNAME", "IntellivoidSupport");
    SpamProtectionBot::setLogHandler(new VerboseAdventure(TELEGRAM_BOT_NAME));

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
        $telegram->addCommandsPaths([$current_directory . DIRECTORY_SEPARATOR . 'commands']);
    }
    catch (Longman\TelegramBot\Exception\TelegramException $e)
    {
        SpamProtectionBot::getLogHandler()->logException($e, "Worker");
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
        SpamProtectionBot::getLogHandler()->logException($e, "Worker");
        exit(255);
    }

    // Start the worker instance
    SpamProtectionBot::getLogHandler()->log(EventType::INFO, "Starting Worker", "Worker");
    SpamProtectionBot::$DeepAnalytics = new DeepAnalytics();

    // Create the database connections
    SpamProtectionBot::$TelegramClientManager = new TelegramClientManager();
    if(SpamProtectionBot::$TelegramClientManager->getDatabase()->connect_error)
    {
        SpamProtectionBot::getLogHandler()->log(EventType::ERROR, "Failed to initialize TelegramClientManager, " . SpamProtectionBot::$TelegramClientManager->getDatabase()->connect_error, "Worker");
        exit(255);
    }

    SpamProtectionBot::$SpamProtection = new SpamProtection();
    if(SpamProtectionBot::$SpamProtection->getDatabase()->connect_error)
    {
        SpamProtectionBot::getLogHandler()->log(EventType::ERROR, "Failed to initialize SpamProtection, " . SpamProtectionBot::$SpamProtection->getDatabase()->connect_error, "Worker");
        exit(255);
    }

    SpamProtectionBot::$CoffeeHouse = new CoffeeHouse();
    if(SpamProtectionBot::$CoffeeHouse->getDatabase()->connect_error)
    {
        SpamProtectionBot::getLogHandler()->log(EventType::ERROR, "Failed to initialize CoffeeHouse, " . SpamProtectionBot::$CoffeeHouse->getDatabase()->connect_error, "Worker");
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
        SpamProtectionBot::getLogHandler()->logException($e, "Worker");
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
                SpamProtectionBot::getLogHandler()->log(EventType::INFO, "Processing $UpdateCount update(s)", "Worker");

                /** @var Update $result */
                foreach ($ServerResponse->getResult() as $result)
                {
                    try
                    {
                        SpamProtectionBot::getLogHandler()->log(EventType::INFO, "Processing update ID " . $result->getUpdateId(), "Worker");
                        $telegram->processUpdate($result);
                    }
                    catch(Exception $e)
                    {
                        SpamProtectionBot::getLogHandler()->logException($e, "Worker");
                    }
                }
            }
        }
        catch(Exception $e)
        {
            SpamProtectionBot::getLogHandler()->logException($e, "Worker");
        }

    });

    // Start working
    SpamProtectionBot::getLogHandler()->log(EventType::INFO, "Worker started successfully", "Worker");

    while(true)
    {
        try
        {
            $BackgroundWorker->getWorker()->work();
        }
        catch(Exception $e)
        {
            SpamProtectionBot::getLogHandler()->logException($e, "Worker");
        }
    }
