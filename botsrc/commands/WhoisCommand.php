<?php

    /** @noinspection PhpMissingFieldTypeInspection */
    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\UserCommands;

    use Exception;
    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\CallbackQuery;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use pop\pop;
    use SpamProtection\Abstracts\BlacklistFlag;
    use SpamProtection\Abstracts\DetectionAction;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtection\Utilities\Hashing;
    use SpamProtectionBot;
    use TelegramClientManager\Abstracts\SearchMethods\TelegramClientSearchMethod;
    use TelegramClientManager\Abstracts\TelegramChatType;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;
    use TelegramClientManager\Objects\TelegramClient;
    use TgFileLogging;

    /**
     * Info command
     *
     * Allows the user to see the current information about requested user, either by
     * a reply to a message or by providing the private Telegram ID or Telegram ID
     */
    class WhoisCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'whois';

        /**
         * @var string
         */
        protected $description = 'Resolves information about the target object';

        /**
         * @var string
         */
        protected $usage = '/whois [None/Reply/ID/Private Telegram ID/Username/]';

        /**
         * @var string
         */
        protected $version = '2.0.0';

        /**
         * @var bool
         */
        protected $private_only = false;

        /**
         * The chat/channel object of the current chat/channel
         *
         * @var TelegramClient\Chat|null
         */
        public $ChatObject = null;

        /**
         * The client of the chat/channel of the current chat/channel
         *
         * @var TelegramClient|null
         */
        public $ChatClient = null;

        /**
         * The user/bot object of the initializer (Entity that sent the message/action)
         *
         * @var TelegramClient\User|null
         */
        public $UserObject = null;

        /**
         * The user/bot client of the initializer (Entity that sent the message/action)
         *
         * @var TelegramClient|null
         */
        public $UserClient = null;

        /**
         * The direct client combination of the user initializer and the current chat/channel
         *
         * @var TelegramClient|null
         */
        public $DirectClient = null;

        /**
         * The original sender object of the forwarded content
         *
         * @var TelegramClient\User|null
         */
        public $ForwardUserObject = null;

        /**
         * The original sender client of the forwarded content
         *
         * @var TelegramClient|null
         */
        public $ForwardUserClient = null;

        /**
         * The channel origin object of the forwarded content
         *
         * @var TelegramClient\Chat|null
         */
        public $ForwardChannelObject = null;

        /**
         * The channel origin client of the forwarded content
         *
         * @var TelegramClient|null
         */
        public $ForwardChannelClient = null;

        /**
         * The target user object of the message that the reply is to
         *
         * @var TelegramClient\User|null
         */
        public $ReplyToUserObject = null;

        /**
         * The target user client of the message that the reply is to
         *
         * @var TelegramClient|null
         */
        public $ReplyToUserClient = null;

        /**
         * The original sender object of the forwarded content that this message is replying to
         *
         * @var TelegramClient\User|null
         */
        public $ReplyToUserForwardUserObject = null;

        /**
         * The original sender client of the forwarded content that this message is replying to
         *
         * @var TelegramClient|null
         */
        public $ReplyToUserForwardUserClient = null;

        /**
         * The original channel object origin of the forwarded content that this message is replying to
         *
         * @var TelegramClient\Chat|null
         */
        public $ReplyToUserForwardChannelObject = null;

        /**
         * The original channel cient origin of the forwarded content that this message is replying to
         *
         * @var TelegramClient|null
         */
        public $ReplyToUserForwardChannelClient = null;

        /**
         * Array of user mentions by UserID:ObjectType
         *
         * @var TelegramClient\User[]|null
         */
        public $MentionUserObjects = null;

        /**
         * Array of user mentions by UserID:ObjectClient
         *
         * @var TelegramClient[]|null
         */
        public $MentionUserClients = null;

        /**
         * Array of new chat members (objects) that has been added to the chat
         *
         * @var TelegramClient\User[]|null
         */
        public $NewChatMembersObjects = null;

        /**
         * Array of new chat members (clients) that has been added to the chat
         *
         * @var TelegramClient[]|null
         */
        public $NewChatMembersClients = null;

        /**
         * When enabled, the results will be sent privately and
         * the message will be deleted
         *
         * @var bool
         */
        public $PrivateMode = false;

        /**
         * The destination chat relative to the private mode
         *
         * @var TelegramClient\Chat|null
         */
        public $DestinationChat = null;

        /**
         * The message ID to reply to
         *
         * @var int|null
         */
        public $ReplyToID = null;

        /**
         * The chat of the callback query
         *
         * @var TelegramClient\Chat|null
         */
        public $CallbackQueryChatObject = null;

        /**
         * The chat client of the callback query
         *
         * @var TelegramClient|null
         */
        public $CallbackQueryChatClient = null;

        /**
         * The user of the callback query
         *
         * @var TelegramClient\User|null
         */
        public $CallbackQueryUserObject = null;

        /**
         * The user client of the callback query
         *
         * @var TelegramClient|null
         */
        public $CallbackQueryUserClient = null;

        /**
         * The client of the callback query
         *
         * @var TelegramClient|null
         */
        public $CallbackQueryClient = null;

        /**
         * Finds the callback clients
         *
         * @param CallbackQuery $callbackQuery
         */
        public function findCallbackClients(CallbackQuery $callbackQuery)
        {
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();

            if($callbackQuery !== null)
            {
                if($callbackQuery->getFrom() !== null)
                {
                    try
                    {
                        $this->CallbackQueryUserObject = TelegramClient\User::fromArray($callbackQuery->getFrom()->getRawData());
                        $this->CallbackQueryUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($this->CallbackQueryUserObject);
                    }
                    catch(Exception $e)
                    {
                        unset($e);
                    }
                }

                if($callbackQuery->getMessage() !== null)
                {
                    if($callbackQuery->getMessage()->getChat() !== null)
                    {
                        try
                        {
                            $this->CallbackQueryChatObject = TelegramClient\Chat::fromArray($callbackQuery->getMessage()->getChat()->getRawData());
                            $this->CallbackQueryChatClient = $TelegramClientManager->getTelegramClientManager()->registerChat($this->CallbackQueryChatObject);
                        }
                        catch(Exception $e)
                        {
                            unset($e);
                        }
                    }
                }

                if($this->CallbackQueryUserObject !== null && $this->CallbackQueryChatObject !== null)
                {
                    try
                    {
                        $this->CallbackQueryClient = $TelegramClientManager->getTelegramClientManager()->registerClient(
                            $this->CallbackQueryChatObject, $this->CallbackQueryUserObject
                        );
                    }
                    catch(Exception $e)
                    {
                        unset($e);
                    }
                }
            }
        }

        /**
         * Parses the request and establishes all client connections
         * @noinspection DuplicatedCode
         */
        public function findClients()
        {
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();

            $this->ChatObject = TelegramClient\Chat::fromArray($this->getMessage()->getChat()->getRawData());
            $this->UserObject = TelegramClient\User::fromArray($this->getMessage()->getFrom()->getRawData());

            // Parse the callback query
            if($this->getCallbackQuery() !== null)
            {
                if($this->getCallbackQuery()->getFrom() !== null)
                {
                    try
                    {
                        $this->CallbackQueryUserObject = TelegramClient\User::fromArray($this->getCallbackQuery()->getFrom()->getRawData());
                        $this->CallbackQueryUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($this->CallbackQueryUserObject);
                    }
                    catch(Exception $e)
                    {
                        unset($e);
                    }
                }

                if($this->getCallbackQuery()->getMessage() !== null)
                {
                    if($this->getCallbackQuery()->getMessage()->getChat() !== null)
                    {
                        try
                        {
                            $this->CallbackQueryChatObject = TelegramClient\Chat::fromArray($this->getCallbackQuery()->getMessage()->getChat()->getRawData());
                            $this->CallbackQueryChatClient = $TelegramClientManager->getTelegramClientManager()->registerChat($this->CallbackQueryChatObject);
                        }
                        catch(Exception $e)
                        {
                            unset($e);
                        }
                    }
                }

                if($this->CallbackQueryUserObject !== null && $this->CallbackQueryChatObject !== null)
                {
                    try
                    {
                        $this->CallbackQueryClient = $TelegramClientManager->getTelegramClientManager()->registerClient(
                            $this->CallbackQueryChatObject, $this->CallbackQueryUserObject
                        );
                    }
                    catch(Exception $e)
                    {
                        unset($e);
                    }
                }
            }

            try
            {
                $this->DirectClient = $TelegramClientManager->getTelegramClientManager()->registerClient(
                    $this->ChatObject, $this->UserObject
                );
            }
            catch(Exception $e)
            {
                SpamProtectionBot::getLogHandler()->logException($e, "Worker");
                return null;
            }

            // Define and update chat client
            try
            {
                $this->ChatClient = $TelegramClientManager->getTelegramClientManager()->registerChat($this->ChatObject);
                if(isset($this->ChatClient->SessionData->Data["chat_settings"]) == false)
                {
                    $ChatSettings = SettingsManager::getChatSettings($this->ChatClient);
                    $this->ChatClient = SettingsManager::updateChatSettings($this->ChatClient, $ChatSettings);
                    $TelegramClientManager->getTelegramClientManager()->updateClient($this->ChatClient);
                }
            }
            catch(Exception $e)
            {
                SpamProtectionBot::getLogHandler()->logException($e, "Worker");
                return null;
            }

            // Define and update user client
            try
            {
                $this->UserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($this->UserObject);
                if(isset($this->UserClient->SessionData->Data["user_status"]) == false)
                {
                    $UserStatus = SettingsManager::getUserStatus($this->UserClient);
                    $this->UserClient = SettingsManager::updateUserStatus($this->UserClient, $UserStatus);
                    $TelegramClientManager->getTelegramClientManager()->updateClient($this->UserClient);
                }
            }
            catch(Exception $e)
            {
                SpamProtectionBot::getLogHandler()->logException($e, "Worker");
                return null;
            }

            // Define and update the forwarder if available
            try
            {
                if($this->getMessage()->getForwardFrom() !== null)
                {
                    $this->ForwardUserObject = TelegramClient\User::fromArray($this->getMessage()->getForwardFrom()->getRawData());
                    $this->ForwardUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($this->ForwardUserObject);
                    if(isset($this->ForwardUserClient->SessionData->Data["user_status"]) == false)
                    {
                        $ForwardUserStatus = SettingsManager::getUserStatus($this->ForwardUserClient);
                        $this->ForwardUserClient = SettingsManager::updateUserStatus($this->ForwardUserClient, $ForwardUserStatus);
                        $TelegramClientManager->getTelegramClientManager()->updateClient($this->ForwardUserClient);
                    }
                }
            }
            catch(Exception $e)
            {
                $ReferenceID = SpamProtectionBot::getLogHandler()->logException($e, "Worker");
                /** @noinspection PhpUnhandledExceptionInspection */
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "Oops! Something went wrong! contact someone in @IntellivoidDiscussions\n\n" .
                        "Error Code: <code>" . $ReferenceID . "</code>\n" .
                        "Object: <code>Events/generic_request::forward_from_user.bin</code>"
                ]);
            }

            // Define and update the channel forwarder if available
            try
            {
                if($this->getMessage()->getForwardFromChat() !== null)
                {
                    $this->ForwardChannelObject = TelegramClient\Chat::fromArray($this->getMessage()->getForwardFromChat()->getRawData());
                    $this->ForwardChannelClient = $TelegramClientManager->getTelegramClientManager()->registerChat($this->ForwardChannelObject);
                    if(isset($this->ForwardChannelClient->SessionData->Data["channel_status"]) == false)
                    {
                        $ForwardChannelStatus = SettingsManager::getChannelStatus($this->ForwardChannelClient);
                        $this->ForwardChannelClient = SettingsManager::updateChannelStatus($this->ForwardChannelClient, $ForwardChannelStatus);
                        $TelegramClientManager->getTelegramClientManager()->updateClient($this->ForwardChannelClient);
                    }
                }
            }
            catch(Exception $e)
            {
                $ReferenceID = SpamProtectionBot::getLogHandler()->logException($e, "Worker");
                /** @noinspection PhpUnhandledExceptionInspection */
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "Oops! Something went wrong! contact someone in @IntellivoidDiscussions\n\n" .
                        "Error Code: <code>" . $ReferenceID . "</code>\n" .
                        "Object: <code>Events/generic_request::forward_from_channel.bin</code>"
                ]);
            }

            try
            {
                if($this->getMessage()->getReplyToMessage() !== null)
                {
                    if($this->getMessage()->getReplyToMessage()->getFrom() !== null)
                    {
                        $this->ReplyToUserObject = TelegramClient\User::fromArray($this->getMessage()->getReplyToMessage()->getFrom()->getRawData());
                        $this->ReplyToUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($this->ReplyToUserObject);

                        if(isset($this->ReplyToUserClient->SessionData->Data["user_status"]) == false)
                        {
                            $ForwardUserStatus = SettingsManager::getUserStatus($this->ReplyToUserClient);
                            $this->ReplyToUserClient = SettingsManager::updateUserStatus($this->ReplyToUserClient, $ForwardUserStatus);
                            $TelegramClientManager->getTelegramClientManager()->updateClient($this->ReplyToUserClient);
                        }
                    }
                }
            }
            catch(Exception $e)
            {
                $ReferenceID = SpamProtectionBot::getLogHandler()->logException($e, "Worker");
                /** @noinspection PhpUnhandledExceptionInspection */
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "Oops! Something went wrong! contact someone in @IntellivoidDiscussions\n\n" .
                        "Error Code: <code>" . $ReferenceID . "</code>\n" .
                        "Object: <code>Events/generic_request::reply_to_user.bin</code>"
                ]);
            }

            try
            {
                if($this->getMessage()->getReplyToMessage() !== null)
                {
                    if($this->getMessage()->getReplyToMessage()->getForwardFrom() !== null)
                    {
                        $this->ReplyToUserForwardChannelObject = TelegramClient\User::fromArray($this->getMessage()->getReplyToMessage()->getForwardFrom()->getRawData());
                        $this->ReplyToUserForwardChannelClient = $TelegramClientManager->getTelegramClientManager()->registerUser($this->ReplyToUserForwardChannelObject);

                        if(isset($this->ReplyToUserForwardChannelClient->SessionData->Data["channel_status"]) == false)
                        {
                            $ForwardChannelStatus = SettingsManager::getChannelStatus($this->ReplyToUserForwardChannelClient);
                            $this->ReplyToUserForwardChannelClient = SettingsManager::updateChannelStatus($this->ReplyToUserForwardChannelClient, $ForwardChannelStatus);
                            $TelegramClientManager->getTelegramClientManager()->updateClient($this->ReplyToUserForwardChannelClient);
                        }
                    }
                }
            }
            catch(Exception $e)
            {
                $ReferenceID = SpamProtectionBot::getLogHandler()->logException($e, "Worker");
                /** @noinspection PhpUnhandledExceptionInspection */
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "Oops! Something went wrong! contact someone in @IntellivoidDiscussions\n\n" .
                        "Error Code: <code>" . $ReferenceID . "</code>\n" .
                        "Object: <code>Events/generic_request::reply_to_user_forwarder_channel.bin</code>"
                ]);
            }

            try
            {
                if($this->getMessage()->getReplyToMessage() !== null)
                {
                    if($this->getMessage()->getReplyToMessage()->getForwardFromChat() !== null)
                    {
                        $this->ReplyToUserForwardChannelObject = TelegramClient\Chat::fromArray($this->getMessage()->getReplyToMessage()->getForwardFromChat()->getRawData());
                        $this->ReplyToUserForwardChannelClient = $TelegramClientManager->getTelegramClientManager()->registerChat($this->ReplyToUserForwardChannelObject);

                        if(isset($this->ReplyToUserForwardChannelClient->SessionData->Data["user_status"]) == false)
                        {
                            $ForwardUserStatus = SettingsManager::getUserStatus($this->ReplyToUserForwardChannelClient);
                            $this->ReplyToUserForwardChannelClient = SettingsManager::updateUserStatus($this->ReplyToUserForwardChannelClient, $ForwardUserStatus);
                            $TelegramClientManager->getTelegramClientManager()->updateClient($this->ReplyToUserForwardChannelClient);
                        }
                    }
                }
            }
            catch(Exception $e)
            {
                $ReferenceID = SpamProtectionBot::getLogHandler()->logException($e, "Worker");
                /** @noinspection PhpUnhandledExceptionInspection */
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "Oops! Something went wrong! contact someone in @IntellivoidDiscussions\n\n" .
                        "Error Code: <code>" . $ReferenceID . "</code>\n" .
                        "Object: <code>Events/generic_request::reply_to_user_forwarder_user.bin</code>"
                ]);
            }

            try
            {
                $this->MentionUserObjects = array();
                $this->MentionUserClients = array();

                // The message in general
                if($this->getMessage()->getEntities() !== null)
                {
                    foreach($this->getMessage()->getEntities() as $messageEntity)
                    {
                        /** @noinspection DuplicatedCode */
                        if($messageEntity->getUser() !== null)
                        {
                            $MentionUserObject = TelegramClient\User::fromArray($messageEntity->getUser()->getRawData());
                            /** @noinspection DuplicatedCode */
                            $MentionUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($MentionUserObject);
                            if(isset($MentionUserClient->SessionData->Data["user_status"]) == false)
                            {
                                $UserStatus = SettingsManager::getUserStatus($MentionUserClient);
                                $MentionUserClient = SettingsManager::updateUserStatus($MentionUserClient, $UserStatus);
                                $TelegramClientManager->getTelegramClientManager()->updateClient($MentionUserClient);
                            }

                            $this->MentionUserObjects[$MentionUserObject->ID] = $MentionUserObject;
                            $this->MentionUserClients[$MentionUserObject->ID] = $MentionUserClient;
                        }
                    }
                }

                // If the reply contains mentions
                if($this->getMessage()->getReplyToMessage() !== null)
                {
                    if($this->getMessage()->getReplyToMessage()->getEntities() !== null)
                    {
                        foreach($this->getMessage()->getReplyToMessage()->getEntities() as $messageEntity)
                        {
                            /** @noinspection DuplicatedCode */
                            if($messageEntity->getUser() !== null)
                            {
                                $MentionUserObject = TelegramClient\User::fromArray($messageEntity->getUser()->getRawData());
                                if(isset($this->MentionUserObjects[$MentionUserObject->ID]) == false)
                                {
                                    /** @noinspection DuplicatedCode */
                                    $MentionUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($MentionUserObject);
                                    if(isset($MentionUserClient->SessionData->Data["user_status"]) == false)
                                    {
                                        $UserStatus = SettingsManager::getUserStatus($MentionUserClient);
                                        $MentionUserClient = SettingsManager::updateUserStatus($MentionUserClient, $UserStatus);
                                        $TelegramClientManager->getTelegramClientManager()->updateClient($MentionUserClient);
                                    }

                                    $this->MentionUserObjects[$MentionUserObject->ID] = $MentionUserObject;
                                    $this->MentionUserClients[$MentionUserObject->ID] = $MentionUserClient;
                                }
                            }
                        }
                    }
                }
            }
            catch(Exception $e)
            {
                $ReferenceID = SpamProtectionBot::getLogHandler()->logException($e, "Worker");
                /** @noinspection PhpUnhandledExceptionInspection */
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "Oops! Something went wrong! contact someone in @IntellivoidDiscussions\n\n" .
                        "Error Code: <code>" . $ReferenceID . "</code>\n" .
                        "Object: <code>Events/generic_request::mentions.bin</code>"
                ]);
            }

            try
            {
                $this->NewChatMembersObjects = array();
                $this->NewChatMembersClients = array();

                // The message in general
                if($this->getMessage()->getNewChatMembers() !== null)
                {
                    foreach($this->getMessage()->getNewChatMembers() as $chatMember)
                    {
                        /** @noinspection DuplicatedCode */
                        if($chatMember->getUser() !== null)
                        {
                            $NewUserObject = TelegramClient\User::fromArray($chatMember->getUser()->getRawData());
                            /** @noinspection DuplicatedCode */
                            $NewUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($NewUserObject);
                            if(isset($NewUserClient->SessionData->Data["user_status"]) == false)
                            {
                                $UserStatus = SettingsManager::getUserStatus($NewUserClient);
                                $NewUserClient = SettingsManager::updateUserStatus($NewUserClient, $UserStatus);
                                $TelegramClientManager->getTelegramClientManager()->updateClient($NewUserClient);
                            }

                            $this->NewChatMembersObjects[$NewUserObject->ID] = $NewUserObject;
                            $this->NewChatMembersClients[$NewUserObject->ID] = $NewUserClient;
                        }
                    }
                }
            }
            catch(Exception $e)
            {
                $ReferenceID = SpamProtectionBot::getLogHandler()->logException($e, "Worker");
                /** @noinspection PhpUnhandledExceptionInspection */
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "Oops! Something went wrong! contact someone in @IntellivoidDiscussions\n\n" .
                        "Error Code: <code>" . $ReferenceID . "</code>\n" .
                        "Object: <code>Events/generic_request::mentions.bin</code>"
                ]);
            }

            return $this;
        }

        /**
         * Attempts to find the target user that the reply/message is referring to
         *
         * @param bool $reply_only If enabled, the target user can refer to the user of that sent the message
         * @return TelegramClient|null
         */
        public function findTarget(bool $reply_only=true)
        {
            if($this->ReplyToUserClient !== null)
            {
                return $this->ReplyToUserClient;
            }

            if($this->MentionUserClients !== null)
            {
                if(count($this->MentionUserClients) > 0)
                {
                    return $this->MentionUserClients[array_keys($this->MentionUserClients)[0]];
                }
            }

            if($reply_only == false)
            {
                if($this->UserClient !== null)
                {
                    return $this->UserClient;
                }
            }

            return null;
        }

        /**
         * Finds the original target of a forwarded message
         *
         * @param bool $reply_only If enabled, the target user can refer to the user of that sent the message
         * @return TelegramClient|null
         */
        public function findForwardedTarget(bool $reply_only=true)
        {
            if($this->ReplyToUserForwardUserClient !== null)
            {
                return $this->ReplyToUserForwardUserClient;
            }

            if($this->ReplyToUserForwardChannelClient !== null)
            {
                return $this->ReplyToUserForwardChannelClient;
            }

            if($reply_only == false)
            {
                if($this->ForwardUserClient !== null)
                {
                    return $this->ForwardUserClient;
                }

                if($this->ForwardChannelClient !== null)
                {
                    return $this->ForwardChannelClient;
                }
            }

            return null;
        }

        /**
         * Generates a HTML mention
         *
         * @param TelegramClient $client
         * @return string
         */
        public static function generateMention(TelegramClient $client)
        {
            switch($client->Chat->Type)
            {
                case TelegramChatType::Private:
                    /** @noinspection DuplicatedCode */
                    if($client->User->Username == null)
                    {
                        if($client->User->LastName == null)
                        {
                            return "<a href=\"tg://user?id=" . $client->User->ID . "\">" . self::escapeHTML($client->User->FirstName) . "</a>";
                        }
                        else
                        {
                            return "<a href=\"tg://user?id=" . $client->User->ID . "\">" . self::escapeHTML($client->User->FirstName . " " . $client->User->LastName) . "</a>";
                        }
                    }
                    else
                    {
                        return "@" . $client->User->Username;
                    }
                    break;

                case TelegramChatType::SuperGroup:
                case TelegramChatType::Group:
                case TelegramChatType::Channel:
                    /** @noinspection DuplicatedCode */
                    if($client->Chat->Username == null)
                    {
                        if($client->Chat->Title !== null)
                        {
                            return "<a href=\"tg://user?id=" . $client->User->ID . "\">" . self::escapeHTML($client->Chat->Title) . "</a>";
                        }
                    }
                    else
                    {
                        return "@" . $client->Chat->Username;
                    }

                    break;

                default:
                    return "<a href=\"tg://user?id=" . $client->Chat->ID . "\">Unknown</a>";
            }

            return "Unknown";
        }

        /**
         * Generates a private mention that cannot be linked back to the original user but can be used as a way to
         * uniquely identify a user.
         *
         * @param TelegramClient $client
         * @param bool $include_username
         * @return string
         */
        public static function generatePrivateMention(TelegramClient $client)
        {
            switch($client->Chat->Type)
            {
                case TelegramChatType::Private:
                    /** @noinspection DuplicatedCode */
                    if($client->User->LastName == null)
                    {
                        return self::escapeHTML($client->User->FirstName);
                    }
                    else
                    {
                        return self::escapeHTML($client->User->FirstName . " " . $client->User->LastName);
                    }

                case TelegramChatType::SuperGroup:
                case TelegramChatType::Group:
                case TelegramChatType::Channel:
                    /** @noinspection DuplicatedCode */
                    if($client->Chat->Title !== null)
                    {
                        return self::escapeHTML($client->Chat->Title);
                    }

                    break;

                default:
                    return "Unknown";
            }

            return "Unknown";
        }

        /**
         * Command execute method
         *
         * @return ServerResponse
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramException
         * @noinspection DuplicatedCode
         */
        public function execute(): ServerResponse
        {
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();

            try
            {
                // Find all clients
                $this->findClients();
                $this->DestinationChat = $this->ChatObject;
                $this->ReplyToID = $this->getMessage()->getMessageId();
            }
            catch(Exception $e)
            {
                $ReferenceID = SpamProtectionBot::getLogHandler()->logException($e, "Worker");
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "Oops! Something went wrong! contact someone in @IntellivoidDiscussions\n\n" .
                        "Error Code: <code>" . $ReferenceID . "</code>\n" .
                        "Object: <code>Commands/" . $this->name . ".bin</code>"
                ]);
            }

            // Tally DeepAnalytics trait
            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'whois_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->ChatClient->ID);
            $DeepAnalytics->tally('tg_spam_protection', 'whois_command', (int)$this->ChatClient->ID);

            // Ignore forwarded commands
            if($this->getMessage()->getForwardFrom() !== null || $this->getMessage()->getForwardFromChat())
            {
                return Request::emptyResponse();
            }

            // Parse the options
            if($this->getMessage()->getText(true) !== null && strlen($this->getMessage()->getText(true)) > 0)
            {
                $options = pop::parse($this->getMessage()->getText(true));

                if(isset($options["p"]) == true || isset($options["private"]))
                {
                    if($this->ChatObject->Type !== TelegramChatType::Private)
                    {
                        $this->PrivateMode = true;
                        $this->DestinationChat = new TelegramClient\Chat();
                        $this->DestinationChat->ID = $this->UserObject->ID;
                        $this->DestinationChat->Type = TelegramChatType::Private;
                        $this->DestinationChat->FirstName = $this->UserObject->FirstName;
                        $this->DestinationChat->LastName = $this->UserObject->LastName;
                        $this->DestinationChat->Username = $this->UserObject->Username;
                        $this->ReplyToID = null;
                    }
                }

                if(isset($options["info"]))
                {
                    if($this->PrivateMode)
                    {
                        Request::deleteMessage([
                            "chat_id" => $this->ChatObject->ID,
                            "message_id" => $this->getMessage()->getMessageId()
                        ]);
                    }

                    return Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "parse_mode" => "html",
                        "reply_to_message_id" => $this->ReplyToID,
                        "text" =>
                            $this->name . " (v" . $this->version . ")\n" .
                            " Usage: <code>" . $this->usage . "</code>\n\n" .
                            "<i>" . $this->description . "</i>"
                    ]);
                }
            }

            // If this message is a reply
            if($this->getMessage()->getReplyToMessage() !== null)
            {
                // If the reply is to forwarded content
                if($this->findForwardedTarget() !== null)
                {
                    if($this->findTarget() !== null)
                    {
                        if($this->PrivateMode)
                        {
                            Request::deleteMessage([
                                "chat_id" => $this->ChatObject->ID,
                                "message_id" => $this->getMessage()->getMessageId()
                            ]);
                        }

                        return Request::sendMessage([
                            "chat_id" => $this->DestinationChat->ID,
                            "parse_mode" => "html",
                            "reply_to_message_id" => $this->ReplyToID,
                            "text" =>
                                self::resolveTarget($this->findForwardedTarget(), false, "None", true) .
                                "\n\n" .
                                self::resolveTarget($this->findTarget(), false, "None")
                        ]);
                    }

                    if($this->PrivateMode)
                    {
                        Request::deleteMessage([
                            "chat_id" => $this->ChatObject->ID,
                            "message_id" => $this->getMessage()->getMessageId()
                        ]);
                    }

                    return Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "parse_mode" => "html",
                        "reply_to_message_id" => $this->ReplyToID,
                        "text" => self::resolveTarget($this->findForwardedTarget(), false, "None", true)
                    ]);
                }

                // If the reply is directly to another uer
                if($this->findTarget() !== null)
                {
                    if($this->PrivateMode)
                    {
                        Request::deleteMessage([
                            "chat_id" => $this->ChatObject->ID,
                            "message_id" => $this->getMessage()->getMessageId()
                        ]);
                    }

                    return Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "parse_mode" => "html",
                        "reply_to_message_id" => $this->ReplyToID,
                        "text" => self::resolveTarget($this->findTarget(), false, "None")
                    ]);
                }
            }

            // If the message contains text which is not null or empty
            if($this->getMessage()->getText(true) !== null && strlen($this->getMessage()->getText(true)) > 0)
            {
                // NOTE: Argument parsing is done with pop now.
                $options = pop::parse($this->getMessage()->getText(true));

                $TargetTelegramParameter = array_values($options)[(count($options)-1)];
                if(is_bool($TargetTelegramParameter))
                {
                    $TargetTelegramParameter = array_keys($options)[(count($options)-1)];
                }

                // The next key for a chat/channel should be the chat/channel id, otherwise it's invalid.
                if (array_key_exists("c", $options) == true)
                {
                    $TargetTelegramParameter = $this->ChatObject->ID;
                    // If `c`/`chat` is true, then the next parameter is the ID.
                    if (is_bool($options["c"]) == true)
                    {
                        $idx = array_search("c", array_keys($options));
                        $idx += 1;
                        if ($idx < sizeof($options))
                        {
                            // Ensure the argument is a number and not another flag
                            $id = array_keys($options)[$idx];
                            if (is_numeric($id) === true)
                            {
                                $TargetTelegramParameter = "-" . $id;
                            }
                        }
                    }
                    else
                    {
                        $TargetTelegramParameter = $options["c"];
                    }
                }

                if (array_key_exists("chat", $options) == true)
                {
                    $TargetTelegramParameter = $this->ChatObject->ID;
                    // If `c`/`chat` is true, then the next parameter is the ID.
                    if (is_bool($options["chat"]) == true)
                    {
                        $idx = array_search("chat", array_keys($options));
                        $idx += 1;
                        if ($idx < sizeof($options))
                        {
                            // Ensure the argument is a number and not another flag
                            $id = array_keys($options)[$idx];
                            if (is_numeric($id) === true)
                            {
                                $TargetTelegramParameter = "-" . $id;
                            }
                        }
                    }
                    else
                    {
                        $TargetTelegramParameter = $options["chat"];
                    }
                }

                // Fallback
                if ($TargetTelegramParameter == null)
                {
                    $TargetTelegramParameter = $this->UserObject->ID;
                }

                $EstimatedPrivateID = Hashing::telegramClientPublicID((int)$TargetTelegramParameter, (int)$TargetTelegramParameter);

                try
                {
                    $TargetTelegramClient = $TelegramClientManager->getTelegramClientManager()->getClient(TelegramClientSearchMethod::byPublicId, $EstimatedPrivateID);

                    if($this->PrivateMode)
                    {
                        Request::deleteMessage([
                            "chat_id" => $this->ChatObject->ID,
                            "message_id" => $this->getMessage()->getMessageId()
                        ]);
                    }

                    return Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "parse_mode" => "html",
                        "reply_to_message_id" => $this->ReplyToID,
                        "text" => self::resolveTarget($TargetTelegramClient, true, "ID")
                    ]);
                }
                catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                {
                    unset($telegramClientNotFoundException);
                }

                try
                {
                    $TargetTelegramClient = $TelegramClientManager->getTelegramClientManager()->getClient(TelegramClientSearchMethod::byPublicId, $TargetTelegramParameter);

                    if($this->PrivateMode)
                    {
                        Request::deleteMessage([
                            "chat_id" => $this->ChatObject->ID,
                            "message_id" => $this->getMessage()->getMessageId()
                        ]);
                    }

                    return Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "parse_mode" => "html",
                        "reply_to_message_id" => $this->ReplyToID,
                        "text" => self::resolveTarget($TargetTelegramClient, true)
                    ]);
                }
                catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                {
                    unset($telegramClientNotFoundException);
                }

                try
                {
                    $TargetTelegramClient = $TelegramClientManager->getTelegramClientManager()->getClient(
                        TelegramClientSearchMethod::byUsername, str_ireplace("@", "", $TargetTelegramParameter)
                    );

                    if($this->PrivateMode)
                    {
                        Request::deleteMessage([
                            "chat_id" => $this->ChatObject->ID,
                            "message_id" => $this->getMessage()->getMessageId()
                        ]);
                    }

                    return Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "parse_mode" => "html",
                        "reply_to_message_id" => $this->ReplyToID,
                        "text" => self::resolveTarget($TargetTelegramClient, true, "Username")
                    ]);
                }
                catch(TelegramClientNotFoundException $telegramClientNotFoundException)
                {
                    unset($telegramClientNotFoundException);
                }

                if(count($this->MentionUserClients) > 0)
                {
                    if($this->PrivateMode)
                    {
                        Request::deleteMessage([
                            "chat_id" => $this->ChatObject->ID,
                            "message_id" => $this->getMessage()->getMessageId()
                        ]);
                    }

                    return Request::sendMessage([
                        "chat_id" => $this->DestinationChat->ID,
                        "parse_mode" => "html",
                        "reply_to_message_id" => $this->ReplyToID,
                        "text" => self::resolveTarget($this->MentionUserClients[array_keys($this->MentionUserClients)[0]], true, "Mention")
                    ]);
                }

                if($this->PrivateMode)
                {
                    Request::deleteMessage([
                        "chat_id" => $this->ChatObject->ID,
                        "message_id" => $this->getMessage()->getMessageId()
                    ]);
                }

                return Request::sendMessage([
                    "chat_id" => $this->DestinationChat->ID,
                    "reply_to_message_id" => $this->ReplyToID,
                    "text" => str_ireplace('%s', $TargetTelegramParameter,
                        LanguageCommand::localizeChatText($this, "Unable to resolve the query '%s'!", ['s']))
                ]);
            }

            if($this->PrivateMode)
            {
                Request::deleteMessage([
                    "chat_id" => $this->ChatObject->ID,
                    "message_id" => $this->getMessage()->getMessageId()
                ]);
            }

            return Request::sendMessage([
                "chat_id" => $this->DestinationChat->ID,
                "parse_mode" => "html",
                "reply_to_message_id" => $this->ReplyToID,
                "text" => self::resolveTarget($this->UserClient, false, "None")
            ]);
        }

        /**
         * Resolves the client target and returns the generated information about the target
         *
         * @param TelegramClient $target_client
         * @param bool $is_resolved
         * @param string $resolved_type
         * @param bool $is_forwarded
         * @return string
         */
        public function resolveTarget(TelegramClient $target_client, bool $is_resolved=false, string $resolved_type="Private ID", bool $is_forwarded=false): string
        {
            switch($target_client->Chat->Type)
            {
                case TelegramChatType::SuperGroup:
                case TelegramChatType::Group:
                    if($is_resolved)
                    {
                        return $this->generateChatInfoString($target_client, LanguageCommand::localizeChatText($this, "Resolved Chat " . $resolved_type));
                    }

                    if($is_forwarded)
                    {
                        return $this->generateChatInfoString($target_client, LanguageCommand::localizeChatText($this, "Forwarded Chat"));
                    }

                    return $this->generateChatInfoString($target_client, LanguageCommand::localizeChatText($this, "Chat Information"));

                case TelegramChatType::Channel:
                    if($is_resolved)
                    {
                        return $this->generateChannelInfoString($target_client, LanguageCommand::localizeChatText($this, "Resolved Channel " . $resolved_type));
                    }

                    if($is_forwarded)
                    {
                        return $this->generateChannelInfoString($target_client, LanguageCommand::localizeChatText($this, "Forwarded Channel"));
                    }

                    return $this->generateChannelInfoString($target_client, LanguageCommand::localizeChatText($this, "Channel Information"));

                case TelegramChatType::Private:
                    if($is_resolved)
                    {
                        return $this->generateUserInfoString($target_client, LanguageCommand::localizeChatText($this, "Resolved User " . $resolved_type));
                    }

                    if($is_forwarded)
                    {
                        return $this->generateUserInfoString($target_client, LanguageCommand::localizeChatText($this, "Original Sender"));
                    }

                    return $this->generateUserInfoString($target_client, LanguageCommand::localizeChatText($this, "User Information"));

                default:
                    return $this->generateGenericInfoString($target_client, LanguageCommand::localizeChatText($this, "Resolved Information"));
            }
        }

        /**
         * If the client is neither a user, group, super group or channel then it's generic information
         *
         * @param TelegramClient $client
         * @param string $title
         * @return string
         */
        private function generateGenericInfoString(TelegramClient $client, string $title="Resolved Information"): string
        {
            $Response = "<b>$title</b>\n\n";

            $Response .= str_ireplace(
                '%s', "<code>" . $client->PublicID . "</code>",
                LanguageCommand::localizeChatText($this, "Private ID: %s", ['s'])) . "\n";
            $Response .= str_ireplace(
                '%s', "<code>" . $client->User->ID . "</code>",
                LanguageCommand::localizeChatText($this, "User ID: %s", ['s'])) . "\n";
            $Response .= str_ireplace(
                '%s', "<code>" . $client->Chat->ID . "</code>",
                LanguageCommand::localizeChatText($this, "Chat ID: %s", ['s'])) . "\n";

            if($client->User->FirstName !== null)
            {
                $Response .= str_ireplace(
                    '%s', "<code>" . self::escapeHTML($client->User->FirstName) . "</code>",
                    LanguageCommand::localizeChatText($this, "User First Name: %s", ['s'])) . "\n";
            }

            if($client->User->LastName !== null)
            {
                $Response .= str_ireplace(
                    '%s', "<code>" . self::escapeHTML($client->User->LastName) . "</code>",
                    LanguageCommand::localizeChatText($this, "User Last Name: %s", ['s'])) . "\n";
            }

            if($client->User->Username !== null)
            {
                $Response .= str_ireplace(
                    '%s', "<code>" . self::escapeHTML($client->User->Username) . "</code> (@" . $client->User->Username . ")",
                    LanguageCommand::localizeChatText($this, "User Username: %s", ['s'])) . "\n";
            }

            if($client->User->IsBot)
            {
                $Response .= str_ireplace("%s", "<code>True</code>", LanguageCommand::localizeChatText($this,"Is Bot: %s", ['s'])) . "\n";
            }

            if($client->Chat->Type !== null)
            {
                $Response .= str_ireplace("%s", "<code>" . self::escapeHTML($client->Chat->Type) . "</code>", LanguageCommand::localizeChatText($this,"Chat Type: %s", ['s'])) . "\n";
            }

            if($client->Chat->Username !== null)
            {
                $Response .= str_ireplace("%s", "<code>" . self::escapeHTML($client->Chat->Username) . "</code>", LanguageCommand::localizeChatText($this,"Chat Username: %s", ['s'])) . "\n";
            }

            if($client->Chat->Title !== null)
            {
                $Response .= str_ireplace("%s", "<code>" . self::escapeHTML($client->Chat->Title) . "</code>", LanguageCommand::localizeChatText($this,"Chat Title: %s", ['s'])) . "\n";
            }

            return $Response;
        }

        /**
         * Generates a user information string
         *
         * @param TelegramClient $user_client
         * @param string $title
         * @return string
         * @noinspection DuplicatedCode
         */
        private function generateUserInfoString(TelegramClient $user_client, string $title="User Information"): string
        {
            // TODO: Add language support
            $UserStatus = SettingsManager::getUserStatus($user_client);
            $RequiresExtraNewline = false;
            $Response = "<b>$title</b>\n\n";

            if(in_array($user_client->User->ID, MAIN_OPERATOR_IDS, true))
            {
                $RequiresExtraNewline = true;
                $Response .= "\u{2705} " . LanguageCommand::localizeChatText($this, "This user is a main operator") . "\n";
            }

            if($user_client->AccountID !== null && $user_client->AccountID !== 0)
            {
                $RequiresExtraNewline = true;
                $Response .= "\u{2705} " . LanguageCommand::localizeChatText($this, "This user's Telegram account is verified by Intellivoid Accounts") . "\n";
            }

            if($user_client->User->IsBot == false)
            {
                if($UserStatus->GeneralizedSpamLabel == "spam")
                {
                    $RequiresExtraNewline = true;
                    $Response .= "\u{26A0} <b>" . LanguageCommand::localizeChatText($this, "This user may be a potential spammer") . "</b>\n";
                }
            }

            if($UserStatus->IsBlacklisted)
            {
                if($user_client->User->IsBot)
                {
                    $RequiresExtraNewline = true;
                    $Response .= "\u{26A0} <b>" . LanguageCommand::localizeChatText($this, "This bot is blacklisted!") . "</b>\n";
                }
                else
                {
                    $RequiresExtraNewline = true;
                    $Response .= "\u{26A0} <b>" . LanguageCommand::localizeChatText($this, "This user is blacklisted!") . "</b>\n";
                }
            }

            if($UserStatus->IsAgent)
            {
                if($user_client->User->IsBot)
                {
                    $RequiresExtraNewline = true;
                    $Response .= "\u{1F46E} " . LanguageCommand::localizeChatText($this, "This bot is an agent who actively reports spam automatically") . "\n";
                }
                else
                {
                    $RequiresExtraNewline = true;
                    $Response .= "\u{1F46E} " . LanguageCommand::localizeChatText($this, "This user is an agent who actively reports spam automatically") . "\n";
                }
            }

            if($UserStatus->IsOperator)
            {
                $RequiresExtraNewline = true;
                $Response .= "\u{1F46E} " . LanguageCommand::localizeChatText($this, "This user is an operator who can blacklist users") . "\n";
            }

            if($RequiresExtraNewline)
            {
                $Response .= "\n";
            }


            $Response .= str_ireplace("%s", "<code>" . $user_client->PublicID . "</code>",
                LanguageCommand::localizeChatText($this, "Private ID: %s", ['s']) ) . "\n";

            if($user_client->User->IsBot)
            {
                $Response .= str_ireplace("%s", "<code>" . $user_client->User->ID . "</code>",
                        LanguageCommand::localizeChatText($this, "Bot ID: %s", ['s']) ) . "\n";
            }
            else
            {
                $Response .= str_ireplace("%s", "<code>" . $user_client->User->ID . "</code>",
                    LanguageCommand::localizeChatText($this, "User ID: %s", ['s']) ) . "\n";
            }

            if($user_client->User->FirstName !== null)
            {
                $Response .= str_ireplace("%s", "<code>" . self::escapeHTML($user_client->User->FirstName) . "</code>",
                    LanguageCommand::localizeChatText($this, "First Name: %s", ['s']) ) . "\n";
            }

            if($user_client->User->LastName !== null)
            {
                $Response .= str_ireplace("%s", "<code>" . self::escapeHTML($user_client->User->LastName) . "</code>",
                    LanguageCommand::localizeChatText($this, "Last Name: %s", ['s']) ) . "\n";
            }

            if($user_client->User->Username !== null)
            {
                $Response .= str_ireplace("%s", "<code>" . $user_client->User->Username . "</code>",
                    LanguageCommand::localizeChatText($this, "Username: %s", ['s']) ) . " (@" . $user_client->User->Username . ")\n";
            }

            if($user_client->User->IsBot == false)
            {
                $Response .= str_ireplace("%s", "<code>" . $UserStatus->getTrustPrediction() . "</code>",
                        LanguageCommand::localizeChatText($this, "Trust Prediction: %s", ['s']) ) . "\n";
            }

            if($UserStatus->LargeLanguageGeneralizedID !== null)
            {
                $Response .= str_ireplace("%s", "<code>" . $UserStatus->GeneralizedLanguage . "</code> (<code>" . ($UserStatus->GeneralizedLanguageProbability * 100) . "</code>)",
                    LanguageCommand::localizeChatText($this, "Language Prediction: %s", ['s']) ) . "\n";
            }

            if($UserStatus->GeneralizedSpamLabel == "spam")
            {
                $Response .= str_ireplace("%s", "<code>" . LanguageCommand::localizeChatText($this, "True") . "</code>",
                        LanguageCommand::localizeChatText($this, "Potential Spammer: %s", ['s']) ) . "\n";
            }

            $Response .= str_ireplace("%s", "<code>" . $UserStatus->ReputationPoints . "</code>",
                    LanguageCommand::localizeChatText($this, "Reputation: %s", ['s']) ) . "\n";

            if($UserStatus->IsWhitelisted)
            {
                $Response .= str_ireplace("%s", "<code>" . LanguageCommand::localizeChatText($this, "True") . "</code>",
                        LanguageCommand::localizeChatText($this, "Whitelisted: %s", ['s']) ) . "\n";
            }

            if($UserStatus->IsBlacklisted)
            {
                $Response .= str_ireplace("%s", "<code>" . LanguageCommand::localizeChatText($this, "True") . "</code>",
                        LanguageCommand::localizeChatText($this, "Blacklisted: %s", ['s']) ) . "\n";

                switch($UserStatus->BlacklistFlag)
                {
                    case BlacklistFlag::BanEvade:
                        $Response .= str_ireplace("%s", "<code>" .
                                LanguageCommand::localizeChatText($this, BlacklistCommand::blacklistFlagToReason($UserStatus->BlacklistFlag))  . "</code>",
                                LanguageCommand::localizeChatText($this, "Blacklist Reason: %s", ['s']) ) . "\n";

                        $Response .= str_ireplace("%s", "<code>" . $UserStatus->OriginalPrivateID . "</code>",
                                LanguageCommand::localizeChatText($this, "Original Private ID: %s", ['s']) ) . "\n";
                        break;

                    default:
                        $Response .= str_ireplace("%s", "<code>" .
                                LanguageCommand::localizeChatText($this, BlacklistCommand::blacklistFlagToReason($UserStatus->BlacklistFlag))  . "</code>",
                                LanguageCommand::localizeChatText($this, "Blacklist Reason: %s", ['s']) ) . "\n";
                        break;
                }

            }

            $Response .= str_ireplace("%s", "<a href=\"tg://user?id=" . $user_client->User->ID . "\">tg://user?id=" . $user_client->User->ID . "</a>",
                    LanguageCommand::localizeChatText($this, "User Link: %s", ['s']) ) . "\n";

            if($UserStatus->OperatorNote !== "None")
            {
                $Response .= "\n" . self::escapeHTML($UserStatus->OperatorNote) . "\n";
            }

            return $Response;
        }

        /**
         * Generates a user information string
         *
         * @param TelegramClient $chat_client
         * @param string $title
         * @return string
         * @noinspection DuplicatedCode
         */
        private function generateChatInfoString(TelegramClient $chat_client, string $title="Chat Information"): string
        {
            $ChatSettings = SettingsManager::getChatSettings($chat_client);
            $RequiresExtraNewline = false;
            $Response = "<b>$title</b>\n\n";

            if($ChatSettings->ForwardProtectionEnabled)
            {
                $RequiresExtraNewline = true;
                $Response .= "\u{1F6E1} " . LanguageCommand::localizeChatText($this, "This chat has forward protection enabled") . "\n";
            }

            if($ChatSettings->IsVerified)
            {
                $RequiresExtraNewline = true;
                $Response .= "\u{2705} " .  LanguageCommand::localizeChatText($this, "This chat is verified by Intellivoid Technologies") . "\n";
            }

            if($RequiresExtraNewline)
            {
                $Response .= "\n";
            }

            $Response .= str_ireplace("%s", "<code>" . $chat_client->PublicID . "</code>",
                    LanguageCommand::localizeChatText($this, "Private ID: %s", ['s']) ) . "\n";
            $Response .= str_ireplace("%s", "<code>" . $chat_client->Chat->ID . "</code>",
                    LanguageCommand::localizeChatText($this, "Chat ID: %s", ['s']) ) . "\n";
            /** @noinspection PhpToStringImplementationInspection */
            $Response .= str_ireplace("%s", "<code>" . $chat_client->Chat->Type . "</code>",
                    LanguageCommand::localizeChatText($this, "Chat Type: %s", ['s']) ) . "\n";
            $Response .= str_ireplace("%s", "<code>" . self::escapeHTML($chat_client->Chat->Title) . "</code>",
                    LanguageCommand::localizeChatText($this, "Chat Title: %s", ['s']) ) . "\n";


            if($chat_client->Chat->Username !== null)
            {
                $Response .= str_ireplace("%s", "<code>" . $chat_client->Chat->Username . "</code> (@" . $chat_client->Chat->Username . ")",
                        LanguageCommand::localizeChatText($this, "Chat Username: %s", ['s']) ) . "\n";
            }

            if($ChatSettings->ForwardProtectionEnabled)
            {
                $Response .= str_ireplace("%s", "<code>" . LanguageCommand::localizeChatText($this, "True") . "</code>",
                        LanguageCommand::localizeChatText($this, "Forward Protection Enabled: %s", ['s']) ) . "\n";
            }

            if($ChatSettings->DetectSpamEnabled)
            {
                $Response .= str_ireplace("%s", "<code>" . LanguageCommand::localizeChatText($this, "True") . "</code>",
                        LanguageCommand::localizeChatText($this, "Spam Detection Enabled: %s", ['s']) ) . "\n";

                switch($ChatSettings->DetectSpamAction)
                {
                    case DetectionAction::Nothing:
                        $Response .= str_ireplace("%s", "<code>" .  LanguageCommand::localizeChatText($this, "Nothing") . "</code>",
                                LanguageCommand::localizeChatText($this, "Spam Detection Action: %s", ['s']) ) . "\n";
                        break;

                    case DetectionAction::DeleteMessage:
                        $Response .= str_ireplace("%s", "<code>" .  LanguageCommand::localizeChatText($this, "Delete Content") . "</code>",
                                LanguageCommand::localizeChatText($this, "Spam Detection Action: %s", ['s']) ) . "\n";
                        break;

                    case DetectionAction::KickOffender:
                        $Response .= str_ireplace("%s", "<code>" .  LanguageCommand::localizeChatText($this, "Remove Offender") . "</code>",
                                LanguageCommand::localizeChatText($this, "Spam Detection Action: %s", ['s']) ) . "\n";
                        break;

                    case DetectionAction::BanOffender:
                        $Response .= str_ireplace("%s", "<code>" .  LanguageCommand::localizeChatText($this, "Permanently Ban Offender") . "</code>",
                                LanguageCommand::localizeChatText($this, "Spam Detection Action: %s", ['s']) ) . "\n";
                        break;
                }
            }
            else
            {
                $Response .= str_ireplace("%s", "<code>" .  LanguageCommand::localizeChatText($this, "False") . "</code>",
                        LanguageCommand::localizeChatText($this, "Spam Detection Enabled: %s", ['s']) ) . "\n";
            }

            if($ChatSettings->GeneralAlertsEnabled)
            {
                $Response .= str_ireplace("%s", "<code>" .  LanguageCommand::localizeChatText($this, "True") . "</code>",
                        LanguageCommand::localizeChatText($this, "General Alerts Enabled: %s", ['s']) ) . "\n";
            }

            if($ChatSettings->BlacklistProtectionEnabled)
            {
                $Response .= str_ireplace("%s", "<code>" .  LanguageCommand::localizeChatText($this, "True") . "</code>",
                        LanguageCommand::localizeChatText($this, "Blacklist Protection Enabled: %s", ['s']) ) . "\n";
            }
            else
            {
                $Response .= str_ireplace("%s", "<code>" .  LanguageCommand::localizeChatText($this, "False") . "</code>",
                        LanguageCommand::localizeChatText($this, "Blacklist Protection Enabled: %s", ['s']) ) . "\n";
            }

            if($ChatSettings->ActiveSpammerProtectionEnabled)
            {
                $Response .= str_ireplace("%s", "<code>" .  LanguageCommand::localizeChatText($this, "True") . "</code>",
                        LanguageCommand::localizeChatText($this, "Active Spammer Protection Enabled: %s", ['s']) ) . "\n";
            }
            else
            {
                $Response .= str_ireplace("%s", "<code>" .  LanguageCommand::localizeChatText($this, "False") . "</code>",
                        LanguageCommand::localizeChatText($this, "Active Spammer Protection Enabled: %s", ['s']) ) . "\n";
            }

            if($ChatSettings->DeleteOlderMessages)
            {
                $Response .= str_ireplace("%s", "<code>" .  LanguageCommand::localizeChatText($this, "True") . "</code>",
                        LanguageCommand::localizeChatText($this, "Delete Older Messages: %s", ['s']) ) . "\n";
            }
            else
            {
                $Response .= str_ireplace("%s", "<code>" .  LanguageCommand::localizeChatText($this, "False") . "</code>",
                        LanguageCommand::localizeChatText($this, "Delete Older Messages: %s", ['s']) ) . "\n";
            }

            if($ChatSettings->LargeLanguageGeneralizedID !== null)
            {
                $Response .= str_ireplace("%s", "<code>" .  $ChatSettings->GeneralizedLanguage . "</code> (<code>" . ($ChatSettings->GeneralizedLanguageProbability * 100) . "</code>)",
                        LanguageCommand::localizeChatText($this, "Language Prediction: %s", ['s']) ) . "\n";
            }

            $Response .= str_ireplace("%s", "<code>" . $ChatSettings->calculateAverageMessagesPerMinute() . "</code>",
                    LanguageCommand::localizeChatText($this, "Messages per minute: %s", ['s']) ) . "\n";

            if($ChatSettings->ConfiguredLanguage !== null)
            {
                $Response .= str_ireplace("%s", "<code>" .  $ChatSettings->ConfiguredLanguage . "</code>",
                        LanguageCommand::localizeChatText($this, "Configured Language: %s", ['s']) ) . "\n";
            }

            if($ChatSettings->StrictLocalization)
            {
                $Response .= str_ireplace("%s", "<code>" .  LanguageCommand::localizeChatText($this, "True") . "</code>",
                        LanguageCommand::localizeChatText($this, "Strict Localization Enabled: %s", ['s']) ) . "\n";
            }
            else
            {
                $Response .= str_ireplace("%s", "<code>" .  LanguageCommand::localizeChatText($this, "False") . "</code>",
                        LanguageCommand::localizeChatText($this, "Strict Localization Enabled: %s", ['s']) ) . "\n";
            }

            return $Response;
        }

        /**
         * Generates a user information string
         *
         * @param TelegramClient $channel_client
         * @param string $title
         * @return string
         * @noinspection DuplicatedCode
         */
        private function generateChannelInfoString(TelegramClient $channel_client, string $title="Channel Information"): string
        {
            $ChannelStatus = SettingsManager::getChannelStatus($channel_client);
            $RequiresExtraNewline = false;
            $Response = "<b>$title</b>\n\n";

            if($ChannelStatus->IsOfficial)
            {
                $RequiresExtraNewline = true;
                $Response .= "\u{2705} " . LanguageCommand::localizeChatText($this, "This channel is official") . "\n";
            }

            if($ChannelStatus->IsBlacklisted)
            {
                $RequiresExtraNewline = true;
                $Response .= "\u{26A0} " . LanguageCommand::localizeChatText($this, "This channel is blacklisted") . "\n";
            }

            if($ChannelStatus->IsWhitelisted)
            {
                $RequiresExtraNewline = true;
                $Response .= "\u{1F530} " . LanguageCommand::localizeChatText($this, "This channel is whitelisted") . "\n";
            }

            if($ChannelStatus->LargeSpamGeneralizedID !== null)
            {
                if($ChannelStatus->GeneralizedSpamProbability > $ChannelStatus->GeneralizedHamProbability)
                {
                    $RequiresExtraNewline = true;
                    $Response .= "\u{26A0} <b>" . LanguageCommand::localizeChatText($this, "This channel may be promoting spam!") . "</b>\n";
                }
            }

            if($RequiresExtraNewline)
            {
                $Response .= "\n";
            }

            $Response .= str_ireplace("%s", "<code>" .  $channel_client->PublicID . "</code>",
                    LanguageCommand::localizeChatText($this, "Private ID: %s", ['s']) ) . "\n";
            $Response .= str_ireplace("%s", "<code>" .  $channel_client->Chat->ID . "</code>",
                    LanguageCommand::localizeChatText($this, "Channel ID: %s", ['s']) ) . "\n";
            $Response .= str_ireplace("%s", "<code>" .  self::escapeHTML($channel_client->Chat->Title) . "</code>",
                    LanguageCommand::localizeChatText($this, "Channel Title: %s", ['s']) ) . "\n";

            if($channel_client->Chat->Username !== null)
            {
                $Response .= str_ireplace("%s", "<code>" .  self::escapeHTML($channel_client->Chat->Username) . "</code> (@" . $channel_client->Chat->Username . ")",
                        LanguageCommand::localizeChatText($this, "Channel Username: %s", ['s']) ) . "\n";
            }

            if($ChannelStatus->IsBlacklisted)
            {
                $Response .= str_ireplace("%s", "<code>" .  "True" . "</code>",
                        LanguageCommand::localizeChatText($this, "Blacklisted: %s", ['s']) ) . "\n";

                switch($ChannelStatus->BlacklistFlag)
                {
                    case BlacklistFlag::BanEvade:
                        $Response .= str_ireplace("%s", "<code>" .  LanguageCommand::localizeChatText($this, "Ban Evade") . "</code>",
                                LanguageCommand::localizeChatText($this, "Blacklist Reason: %s", ['s']) ) . "\n";
                        $Response .= str_ireplace("%s", "<code>" .  LanguageCommand::localizeChatText($this, "Not applicable to channels") . "</code>",
                                LanguageCommand::localizeChatText($this, "Original Private ID: %s", ['s']) ) . "\n";
                        break;

                    default:
                        $Response .= str_ireplace("%s", "<code>" .  LanguageCommand::localizeChatText($this, BlacklistCommand::blacklistFlagToReason($ChannelStatus->BlacklistFlag)) . "</code>",
                                LanguageCommand::localizeChatText($this, "Blacklist Reason: %s", ['s']) ) . "\n";
                        break;
                }
            }

            if($ChannelStatus->LargeLanguageGeneralizedID !== null)
            {
                $Response .= str_ireplace("%s", "<code>" .  $ChannelStatus->GeneralizedLanguage . "</code> (<code>" . ($ChannelStatus->GeneralizedLanguageProbability * 100) . "</code>)",
                        LanguageCommand::localizeChatText($this, "Language Prediction: %s", ['s']) ) . "\n";
            }

            return $Response;
        }

        /**
         * Escapes problematic characters for HTML content
         *
         * @param string $input
         * @return string
         */
        private static function escapeHTML(string $input): string
        {
            return htmlspecialchars($input, ENT_COMPAT);
        }
    }