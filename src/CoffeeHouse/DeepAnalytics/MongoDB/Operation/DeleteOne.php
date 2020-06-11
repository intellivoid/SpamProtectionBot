<?php

    namespace MongoDB\Operation;

    use MongoDB\DeleteResult;
    use MongoDB\Driver\Exception\RuntimeException as DriverRuntimeException;
    use MongoDB\Driver\Server;
    use MongoDB\Exception\InvalidArgumentException;
    use MongoDB\Exception\UnsupportedException;

    /**
     * Operation for deleting a single document with the delete command.
     *
     * @api
     * @see \MongoDB\Collection::deleteOne()
     * @see http://docs.mongodb.org/manual/reference/command/delete/
     */
    class DeleteOne implements Executable, Explainable
    {
        /** @var Delete */
        private $delete;

        /**
         * Constructs a delete command.
         *
         * Supported options:
         *
         *  * collation (document): Collation specification.
         *
         *    This is not supported for server versions < 3.4 and will result in an
         *    exception at execution time if used.
         *
         *  * session (MongoDB\Driver\Session): Client session.
         *
         *    Sessions are not supported for server versions < 3.6.
         *
         *  * writeConcern (MongoDB\Driver\WriteConcern): Write concern.
         *
         * @param string       $databaseName   Database name
         * @param string       $collectionName Collection name
         * @param array|object $filter         Query by which to delete documents
         * @param array        $options        Command options
         * @throws InvalidArgumentException for parameter/option parsing errors
         */
        public function __construct($databaseName, $collectionName, $filter, array $options = [])
        {
            $this->delete = new Delete($databaseName, $collectionName, $filter, 1, $options);
        }

        /**
         * Execute the operation.
         *
         * @see Executable::execute()
         * @param Server $server
         * @return DeleteResult
         * @throws UnsupportedException if collation is used and unsupported
         * @throws DriverRuntimeException for other driver errors (e.g. connection errors)
         */
        public function execute(Server $server)
        {
            return $this->delete->execute($server);
        }

        public function getCommandDocument(Server $server)
        {
            return $this->delete->getCommandDocument($server);
        }
    }
