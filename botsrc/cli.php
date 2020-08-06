<?php

    /** @noinspection DuplicatedCode */


    /**
     * cli.php is the main execution point for the bot to start polling, this method uses BackgroundWorker to
     * instantly process a batch of updates in the background without waiting for the updates to be completed.
     *
     * In exchange for this performance upgrade, each worker will use up database connections, make sure
     * the database can handle these connections without maxing out
     */

    use BackgroundWorker\BackgroundWorker;
    use Longman\TelegramBot\Exception\TelegramException;

    // Import all required auto loaders
    if(defined("PPM") == false)
    {
        /** @noinspection PhpIncludeInspection */
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'SpamProtection' . DIRECTORY_SEPARATOR . 'SpamProtection.php');
        /** @noinspection PhpIncludeInspection */
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'BackgroundWorker' . DIRECTORY_SEPARATOR . 'BackgroundWorker.php');
    }
    else
    {
        \ppm\ppm::import("net.intellivoid.acm");
        \ppm\ppm::import("net.intellivoid.background_worker");
        \ppm\ppm::import("net.intellivoid.coffeehouse");
        \ppm\ppm::import("net.intellivoid.deepanalytics");
        \ppm\ppm::import("net.intellivoid.spam_protection");
    }

    require __DIR__ . '/vendor/autoload.php';
        if(class_exists("SpamProtectionBot") == false)
    {
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'SpamProtectionBot.php');
    }

    if(class_exists("TgFileLogging") == false)
    {
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'TgFileLogging.php');
    }

    // Load all configurations

    /** @noinspection PhpUnhandledExceptionInspection */
    $TelegramServiceConfiguration = SpamProtectionBot::getTelegramConfiguration();

    /** @noinspection PhpUnhandledExceptionInspection */
    $DatabaseConfiguration = SpamProtectionBot::getDatabaseConfiguration();

    /** @noinspection PhpUnhandledExceptionInspection */
    $BackgroundWorkerConfiguration = SpamProtectionBot::getBackgroundWorkerConfiguration();

    // Create the Telegram Bot instance (NO SQL)

    define("TELEGRAM_BOT_NAME", $TelegramServiceConfiguration['BotName'], false);

    if(strtolower($TelegramServiceConfiguration['BotName']) == 'true')
    {
        define("TELEGRAM_BOT_ENABLED", true);
    }
    else
    {
        define("TELEGRAM_BOT_ENABLED", false);
    }

    TgFileLogging::writeLog(TgFileLogging::INFO, TELEGRAM_BOT_NAME . "_main",
        "Starting Service"
    );
    
    try
    {
        $telegram = new Longman\TelegramBot\Telegram(
            $TelegramServiceConfiguration['BotToken'],
            $TelegramServiceConfiguration['BotName']
        );
    }
    catch (Longman\TelegramBot\Exception\TelegramException $e)
    {
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_main",
            "Telegram Exception Raised: " . $e->getMessage()
        );
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_main",
            "Line: " . $e->getLine()
        );
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_main",
            "File: " . $e->getFile()
        );
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_main",
            "Trace: " . json_encode($e->getTrace())
        );
        exit(255);
    }

    $telegram->useGetUpdatesWithoutDatabase();

    // Start the workers using the supervisor

    TgFileLogging::writeLog(TgFileLogging::INFO, TELEGRAM_BOT_NAME . "_main",
        "Starting Supervisor"
    );

    try
    {
        SpamProtectionBot::$BackgroundWorker = new BackgroundWorker();
        SpamProtectionBot::getBackgroundWorker()->getClient()->addServer(
            $BackgroundWorkerConfiguration["Host"],
            (int)$BackgroundWorkerConfiguration["Port"]
        );
        SpamProtectionBot::getBackgroundWorker()->getSupervisor()->restartWorkers(
            __DIR__ . DIRECTORY_SEPARATOR . 'worker.php', TELEGRAM_BOT_NAME,
            (int)$BackgroundWorkerConfiguration['MaxWorkers']
        );
    }
    catch(Exception $e)
    {
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_main",
            "Supervisor Exception Raised: " . $e->getMessage()
        );
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_main",
            "Line: " . $e->getLine()
        );
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_main",
            "File: " . $e->getFile()
        );
        TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_main",
            "Trace: " . json_encode($e->getTrace())
        );
        TgFileLogging::writeLog(TgFileLogging::WARNING, TELEGRAM_BOT_NAME . "_main",
            "Make sure Gearman is running!"
        );
        exit(255);
    }

    // Start listening to updates

    while(true)
    {
        try
        {
            TgFileLogging::writeLog(TgFileLogging::INFO, TELEGRAM_BOT_NAME . "_main",
                "Listening for updates"
            );
            $server_response = $telegram->handleBackgroundUpdates(SpamProtectionBot::getBackgroundWorker());
            if ($server_response->isOk())
            {
                $update_count = count($server_response->getResult());
                if($update_count > 0)
                {
                    if($update_count == 1)
                    {
                        TgFileLogging::writeLog(TgFileLogging::INFO, TELEGRAM_BOT_NAME . "_main",
                            "Processed $update_count update"
                        );
                    }
                    else
                    {
                        TgFileLogging::writeLog(TgFileLogging::INFO, TELEGRAM_BOT_NAME . "_main",
                            "Processed $update_count updates"
                        );
                    }
                }
            }
            else
            {
                TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_main",
                    "Failed to fetch updates: " . $server_response->printError(true)
                );
            }
        }
        catch (TelegramException $e)
        {
            TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_main",
                "Telegram Exception Raised: " . $e->getMessage()
            );
            TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_main",
                "Line: " . $e->getLine()
            );
            TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_main",
                "File: " . $e->getFile()
            );
            TgFileLogging::writeLog(TgFileLogging::ERROR, TELEGRAM_BOT_NAME . "_main",
                "Trace: " . json_encode($e->getTrace())
            );
        }
    }