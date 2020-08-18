<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\UserCommands;

    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use pop\pop;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtection\Objects\ChannelStatus;
    use SpamProtection\Objects\ChatSettings;
    use SpamProtection\Objects\UserStatus;
    use SpamProtectionBot;
    use TelegramClientManager\Abstracts\SearchMethods\TelegramClientSearchMethod;
    use TelegramClientManager\Abstracts\TelegramChatType;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;

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
        protected $description = 'Allows the main administrator to get or modify property values of a object';

        /**
         * @var string
         */
        protected $usage = '/prop [u/c] [Private ID] [Property Bit] [Property Value]';

        /**
         * @var string
         */
        protected $version = '2.0.0';

        /**
         * @var bool
         */
        protected $private_only = false;

        /**
         * The whois command used for finding targets
         *
         * @var WhoisCommand|null
         */
        public $WhoisCommand = null;


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
            $this->WhoisCommand = new WhoisCommand($this->telegram, $this->update);
            $this->WhoisCommand->findClients();

            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'prop_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->WhoisCommand->ChatObject->ID);
            $DeepAnalytics->tally('tg_spam_protection', 'prop_command', (int)$this->WhoisCommand->ChatObject->ID);

            // Ignore forwarded commands
            if($this->getMessage()->getForwardFrom() !== null || $this->getMessage()->getForwardFromChat())
            {
                return null;
            }

            // Parse the options
            if($this->getMessage()->getText(true) !== null && strlen($this->getMessage()->getText(true)) > 0)
            {
                $options = pop::parse($this->getMessage()->getText(true));

                if(isset($options["info"]))
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" =>
                            $this->name . " (v" . $this->version . ")\n" .
                            " Usage: <code>" . $this->usage . "</code>\n\n" .
                            "<i>" . $this->description . "</i>"
                    ]);
                }
            }

            if($this->WhoisCommand->UserObject->Username !== "Netkas")
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => "This command can only be used by @IntellivoidSupport"
                ]);
            }

            if($this->getMessage()->getText(true) !== null && strlen($this->getMessage()->getText(true)) > 0)
            {
                $options = pop::parse($this->getMessage()->getText(true));
                $private_telegram_id = null;
                $pointer = null;
                $set_value = null;

                if(isset($options["ptid"]))
                {
                    $private_telegram_id = $options["ptid"];
                }

                if(isset($options["pointer"]))
                {
                    $pointer = $options["pointer"];
                }

                if(isset($options["set-value"]))
                {
                    $set_value = $options["set-value"];
                }

                if($private_telegram_id == null)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "Invalid usage, missing option --ptid"
                    ]);
                }

                try
                {
                    $TargetClient = $TelegramClientManager->getTelegramClientManager()->getClient(
                        TelegramClientSearchMethod::byPublicId, $private_telegram_id
                    );
                }
                catch (TelegramClientNotFoundException $e)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "Property set error, Invalid Private ID"
                    ]);
                }

                switch($TargetClient->Chat->Type)
                {
                    case TelegramChatType::Private:
                        $UserStatus = SettingsManager::getUserStatus($TargetClient);
                        $Bytes = $UserStatus->toArray();

                        if($pointer !== null)
                        {
                            if(array_key_exists($pointer, $Bytes) == false)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "Property <code>" . $pointer . "</code> not found"
                                ]);
                            }

                            if($set_value == null)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "<code>" . json_encode($Bytes[$pointer], JSON_PRETTY_PRINT) . "</code>"
                                ]);
                            }
                            else
                            {
                                $Results[$pointer] = $set_value;
                                $TargetUserStatus = UserStatus::fromArray($TargetClient->User, $Results);
                                $TargetClient = SettingsManager::updateUserStatus($TargetClient, $TargetUserStatus);
                                $TelegramClientManager->getTelegramClientManager()->updateClient($TargetClient);

                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "Property <code>" . $pointer . "</code> updated successfully"
                                ]);
                            }
                        }
                        else
                        {
                            if($set_value !== null)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "Property set error, the parameter --set-value requires the parameter --pointer"
                                ]);
                            }

                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "text" => "<code>" . json_encode($Bytes, JSON_PRETTY_PRINT) . "</code>"
                            ]);
                        }
                        break;

                    case TelegramChatType::Channel:
                        $ChannelStatus = SettingsManager::getChannelStatus($TargetClient);
                        $Bytes = $ChannelStatus->toArray();

                        if($pointer !== null)
                        {
                            if(array_key_exists($pointer, $Bytes) == false)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "Property <code>" . $pointer . "</code> not found"
                                ]);
                            }

                            if($set_value == null)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "<code>" . json_encode($Bytes[$pointer], JSON_PRETTY_PRINT) . "</code>"
                                ]);
                            }
                            else
                            {
                                $Bytes[$pointer] = $set_value;
                                $TargetChannelStatus = ChannelStatus::fromArray($TargetClient->Chat, $Bytes);
                                $TargetChannelClient = SettingsManager::updateChannelStatus($TargetClient, $TargetChannelStatus);
                                $TelegramClientManager->getTelegramClientManager()->updateClient($TargetChannelClient);

                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "Property <code>" . $pointer . "</code> updated successfully"
                                ]);
                            }
                        }
                        else
                        {
                            if($set_value !== null)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "Property set error, the parameter --set-value requires the parameter --pointer"
                                ]);
                            }

                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "text" => "<code>" . json_encode($Bytes, JSON_PRETTY_PRINT) . "</code>"
                            ]);
                        }
                        break;

                    case TelegramChatType::Group:
                    case TelegramChatType::SuperGroup:
                        $ChatStatus = SettingsManager::getChatSettings($TargetClient);
                        $Bytes = $ChatStatus->toArray();

                        if($pointer !== null)
                        {
                            if(array_key_exists($pointer, $Bytes) == false)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "Property <code>" . $pointer . "</code> not found"
                                ]);
                            }

                            if($set_value == null)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "<code>" . json_encode($Bytes[$pointer], JSON_PRETTY_PRINT) . "</code>"
                                ]);
                            }
                            else
                            {
                                $Bytes[$pointer] = $set_value;
                                $TargetChatSettings = ChatSettings::fromArray($TargetClient->Chat, $Bytes);
                                $TargetChatClient = SettingsManager::updateChatSettings($TargetClient, $TargetChatSettings);
                                $TelegramClientManager->getTelegramClientManager()->updateClient($TargetChatClient);

                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "Property <code>" . $pointer . "</code> updated successfully"
                                ]);
                            }
                        }
                        else
                        {
                            if($set_value !== null)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "Property set error, the parameter --set-value requires the parameter --pointer"
                                ]);
                            }

                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "text" => "<code>" . json_encode($Bytes, JSON_PRETTY_PRINT) . "</code>"
                            ]);
                        }
                        break;

                    default:
                        return Request::sendMessage([
                            "chat_id" => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            "text" => "Property set error, this object type contains no properties that can be modified."
                        ]);
                }
            }

            return Request::sendMessage([
                "chat_id" => $this->getMessage()->getChat()->getId(),
                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                "parse_mode" => "html",
                "text" => "Invalid usage, missing arguments"
            ]);
        }

    }