<?php


    namespace SpamProtection\Utilities;


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

        /**
         * Generates a unique hash of the message ID
         *
         * @param int $message_id
         * @param int $chat_id
         * @param int $user_id
         * @param int $timestamp
         * @param string $message_content_hash
         * @return string
         */
        public static function messageHash(int $message_id, int $chat_id, int $user_id, int $timestamp, string $message_content_hash): string
        {
            $combination = self::telegramClientPublicID($chat_id, $user_id);
            $message = hash('crc32b', $message_id . $message_content_hash);

            return hash('sha256', $combination . $message . $timestamp);
        }

        /**
         * Generates a unique hash of the message content
         *
         * @param string $content
         * @return string
         */
        public static function messageContent(string $content): string
        {
            return hash('sha256', $content . 'IVASP');
        }
    }