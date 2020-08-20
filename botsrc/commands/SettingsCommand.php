<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\UserCommands;

    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\Message;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Abstracts\DetectionAction;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtectionBot;
    use TelegramClientManager\Abstracts\TelegramChatType;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;

    /**
     * Settings Command
     *
     * Allows the chat administrator to alter settings for the chat
     */
    class SettingsCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'settings';

        /**
         * @var string
         */
        protected $description = 'Allows the chat administrator to alter settings for the chat';

        /**
         * @var string
         */
        protected $usage = '/settings [Option] [Value]';

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
         * @throws TelegramException
         * @throws TelegramClientNotFoundException
         * @noinspection DuplicatedCode
         */
        public function execute()
        {
            // Find clients
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
            $this->WhoisCommand = new WhoisCommand($this->telegram, $this->update);
            $this->WhoisCommand->findClients();

            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'settings_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->WhoisCommand->ChatObject->ID);
            $DeepAnalytics->tally('tg_spam_protection', 'settings_command', (int)$this->WhoisCommand->ChatObject->ID);

            // Ignore forwarded commands
            if($this->getMessage()->getForwardFrom() !== null || $this->getMessage()->getForwardFromChat())
            {
                return null;
            }

            if($this->WhoisCommand->ChatObject->Type !== TelegramChatType::Group)
            {
                if($this->WhoisCommand->ChatObject->Type !== TelegramChatType::SuperGroup)
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "This command can only be used in group chats!"
                    ]);
                }
            }

            // Verify if the user is an administrator
            $UserChatMember = Request::getChatMember([
                "user_id" => $this->WhoisCommand->UserObject->ID,
                "chat_id" => $this->WhoisCommand->ChatObject->ID
            ]);

            if($UserChatMember->isOk() == false)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => "This command can only be used by chat administrators"
                ]);
            }

            if($UserChatMember->getResult()->status !== "creator")
            {
                if($UserChatMember->getResult()->status !== "administrator")
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "This command can only be used by chat administrators"
                    ]);
                }
            }

            if($UserChatMember->getResult()->status !== "administrator")
            {
                if($UserChatMember->getResult()->status !== "creator")
                {
                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => "This command can only be used by chat administrators"
                    ]);
                }
            }

            // Parse the text
            if($this->getMessage()->getText(true) !== null)
            {
                if (strlen($this->getMessage()->getText(true)) > 0)
                {
                    $CommandParameters = explode(" ", $this->getMessage()->getText(true));
                    $CommandParameters = array_values(array_filter($CommandParameters, 'strlen'));

                    if(count($CommandParameters) !== 2)
                    {

                        return self::displayUsage($this->getMessage(), "Missing parameter");
                    }

                    $TargetOptionParameter = $CommandParameters[0];
                    $TargetValueParameter = $CommandParameters[1];

                    $ChatSettings = SettingsManager::getChatSettings($this->WhoisCommand->ChatClient);

                    switch(strtolower($TargetOptionParameter))
                    {
                        case "detect_spam":
                            $ChatSettings->DetectSpamEnabled = self::isEnabledValue($TargetValueParameter);
                            SettingsManager::updateChatSettings($this->WhoisCommand->ChatClient, $ChatSettings);
                            $TelegramClientManager->getTelegramClientManager()->updateClient($this->WhoisCommand->ChatClient);

                            if($ChatSettings->DetectSpamEnabled)
                            {
                                switch($ChatSettings->DetectSpamAction)
                                {
                                    case DetectionAction::Nothing:
                                        if($ChatSettings->GeneralAlertsEnabled == false)
                                        {
                                            return Request::sendMessage([
                                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                                "parse_mode" => "html",
                                                "text" =>
                                                    "Success? Spam will be detected but nothing will happen because General Alerts is disabled and the current action on spam detection is to do nothing\n\n".
                                                    "To fix this, send the following commands:\n".
                                                    "  <code>/settings detect_spam_action delete</code>\n".
                                                    "  <code>/settings general_alerts enabled</code>"
                                            ]);
                                        }
                                        else
                                        {
                                            return Request::sendMessage([
                                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                                "parse_mode" => "html",
                                                "text" =>
                                                    "Success! Spam will be detected and alerts will be shown but the bot can't do anything about the spam\n\n".
                                                    "To fix this, send the following commands:\n".
                                                    "  <code>/settings detect_spam_action delete</code>"
                                            ]);
                                        }

                                    case DetectionAction::DeleteMessage:
                                        return Request::sendMessage([
                                            "chat_id" => $this->getMessage()->getChat()->getId(),
                                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                            "parse_mode" => "html",
                                            "text" => "Success! Spam will be detected and deleted"
                                        ]);

                                    case DetectionAction::KickOffender:
                                        return Request::sendMessage([
                                            "chat_id" => $this->getMessage()->getChat()->getId(),
                                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                            "parse_mode" => "html",
                                            "text" => "Success! Spam will be detected and deleted, additionally the offender will be removed from this group"
                                        ]);

                                    case DetectionAction::BanOffender:
                                        return Request::sendMessage([
                                            "chat_id" => $this->getMessage()->getChat()->getId(),
                                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                            "parse_mode" => "html",
                                            "text" => "Success! Spam will be detected and deleted, additionally the offender will be banned from this group"
                                        ]);

                                    default:
                                        return Request::sendMessage([
                                            "chat_id" => $this->getMessage()->getChat()->getId(),
                                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                            "parse_mode" => "html",
                                            "text" => "Success!"
                                        ]);
                                }
                            }
                            else
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "Success! The bot will no longer detect spam in this group"
                                ]);
                            }
                            break;

                        case "detect_spam_action":
                            if(self::actionFromString($TargetValueParameter) == null)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" =>
                                        "This is an invalid action!\n\n".
                                        "  <code>nothing</code> - Does nothing upon detection\n".
                                        "  <code>delete</code> - Deletes the message only without affecting the user\n".
                                        "  <code>kick</code> - Deletes the message and removes the user\n".
                                        "  <code>ban</code> - Deletes the message and bans the user\n\n".
                                        "Example usage: <code>/settings detect_spam_action delete</code>"
                                ]);
                            }

                            $ChatSettings->DetectSpamAction = self::actionFromString($TargetValueParameter);
                            SettingsManager::updateChatSettings($this->WhoisCommand->ChatClient, $ChatSettings);
                            $TelegramClientManager->getTelegramClientManager()->updateClient($this->WhoisCommand->ChatClient);

                            if($ChatSettings->DetectSpamEnabled == false)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" =>
                                        "Success! But it seems like that the bot is currently not detecting spam\n\n".
                                        "To fix this, send the following commands:\n".
                                        "  <code>/settings detect_spam enabled</code>"
                                ]);
                            }
                            else
                            {
                                switch($ChatSettings->DetectSpamAction)
                                {
                                    case DetectionAction::Nothing:
                                        if($ChatSettings->GeneralAlertsEnabled == false)
                                        {
                                            return Request::sendMessage([
                                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                                "parse_mode" => "html",
                                                "text" =>
                                                    "Success? Spam will be detected but nothing will happen because General Alerts is disabled\n\n".
                                                    "To fix this, send the following commands:\n".
                                                    "  <code>/settings general_alerts enabled</code>"
                                            ]);
                                        }
                                        else
                                        {
                                            return Request::sendMessage([
                                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                                "parse_mode" => "html",
                                                "text" => "Success! When spam is detected alerts will be shown"
                                            ]);
                                        }

                                    case DetectionAction::DeleteMessage:
                                        return Request::sendMessage([
                                            "chat_id" => $this->getMessage()->getChat()->getId(),
                                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                            "parse_mode" => "html",
                                            "text" => "Success! When spam is detected it will be deleted"
                                        ]);

                                    case DetectionAction::KickOffender:
                                        return Request::sendMessage([
                                            "chat_id" => $this->getMessage()->getChat()->getId(),
                                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                            "parse_mode" => "html",
                                            "text" => "Success! When spam is detected it will be deleted and the offender will be removed from this group"
                                        ]);

                                    case DetectionAction::BanOffender:
                                        return Request::sendMessage([
                                            "chat_id" => $this->getMessage()->getChat()->getId(),
                                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                            "parse_mode" => "html",
                                            "text" => "Success! When spam is detected it will be deleted and the offender will be banned from this group"
                                        ]);

                                    default:
                                        return Request::sendMessage([
                                            "chat_id" => $this->getMessage()->getChat()->getId(),
                                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                            "parse_mode" => "html",
                                            "text" => "Success!"
                                        ]);
                                }
                            }
                            break;

                        case "blacklists":
                            $ChatSettings->BlacklistProtectionEnabled = self::isEnabledValue($TargetValueParameter);
                            SettingsManager::updateChatSettings($this->WhoisCommand->ChatClient, $ChatSettings);
                            $TelegramClientManager->getTelegramClientManager()->updateClient($this->WhoisCommand->ChatClient);

                            if($ChatSettings->BlacklistProtectionEnabled)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "Success! This chat will be protected from unwanted spammers"
                                ]);
                            }
                            else
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "Success! Not recommended though! your chat may become vulnerable to spammers, raiders and scammers."
                                ]);
                            }
                            break;

                        case "general_alerts":
                            $ChatSettings->GeneralAlertsEnabled = self::isEnabledValue($TargetValueParameter);
                            SettingsManager::updateChatSettings($this->WhoisCommand->ChatClient, $ChatSettings);
                            $TelegramClientManager->getTelegramClientManager()->updateClient($this->WhoisCommand->ChatClient);

                            if($ChatSettings->GeneralAlertsEnabled)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "Success! General alerts will be displayed in this chat"
                                ]);
                            }
                            else
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "Success! General alerts will no longer be displayed in this chat"
                                ]);
                            }
                            break;

                        case "potential_spammer_protection":
                            $ChatSettings->ActiveSpammerProtectionEnabled = self::isEnabledValue($TargetValueParameter);
                            SettingsManager::updateChatSettings($this->WhoisCommand->ChatClient, $ChatSettings);
                            $TelegramClientManager->getTelegramClientManager()->updateClient($this->WhoisCommand->ChatClient);

                            if($ChatSettings->ActiveSpammerProtectionEnabled)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "Success! The bot will ban potential spammers from this chat"
                                ]);
                            }
                            else
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "Success! The bot will not ban any potential spammers from this chat"
                                ]);
                            }
                            break;

                        case "active_spammer_alert":
                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "text" => "This feature is deprecated, use <code>potential_spammer_protection</code> instead."
                            ]);

                        case "delete_old_messages":
                            $ChatSettings->DeleteOlderMessages = self::isEnabledValue($TargetValueParameter);
                            SettingsManager::updateChatSettings($this->WhoisCommand->ChatClient, $ChatSettings);
                            $TelegramClientManager->getTelegramClientManager()->updateClient($this->WhoisCommand->ChatClient);

                            if($ChatSettings->DeleteOlderMessages)
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "Success! older spam detection messages will be deleted"
                                ]);
                            }
                            else
                            {
                                return Request::sendMessage([
                                    "chat_id" => $this->getMessage()->getChat()->getId(),
                                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                    "parse_mode" => "html",
                                    "text" => "Success! old spam detections will not be deleted"
                                ]);
                            }
                            break;

                        default:

                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "text" =>
                                    "This is not an valid option to modify, here are the valid options\n\n".
                                    "   <code>detect_spam</code>\n".
                                    "   <code>detect_spam_action</code>\n".
                                    "   <code>blacklists</code>\n".
                                    "   <code>general_alerts</code>\n".
                                    "   <code>potential_spammer_protection</code>\n".
                                    "   <code>delete_old_messages</code>\n\n".
                                    "For further information, send <code>/help settings</code>"
                            ]);
                    }
                }
            }


            return self::displayUsage($this->getMessage(), "Missing parameter");
        }

        /**
         * Determines the action from the given string, returns null if invalid
         *
         * @param string $input
         * @return string|null
         */
        private static function actionFromString(string $input)
        {
            switch(strtolower($input))
            {
                case "nothing":
                    return DetectionAction::Nothing;

                case "delete":
                    return DetectionAction::DeleteMessage;

                case "kick":
                    return DetectionAction::KickOffender;

                case "ban":
                    return DetectionAction::BanOffender;

                default:
                    return null;
            }
        }

        /**
         * Determines if the value is an enabled value or not
         *
         * @param string $input
         * @return bool
         */
        private static function isEnabledValue(string $input): bool
        {
            switch(strtolower($input))
            {
                case "enable":
                case "enabled":
                case "on":
                    return true;

                case "disable":
                case "disabled":
                case "off":
                default:
                    return false;
            }
        }

        /**
         * Displays the command usage
         *
         * @param Message $message
         * @param string $error
         * @return ServerResponse
         * @throws TelegramException
         */
        public static function displayUsage(Message $message, string $error="Missing parameter")
        {
            return Request::sendMessage([
                "chat_id" => $message->getChat()->getId(),
                "parse_mode" => "html",
                "reply_to_message_id" => $message->getMessageId(),
                "text" =>
                    "$error\n\n" .
                    "Usage:\n" .
                    "   <b>/settings</b> <code>[Option]</code> <code>[Value]</code>\n".
                    "   <b>/settings</b> <code>detect_spam</code> <code>[On/Off/Enable/Disable]</code>\n".
                    "   <b>/settings</b> <code>detect_spam_action</code> <code>[Nothing/Delete/Kick/Ban]</code>\n".
                    "   <b>/settings</b> <code>blacklists</code> <code>[On/Off/Enable/Disable]</code>\n".
                    "   <b>/settings</b> <code>general_alerts</code> <code>[On/Off/Enable/Disable]</code>\n".
                    "   <b>/settings</b> <code>potential_spammer_protection</code> <code>[On/Off/Enable/Disable]</code>\n".
                    "   <b>/settings</b> <code>delete_old_messages</code> <code>[On/Off/Enable/Disable]</code>\n\n".
                    "For more information send <code>/help settings</code>"
            ]);
        }
    }