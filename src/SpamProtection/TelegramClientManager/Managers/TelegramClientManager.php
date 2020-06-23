<?php


    namespace TelegramClientManager\Managers;

    use msqg\QueryBuilder;
    use TelegramClientManager\Abstracts\SearchMethods\TelegramClientSearchMethod;
    use TelegramClientManager\Abstracts\TelegramChatType;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;
    use TelegramClientManager\Objects\TelegramClient;
    use TelegramClientManager\Objects\TelegramClient\Chat;
    use TelegramClientManager\Objects\TelegramClient\User;
    use TelegramClientManager\Utilities\Hashing;
    use ZiProto\ZiProto;

    /**
     * Class TelegramClientManager
     * @package TelegramClientManager\Managers
     */
    class TelegramClientManager
    {
        /**
         * @var TelegramClientManager
         */
        private $telegramClientManager;

        /**
         * TelegramClientManager constructor.
         * @param \TelegramClientManager\TelegramClientManager $telegramClientManager
         */
        public function __construct(\TelegramClientManager\TelegramClientManager $telegramClientManager)
        {
            $this->telegramClientManager = $telegramClientManager;
        }

        /**
         * Full registration command, returns an array of a structure of ("CLIENT", "CHAT" & "USER")
         *
         * @param Chat $chat
         * @param User $user
         * @param bool $return_public_id
         * @return array
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @noinspection PhpUnused
         */
        public function register(Chat $chat, User $user, bool $return_public_id=false): array
        {
            $Results = array();

            $Results['CLIENT'] = $this->registerClient($chat, $user, $return_public_id);
            $Results['USER'] = $this->registerUser($user, $return_public_id);
            $Results['CHAT'] = $this->registerChat($chat, $return_public_id);

            return $Results;
        }

        /**
         * Registers a new Telegram Client into the database
         *
         * @param Chat $chat
         * @param User $user
         * @param bool $return_public_id
         * @return TelegramClient|string
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         */
        public function registerClient(Chat $chat, User $user, bool $return_public_id=false)
        {
            $CurrentTime = (int)time();
            $PublicID = Hashing::telegramClientPublicID($chat->ID, $user->ID);

            try
            {
                // Make sure duplicate usernames are not possible
                $ExistingClient = $this->getClient(TelegramClientSearchMethod::byPublicId, $PublicID);

                $UpdateRequired = false;

                if($chat->getUniqueHash() !== $ExistingClient->Chat->getUniqueHash())
                {
                    $UpdateRequired = true;
                }

                if($user->getUniqueHash() !== $ExistingClient->User->getUniqueHash())
                {
                    $UpdateRequired = true;
                }

                if($UpdateRequired)
                {
                    $ExistingClient = $this->getClient(TelegramClientSearchMethod::byPublicId, $ExistingClient->PublicID);
                    $ExistingClient->User = $user;
                    $ExistingClient->Chat = $chat;
                    $ExistingClient->LastActivityTimestamp = $CurrentTime;
                    $ExistingClient->Available = true;
                    $this->updateClient($ExistingClient);
                }

                if($return_public_id)
                {
                    return $PublicID;
                }

                return $ExistingClient;
            }
            catch (TelegramClientNotFoundException $e)
            {
                // Ignore this exception
                unset($e);
            }

            $PublicID = $this->telegramClientManager->getDatabase()->real_escape_string($PublicID);
            $Available = (int)true;
            $AccountID = 0;
            $User = ZiProto::encode($user->toArray());
            $User = $this->telegramClientManager->getDatabase()->real_escape_string($User);
            $Chat = ZiProto::encode($chat->toArray());
            $Chat = $this->telegramClientManager->getDatabase()->real_escape_string($Chat);
            $SessionData = new TelegramClient\SessionData();
            $SessionData = ZiProto::encode($SessionData->toArray());
            $SessionData = $this->telegramClientManager->getDatabase()->real_escape_string($SessionData);
            $ChatID = $this->telegramClientManager->getDatabase()->real_escape_string($chat->ID);
            $UserID = $this->telegramClientManager->getDatabase()->real_escape_string($user->ID);
            $Username = null;
            $LastActivity = $CurrentTime;
            $Created = $CurrentTime;

            if((int)$ChatID == (int)$UserID)
            {
                if($user->Username !== null)
                {
                    $Username = $this->telegramClientManager->getDatabase()->real_escape_string($user->Username);
                }

                if($chat->Username !== null)
                {
                    $Username = $this->telegramClientManager->getDatabase()->real_escape_string($chat->Username);
                }
            }

            $Query = QueryBuilder::insert_into('telegram_clients', array(
                    'public_id' => $PublicID,
                    'available' => $Available,
                    'account_id' => $AccountID,
                    'user' => $User,
                    'chat' => $Chat,
                    'session_data' => $SessionData,
                    'chat_id' => $ChatID,
                    'user_id' => $UserID,
                    'username' => $Username,
                    'last_activity' => $LastActivity,
                    'created' => $Created
                )
            );

            $QueryResults = $this->telegramClientManager->getDatabase()->query($Query);

            if($QueryResults == false)
            {
                throw new DatabaseException($Query, $this->telegramClientManager->getDatabase()->error);
            }

            if($return_public_id)
            {
                return $PublicID;
            }

            return $this->getClient(TelegramClientSearchMethod::byPublicId, $PublicID);
        }

        /**
         * Gets an existing Telegram Client from the database
         *
         * @param string $search_method
         * @param string $value
         * @return TelegramClient
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @noinspection DuplicatedCode
         */
        public function getClient(string $search_method, string $value): TelegramClient
        {
            switch($search_method)
            {
                case TelegramClientSearchMethod::byId:
                case TelegramClientSearchMethod::byAccountId:
                    $search_method = $this->telegramClientManager->getDatabase()->real_escape_string($search_method);
                    $value = (int)$value;
                    break;

                case TelegramClientSearchMethod::byChatId:
                case TelegramClientSearchMethod::byUserId:
                    $search_method = $this->telegramClientManager->getDatabase()->real_escape_string("public_id");
                    $value = Hashing::telegramClientPublicID((int)$value, (int)$value);
                    break;

                case TelegramClientSearchMethod::byPublicId:
                case TelegramClientSearchMethod::byUsername:
                    $search_method =$this->telegramClientManager->getDatabase()->real_escape_string($search_method);
                    $value = $this->telegramClientManager->getDatabase()->real_escape_string($value);
                    break;

                default:
                    throw new InvalidSearchMethod();
            }

            $Query = QueryBuilder::select('telegram_clients', [
                'id',
                'public_id',
                'available',
                'account_id',
                'user',
                'chat',
                'session_data',
                'chat_id',
                'user_id',
                'username',
                'last_activity',
                'created'
            ], $search_method, $value, null, null, 1);

            $QueryResults = $this->telegramClientManager->getDatabase()->query($Query);

            if($QueryResults == false)
            {
                throw new DatabaseException($Query, $this->telegramClientManager->getDatabase()->error);
            }
            else
            {
                if($QueryResults->num_rows !== 1)
                {
                    throw new TelegramClientNotFoundException();
                }

                $Row = $QueryResults->fetch_array(MYSQLI_ASSOC);
                $Row['user'] = ZiProto::decode($Row['user']);
                $Row['chat'] = ZiProto::decode($Row['chat']);
                $Row['session_data'] = ZiProto::decode($Row['session_data']);
                return TelegramClient::fromArray($Row);
            }
        }

        /**
         * Gets all associated clients with a specific search method
         *
         * @param string $search_method
         * @param string $value
         * @return array
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @noinspection DuplicatedCode
         * @noinspection PhpUnused
         */
        public function getAssociatedClients(string $search_method, string $value): array
        {
            switch($search_method)
            {
                case TelegramClientSearchMethod::byId:
                case TelegramClientSearchMethod::byAccountId:
                    $search_method = $this->telegramClientManager->getDatabase()->real_escape_string($search_method);
                    $value = (int)$value;
                    break;

                case TelegramClientSearchMethod::byChatId:
                case TelegramClientSearchMethod::byUserId:
                    $search_method = $this->telegramClientManager->getDatabase()->real_escape_string("public_id");
                    $value = Hashing::telegramClientPublicID((int)$value, (int)$value);
                    break;

                case TelegramClientSearchMethod::byPublicId:
                case TelegramClientSearchMethod::byUsername:
                    $search_method =$this->telegramClientManager->getDatabase()->real_escape_string($search_method);
                    $value = $this->telegramClientManager->getDatabase()->real_escape_string($value);
                    break;

                default:
                    throw new InvalidSearchMethod();
            }

            $Query = QueryBuilder::select('telegram_clients', [
                'id',
                'public_id',
                'available',
                'account_id',
                'user',
                'chat',
                'session_data',
                'chat_id',
                'user_id',
                'username',
                'last_activity',
                'created'
            ], $search_method, $value);

            $QueryResults = $this->telegramClientManager->getDatabase()->query($Query);
            if($QueryResults == false)
            {
                throw new DatabaseException($this->telegramClientManager->getDatabase()->error, $Query);
            }
            else
            {
                $ResultsArray = [];

                while($Row = $QueryResults->fetch_assoc())
                {
                    $Row['user'] = ZiProto::decode($Row['user']);
                    $Row['chat'] = ZiProto::decode($Row['chat']);
                    $Row['session_data'] = ZiProto::decode($Row['session_data']);
                    $ResultsArray[] = TelegramClient::fromArray($Row);
                }

                return $ResultsArray;
            }
        }

        /**
         * Updates an existing Telegram client in the database
         *
         * @param TelegramClient $telegramClient
         * @param bool $retry_duplication
         * @return bool
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         */
        public function updateClient(TelegramClient $telegramClient, bool $retry_duplication=true): bool
        {
            $id = (int)$telegramClient->ID;
            $available = (int)$telegramClient->Available;
            $account_id = (int)$telegramClient->AccountID;
            $user = ZiProto::encode($telegramClient->User->toArray());
            $user = $this->telegramClientManager->getDatabase()->real_escape_string($user);
            $chat = ZiProto::encode($telegramClient->Chat->toArray());
            $chat = $this->telegramClientManager->getDatabase()->real_escape_string($chat);
            $session_data = ZiProto::encode($telegramClient->SessionData->toArray());
            $session_data = $this->telegramClientManager->getDatabase()->real_escape_string($session_data);
            $chat_id = $this->telegramClientManager->getDatabase()->real_escape_string($telegramClient->Chat->ID);
            $user_id = $this->telegramClientManager->getDatabase()->real_escape_string($telegramClient->User->ID);
            $username = null;
            $last_activity = (int)time();

            if($telegramClient->getUsername() !== null)
            {
                $username =$this->telegramClientManager->getDatabase()->real_escape_string($telegramClient->getUsername());
                $this->fixDuplicateUsername($telegramClient->Chat, $telegramClient->User);
            }

            $Query = QueryBuilder::update('telegram_clients', array(
                'available' => $available,
                'account_id' => $account_id,
                'user' => $user,
                'chat' => $chat,
                'session_data' => $session_data,
                'chat_id' => $chat_id,
                'user_id' => $user_id,
                'username' => $username,
                'last_activity' => $last_activity
            ), 'id', $id);
            $QueryResults = $this->telegramClientManager->getDatabase()->query($Query);

            if($QueryResults == true)
            {
                return true;
            }
            else
            {
                if($retry_duplication)
                {
                    $this->fixDuplicateUsername($telegramClient->Chat, $telegramClient->User);
                    return $this->updateClient($telegramClient, false);
                }

                throw new DatabaseException($Query, $this->telegramClientManager->getDatabase()->error);
            }
        }

        /**
         * Registers the client as a user only (private)
         *
         * @param User $user
         * @param bool $return_public_id
         * @return TelegramClient|string
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @noinspection PhpUnused
         */
        public function registerUser(User $user, $return_public_id=false)
        {
            $ChatObject = new Chat();
            $ChatObject->ID = $user->ID;
            $ChatObject->Type = TelegramChatType::Private;
            $ChatObject->Title = null;
            $ChatObject->Username = $user->Username;
            $ChatObject->FirstName = $user->FirstName;
            $ChatObject->LastName = $user->LastName;

            return $this->registerClient($ChatObject, $user, $return_public_id);
        }

        /**
         * Registers the client as a chat only (bot based)
         *
         * @param Chat $chat
         * @param bool $return_public_id
         * @return TelegramClient|string
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @noinspection PhpUnused
         */
        public function registerChat(Chat $chat, $return_public_id=false)
        {
            $UserObject = new User();
            $UserObject->ID = $chat->ID;
            $UserObject->FirstName = $chat->Title;
            $UserObject->LastName = null;
            $UserObject->LanguageCode = null;
            $UserObject->IsBot = false;
            $UserObject->Username = $chat->Username;

            return $this->registerClient($chat, $UserObject, $return_public_id);
        }
        /**
         * Searches and overwrites old duplicate usernames
         *
         * @param Chat $chat
         * @param User $user
         * @return bool
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         */
        public function fixDuplicateUsername(Chat $chat, User $user): bool
        {
            if((int)$user->ID == (int)$chat->ID)
            {
                $Username = null;

                if($user->Username !== null)
                {
                    $Username = $user->Username;
                }

                if($chat->Username !== null)
                {
                    $Username = $chat->Username;
                }

                if($Username !== null)
                {
                    $ExistingClient = $this->getClientByUsername($Username);

                    if($ExistingClient !== null)
                    {
                        $ExistingClient->User->Username = null;
                        $ExistingClient->Chat->Username = null;
                        $this->updateClient($ExistingClient);

                        return true;
                    }
                }
            }

            return false;
        }

        /**
         * Returns a telegram client by username returns null if not found
         *
         * @param string $username
         * @return TelegramClient|null
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         */
        public function getClientByUsername(string $username)
        {
            try
            {
                return $this->getClient(TelegramClientSearchMethod::byUsername, $username);
            }
            catch(TelegramClientNotFoundException $telegramClientNotFoundException)
            {
                return null;
            }
        }

    }