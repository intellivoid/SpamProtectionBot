<?php

    use acm\acm;
    use acm\Objects\Schema;

    if(class_exists('acm\acm') == false)
    {
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'acm' . DIRECTORY_SEPARATOR . 'acm.php');
    }

    $acm = new acm(__DIR__, 'CoffeeHouse');

    $DatabaseSchema = new Schema();
    $DatabaseSchema->setDefinition('Host', 'localhost');
    $DatabaseSchema->setDefinition('Port', '3306');
    $DatabaseSchema->setDefinition('Username', 'admin');
    $DatabaseSchema->setDefinition('Password', 'admin');
    $DatabaseSchema->setDefinition('Name', 'coffeehouse');
    $acm->defineSchema('Database', $DatabaseSchema);

    $ServerSchema = new Schema();
    $ServerSchema->setDefinition('Host', '192.168.0.107');
    $ServerSchema->setDefinition('SpamPredictionPort', '5601');
    $acm->defineSchema('CoffeeHouseServer', $ServerSchema);

    $acm->processCommandLine();