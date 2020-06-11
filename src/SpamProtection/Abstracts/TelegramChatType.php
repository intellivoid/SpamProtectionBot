<?php


    namespace SpamProtection\Abstracts;


    /**
     * Class TelegramChatType
     * @package SpamProtection\Abstracts
     */
    abstract class TelegramChatType
    {
        const Private = "private";

        const Group = "group";

        const SuperGroup = "supergroup";

        const Channel = "channel";
    }