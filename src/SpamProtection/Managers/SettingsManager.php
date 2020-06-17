<?php


    namespace SpamProtection\Managers;


    use SpamProtection\Objects\ChatSettings;
    use SpamProtection\Objects\TelegramClient;
    use SpamProtection\Objects\UserStatus;

    /**
     * Class ChatSettingsManager
     * @package SpamProtection\Managers
     */
    class SettingsManager
    {
        /**
         * Returns the user status of the telegram client
         *
         * @param TelegramClient $telegramClient
         * @return UserStatus
         * @noinspection PhpUnused
         */
        public static function getUserStatus(TelegramClient $telegramClient): UserStatus
        {
            if(isset($telegramClient->SessionData->Data["user_status"]) == false)
            {
                $telegramClient->SessionData->Data["user_status"] = UserStatus::fromArray($telegramClient->User, array())->toArray();
            }

            return UserStatus::fromArray($telegramClient->User, $telegramClient->SessionData->Data["user_status"]);
        }

        /**
         * Updates the user status configuration in the telegram client
         *
         * @param TelegramClient $telegramClient
         * @param UserStatus $userStatus
         * @return TelegramClient
         * @noinspection PhpUnused
         */
        public static function updateUserStatus(TelegramClient $telegramClient, UserStatus $userStatus): TelegramClient
        {
            $telegramClient->SessionData->Data["user_status"] = $userStatus->toArray();
            return $telegramClient;
        }

        /**
         * Returns the chat settings of the Telegram Client
         *
         * @param TelegramClient $telegramClient
         * @return ChatSettings
         * @noinspection PhpUnused
         */
        public static function getChatSettings(TelegramClient $telegramClient): ChatSettings
        {
            if(isset($telegramClient->SessionData->Data["chat_settings"]) == false)
            {
                $telegramClient->SessionData->Data["chat_settings"] = ChatSettings::fromArray($telegramClient->Chat, array())->toArray();
            }

            return ChatSettings::fromArray($telegramClient->Chat, $telegramClient->SessionData->Data["chat_settings"]);
        }

        /**
         * Updates the chat configuration in the telegram client
         *
         * @param TelegramClient $telegramClient
         * @param ChatSettings $chatSettings
         * @return TelegramClient
         * @noinspection PhpUnused
         */
        public static function updateChatSettings(TelegramClient $telegramClient, ChatSettings $chatSettings): TelegramClient
        {
            $telegramClient->SessionData->Data["chat_settings"] = $chatSettings->toArray();
            return $telegramClient;
        }
    }