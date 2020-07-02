<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\UserCommands;

    use Exception;
    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtection\Objects\ChatSettings;
    use SpamProtection\Objects\UserStatus;
    use SpamProtectionBot;
    use TelegramClientManager\Abstracts\SearchMethods\TelegramClientSearchMethod;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;
    use TelegramClientManager\Objects\TelegramClient\Chat;
    use TelegramClientManager\Objects\TelegramClient\User;

    /**
     * Property Editor command
     *
     * Allows the main administrator to get or modify property values of a user or chat
     */
    class PropCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'prop';

        /**
         * @var string
         */
        protected $description = 'Allows the main administrator to get or modify property values of a user or chat';

        /**
         * @var string
         */
        protected $usage = '/prop [u/c] [Private ID] [Property Bit] [Property Value]';

        /**
         * @var string
         */
        protected $version = '1.0.1';

        /**
         * @var bool
         */
        protected $private_only = false;

        /**
         * Command execute method
         *
         * @return ServerResponse
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @noinspection DuplicatedCode
         */
        public function execute()
        {
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();

            $ChatObject = Chat::fromArray($this->getMessage()->getChat()->getRawData());
            $UserObject = User::fromArray($this->getMessage()->getFrom()->getRawData());

            try
            {
                $TelegramClient = $TelegramClientManager->getTelegramClientManager()->registerClient($ChatObject, $UserObject);

                // Define and update chat client
                $ChatClient = $TelegramClientManager->getTelegramClientManager()->registerChat($ChatObject);
                if(isset($UserClient->SessionData->Data["chat_settings"]) == false)
                {
                    $ChatSettings = SettingsManager::getChatSettings($ChatClient);
                    $ChatClient = SettingsManager::updateChatSettings($ChatClient, $ChatSettings);
                    $TelegramClientManager->getTelegramClientManager()->updateClient($ChatClient);
                }

                // Define and update user client
                $UserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($UserObject);
                if(isset($UserClient->SessionData->Data["user_status"]) == false)
                {
                    $UserStatus = SettingsManager::getUserStatus($UserClient);
                    $UserClient = SettingsManager::updateUserStatus($UserClient, $UserStatus);
                    $TelegramClientManager->getTelegramClientManager()->updateClient($UserClient);
                }

                // Define and update the forwarder if available
                if($this->getMessage()->getReplyToMessage() !== null)
                {
                    if($this->getMessage()->getReplyToMessage()->getForwardFrom() !== null)
                    {
                        $ForwardUserObject = User::fromArray($this->getMessage()->getReplyToMessage()->getForwardFrom()->getRawData());
                        $ForwardUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($ForwardUserObject);
                        if(isset($ForwardUserClient->SessionData->Data["user_status"]) == false)
                        {
                            $ForwardUserStatus = SettingsManager::getUserStatus($ForwardUserClient);
                            $ForwardUserClient = SettingsManager::updateUserStatus($ForwardUserClient, $ForwardUserStatus);
                            $TelegramClientManager->getTelegramClientManager()->updateClient($ForwardUserClient);
                        }
                    }

                    if($this->getMessage()->getReplyToMessage()->getForwardFromChat() !== null)
                    {
                        $ForwardChannelObject = Chat::fromArray($this->getMessage()->getReplyToMessage()->getForwardFromChat()->getRawData());
                        $ForwardChannelClient = $TelegramClientManager->getTelegramClientManager()->registerChat($ForwardChannelObject);
                        if(isset($ForwardChannelClient->SessionData->Data["channel_status"]) == false)
                        {
                            $ForwardChannelStatus = SettingsManager::getChannelStatus($ForwardChannelClient);
                            $ForwardChannelClient = SettingsManager::updateChannelStatus($ForwardChannelClient, $ForwardChannelStatus);
                            $TelegramClientManager->getTelegramClientManager()->updateClient($ForwardChannelClient);
                        }
                    }
                }
            }
            catch(Exception $e)
            {
                $ReferenceID = TgFileLogging::dumpException($e, TELEGRAM_BOT_NAME, $this->name);
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

            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'prop_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$TelegramClient->getChatId());
            $DeepAnalytics->tally('tg_spam_protection', 'prop_command', (int)$TelegramClient->getChatId());

            if($UserClient->User->Username !== "IntellivoidSupport")
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => "This command can only be used by @IntellivoidSupport"
                ]);
            }

            $CommandParameters = explode(" ", $this->getMessage()->getText(true));
            $CommandParameters = array_values(array_filter($CommandParameters, 'strlen'));

            if(count($CommandParameters) == 0)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => "Invalid object type"
                ]);
            }

            if($CommandParameters[0] == "u")
            {
                if(count($CommandParameters) < 2)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "Missing Private ID parameter"
                    ]);
                }

                try
                {
                    $TargetUserClient = $TelegramClientManager->getTelegramClientManager()->getClient(
                        TelegramClientSearchMethod::byPublicId, $CommandParameters[1]
                    );
                }
                catch (TelegramClientNotFoundException $e)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "Invalid Private ID"
                    ]);
                }

                $TargetUserStatus = SettingsManager::getUserStatus($TargetUserClient);

                if(count($CommandParameters) > 2)
                {
                    if(count($CommandParameters) == 3)
                    {
                        $Results = $TargetUserStatus->toArray();


                        if(isset($Results[$CommandParameters[2]]))
                        {
                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "text" => "<code>" . $Results[$CommandParameters[2]] . "</code>"
                            ]);
                        }
                        else
                        {
                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "text" => "Property <code>" . $CommandParameters[2] . "</code> not found"
                            ]);
                        }
                    }

                    if(count($CommandParameters) == 4)
                    {
                        $Results = $TargetUserStatus->toArray();

                        if(isset($Results[$CommandParameters[2]]) == false)
                        {
                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "text" => "Property <code>" . $CommandParameters[2] . "</code> not found"
                            ]);
                        }

                        $Results[$CommandParameters[2]] = $CommandParameters[3];
                        $TargetUserStatus = UserStatus::fromArray($TargetUserClient->User, $Results);
                        $TargetUserClient = SettingsManager::updateUserStatus($TargetUserClient, $TargetUserStatus);
                        $TelegramClientManager->getTelegramClientManager()->updateClient($TargetUserClient);

                        return Request::sendMessage([
                            "chat_id" => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            "text" => "Property <code>" . $CommandParameters[2] . "</code> updated successfully"
                        ]);
                    }
                }
                else
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "<code>" . json_encode($TargetUserStatus->toArray(), JSON_PRETTY_PRINT) . "</code>"
                    ]);
                }

            }
            elseif($CommandParameters[0] == "c")
            {
                if(count($CommandParameters) < 2)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "Missing Private Chat ID parameter"
                    ]);
                }

                try
                {
                    $TargetChatClient = $TelegramClientManager->getTelegramClientManager()->getClient(
                        TelegramClientSearchMethod::byPublicId, $CommandParameters[1]
                    );
                }
                catch (TelegramClientNotFoundException $e)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "Invalid Private ID"
                    ]);
                }

                $TargetChatSettings = SettingsManager::getChatSettings($TargetChatClient);

                if(count($CommandParameters) > 2)
                {
                    if(count($CommandParameters) == 3)
                    {
                        $Results = $TargetChatSettings->toArray();

                        if(isset($Results[$CommandParameters[2]]))
                        {
                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "text" => "<code>" . $Results[$CommandParameters[2]] . "</code>"
                            ]);
                        }
                        else
                        {
                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "text" => "Property <code>" . $CommandParameters[2] . "</code> not found"
                            ]);
                        }
                    }

                    if(count($CommandParameters) == 4)
                    {
                        $Results = $TargetChatSettings->toArray();

                        if(isset($Results[$CommandParameters[2]]) == false)
                        {

                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "text" => "Property <code>" . $CommandParameters[2] . "</code> not found"
                            ]);
                        }

                        $Results[$CommandParameters[2]] = $CommandParameters[3];
                        $TargetChatSettings = ChatSettings::fromArray($TargetChatClient->Chat, $Results);
                        $TargetChatClient = SettingsManager::updateChatSettings($TargetChatClient, $TargetChatSettings);
                        $TelegramClientManager->getTelegramClientManager()->updateClient($TargetChatClient);

                        return Request::sendMessage([
                            "chat_id" => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            "text" => "Property <code>" . $CommandParameters[2] . "</code> updated successfully"
                        ]);
                    }
                }
                else
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "<code>" . json_encode($TargetChatSettings->toArray(), JSON_PRETTY_PRINT) . "</code>"
                    ]);
                }
            }
            else
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => "Invalid object type"
                ]);
            }

            return null;
        }
    }