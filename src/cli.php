#!/usr/bin/env php
<?php

    use acm\acm;
    use acm\Objects\Schema;
    use Longman\TelegramBot\Exception\TelegramException;

    require __DIR__ . '/vendor/autoload.php';
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'CoffeeHouse' . DIRECTORY_SEPARATOR . 'CoffeeHouse.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'SpamProtection' . DIRECTORY_SEPARATOR . 'SpamProtection.php');

    if(class_exists('DeepAnalytics\DeepAnalytics') == false)
    {
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'DeepAnalytics' . DIRECTORY_SEPARATOR . 'DeepAnalytics.php');
    }

    $acm = new acm(__DIR__, 'SpamProtectionBot');

    $TelegramSchema = new Schema();
    $TelegramSchema->setDefinition('BotName', '<BOT NAME HERE>');
    $TelegramSchema->setDefinition('BotToken', '<BOT TOKEN>');
    $TelegramSchema->setDefinition('BotEnabled', 'true');
    $TelegramSchema->setDefinition('WebHook', 'http://localhost');
    $TelegramSchema->setDefinition('MaxConnections', '100');
    $acm->defineSchema('TelegramService', $TelegramSchema);

    $DatabaseSchema = new Schema();
    $DatabaseSchema->setDefinition('Host', '127.0.0.1');
    $DatabaseSchema->setDefinition('Port', '3306');
    $DatabaseSchema->setDefinition('Username', 'root');
    $DatabaseSchema->setDefinition('Password', 'admin');
    $DatabaseSchema->setDefinition('Database', 'telegram');
    $acm->defineSchema('Database', $DatabaseSchema);

    $TelegramServiceConfiguration = $acm->getConfiguration('TelegramService');
    $DatabaseConfiguration = $acm->getConfiguration('Database');

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

    while(true)
    {
        try
        {
            $server_response = $telegram->handleGetUpdates();
            $current_timestamp = date('[Y-m-d H:i:s]', time());
            if ($server_response->isOk())
            {
                print($current_timestamp . ' - Processed ' . count($server_response->getResult()) . ' updates' . PHP_EOL);
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