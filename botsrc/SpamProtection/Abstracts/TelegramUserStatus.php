<?php


    namespace SpamProtection\Abstracts;

    /**
     * The member's status in the chat. Can be “creator”, “administrator”, “member”, “restricted”, “left” or “kicked”
     *
     * Class TelegramUserStatus
     * @package SpamProtection\Abstracts
     */
    abstract class TelegramUserStatus
    {
        const Creator = "creator";

        const Administrator = "administrator";

        const Member = "member";

        const Restricted = "restricted";

        const Left = "left";

        const Kicked = "kicked";
    }