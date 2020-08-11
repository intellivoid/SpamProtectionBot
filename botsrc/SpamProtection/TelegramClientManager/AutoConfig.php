<?php

    use acm\acm;
    use acm\Objects\Schema;

    /**
     * ACM AutoConfig file for Telegram Client Manager
     */

    if(class_exists('acm\acm') == false)
    {
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'acm' . DIRECTORY_SEPARATOR . 'acm.php');
    }

    $acm = new acm(__DIR__, 'Telegram Client Manager');

    // Database Schema Configuration
    $DatabaseSchema = new Schema();
    $DatabaseSchema->setDefinition('Host', 'localhost');
    $DatabaseSchema->setDefinition('Port', '3306');
    $DatabaseSchema->setDefinition('Username', 'root');
    $DatabaseSchema->setDefinition('Password', '');
    $DatabaseSchema->setDefinition('Name', 'intellivoid');
    $acm->defineSchema('Database', $DatabaseSchema);

    // If auto-loaded via CLI, Process any arguments passed to the main execution point
    $acm->processCommandLine();