<?php

    use acm\acm;
    use acm\Objects\Schema;

    if(class_exists('acm\acm') == false)
    {
        include_once(__DIR__ . DIRECTORY_SEPARATOR . 'acm' . DIRECTORY_SEPARATOR . 'acm.php');
    }

    $acm = new acm(__DIR__, 'deep_analytics');

    $MongoDbSchema = new Schema();
    $MongoDbSchema->setDefinition('Host', '127.0.0.1');
    $MongoDbSchema->setDefinition('Port', '27017');
    $MongoDbSchema->setDefinition('Username', '');
    $MongoDbSchema->setDefinition('Password', '');
    $MongoDbSchema->setDefinition('Database', 'deep_analytics');
    $acm->defineSchema('MongoDB', $MongoDbSchema);

    $acm->processCommandLine();