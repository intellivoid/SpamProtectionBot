<?php


    namespace CoffeeHouse\Managers;


    use CoffeeHouse\Abstracts\ForeignSessionSearchMethod;
    use CoffeeHouse\Classes\Hashing;
    use CoffeeHouse\CoffeeHouse;
    use CoffeeHouse\Exceptions\DatabaseException;
    use CoffeeHouse\Exceptions\ForeignSessionNotFoundException;
    use CoffeeHouse\Exceptions\InvalidSearchMethodException;
    use CoffeeHouse\Objects\ForeignSession;
    use msqg\QueryBuilder;
    use ZiProto\ZiProto;

    /**
     * Class ForeignSessionsManager
     * @package CoffeeHouse\Managers
     */
    class ForeignSessionsManager
    {

        /**
         * @var CoffeeHouse
         */
        private $coffeeHouse;

        /**
         * ForeignSessionsManager constructor.
         * @param CoffeeHouse $coffeeHouse
         */
        public function __construct(CoffeeHouse $coffeeHouse)
        {

            $this->coffeeHouse = $coffeeHouse;
        }


        /**
         * Creates a new foreign session
         *
         * @param string $language
         * @return ForeignSession
         * @throws DatabaseException
         * @throws ForeignSessionNotFoundException
         * @throws InvalidSearchMethodException
         */
        public function createSession(string $language): ForeignSession
        {
            $created = (int)time();
            $last_updated = $created;
            $session_id = Hashing::foreignSessionId($language, $created);
            $session_id = $this->coffeeHouse->getDatabase()->real_escape_string($session_id);
            $headers = $this->coffeeHouse->getDatabase()->real_escape_string(ZiProto::encode(array()));
            $cookies = $this->coffeeHouse->getDatabase()->real_escape_string(ZiProto::encode(array()));
            $variables = $this->coffeeHouse->getDatabase()->real_escape_string(ZiProto::encode(array()));
            $language = $this->coffeeHouse->getDatabase()->real_escape_string($language);
            $available = (int)true;
            $messages = 0;
            $expires = $created + 10800;

           $Query = QueryBuilder::insert_into('foreign_sessions', array(
                'session_id' => $session_id,
                'headers' => $headers,
                'cookies' => $cookies,
                'variables' => $variables,
                'language' => $language,
                'available' => $available,
                'messages' => $messages,
                'expires' => $expires,
                'last_updated' => $last_updated,
                'created' => $created
            ));
            $QueryResults = $this->coffeeHouse->getDatabase()->query($Query);
            if($QueryResults)
            {
                return($this->getSession(ForeignSessionSearchMethod::bySessionId, $session_id));
            }
            else
            {
                throw new DatabaseException($this->coffeeHouse->getDatabase()->error);
            }
        }

        /**
         * Gets an existing Foreign Session from the database
         *
         * @param string $search_method
         * @param string $value
         * @return ForeignSession
         * @throws DatabaseException
         * @throws ForeignSessionNotFoundException
         * @throws InvalidSearchMethodException
         */
        public function getSession(string $search_method, string $value): ForeignSession
        {
            switch($search_method)
            {
                case ForeignSessionSearchMethod::byId:
                    $search_method = $this->coffeeHouse->getDatabase()->real_escape_string($search_method);
                    $value = (int)$value;
                    break;

                case ForeignSessionSearchMethod::bySessionId:
                    $search_method = $this->coffeeHouse->getDatabase()->real_escape_string($search_method);
                    $value =  $this->coffeeHouse->getDatabase()->real_escape_string($value);
                    break;

                default:
                    throw new InvalidSearchMethodException();
            }

            $Query = QueryBuilder::select('foreign_sessions', [
                'id',
                'session_id',
                'headers',
                'cookies',
                'variables',
                'language',
                'available',
                'messages',
                'expires',
                'last_updated',
                'created'
            ], $search_method, $value);
            $QueryResults = $this->coffeeHouse->getDatabase()->query($Query);

            if($QueryResults)
            {
                $Row = $QueryResults->fetch_array(MYSQLI_ASSOC);

                if ($Row == False)
                {
                    throw new ForeignSessionNotFoundException();
                }
                else
                {
                    $Row['headers'] = ZiProto::decode($Row['headers']);
                    $Row['cookies'] = ZiProto::decode($Row['cookies']);
                    $Row['variables'] = ZiProto::decode($Row['variables']);
                    return(ForeignSession::fromArray($Row));
                }
            }
            else
            {
                throw new DatabaseException($this->coffeeHouse->getDatabase()->error);
            }
        }

        /**
         * Updates an existing Foreign Session
         *
         * @param ForeignSession $foreignSession
         * @return bool
         * @throws DatabaseException
         */
        public function updateSession(ForeignSession $foreignSession): bool
        {
            $id = (int)$foreignSession->ID;
            $headers = $this->coffeeHouse->getDatabase()->real_escape_string(ZiProto::encode($foreignSession->Headers));
            $cookies = $this->coffeeHouse->getDatabase()->real_escape_string(ZiProto::encode($foreignSession->Cookies));
            $variables = $this->coffeeHouse->getDatabase()->real_escape_string(ZiProto::encode($foreignSession->Variables));
            $language = $this->coffeeHouse->getDatabase()->real_escape_string($foreignSession->Language);
            $available = (int)$foreignSession->Available;
            $messages = (int)$foreignSession->Messages;
            $last_updated = (int)time();

            $Query = QueryBuilder::update('foreign_sessions', array(
                'headers' => $headers,
                'cookies' => $cookies,
                'variables' => $variables,
                'language' => $language,
                'available' => $available,
                'messages' => $messages,
                'last_updated' => $last_updated
            ), 'id', $id);
            $QueryResults = $this->coffeeHouse->getDatabase()->query($Query);

            if($QueryResults)
            {
                return(True);
            }
            else
            {
                throw new DatabaseException($this->coffeeHouse->getDatabase()->error);
            }
        }
    }