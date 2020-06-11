<?php

    namespace MongoDB;

    $LocalDirectory = __DIR__ . DIRECTORY_SEPARATOR;

    include_once($LocalDirectory . 'Exception' . DIRECTORY_SEPARATOR . 'Exception.php');
    include_once($LocalDirectory . 'Exception' . DIRECTORY_SEPARATOR . 'RuntimeException.php');
    include_once($LocalDirectory . 'Exception' . DIRECTORY_SEPARATOR . 'BadMethodCallException.php');
    include_once($LocalDirectory . 'Exception' . DIRECTORY_SEPARATOR . 'InvalidArgumentException.php');
    include_once($LocalDirectory . 'Exception' . DIRECTORY_SEPARATOR . 'UnexpectedValueException.php');
    include_once($LocalDirectory . 'Exception' . DIRECTORY_SEPARATOR . 'UnsupportedException.php');

    include_once($LocalDirectory . 'GridFS' . DIRECTORY_SEPARATOR . 'Exception' . DIRECTORY_SEPARATOR . 'CorruptFileException.php');
    include_once($LocalDirectory . 'GridFS' . DIRECTORY_SEPARATOR . 'Exception' . DIRECTORY_SEPARATOR . 'FileNotFoundException.php');
    include_once($LocalDirectory . 'GridFS' . DIRECTORY_SEPARATOR . 'Bucket.php');
    include_once($LocalDirectory . 'GridFS' . DIRECTORY_SEPARATOR . 'CollectionWrapper.php');
    include_once($LocalDirectory . 'GridFS' . DIRECTORY_SEPARATOR . 'ReadableStream.php');
    include_once($LocalDirectory . 'GridFS' . DIRECTORY_SEPARATOR . 'StreamWrapper.php');
    include_once($LocalDirectory . 'GridFS' . DIRECTORY_SEPARATOR . 'WritableStream.php');

    include_once($LocalDirectory . 'Model' . DIRECTORY_SEPARATOR . 'CollectionInfoIterator.php');
    include_once($LocalDirectory . 'Model' . DIRECTORY_SEPARATOR . 'CollectionInfoCommandIterator.php');
    include_once($LocalDirectory . 'Model' . DIRECTORY_SEPARATOR . 'DatabaseInfoIterator.php');
    include_once($LocalDirectory . 'Model' . DIRECTORY_SEPARATOR . 'IndexInfoIterator.php');
    include_once($LocalDirectory . 'Model' . DIRECTORY_SEPARATOR . 'BSONArray.php');
    include_once($LocalDirectory . 'Model' . DIRECTORY_SEPARATOR . 'BSONDocument.php');
    include_once($LocalDirectory . 'Model' . DIRECTORY_SEPARATOR . 'BSONIterator.php');
    include_once($LocalDirectory . 'Model' . DIRECTORY_SEPARATOR . 'CachingIterator.php');
    include_once($LocalDirectory . 'Model' . DIRECTORY_SEPARATOR . 'ChangeStreamIterator.php');
    include_once($LocalDirectory . 'Model' . DIRECTORY_SEPARATOR . 'CollectionInfo.php');
    include_once($LocalDirectory . 'Model' . DIRECTORY_SEPARATOR . 'DatabaseInfo.php');
    include_once($LocalDirectory . 'Model' . DIRECTORY_SEPARATOR . 'DatabaseInfoLegacyIterator.php');
    include_once($LocalDirectory . 'Model' . DIRECTORY_SEPARATOR . 'IndexInfo.php');
    include_once($LocalDirectory . 'Model' . DIRECTORY_SEPARATOR . 'IndexInfoIteratorIterator.php');
    include_once($LocalDirectory . 'Model' . DIRECTORY_SEPARATOR . 'IndexInput.php');

    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'Executable.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'Explainable.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'Aggregate.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'BulkWrite.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'Count.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'CountDocuments.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'CreateCollection.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'CreateIndexes.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'DatabaseCommand.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'Delete.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'DeleteMany.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'DeleteOne.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'Distinct.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'DropCollection.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'DropDatabase.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'DropIndexes.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'EstimatedDocumentCount.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'Explain.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'Find.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'FindAndModify.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'FindOne.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'FindOneAndDelete.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'FindOneAndReplace.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'FindOneAndUpdate.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'InsertMany.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'InsertOne.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'ListCollections.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'ListDatabases.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'ListIndexes.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'MapReduce.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'ModifyCollection.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'ReplaceOne.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'Update.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'UpdateMany.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'UpdateOne.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'Watch.php');
    include_once($LocalDirectory . 'Operation' . DIRECTORY_SEPARATOR . 'WithTransaction.php');

    include_once($LocalDirectory . 'BulkWriteResult.php');
    include_once($LocalDirectory . 'ChangeStream.php');
    include_once($LocalDirectory . 'Client.php');
    include_once($LocalDirectory . 'Collection.php');
    include_once($LocalDirectory . 'Database.php');
    include_once($LocalDirectory . 'DeleteResult.php');
    include_once($LocalDirectory . 'functions.php');
    include_once($LocalDirectory . 'InsertManyResult.php');
    include_once($LocalDirectory . 'InsertOneResult.php');
    include_once($LocalDirectory . 'MapReduceResult.php');
    include_once($LocalDirectory . 'UpdateResult.php');