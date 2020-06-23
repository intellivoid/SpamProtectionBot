<?php

    use Longman\TelegramBot\Exception\TelegramException;

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
    }
    catch (Longman\TelegramBot\Exception\TelegramException $e)
    {
        print("Telegram Exception" . PHP_EOL);
        var_dump($e);
        exit(255);
    }

    $telegram->useGetUpdatesWithoutDatabase();

    SpamProtectionBot::$BackgroundWorker = new \BackgroundWorker\BackgroundWorker();
    SpamProtectionBot::getBackgroundWorker()->getClient()->addServer();
    SpamProtectionBot::getBackgroundWorker()->getSupervisor()->restartWorkers(
        __DIR__ . DIRECTORY_SEPARATOR . 'worker.php', TELEGRAM_BOT_NAME, 10
    );

    while(true)
    {
        try
        {
            print("Processing updates" . PHP_EOL);
            $server_response = $telegram->handleGetUpdates();
            $current_timestamp = date('[Y-m-d H:i:s]', time());
            if ($server_response->isOk())
            {
                if(count($server_response->getResult()) > 0)
                {
                    $Event = $current_timestamp . ' - Processed ' . count($server_response->getResult()) . ' updates';
                    print($Event . PHP_EOL);
                }
            }
            else
            {
                print($current_timestamp . ' - Failed to fetch updates' . PHP_EOL);
                print($server_response->printError());
            }
        }
        catch (TelegramException $e)
        {
            print("Telegram Exception" . PHP_EOL);
            var_dump($e);
        }
    }