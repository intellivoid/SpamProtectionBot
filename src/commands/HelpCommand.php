<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use DeepAnalytics\DeepAnalytics;
    use Exception;
    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Objects\TelegramClient\Chat;
    use SpamProtection\Objects\TelegramClient\User;
    use SpamProtection\SpamProtection;

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
        protected $version = '1.0.0';

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
                        "Object: <code>Commands/help.bin</code>"
                ]);
            }

            $DeepAnalytics = new DeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'help_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$TelegramClient->getChatId());
            $DeepAnalytics->tally('tg_spam_protection', 'help_command', (int)$TelegramClient->getChatId());

            $CommandParameters = explode(" ", $this->getMessage()->getText(true));
            $CommandParameters = array_filter($CommandParameters, 'strlen');

            if(count($CommandParameters) > 0)
            {
                switch(str_ireplace("/", "", strtolower($CommandParameters[0])))
                {
                    case "userinfo":
                        return Request::sendMessage([
                            'chat_id' => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "disable_web_page_preview" => true,
                            "parse_mode" => "html",
                            'text' =>
                                "<b>User Information Command</b>\n\n".
                                "This command will allow you to to lookup a user's information if it's available to this bot.\n\n".
                                "Usage Examples:\n".
                                "   <code>/userinfo</code> - This will return information about yourself!\n".
                                "   <code>/userinfo</code> (<code>In reply to a user</code>) - This will return information about the user you replied to\n".
                                "   <code>/userinfo</code> [<code>ID</code>] - Returns information about the user via a User ID\n".
                                "   <code>/userinfo</code> [<code>Private Telegram ID</code>] - Returns information about the user via a Private Telegram ID\n".
                                "   <code>/userinfo</code> [<code>Username</code>] - Returns information about the user via a Username\n\n\n".
                                "<i>What's the trust prediction?</i>\n".
                                "The trust prediction is formatted like this (<code>ham</code>/<code>spam</code>), it's ".
                                "a value generated by the bot to predict if this user is an active spammer or not.\n\n".
                                "<i>What does Active Spammer mean?</i>\n".
                                "A general indication determined by the bot if the user is believed to be an active spammer, ".
                                "you should be careful about these types of users!\n\n".
                                "<i>Who are users verified by Intellivoid Accounts?</i>\n".
                                "These are users who linked their Telegram Account to their Intellivoid Account, see https://accounts.intellivoid.net/\n\n".
                                "<i>What is a private ID?</i>\n".
                                "It's a simple ID used to mask a user's real ID, this is a small mechanism to protect users and chats from data scraping"
                        ]);

                    case "chatinfo":
                        return Request::sendMessage([
                            'chat_id' => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "disable_web_page_preview" => true,
                            "parse_mode" => "html",
                            'text' =>
                                "<b>Chat Information Command</b>\n\n".
                                "This command will allow you to to lookup a chat's information if it's available to this bot.\n\n".
                                "Usage Examples:\n".
                                "   <code>/chatinfo</code> - This will return information about the current chat you are in\n".
                                "   <code>/chatinfo</code> [<code>ID</code>] - Returns information about the chat via a Chat ID\n".
                                "   <code>/chatinfo</code> [<code>Private Chat ID</code>] - Returns information about the chat via a Private ID\n".
                                "   <code>/chatinfo</code> [<code>Username</code>] - Returns information about the chat via a Username\n\n\n".
                                "<i>What is spam detection?</i>\n".
                                "Spam detection uses machine learning to determine if the message sent in a chat is spam or not\n\n".
                                "<i>What are General alerts?</i>\n".
                                "Basic notifications created by the bot when it detects spam or an active spammer, etc.\n\n".
                                "<i>What is blacklist protection?</i>\n".
                                "Blacklisted users are users who were blacklisted by operators due to abuse, when these blacklisted ".
                                "users join your chat, this bot can ban them and prevent them from abusing your chat\n\n".
                                "<i>What is an active spammer?</i>\n".
                                "A general indication determined by the bot if the user is believed to be an active spammer, ".
                                "you should be careful about these types of users!\n\n".
                                "<i>What is a private ID?</i>\n".
                                "It's a simple ID used to mask a user's real ID, this is a small mechanism to protect users and chats from data scraping"
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
                                "<b>Chat Settigns Command</b>\n\n".
                                "This command will allow chat adminstrators to alter the spam protection settings for the given chat\n\n".
                                "Usage Example:\n" .
                                "   <code>/settings</code> [<code>Option</code>] [<code>Value</code>]\n".
                                "   <code>/settings</code> <code>detect_spam</code> [<code>On</code>/<code>Off</code>/<code>Enable</code>/<code>Disable</code>]\n".
                                "   <code>/settings</code> <code>detect_spam_action</code> [<code>Nothing</code>/<code>Delete</code>/<code>Kick</code>/<code>Ban</code>]\n".
                                "   <code>/settings</code> <code>blacklists</code> [<code>On</code>/<code>Off</code>/<code>Enable</code>/<code>Disable</code>]\n".
                                "   <code>/settings</code> <code>general_alerts</code> [<code>On</code>/<code>Off</code>/<code>Enable</code>/<code>Disable</code>]\n".
                                "   <code>/settings</code> <code>active_spammer_alert</code> [<code>On</code>/<code>Off</code>/<code>Enable</code>/<code>Disable</code>]\n\n\n".
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
                        "   /userinfo [<code>Reply</code>/<code>ID</code>/<code>Username</code>/<code>Private ID</code>]\n".
                        "   /chatinfo [<code>ID</code>/<code>Username</code>/<code>Private ID</code>]\n".
                        "   /msginfo [<code>Message Hash</code>]\n".
                        "   /settings [<code>Option</code>] [<code>Value</code>]\n\n".
                        "To get further details about a command usage, send /help followed by the command, for example: <code>/help userinfo</code>"
                ]);
            }
        }
    }