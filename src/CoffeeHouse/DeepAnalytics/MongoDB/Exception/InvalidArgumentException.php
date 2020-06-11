<?php


    namespace MongoDB\Exception;

    use MongoDB\Driver\Exception\InvalidArgumentException as DriverInvalidArgumentException;
    use function array_pop;
    use function count;
    use function get_class;
    use function gettype;
    use function implode;
    use function is_array;
    use function is_object;
    use function sprintf;

    /**
     * Class InvalidArgumentException
     * @package MongoDB\Exception
     */
    class InvalidArgumentException extends DriverInvalidArgumentException implements Exception
    {
        /**
         * Thrown when an argument or option has an invalid type.
         *
         * @param string          $name         Name of the argument or option
         * @param mixed           $value        Actual value (used to derive the type)
         * @param string|string[] $expectedType Expected type
         * @return self
         */
        public static function invalidType($name, $value, $expectedType)
        {
            if (is_array($expectedType)) {
                switch (count($expectedType)) {
                    case 1:
                        $typeString = array_pop($expectedType);
                        break;

                    case 2:
                        $typeString = implode('" or "', $expectedType);
                        break;

                    default:
                        $lastType = array_pop($expectedType);
                        $typeString = sprintf('%s", or "%s', implode('", "', $expectedType), $lastType);
                        break;
                }

                $expectedType = $typeString;
            }

            return new static(sprintf('Expected %s to have type "%s" but found "%s"', $name, $expectedType, is_object($value) ? get_class($value) : gettype($value)));
        }
    }
