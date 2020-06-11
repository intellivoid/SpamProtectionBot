<?php


    namespace SpamProtection\Abstracts;


    /**
     * Class TelegramClientSearchMethod
     * @package SpamProtection\Abstracts
     */
    abstract class TelegramClientSearchMethod
    {
        const byId = 'id';

        const byPublicId = 'public_id';

        const byAccountId = 'account_id';

        const byChatId = 'chat_id';

        const byUserId = 'user_id';

        const byUsername = 'username';
    }