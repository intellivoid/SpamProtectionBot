<?php


    namespace SpamProtection\Objects\TelegramClient;


    use SpamProtection\Abstracts\TelegramChatType;

    /**
     * Class Chat
     * @package SpamProtection\Objects\TelegramClient
     */
    class Chat
    {
        /**
         * The unique identifier for this chat, this number may be greater
         * than 32 bits.
         *
         * @var int
         */
        public $ID;

        /**
         * Type of chat, can be either "private", "group", "supergroup" or "channel"
         *
         * @var TelegramChatType
         */
        public $Type;

        /**
         * Title for supergroups, channels and group chats
         *
         * @optional
         * @var string
         */
        public $Title;

        /**
         * Username for private chats, supergroups and channels if available
         *
         * @optional
         * @var string
         */
        public $Username;

        /**
         * First name of the other party in a private chat
         *
         * @optional
         * @var string
         */
        public $FirstName;

        /**
         * Last name of the other party in a private chat
         *
         * @optional
         * @var string
         */
        public $LastName;

        /**
         * Creates array from object
         *
         * @return array
         */
        public function toArray(): array
        {
            return array(
                'id' => (int)$this->ID,
                'type' => (string)$this->Type,
                'title' => $this->Title,
                'username' => $this->Username,
                'first_name' => $this->FirstName,
                'last_name' => $this->LastName
            );
        }

        /**
         * Creates object from array
         *
         * @param array $data
         * @return Chat
         */
        public static function fromArray(array $data): Chat
        {
            $ChatObject = new Chat();

            if(isset($data['id']))
            {
                $ChatObject->ID = (int)$data['id'];
            }

            if(isset($data['type']))
            {
                switch($data['type'])
                {
                    case 'private':
                        $ChatObject->Type = TelegramChatType::Private;
                        break;

                    case 'group':
                        $ChatObject->Type = TelegramChatType::Group;
                        break;

                    case 'supergroup':
                        $ChatObject->Type = TelegramChatType::SuperGroup;
                        break;

                    case 'channel':
                        $ChatObject->Type = TelegramChatType::Channel;
                        break;

                    default: break;
                }
            }

            if(isset($data['title']))
            {
                $ChatObject->Title = $data['title'];
            }

            if(isset($data['username']))
            {
                $ChatObject->Username = $data['username'];
            }

            if(isset($data['first_name']))
            {
                $ChatObject->FirstName = $data['first_name'];
            }

            if(isset($data['last_name']))
            {
                $ChatObject->LastName = $data['last_name'];
            }

            return $ChatObject;
        }
    }