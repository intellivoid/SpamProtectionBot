<?php


    namespace CoffeeHouse\Objects;


    class ForeignSession
    {
        /**
         * Internal Database ID
         *
         * @var int
         */
        public $ID;

        /**
         * Public Session ID
         *
         * @var string
         */
        public $SessionID;

        /**
         * HTTP Headers used in this session
         *
         * @var mixed
         */
        public $Headers;

        /**
         * HTTP Cookies used in this session
         *
         * @var mixed
         */
        public $Cookies;

        /**
         * Variables used with this session
         *
         * @var mixed
         */
        public $Variables;

        /**
         * The language that is used in this session
         *
         * @var string
         */
        public $Language;

        /**
         * Indicates if this session is still available at the remote session
         *
         * @var bool
         */
        public $Available;

        /**
         * The total amount of messages that has been sent to this session
         *
         * @var int
         */
        public $Messages;

        /**
         * Unix Timestamp of when this session expires
         *
         * @var int
         */
        public $Expires;

        /**
         * Unix Timestamp of when this session was last updated
         *
         * @var int
         */
        public $LastUpdated;

        /**
         * The Unix Timestamp of when this session was created
         *
         * @var int
         */
        public $Created;

        /**
         * Creates array from this object
         *
         * @return array
         */
        public function toArray(): array
        {
            return array(
                'id' => $this->ID,
                'session_id' => $this->SessionID,
                'headers' => $this->Headers,
                'cookies' => $this->Cookies,
                'variables' => $this->Variables,
                'language' => $this->Language,
                'available' => $this->Available,
                'messages' => $this->Messages,
                'expires' => $this->Expires,
                'last_updated' => $this->LastUpdated,
                'created' => $this->Created
            );
        }

        /**
         * Creates object from array
         *
         * @param array $data
         * @return ForeignSession
         */
        public static function fromArray(array $data): ForeignSession
        {
            $ForeignSessionObject = new ForeignSession();

            if(isset($data['id']))
            {
                $ForeignSessionObject->ID = (int)$data['id'];
            }

            if(isset($data['session_id']))
            {
                $ForeignSessionObject->SessionID = $data['session_id'];
            }

            if(isset($data['headers']))
            {
                $ForeignSessionObject->Headers = $data['headers'];
            }

            if(isset($data['cookies']))
            {
                $ForeignSessionObject->Cookies = $data['cookies'];
            }

            if(isset($data['variables']))
            {
                $ForeignSessionObject->Variables = $data['variables'];
            }

            if(isset($data['language']))
            {
                $ForeignSessionObject->Language = $data['language'];
            }

            if(isset($data['available']))
            {
                $ForeignSessionObject->Available = (bool)$data['available'];
            }

            if(isset($data['messages']))
            {
                $ForeignSessionObject->Messages = (int)$data['messages'];
            }

            if(isset($data['expires']))
            {
                $ForeignSessionObject->Expires = (int)$data['expires'];
            }

            if(isset($data['last_updated']))
            {
                $ForeignSessionObject->LastUpdated = (int)$data['last_updated'];
            }

            if(isset($data['created']))
            {
                $ForeignSessionObject->Created = (int)$data['created'];
            }

            return $ForeignSessionObject;
        }
    }