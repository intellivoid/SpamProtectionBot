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

    /** @noinspection PhpUnhandledExceptionInspection */
    $TelegramServiceConfiguration = SpamProtectionBot::getTelegramConfiguration();

    /** @noinspection PhpUnhandledExceptionInspection */
    $DatabaseConfiguration = SpamProtectionBot::getDatabaseConfiguration();

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
        print("Telegram Exception" . PHP_EOL);
        var_dump($e);
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

    // Create the database connections
    SpamProtectionBot::$TelegramClientManager = new TelegramClientManager();
    SpamProtectionBot::$SpamProtection = new SpamProtection();
    SpamProtectionBot::$DeepAnalytics = new DeepAnalytics();
    SpamProtectionBot::$CoffeeHouse = new CoffeeHouse();

    $BackgroundWorker = new BackgroundWorker();
    $BackgroundWorker->getWorker()->addServer("127.0.0.1", 4730);
    $BackgroundWorker->getWorker()->getGearmanWorker()->addFunction("process_batch", function(GearmanJob $job) use ($telegram){
        $ServerResponse = new ServerResponse(
            json_decode($job->workload(), true), TELEGRAM_BOT_NAME
        );

        if(count($ServerResponse->getResult()) > 0)
        {
            /** @var Update $result */
            foreach ($ServerResponse->getResult() as $result)
            {
                try
                {
                    $telegram->processUpdate($result);
                }
                catch(Exception $e)
                {
                    unset($e);
                }
            }
        }
    });

    $BackgroundWorker->getWorker()->work();