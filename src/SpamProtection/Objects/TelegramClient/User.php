<?php


    namespace SpamProtection\Objects\TelegramClient;


    /**
     * Class User
     * @package SpamProtection\Objects\TelegramClient
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