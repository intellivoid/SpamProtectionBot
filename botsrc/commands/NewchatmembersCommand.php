<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use Exception;
    use Longman\TelegramBot\Commands\SystemCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Abstracts\BlacklistFlag;
    use SpamProtection\Exceptions\TelegramClientNotFoundException;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtectionBot;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Objects\TelegramClient\Chat;
    use TelegramClientManager\Objects\TelegramClient\User;

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
        protected $version = '1.0.1';

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
                        "Object: <code>Events/" . $this->name . ".bin</code>"
                ]);
            }

            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'new_member', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$TelegramClient->getChatId());
            $DeepAnalytics->tally('tg_spam_protection', 'new_member', (int)$TelegramClient->getChatId());

            if(isset($this->getMessage()->getNewChatMembers()[0]))
            {
                $UserObject = User::fromArray($this->getMessage()->getNewChatMembers()[0]->getRawData());
                $UserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($UserObject);
                if(isset($UserClient->SessionData->Data["user_status"]) == false)
                {
                    $UserStatus = SettingsManager::getUserStatus($UserClient);
                    $UserClient = SettingsManager::updateUserStatus($UserClient, $UserStatus);
                    $TelegramClientManager->getTelegramClientManager()->updateClient($UserClient);
                }
            }

            $UserStatus = SettingsManager::getUserStatus($UserClient);
            $ChatSettings = SettingsManager::getChatSettings($ChatClient);

            if($UserObject->Username == "SpamProtectionBot")
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

            if($ChatSettings->ActiveSpammerAlertEnabled)
            {
                if($UserStatus->GeneralizedSpam > 0)
                {
                    if($UserStatus->GeneralizedSpam > $UserStatus->GeneralizedHam)
                    {
                        Request::sendMessage([
                            "chat_id" => $this->getMessage()->getChat()->getId(),
                            "reply_to_message_id" => $this->getMessage()->getMessageId(),
                            "parse_mode" => "html",
                            "text" => "\u{26A0} <b>WARNING</b> \u{26A0}\n\nThis user may be an active spammer"
                        ]);
                    }
                }
            }

            if($ChatSettings->BlacklistProtectionEnabled)
            {
                if($UserStatus->IsBlacklisted)
                {
                    $BanResponse = Request::kickChatMember([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "user_id" => $UserClient->User->ID,
                        "until_date" => 0
                    ]);

                    if($BanResponse->isOk())
                    {
                        $Response = "This user has been banned because they've been blacklisted!\n\n";
                    }
                    else
                    {
                        $Response = "This user is blacklisted! Spam Protection Bot has insufficient privileges to ban this user.\n\n";
                    }

                    $Response .= "<b>Private Telegram ID:</b> <code>" . $UserClient->PublicID . "</code>\n";

                    switch($UserStatus->BlacklistFlag)
                    {
                        case BlacklistFlag::None:
                            $Response .= "<b>Blacklist Reason:</b> <code>None</code>\n";
                            break;

                        case BlacklistFlag::Spam:
                            $Response .= "<b>Blacklist Reason:</b> <code>Spam / Unwanted Promotion</code>\n";
                            break;

                        case BlacklistFlag::BanEvade:
                            $Response .= "<b>Blacklist Reason:</b> <code>Ban Evade</code>\n";
                            $Response .= "<b>Original Private ID:</b> <code>" . $UserStatus->OriginalPrivateID . "</code>\n";
                            break;

                        case BlacklistFlag::ChildAbuse:
                            $Response .= "<b>Blacklist Reason:</b> <code>Child Pornography / Child Abuse</code>\n";
                            break;

                        case BlacklistFlag::Impersonator:
                            $Response .= "<b>Blacklist Reason:</b> <code>Malicious Impersonator</code>\n";
                            break;

                        case BlacklistFlag::PiracySpam:
                            $Response .= "<b>Blacklist Reason:</b> <code>Promotes/Spam Pirated Content</code>\n";
                            break;

                        case BlacklistFlag::PornographicSpam:
                            $Response .= "<b>Blacklist Reason:</b> <code>Promotes/Spam NSFW Content</code>\n";
                            break;

                        case BlacklistFlag::PrivateSpam:
                            $Response .= "<b>Blacklist Reason:</b> <code>Spam / Unwanted Promotion via a unsolicited private message</code>\n";
                            break;

                        case BlacklistFlag::Raid:
                            $Response .= "<b>Blacklist Reason:</b> <code>RAID Initializer / Participator</code>\n";
                            break;

                        case BlacklistFlag::Scam:
                            $Response .= "<b>Blacklist Reason:</b> <code>Scamming</code>\n";
                            break;

                        case BlacklistFlag::Special:
                            $Response .= "<b>Blacklist Reason:</b> <code>Special Reason, consult @IntellivoidSupport</code>\n";
                            break;

                        default:
                            $Response .= "<b>Blacklist Reason:</b> <code>Unknown</code>\n";
                            break;
                    }

                    $Response .= "\n<i>You can find evidence of abuse by searching the Private Telegram ID in @SpamProtectionLogs</i>\n\n";
                    $Response .= "<i>If you think this is a mistake, let us know in @IntellivoidDiscussions</i>";

                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "parse_mode" => "html",
                        "text" => $Response
                    ]);
                }
            }

            return null;
        }
    }