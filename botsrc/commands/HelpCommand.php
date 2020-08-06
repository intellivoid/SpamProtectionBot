<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use Exception;
    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtectionBot;
    use TelegramClientManager\Abstracts\TelegramChatType;
    use TelegramClientManager\Objects\TelegramClient\Chat;
    use TelegramClientManager\Objects\TelegramClient\User;

    /**
     * Help command
     *
     * Gets executed when a user first starts using the bot.
     */
    class HelpCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'help';

        /**
         * @var string
         */
        protected $description = 'Displays the help menu for the general usage of the bot';

        /**
         * @var string
         */
        protected $usage = '/help';

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
            $DeepAnalytics->tally('tg_spam_protection', 'help_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$TelegramClient->getChatId());
            $DeepAnalytics->tally('tg_spam_protection', 'help_command', (int)$TelegramClient->getChatId());

            if($this->getMessage()->getChat()->getType() !== TelegramChatType::Private)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" => "This command can only be used in private!"
                ]);
            }

            $CommandParameters = explode(" ", $this->getMessage()->getText(true));
            $CommandParameters = array_values(array_filter($CommandParameters, 'strlen'));

            if(count($CommandParameters) > 0)
            {
                switch(str_ireplace("/", "", strtolower($CommandParameters[0])))
                {
                    case "whois":
                        return Request::sendMessage([
                            'chat_id' => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "disable_web_page_preview" => true,
                            "parse_mode" => "html",
                            'text' =>
                                "<b>Whois Lookup Command</b>\n\n".
                                "This command will allow you to to lookup information about a channel, chat or user if it's available to this bot.\n\n".
                                "Usage Examples:\n".
                                "   <code>/whois</code> - This will return information about yourself!\n".
                                "   <code>/whois</code> (<code>In reply to a user or channel</code>) - This will return information about the user or channel you replied to, this works for forwarded content too.\n".
                                "   <code>/whois</code> [<code>ID</code>] - Returns information about the channel, chat or user.\n".
                                "   <code>/whois</code> [<code>Private Telegram ID</code>] - Returns information about the user via a Private Telegram ID\n".
                                "   <code>/whois</code> [<code>Username</code>] - Returns information about the channel, chat or user via a Username\n\n\n".
                                "<i>What's the trust prediction?</i>\n".
                                "The trust prediction is formatted like this (<code>ham</code>/<code>spam</code>), it's ".
                                "a value generated by the bot to predict if this user is an active spammer or not.\n\n".
                                "<i>What does Active Spammer mean?</i>\n".
                                "A general indication determined by the bot if the user is believed to be an active spammer, ".
                                "you should be careful about these types of users!\n\n".
                                "<i>Who are users verified by Intellivoid Accounts?</i>\n".
                                "These are users who linked their Telegram Account to their Intellivoid Account, see https://accounts.intellivoid.net/\n\n".
                                "<i>What are verified/official channels and groups?</i>\n".
                                "These are chats or channels we deem official by Intellivoid.\n\n".
                                "<i>What is a private ID?</i>\n".
                                "It's a simple ID used to mask a user's, chat's or channel's real ID, this is a small mechanism to protect everyone from data scraping"
                        ]);

                    case "msginfo":
                        return Request::sendMessage([
                            'chat_id' => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "disable_web_page_preview" => true,
                            "parse_mode" => "html",
                            'text' =>
                                "<b>Message Information Lookup</b>\n\n".
                                "This command will allow you to lookup a message hash and retrieve it's reference information\n\n".
                                "Usage Example:\n".
                                "   <code>/msginfo</code> [<code>Message Hash</code>]\n\n\n".
                                "<i>Where are the message contents?</i>\n".
                                "We don't store the message contents, see @SpamProtectionLogs\n\n".
                                "<i>Where is the link?</i>\n".
                                "The link is only available for public chats\n\n".
                                "<i>What is a private ID?</i>\n".
                                "It's a simple ID used to mask a user's real ID, this is a small mechanism to protect users and chats from data scraping"
                        ]);

                    case "settings":
                        return Request::sendMessage([
                            'chat_id' => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "disable_web_page_preview" => true,
                            "parse_mode" => "html",
                            'text' =>
                                "<b>Chat Settings Command</b>\n\n".
                                "This command will allow chat adminstrators to alter the spam protection settings for the given chat\n\n".
                                "Usage Example:\n" .
                                "   <code>/settings</code> [<code>Option</code>] [<code>Value</code>]\n".
                                "   <code>/settings</code> <code>detect_spam</code> [<code>On</code>/<code>Off</code>/<code>Enable</code>/<code>Disable</code>]\n".
                                "   <code>/settings</code> <code>detect_spam_action</code> [<code>Nothing</code>/<code>Delete</code>/<code>Kick</code>/<code>Ban</code>]\n".
                                "   <code>/settings</code> <code>blacklists</code> [<code>On</code>/<code>Off</code>/<code>Enable</code>/<code>Disable</code>]\n".
                                "   <code>/settings</code> <code>general_alerts</code> [<code>On</code>/<code>Off</code>/<code>Enable</code>/<code>Disable</code>]\n".
                                "   <code>/settings</code> <code>active_spammer_alert</code> [<code>On</code>/<code>Off</code>/<code>Enable</code>/<code>Disable</code>]\n".
                                "   <code>/settings</code> <code>delete_old_messages</code> [<code>On</code>/<code>Off</code>/<code>Enable</code>/<code>Disable</code>]\n\n\n".
                                "<i>Enable Spam Protection</i>\n".
                                "You can enable spam protection in your group by sending the following commands\n".
                                "   <code>/settings detect_spam on</code>\n".
                                "   <code>/settings detect_spam_action delete</code>\n".
                                "This will detect spam in your chat and remove it accordingly without affecting the user\n\n".
                                "<i>How do i disable alerts?</i>\n".
                                "   <code>/general_alerts on</code>\n\n".
                                "<i>How can i let blacklisted users join?</i>\n".
                                "   <code>/blacklists off</code>\n\n".
                                "<i>How to I disable the active spammer alert?</i>\n".
                                "   <code>/active_spammer_alert off</code>\n\n"
                        ]);

                    case "blacklist":
                        return Request::sendMessage([
                            'chat_id' => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            "text" =>
                                "Usage:\n" .
                                "   <b>/blacklist</b> (In reply to target user) <code>[Blacklist Flag]</code>\n" .
                                "   <b>/blacklist</b> (In reply to forwarded content) -f <code>[Blacklist Flag]</code>\n" .
                                "   <b>/blacklist</b> <code>[Private Telegram ID]</code> <code>[Blacklist Flag]</code>\n" .
                                "   <b>/blacklist</b> <code>[User ID]</code> <code>[Blacklist Flag]</code>\n" .
                                "   <b>/blacklist</b> <code>[Username]</code> <code>[Blacklist Flag]</code>\n\n" .
                                "For further instructions, refer to the operator manual"
                        ]);

                    case "help":
                        return Request::sendMessage([
                            'chat_id' => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            'text' => "Need help with the help command? Maybe you are the one who needs help buddy."
                        ]);

                    default:
                        return Request::sendMessage([
                            'chat_id' => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            'text' => "That isn't a valid command!"
                        ]);
                }
            }
            else
            {
                return Request::sendMessage([
                    'chat_id' => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    'text' =>
                        "<b>SpamProtectionBot Help</b>\n\n".
                        "With this bot you can protect your groups from unwanted spam and users who spam, when you add this ".
                        "bot to your group, make sure it has the following permissions:\n\n".
                        " - <code>Delete Messages</code>\n".
                        " - <code>Ban Users</code>\n\n".
                        "Here are the available commands:\n".
                        "   /whois [<code>Reply</code>/<code>ID</code>/<code>Username</code>/<code>Private ID</code>]\n".
                        "   /msginfo [<code>Message Hash</code>]\n".
                        "   /settings [<code>Option</code>] [<code>Value</code>]\n\n".
                        "To get further details about a command usage, send /help followed by the command, for example: <code>/help whois</code>"
                ]);
            }
        }
    }