<?php


    namespace CoffeeHouse\Abstracts;

    /**
     * Class UserSubscriptionSearchMethod
     * @package CoffeeHouse\Abstracts
     */
    abstract class UserSubscriptionSearchMethod
    {
        const byId = 'id';

        const byAccountID = 'account_id';

        const bySubscriptionID = 'subscription_id';

        const byAccessRecordID = 'access_record_id';
    }