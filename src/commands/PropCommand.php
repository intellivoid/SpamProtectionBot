<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\UserCommands;

    use DeepAnalytics\DeepAnalytics;
    use Exception;
    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Abstracts\TelegramClientSearchMethod;
    use SpamProtection\Exceptions\DatabaseException;
    use SpamProtection\Exceptions\InvalidSearchMethod;
    use SpamProtection\Exceptions\TelegramClientNotFoundException;
    use SpamProtection\Objects\ChatSettings;
    use SpamProtection\Objects\TelegramClient\Chat;
    use SpamProtection\Objects\TelegramClient\User;
    use SpamProtection\Objects\UserStatus;
    use SpamProtection\SpamProtection;

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
        protected $name = 'Property Editor Command';

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
        protected $version = '1.0.0';

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
         * @throws TelegramException
         * @noinspection DuplicatedCode
         */
        public function execute()
        {
            $SpamProtection = new SpamProtection();

            $ChatObject = Chat::fromArray($this->getMessage()->getChat()->getRawData());
            $UserObject = User::fromArray($this->getMessage()->getFrom()->getRawData());

            try
            {
                $TelegramClient = $SpamProtection->getTelegramClientManager()->registerClient($ChatObject, $UserObject);

                // Define and update chat client
                $ChatClient = $SpamProtection->getTelegramClientManager()->registerChat($ChatObject);
                if(isset($UserClient->SessionData->Data["chat_settings"]) == false)
                {
                    $ChatSettings = $SpamProtection->getSettingsManager()->getChatSettings($ChatClient);
                    $ChatClient = $SpamProtection->getSettingsManager()->updateChatSettings($ChatClient, $ChatSettings);
                }
                $SpamProtection->getTelegramClientManager()->updateClient($ChatClient);

                // Define and update user client
                $UserClient = $SpamProtection->getTelegramClientManager()->registerUser($UserObject);
                if(isset($UserClient->SessionData->Data["user_status"]) == false)
                {
                    $UserStatus = $SpamProtection->getSettingsManager()->getUserStatus($UserClient);
                    $UserClient = $SpamProtection->getSettingsManager()->updateUserStatus($UserClient, $UserStatus);
                }
                $SpamProtection->getTelegramClientManager()->updateClient($UserClient);

                // Define and update the forwarder if available
                if($this->getMessage()->getForwardFrom() !== null)
                {
                    $ForwardUserObject = User::fromArray($this->getMessage()->getForwardFrom()->getRawData());
                    $ForwardUserClient = $SpamProtection->getTelegramClientManager()->registerUser($ForwardUserObject);
                    if(isset($ForwardUserClient->SessionData->Data["user_status"]) == false)
                    {
                        $ForwardUserStatus = $SpamProtection->getSettingsManager()->getUserStatus($ForwardUserClient);
                        $ForwardUserClient = $SpamProtection->getSettingsManager()->updateUserStatus($ForwardUserClient, $ForwardUserStatus);
                    }
                    $SpamProtection->getTelegramClientManager()->updateClient($ForwardUserClient);
                }
            }
            catch(Exception $e)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "Oops! Something went wrong! contact someone in @IntellivoidDiscussions\n\n" .
                        "Error Code: <code>" . $e->getCode() . "</code>\n" .
                        "Object: <code>Commands/prop.bin</code>"
                ]);
            }

            $DeepAnalytics = new DeepAnalytics();
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
                    $TargetUserClient = $SpamProtection->getTelegramClientManager()->getClient(
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

                $TargetUserStatus = $SpamProtection->getSettingsManager()->getUserStatus($TargetUserClient);

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
                        $TargetUserClient = $SpamProtection->getSettingsManager()->updateUserStatus($TargetUserClient, $TargetUserStatus);
                        $SpamProtection->getTelegramClientManager()->updateClient($TargetUserClient);

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
                    $TargetChatClient = $SpamProtection->getTelegramClientManager()->getClient(
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

                $TargetChatSettings = $SpamProtection->getSettingsManager()->getChatSettings($TargetChatClient);

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
                        $TargetChatClient = $SpamProtection->getSettingsManager()->updateChatSettings($TargetChatClient, $TargetChatSettings);
                        $SpamProtection->getTelegramClientManager()->updateClient($TargetChatClient);

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
        }
    }