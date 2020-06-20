<?php


    namespace TelegramClientManager\Utilities;

    /**
     * Class Hashing
     * @package TelegramClientManager\Utilities
     */
    class Hashing
    {
        /**
         * Creates a unique public telegram client ID
         *
         * @param string $chat_id
         * @param int $user_id
         * @return string
         */
        public static function telegramClientPublicID(string $chat_id, int $user_id): string
        {
            $builder = "TEL-";

            $builder .= hash('sha256', $chat_id);
            $builder .= '-' . hash('crc32', $user_id);

            return $builder;
        }
    }