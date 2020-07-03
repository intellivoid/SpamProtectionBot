<?php


    namespace SpamProtection\Utilities;


    use SpamProtection\Exceptions\DownloadFileException;
    use SpamProtection\Objects\TelegramObjects\PhotoSize;

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
         * Generates a unique hash of the image message
         *
         * @param int $message_id
         * @param int $chat_id
         * @param int $user_id
         * @param int $timestamp
         * @param string $image_content_hash
         * @param PhotoSize $photoSize
         * @return string
         */
        public static function messageImageHash(int $message_id, int $chat_id, int $user_id, int $timestamp, string $image_content_hash, PhotoSize $photoSize): string
        {
            $message_hash = self::messageHash($message_id, $chat_id, $user_id, $timestamp, $image_content_hash);
            return hash('sha256', $message_hash . self::photoSizeHash($photoSize));
        }

        /**
         * Calculates the hash for a PhotoSize
         *
         * @param PhotoSize $photoSize
         * @return string
         */
        public static function photoSizeHash(PhotoSize $photoSize): string
        {
            $file_id = hash('sha256', $photoSize->FileID);
            $file_unique_id = hash('sha256', $photoSize->FileUniqueID);
            $size = hash('sha256', $photoSize->Width . $photoSize->Height);

            return hash('sha256', $file_id . $file_unique_id . $size);
        }

        /**
         * Returns the hash of the contents of a remote file
         *
         * @param string $url
         * @return string
         * @throws DownloadFileException
         * @noinspection PhpUnused
         */
        public static function hashRemoteFile(string $url): string
        {
            $CurlClient = curl_init();
            curl_setopt($CurlClient, CURLOPT_URL, $url);
            curl_setopt($CurlClient, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($CurlClient, CURLOPT_FAILONERROR, true);

            $response = curl_exec($CurlClient);

            if (curl_errno($CurlClient))
            {
                $error_response = curl_error($CurlClient);
                curl_close($CurlClient);

                throw new DownloadFileException($error_response, $url);
            }

            curl_close($CurlClient);
            return hash('sha256', $response);
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