<?php


    namespace CoffeeHouse\Managers;


    use CoffeeHouse\Abstracts\UserSubscriptionSearchMethod;
    use CoffeeHouse\Abstracts\UserSubscriptionStatus;
    use CoffeeHouse\CoffeeHouse;
    use CoffeeHouse\Exceptions\DatabaseException;
    use CoffeeHouse\Exceptions\InvalidSearchMethodException;
    use CoffeeHouse\Exceptions\UserSubscriptionNotFoundException;
    use CoffeeHouse\Objects\UserSubscription;
    use msqg\QueryBuilder;

    /**
     * Class UserSubscriptionManager
     * @package CoffeeHouse\Managers
     */
    class UserSubscriptionManager
    {
        /**
         * @var CoffeeHouse
         */
        private $coffeeHouse;

        /**
         * UserSubscriptionManager constructor.
         * @param CoffeeHouse $coffeeHouse
         */
        public function __construct(CoffeeHouse $coffeeHouse)
        {
            $this->coffeeHouse = $coffeeHouse;
        }

        /**
         * Registers a UserSubscription into the database
         *
         * @param int $account_id
         * @param int $subscription_id
         * @param int $access_record_id
         * @return UserSubscription
         * @throws DatabaseException
         * @throws InvalidSearchMethodException
         * @throws UserSubscriptionNotFoundException
         */
        public function registerUserSubscription(int $account_id, int $subscription_id, int $access_record_id): UserSubscription
        {
            $account_id = (int)$account_id;
            $subscription_id = (int)$subscription_id;
            $access_record_id = (int)$access_record_id;
            $status = (int)UserSubscriptionStatus::Active;
            $created_timestamp = (int)time();

            $Query = QueryBuilder::insert_into('user_subscriptions', array(
                'account_id' => $account_id,
                'subscription_id' => $subscription_id,
                'access_record_id' => $access_record_id,
                'status' => $status,
                'created_timestamp' => $created_timestamp
            ));
            $QueryResults = $this->coffeeHouse->getDatabase()->query($Query);

            if($QueryResults == true)
            {
                return $this->getUserSubscription(UserSubscriptionSearchMethod::byAccountID, $account_id);
            }
            else
            {
                throw new DatabaseException($this->coffeeHouse->getDatabase()->error);
            }
        }

        /**
         * Gets an existing user subscription record from the database
         *
         * @param string $search_method
         * @param int $value
         * @return UserSubscription
         * @throws DatabaseException
         * @throws InvalidSearchMethodException
         * @throws UserSubscriptionNotFoundException
         */
        public function getUserSubscription(string $search_method, int $value): UserSubscription
        {
            switch($search_method)
            {
                case UserSubscriptionSearchMethod::byId:
                case UserSubscriptionSearchMethod::bySubscriptionID:
                case UserSubscriptionSearchMethod::byAccessRecordID:
                case UserSubscriptionSearchMethod::byAccountID:
                    $search_method = $this->coffeeHouse->getDatabase()->real_escape_string($search_method);
                    $value = (int)$value;
                    break;

                default:
                    throw new InvalidSearchMethodException();
            }

            $Query = QueryBuilder::select('user_subscriptions', [
                'id', 'account_id', 'subscription_id', 'access_record_id', 'status', 'created_timestamp'
            ], $search_method, $value);
            $QueryResults = $this->coffeeHouse->getDatabase()->query($Query);

            if($QueryResults == false)
            {
                throw new DatabaseException($this->coffeeHouse->getDatabase()->error);
            }
            else
            {
                if($QueryResults->num_rows !== 1)
                {
                    throw new UserSubscriptionNotFoundException();
                }

                $Row = $QueryResults->fetch_array(MYSQLI_ASSOC);

                return UserSubscription::fromArray($Row);
            }
        }

        /**
         * Updates an existing UserSubscription record in the database
         *
         * @param UserSubscription $userSubscription
         * @return bool
         * @throws DatabaseException
         * @throws InvalidSearchMethodException
         * @throws UserSubscriptionNotFoundException
         */
        public function updateUserSubscription(UserSubscription $userSubscription): bool
        {
            $this->getUserSubscription(UserSubscriptionSearchMethod::byId, $userSubscription->ID);

            $id = (int)$userSubscription->ID;
            $account_id = (int)$userSubscription->AccountID;
            $subscription_id = (int)$userSubscription->SubscriptionID;
            $access_record_id = (int)$userSubscription->AccessRecordID;
            $status = (int)$userSubscription->Status;

            $Query = QueryBuilder::update('user_subscriptions', array(
                'account_id' => $account_id,
                'subscription_id' => $subscription_id,
                'status' => $status
            ), 'id', $id);
            $QueryResults = $this->coffeeHouse->getDatabase()->query($Query);

            if($QueryResults == true)
            {
                return true;
            }
            else
            {
                throw new DatabaseException($this->coffeeHouse->getDatabase()->error);
            }
        }
    }