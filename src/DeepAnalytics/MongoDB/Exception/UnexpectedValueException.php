<?php


    namespace MongoDB\Exception;

    use MongoDB\Driver\Exception\UnexpectedValueException as DriverUnexpectedValueException;

    class UnexpectedValueException extends DriverUnexpectedValueException implements Exception
    {
    }
