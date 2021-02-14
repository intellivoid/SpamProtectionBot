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
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Entities\Update;
use ppm\ppm;
use SpamProtection\SpamProtection;
    use TelegramClientManager\TelegramClientManager;
use VerboseAdventure\Abstracts\EventType;
use VerboseAdventure\Classes\ErrorHandler;
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
    /** @noinspection PhpUnhandledExceptionInspection */
    ppm::import("net.intellivoid.tdlib");


    $current_directory = getcwd();
    VerboseAdventure::setStdout(true); // Enable stdout
    ErrorHandler::registerHandlers(); // Register error handlers

    if(class_exists("SpamProtectionBot") == false)
    {
        if(file_exists($current_directory . DIRECTORY_SEPARATOR . 'SpamProtectionBot.php'))
        {
            require_once($current_directory . DIRECTORY_SEPARATOR . 'SpamProtectionBot.php');
        }
        elseif(file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'SpamProtectionBot.php'))
        {
            require_once(__DIR__ . DIRECTORY_SEPARATOR . 'SpamProtectionBot.php');
        }
        else
        {
            throw new RuntimeException("Cannot locate bot class");
        }
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
    define("LOG_CHANNEL", "DebuggingChannel");
    define("MAIN_OPERATOR_USERNAME", "Netkas");
    SpamProtectionBot::setLogHandler(new VerboseAdventure(TELEGRAM_BOT_NAME));
    SpamProtectionBot::setLastWorkerActivity((int)time());
    SpamProtectionBot::setIsSleeping(false);

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

        if(file_exists($current_directory . DIRECTORY_SEPARATOR . 'SpamProtectionBot.php'))
        {
            $telegram->addCommandsPaths([$current_directory . DIRECTORY_SEPARATOR . 'commands']);
        }
        elseif(file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'SpamProtectionBot.php'))
        {
            $telegram->addCommandsPaths([__DIR__ . DIRECTORY_SEPARATOR . 'commands']);
        }
        else
        {
            print("Cannot locate commands path");
            exit(1);
        }

        \Longman\TelegramBot\TelegramLog::initialize();
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
    $BackgroundWorker->getWorker()->getGearmanWorker()->addFunction($telegram->getBotUsername() . "_updates", function(GearmanJob $job) use ($telegram)
    {
        try
        {
            SpamProtectionBot::setLastWorkerActivity((int)time()); // Set the last activity timestamp
            SpamProtectionBot::processSleepCycle(); // Wake worker if it's sleeping

            $ServerResponse = new ServerResponse(json_decode($job->workload(), true), TELEGRAM_BOT_NAME);
            if(is_null($ServerResponse->getResult()) == false)
            {
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

        }
        catch(Exception $e)
        {
            SpamProtectionBot::getLogHandler()->logException($e, "Worker");
        }

    });

    // Start working
    SpamProtectionBot::getLogHandler()->log(EventType::INFO, "Worker started successfully", "Worker");

    // Set the timeout to 5 seconds
    $BackgroundWorker->getWorker()->getGearmanWorker()->setTimeout(500);

    while(true)
    {
        while(
            @$BackgroundWorker->getWorker()->getGearmanWorker()->work() ||
            $BackgroundWorker->getWorker()->getGearmanWorker()->returnCode() == GEARMAN_TIMEOUT
        )
        {

            if ($BackgroundWorker->getWorker()->getGearmanWorker()->returnCode() == GEARMAN_TIMEOUT)
            {
                SpamProtectionBot::processSleepCycle(); // Go to sleep if there's no activity
                continue;
            }

            if ($BackgroundWorker->getWorker()->getGearmanWorker()->returnCode() != GEARMAN_SUCCESS)
            {
                SpamProtectionBot::getLogHandler()->log(EventType::WARNING, "Gearman returned error code " . $BackgroundWorker->getWorker()->getGearmanWorker()->returnCode(), "Worker");
                break;
            }
        }
    }


