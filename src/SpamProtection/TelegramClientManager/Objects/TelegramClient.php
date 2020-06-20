<?php


    namespace TelegramClientManager\Objects;

    use TelegramClientManager\Objects\TelegramClient\Chat;
    use TelegramClientManager\Objects\TelegramClient\SessionData;
    use TelegramClientManager\Objects\TelegramClient\User;

    /**
     * Class TelegramClient
     * @package TelegramClientManager\Objects
     */
    class TelegramClient
    {
        /**
         * Internal unique ID for this Telegram Client
         *
         * @var int
         */
        public $ID;

        /**
         * Public unique ID for this Telegram Client
         *
         * @var string
         */
        public $PublicID;

        /**
         * Indicates if this client is available or not
         *
         * @var bool
         */
        public $Available;

        /**
         * The Account ID that's associated with this client
         * 0 Means none.
         *
         * @var int
         */
        public $AccountID;

        /**
         * The user associated with this client
         *
         * @var User
         */
        public $User;

        /**
         * The chat for this client
         *
         * @var Chat
         */
        public $Chat;

        /**
         * Session data associated with this Telegram Client
         *
         * @var SessionData
         */
        public $SessionData;

        /**
         * The Unix Timestamp of when this client was last active
         *
         * @var int
         */
        public $LastActivityTimestamp;

        /**
         * The Unix Timestamp of when this client was registered
         *
         * @var int
         */
        public $CreatedTimestamp;

        /**
         * Returns the Chat ID if it exists
         *
         * @return int
         */
        public function getChatId(): int
        {
            if(isset($this->Chat))
            {
                if(isset($this->Chat->ID))
                {
                    return (int)$this->Chat->ID;
                }
            }

            return 0;
        }

        /**
         * Returns the User ID if it exists
         *
         * @return int
         */
        public function getUserId(): int
        {
            if(isset($this->User))
            {
                if(isset($this->User->ID))
                {
                    return (int)$this->User->ID;
                }
            }

            return 0;
        }

        /**
         * Returns the chat username
         *
         * @return string|null
         */
        public function getUsername()
        {
            if((int)$this->User->ID == (int)$this->Chat->ID)
            {
                if($this->User->Username !== null)
                {
                    return $this->User->Username;
                }

                if($this->Chat->Username !== null)
                {
                    return $this->Chat->Username;
                }
            }

            return null;
        }

        /**
         * Creates array from object
         *
         * @return array
         */
        public function toArray(): array
        {
            return array(
                'id' => $this->ID,
                'public_id' => $this->PublicID,
                'available' => $this->Available,
                'account_id' => $this->AccountID,
                'user' => $this->User->toArray(),
                'chat' => $this->Chat->toArray(),
                'session_data' => $this->SessionData->toArray(),
                'chat_id' => (int)self::getChatId(),
                'user_id' => (int)self::getUserId(),
                'last_activity' => $this->LastActivityTimestamp,
                'created' => $this->CreatedTimestamp
            );
        }

        /**
         * Creates object from array
         *
         * @param array $data
         * @return TelegramClient
         */
        public static function fromArray(array $data): TelegramClient
        {
            $TelegramClientObject = new TelegramClient();

            if(isset($data['id']))
            {
                $TelegramClientObject->ID = (int)$data['id'];
            }

            if(isset($data['public_id']))
            {
                $TelegramClientObject->PublicID = $data['public_id'];
            }

            if(isset($data['available']))
            {
                $TelegramClientObject->Available = (bool)$data['available'];
            }

            if(isset($data['account_id']))
            {
                $TelegramClientObject->AccountID = (int)$data['account_id'];
            }

            if(isset($data['user']))
            {
                $TelegramClientObject->User = User::fromArray($data['user']);
            }

            if(isset($data['chat']))
            {
                $TelegramClientObject->Chat = Chat::fromArray($data['chat']);
            }

            if(isset($data['session_data']))
            {
                $TelegramClientObject->SessionData = SessionData::fromArray($data['session_data']);
            }

            if(isset($data['last_activity']))
            {
                $TelegramClientObject->LastActivityTimestamp = (int)$data['last_activity'];
            }

            if(isset($data['created']))
            {
                $TelegramClientObject->CreatedTimestamp = (int)$data['created'];
            }

            return $TelegramClientObject;
        }
    }