<?php


    namespace CoffeeHouse\Objects;

    /**
     * Class UserSubscription
     * @package CoffeeHouse\Objects
     */
    class UserSubscription
    {
        /**
         * The ID of the User Subscription record
         *
         * @var int
         */
        public $ID;

        /**
         * The ID of the Account ID
         *
         * @var int
         */
        public $AccountID;

        /**
         * The ID of the subscription ID
         *
         * @var int
         */
        public $SubscriptionID;

        /**
         * The ID of the access record
         *
         * @var int
         */
        public $AccessRecordID;

        /**
         * The status of the User Subscription
         *
         * @var int
         */
        public $Status;

        /**
         * The Unix Timestamp of this User Subscription
         *
         * @var int
         */
        public $CreatedTimestamp;

        /**
         * Returns an array which represents this object
         *
         * @return array
         */
        public function toArray(): array
        {
            return array(
                'id' => (int)$this->ID,
                'account_id' => (int)$this->AccountID,
                'subscription_id' => (int)$this->SubscriptionID,
                'access_record_id' => (int)$this->AccessRecordID,
                'status' => (int)$this->Status,
                'created_timestamp' => (int)$this->CreatedTimestamp
            );
        }

        /**
         * Constructs object from array
         *
         * @param array $data
         * @return UserSubscription
         */
        public static function fromArray(array $data): UserSubscription
        {
            $UserSubscriptionObject = new UserSubscription();

            if(isset($data['id']))
            {
                $UserSubscriptionObject->ID = (int)$data['id'];
            }

            if(isset($data['account_id']))
            {
                $UserSubscriptionObject->AccountID = (int)$data['account_id'];
            }

            if(isset($data['subscription_id']))
            {
                $UserSubscriptionObject->SubscriptionID = (int)$data['subscription_id'];
            }

            if(isset($data['access_record_id']))
            {
                $UserSubscriptionObject->AccessRecordID = (int)$data['access_record_id'];
            }

            if(isset($data['status']))
            {
                $UserSubscriptionObject->Status = (int)$data['status'];
            }

            if(isset($data['created_timestamp']))
            {
                $UserSubscriptionObject->CreatedTimestamp = (int)$data['created_timestamp'];
            }

            return $UserSubscriptionObject;
        }
    }