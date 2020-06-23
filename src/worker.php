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

    require __DIR__ . '/vendor/autoload.php';
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'BackgroundWorker' . DIRECTORY_SEPARATOR . 'BackgroundWorker.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'CoffeeHouse' . DIRECTORY_SEPARATOR . 'CoffeeHouse.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'SpamProtection' . DIRECTORY_SEPARATOR . 'SpamProtection.php');

    if(class_exists('DeepAnalytics\DeepAnalytics') == false)
    {
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'DeepAnalytics' . DIRECTORY_SEPARATOR . 'DeepAnalytics.php');
    }

    if(class_exists("SpamProtectionBot") == false)
    {
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'SpamProtectionBot.php');
    }

    if(class_exists("TgFileLogging") == false)
    {
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'TgFileLogging.php');
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    $TelegramServiceConfiguration = SpamProtectionBot::getTelegramConfiguration();

    /** @noinspection PhpUnhandledExceptionInspection */
    $DatabaseConfiguration = SpamProtectionBot::getDatabaseConfiguration();

    /** @noinspection PhpUnhandledExceptionInspection */
    $BackgroundWorkerConfiguration = SpamProtectionBot::getBackgroundWorkerConfiguration();

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
        print("Database Exception" . PHP_EOL);
        var_dump($e);
    }

    TgFileLogging::writeLog(TgFileLogging::INFO, TELEGRAM_BOT_NAME . "_worker",
        "Starting worker"
    );

    // Create the database connections
    SpamProtectionBot::$TelegramClientManager = new TelegramClientManager();
    SpamProtectionBot::$SpamProtection = new SpamProtection();
    SpamProtectionBot::$DeepAnalytics = new DeepAnalytics();
    SpamProtectionBot::$CoffeeHouse = new CoffeeHouse();

    $BackgroundWorker = new BackgroundWorker();
    $BackgroundWorker->getWorker()->addServer(
        $BackgroundWorkerConfiguration["host"],
        (int)$BackgroundWorkerConfiguration["port"]
    );
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
