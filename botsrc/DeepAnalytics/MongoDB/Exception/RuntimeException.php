<?php


    namespace MongoDB\Exception;

    use MongoDB\Driver\Exception\RuntimeException as DriverRuntimeException;

    class RuntimeException extends DriverRuntimeException implements Exception
    {
    }
