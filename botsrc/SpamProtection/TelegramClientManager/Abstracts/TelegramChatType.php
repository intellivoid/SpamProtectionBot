<?php


    namespace TelegramClientManager\Abstracts;


    abstract class TelegramChatType
    {
        const Private = "private";

        const Group = "group";

        const SuperGroup = "supergroup";

        const Channel = "channel";
    }