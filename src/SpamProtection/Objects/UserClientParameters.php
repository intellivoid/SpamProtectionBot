<?php


    namespace SpamProtection\Objects;

    /**
     * Class UserClientParameters
     * @package SpamProtection\Objects
     */
    class UserClientParameters
    {
        /**
         * Indicates if this user is a contact
         *
         * @var bool
         */
        public $IsContact;

        /**
         * Indicates if this user is a mutual contact
         *
         * @var bool
         */
        public $IsMutualContact;

        /**
         * Indicates if the account was deleted
         *
         * @var bool
         */
        public $IsDeleted;

        /**
         * Indicates if this user is verified by Telegram
         *
         * @var bool
         */
        public $IsVerified;

        /**
         * Indicates
         *
         * @var bool
         */
        public $IsRestricted;

        /**
         * Indicates if the user is marked as a scammer from Telegram
         *
         * @var bool
         */
        public $IsScam;

        /**
         * Whether this is an official support user from Telegram
         *
         * @var bool
         */
        public $IsSupport;

        /**
         * The phone number of the user if available
         *
         * @var string
         */
        public $PhoneNumber;

        /**
         * The ID of the data center that this user is connected to
         *
         * @var int
         */
        public $DataCenterID;

        /**
         * The Unix Timestamp of when this was last updated
         *
         * @var int
         */
        public $LastUpdated;

        /**
         * Returns an array which represents this object
         *
         * @return array
         */
        public function toArray(): array
        {
            return array(
                "is_contact" => $this->IsContact,
                "is_mutual_contact" => $this->IsMutualContact,
                "is_deleted" => $this->IsDeleted,
                "is_verified" => $this->IsVerified,
                "is_restricted" => $this->IsRestricted,
                "is_scam" => $this->IsScam,
                "is_support" => $this->IsSupport,
                "phone_number" => $this->PhoneNumber,
                "data_center_id" => $this->DataCenterID,
                "last_updated" => $this->LastUpdated
            );
        }

        /**
         * Constructs object from array
         *
         * @param array $data
         * @return UserClientParameters
         * @noinspection DuplicatedCode
         */
        public static function fromArray(array $data): UserClientParameters
        {
            $UserClientParametersObject = new UserClientParameters();

            if(isset($data["is_contact"]))
            {
                $UserClientParametersObject->IsContact = $data["is_contact"];
            }

            if(isset($data["is_mutual_contact"]))
            {
                $UserClientParametersObject->IsMutualContact = $data["is_mutual_contact"];
            }

            if(isset($data["is_deleted"]))
            {
                $UserClientParametersObject->IsDeleted = $data["is_deleted"];
            }

            if(isset($data["is_verified"]))
            {
                $UserClientParametersObject->IsVerified = $data["is_verified"];
            }

            if(isset($data["is_restricted"]))
            {
                $UserClientParametersObject->IsRestricted = $data["is_restricted"];
            }

            if(isset($data["is_scam"]))
            {
                $UserClientParametersObject->IsScam = $data["is_scam"];
            }

            if(isset($data["is_support"]))
            {
                $UserClientParametersObject->IsSupport = $data["is_support"];
            }

            if(isset($data["phone_number"]))
            {
                $UserClientParametersObject->PhoneNumber = $data["phone_number"];
            }

            if(isset($data["data_center_id"]))
            {
                $UserClientParametersObject->DataCenterID = $data["data_center_id"];
            }

            if(isset($data["last_updated"]))
            {
                $UserClientParametersObject->LastUpdated = $data["last_updated"];
            }

            return $UserClientParametersObject;
        }
    }