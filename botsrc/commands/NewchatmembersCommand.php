<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use Longman\TelegramBot\Commands\SystemCommand;
    use Longman\TelegramBot\Commands\UserCommands\BlacklistCommand;
    use Longman\TelegramBot\Commands\UserCommands\WhoisCommand;
    use Longman\TelegramBot\Entities\InlineKeyboard;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Abstracts\BlacklistFlag;
    use SpamProtection\Exceptions\TelegramClientNotFoundException;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtectionBot;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Objects\TelegramClient;

    /**
     * New chat member command
     */
    class NewchatmembersCommand extends SystemCommand
    {
        /**
         * @var string
         */
        protected $name = 'newchatmember';

        /**
         * @var string
         */
        protected $description = 'New Chat Members';

        /**
         * @var string
         */
        protected $version = '2.0.0';

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
         * @throws TelegramException
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws \TelegramClientManager\Exceptions\TelegramClientNotFoundException
         * @noinspection DuplicatedCode
         */
        public function execute()
        {
            // Find all clients
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
            $this->WhoisCommand = new WhoisCommand($this->telegram, $this->update);
            $this->WhoisCommand->findClients();

            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'new_member', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->WhoisCommand->ChatObject->ID);
            $DeepAnalytics->tally('tg_spam_protection', 'new_member', (int)$this->WhoisCommand->ChatObject->ID);

            if(isset($this->getMessage()->getNewChatMembers()[0]))
            {
                $UserObject = TelegramClient\User::fromArray($this->getMessage()->getNewChatMembers()[0]->getRawData());
                $UserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($UserObject);

                if(isset($UserClient->SessionData->Data["user_status"]) == false)
                {
                    $UserStatus = SettingsManager::getUserStatus($UserClient);
                    $UserClient = SettingsManager::updateUserStatus($UserClient, $UserStatus);
                    $TelegramClientManager->getTelegramClientManager()->updateClient($UserClient);
                }
            }
            else
            {
                return null;
            }

            if($UserObject->Username == TELEGRAM_BOT_NAME)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "Thanks for adding me! Remember to give me the following permissions\n\n".
                        " - <code>Delete Messages</code>\n".
                        " - <code>Ban Users</code>\n\n".
                        "If you need help with setting this up bot, send /help\n\n".
                        "I will actively detect and remove spam and I will ban blacklisted users such as spammers, ".
                        "scammers and raiders, if you need any help then feel free to reach out to us at @IntellivoidDiscussions"
                ]);
            }

            // Detect if the user is blacklisted
            if($this->handleBlacklist($UserClient) == null)
            {
                // If they're not blacklisted, maybe they're an potential spammer?
                if($this->handleActiveSpammer($UserClient) !== null)
                {
                    // Tally if success
                    $DeepAnalytics->tally('tg_spam_protection', 'banned_potential', 0);
                    $DeepAnalytics->tally('tg_spam_protection', 'banned_potential', (int)$this->WhoisCommand->ChatObject->ID);
                }
            }
            else
            {
                // Tally if success
                $DeepAnalytics->tally('tg_spam_protection', 'banned_blacklisted', 0);
                $DeepAnalytics->tally('tg_spam_protection', 'banned_blacklisted', (int)$this->WhoisCommand->ChatObject->ID);
            }

            return null;
        }

        /**
         * Handles a potential active spammer upon joining
         *
         * @param TelegramClient $userClient
         * @return ServerResponse|null
         * @throws TelegramException
         * @noinspection DuplicatedCode
         */
        public function handleActiveSpammer(TelegramClient $userClient)
        {
            if($userClient->User->IsBot)
            {
                return null;
            }

            $ChatSettings = SettingsManager::getChatSettings($this->WhoisCommand->ChatClient);
            $UserStatus = SettingsManager::getUserStatus($userClient);

            if($ChatSettings->ActiveSpammerProtectionEnabled)
            {
                if($UserStatus->GeneralizedSpam > 0)
                {
                    if($UserStatus->GeneralizedSpam > $UserStatus->GeneralizedHam)
                    {
                        $BanResponse = Request::kickChatMember([
                            "chat_id" => $this->getMessage()->getChat()->getId(),
                            "user_id" => $userClient->User->ID,
                            "until_date" => 0
                        ]);

                        if($BanResponse->isOk())
                        {
                            $Response = WhoisCommand::generateMention($userClient) . " has been banned because they might be an active spammer\n\n";
                        }
                        else
                        {
                            $Response = WhoisCommand::generateMention($userClient) . " has been detected to be a potential spammer, Spam Protection Bot has insufficient privileges to ban this user.\n\n";
                        }

                        $Response .= "<b>Private Telegram ID:</b> <code>" . $userClient->PublicID . "</code>\n\n";
                        $Response .= "<i>You can find evidence of abuse by searching the Private Telegram ID in @@SpamProtectionLogs else ";
                        $Response .= "If you believe that this is was a mistake then let us know in @SpamProtectionSupport</i>";

                        if($ChatSettings->GeneralAlertsEnabled)
                        {
                            return Request::sendMessage([
                                "chat_id" => $this->getMessage()->getChat()->getId(),
                                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                                "parse_mode" => "html",
                                "reply_markup" => new InlineKeyboard(
                                    [
                                        ["text" => "Logs", "url" => "https://t.me/" . LOG_CHANNEL],
                                        ["text" => "User Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $userClient->User->ID],
                                        ["text" => "Report Problem", "url" => "https://t.me/SpamProtectionSupport"]
                                    ]
                                ),
                                "text" => $Response
                            ]);
                        }
                    }
                }
            }

            return null;
        }

        /**
         * Handles a blacklisted user
         *
         * @param TelegramClient $userClient
         * @return ServerResponse|null
         * @throws TelegramException
         * @noinspection DuplicatedCode
         */
        public function handleBlacklist(TelegramClient $userClient)
        {
            $ChatSettings = SettingsManager::getChatSettings($this->WhoisCommand->ChatClient);
            $UserStatus = SettingsManager::getUserStatus($userClient);

            if($ChatSettings->BlacklistProtectionEnabled)
            {
                if($UserStatus->IsBlacklisted)
                {
                    $BanResponse = Request::kickChatMember([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "user_id" => $userClient->User->ID,
                        "until_date" => 0
                    ]);

                    if($BanResponse->isOk())
                    {
                        $Response = WhoisCommand::generateMention($userClient) . " has been banned because they've been blacklisted!\n\n";
                    }
                    else
                    {
                        $Response = WhoisCommand::generateMention($userClient)  . " is blacklisted! Spam Protection Bot has insufficient privileges to ban this user.\n\n";
                    }

                    $Response .= "<b>Private Telegram ID:</b> <code>" . $userClient->PublicID . "</code>\n";

                    switch($UserStatus->BlacklistFlag)
                    {
                        case BlacklistFlag::BanEvade:
                            $Response .= "<b>Blacklist Reason:</b> <code>Ban Evade</code>\n";
                            $Response .= "<b>Original Private ID:</b> <code>" . $UserStatus->OriginalPrivateID . "</code>\n\n";
                            break;

                        default:
                            $Response .= "<b>Blacklist Reason:</b> <code>" . BlacklistCommand::blacklistFlagToReason($UserStatus->BlacklistFlag) . "</code>\n\n";
                            break;
                    }

                    $Response .= "<i>You can find evidence of abuse by searching the Private Telegram ID in @@SpamProtectionLogs else ";
                    $Response .= "If you believe that this is was a mistake then let us know in @SpamProtectionSupport</i>";

                    if($ChatSettings->GeneralAlertsEnabled)
                    {
                        return Request::sendMessage([
                            "chat_id" => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            "reply_markup" => new InlineKeyboard(
                                [
                                    ["text" => "Logs", "url" => "https://t.me/" . LOG_CHANNEL],
                                    ["text" => "User Info", "url" => "https://t.me/" . TELEGRAM_BOT_NAME . "?start=00_" . $userClient->User->ID],
                                    ["text" => "Report Problem", "url" => "https://t.me/SpamProtectionSupport"]
                                ]
                            ),
                            "text" => $Response
                        ]);
                    }
                }
            }

            return null;
        }
    }