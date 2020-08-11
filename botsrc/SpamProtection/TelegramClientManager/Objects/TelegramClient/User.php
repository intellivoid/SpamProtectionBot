<?php


    namespace TelegramClientManager\Objects\TelegramClient;

    /**
     * Class User
     * @package TelegramClientManager\Objects\TelegramClient
     */
    class User
    {
        /**
         * The unique identifier for this user
         *
         * @var int
         */
        public $ID;

        /**
         * True, if this user is a bot
         *
         * @var bool
         */
        public $IsBot;

        /**
         * User's or bot's first name
         *
         * @var string
         */
        public $FirstName;

        /**
         * User's or bot's last name
         *
         * @optional
         * @var string
         */
        public $LastName;

        /**
         * User's or bot's username
         *
         * @optional
         * @var string
         */
        public $Username;

        /**
         * IETF language tag of the user's language
         *
         * @optional
         * @var bool
         */
        public $LanguageCode;

        /**
         * Returns a unique hash of the chat object to represent the object as a whole
         *
         * @return string
         * @noinspection DuplicatedCode
         * @noinspection PhpUnused
         */
        public function getUniqueHash(): string
        {
            if($this->LastName == null)
            {
                $name_hash = hash('crc32', $this->FirstName);
            }
            else
            {
                $name_hash = hash('crc32', $this->FirstName . $this->LastName);
            }

            if($this->Username == null)
            {
                $username_hash = hash('crc32', "NONE");
            }
            else
            {
                $username_hash = hash('crc32', $this->Username);
            }

            if($this->IsBot == null)
            {
                $isbot_hash = hash('crc32', "NONE");
            }
            else
            {
                $isbot_hash = hash('crc32', (string)$this->IsBot);
            }

            if($this->LanguageCode == null)
            {
                $language_code_hash = hash('crc32', "NONE");
            }
            else
            {
                $language_code_hash = hash('crc32', $this->LanguageCode);
            }

            if($this->ID == null)
            {
                $id_hash = hash('crc32', "NONE");
            }
            else
            {
                $id_hash = hash('crc32', $this->ID);
            }

            return hash('sha256', $name_hash . $username_hash, $isbot_hash . $language_code_hash . $id_hash);
        }

        /**
         * Returns an array that represents this object
         *
         * @return array
         */
        public function toArray(): array
        {
            return array(
                'id' => (int)$this->ID,
                'is_bot' => (bool)$this->IsBot,
                'first_name' => $this->FirstName,
                'last_name' => $this->LastName,
                'username' => $this->Username,
                'language_code' => $this->LanguageCode
            );
        }

        /**
         * Returns object from array structure
         *
         * @param array $data
         * @return User
         */
        public static function fromArray(array $data): User
        {
            $UserObject = new User();

            if(isset($data['id']))
            {
                $UserObject->ID = (int)$data['id'];
            }

            if(isset($data['is_bot']))
            {
                $UserObject->IsBot = (bool)$data['is_bot'];
            }

            if(isset($data['first_name']))
            {
                $UserObject->FirstName = $data['first_name'];
            }

            if(isset($data['last_name']))
            {
                $UserObject->LastName = $data['last_name'];
            }

            if(isset($data['username']))
            {
                $UserObject->Username = $data['username'];
            }

            if(isset($data['language_code']))
            {
                $UserObject->LanguageCode = $data['language_code'];
            }

            return $UserObject;
        }
    }