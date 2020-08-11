<?php

    namespace MongoDB\Operation;

    use MongoDB\Driver\Server;

    /**
     * Explainable interface for explainable operations (count, distinct, find,
     * findAndModify, delete, and update).
     *
     * @internal
     */
    interface Explainable extends Executable
    {
        public function getCommandDocument(Server $server);
    }
