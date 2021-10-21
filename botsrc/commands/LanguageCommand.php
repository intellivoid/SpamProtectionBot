<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUnused */
    /** @noinspection PhpIllegalPsrClassPathInspection */

    namespace Longman\TelegramBot\Commands\UserCommands;

    use Exception;
    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\CallbackQuery;
    use Longman\TelegramBot\Entities\InlineKeyboard;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use SpamProtection\Managers\SettingsManager;
    use SpamProtection\Utilities\Validation;
    use SpamProtectionBot;
    use TelegramClientManager\Abstracts\TelegramChatType;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;
    use VerboseAdventure\Abstracts\EventType;

    /**
     * Language Command
     *
     * Allows the chat administrator/user to change the configured language of the bot
     */
    class LanguageCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'language';

        /**
         * @var string
         */
        protected $description = 'Allows the chat administrator/user to change the configured language of the bot';

        /**
         * @var string
         */
        protected $usage = '/language';

        /**
         * @var string
         */
        protected $version = '1.0.0';

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
         * @throws TelegramException
         * @noinspection DuplicatedCode
         */
        public function execute(): ServerResponse
        {
            // Find clients
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
            $this->WhoisCommand = new WhoisCommand($this->telegram, $this->update);
            $this->WhoisCommand->findClients();

            $DeepAnalytics = SpamProtectionBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_spam_protection', 'messages', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'language_command', 0);
            $DeepAnalytics->tally('tg_spam_protection', 'messages', (int)$this->WhoisCommand->ChatObject->ID);
            $DeepAnalytics->tally('tg_spam_protection', 'language_command', (int)$this->WhoisCommand->ChatObject->ID);

            // Ignore forwarded commands
            if($this->getMessage()->getForwardFrom() !== null || $this->getMessage()->getForwardFromChat())
            {
                return Request::emptyResponse();
            }

            if($this->WhoisCommand->ChatObject->Type == TelegramChatType::Private)
            {
                $LanguageCommand = new LanguageCommand($this->telegram, $this->update);
                return $LanguageCommand->handleUserLanguageChange(null, $this->WhoisCommand, false);
            }

            return Request::emptyResponse();
        }

        /**
         * Handles language localization changes
         *
         * @param CallbackQuery $callbackQuery
         * @param WhoisCommand $whoisCommand
         * @return ServerResponse
         * @throws TelegramException
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         */
        public function handleChatLanguageChange(CallbackQuery $callbackQuery, WhoisCommand $whoisCommand): ServerResponse
        {
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();
            $ChatSettings = SettingsManager::getChatSettings($whoisCommand->CallbackQueryChatClient);
            $StatusResponse = (string)null;

            switch($callbackQuery->getData())
            {
                case "010601":
                    if($ChatSettings->StrictLocalization)
                    {
                        $ChatSettings->StrictLocalization = false;
                        $callbackQuery->answer(["text" => self::localizeChatText($whoisCommand, "Strict localization has been disabled successfully")]);
                    }
                    else
                    {
                        $ChatSettings->StrictLocalization = true;
                        $callbackQuery->answer(["text" => self::localizeChatText($whoisCommand, "Strict localization has been enabled successfully")]);
                    }

                    $whoisCommand->CallbackQueryChatClient = SettingsManager::updateChatSettings(
                        $whoisCommand->CallbackQueryChatClient, $ChatSettings
                    );
                    $TelegramClientManager->getTelegramClientManager()->updateClient($whoisCommand->CallbackQueryChatClient);
                    break;

                case "0106auto":
                    $ChatSettings->ConfiguredLanguage = "auto";

                    $whoisCommand->CallbackQueryChatClient = SettingsManager::updateChatSettings(
                        $whoisCommand->CallbackQueryChatClient, $ChatSettings
                    );
                    $TelegramClientManager->getTelegramClientManager()->updateClient($whoisCommand->CallbackQueryChatClient);
                    $callbackQuery->answer(["text" => self::localizeChatText($whoisCommand, "Current language set to automatic")]);
                    break;
            }

            $CodeToLanguage = [
                "en" => "English",
                "es" => "Spanish",
                "ja" => "Japanese",
                "zh" => "Chinese (simplified)",
                "de" => "German",
                "pl" => "Polish",
                "fr" => "French",
                "nl" => "Dutch",
                "ko" => "Korean",
                "it" => "Italian",
                "tr" => "Turkish",
                "ru" => "Russian",
                "auto" => "Automatic"
            ];

            if(strlen($callbackQuery->getData()) == 8 || strlen($callbackQuery->getData()) == 6)
            {
                if($callbackQuery->getData() == "0106auto")
                {
                    $ChatSettings->ConfiguredLanguage = "auto";

                    $whoisCommand->CallbackQueryChatClient = SettingsManager::updateChatSettings(
                        $whoisCommand->CallbackQueryChatClient, $ChatSettings
                    );
                    $TelegramClientManager->getTelegramClientManager()->updateClient($whoisCommand->CallbackQueryChatClient);
                    $callbackQuery->answer(["text" => self::localizeChatText($whoisCommand, "Current language set to " . $CodeToLanguage["auto"])]);
                }
                else
                {
                    if(in_array(substr(strtolower($callbackQuery->getData()), -2), array_keys($CodeToLanguage)) == false)
                    {

                        $callbackQuery->answer(["show_alert" => true, "text" => self::localizeChatText($whoisCommand, substr($callbackQuery->getData(), -2) . " is not a supported language")]);
                    }
                    else
                    {
                        $selected = strtolower(substr($callbackQuery->getData(), -2));
                        $ChatSettings->ConfiguredLanguage = $selected;

                        $whoisCommand->CallbackQueryChatClient = SettingsManager::updateChatSettings(
                            $whoisCommand->CallbackQueryChatClient, $ChatSettings
                        );
                        $TelegramClientManager->getTelegramClientManager()->updateClient($whoisCommand->CallbackQueryChatClient);
                        $callbackQuery->answer(["text" => self::localizeChatText($whoisCommand, "Current language set to " . $CodeToLanguage[$selected])]);
                    }
                }
            }

            if($ChatSettings->ConfiguredLanguage == "auto")
            {
                $CurrentLanguageText = self::localizeChatText($whoisCommand, "Current Language: %s", ['s']);
                $CurrentLanguageValue = self::localizeChatText($whoisCommand, "Automatic depending on the chat's current language");

            }
            else
            {
                $CurrentLanguageText = self::localizeChatText($whoisCommand, "Current Language: %s", ['s']);
                $CurrentLanguageValue = self::localizeChatText($whoisCommand, $CodeToLanguage[$ChatSettings->ConfiguredLanguage]);
            }

            $StatusResponse .= "<b>" . str_ireplace("%s", $CurrentLanguageValue, $CurrentLanguageText) . "</b>\n";

            if($ChatSettings->StrictLocalization)
            {
                $StrictLocalizationButton = [
                    "text" => "\u{274C} " . self::localizeChatText($whoisCommand, "Disable strict localization"),
                    "callback_data" => "010601"
                ];
                $CurrentLocalizationText = self::localizeChatText($whoisCommand, "Strict Localization: %s", ['s']);
                $CurrentLocalizationValue = self::localizeChatText($whoisCommand, "Enabled");
            }
            else
            {
                $StrictLocalizationButton = [
                    "text" => "\u{2714} " . self::localizeChatText($whoisCommand, "Enable strict localization"),
                    "callback_data" => "010601"
                ];

                $CurrentLocalizationText = self::localizeChatText($whoisCommand, "Strict Localization: %s", ['s']);
                $CurrentLocalizationValue = self::localizeChatText($whoisCommand, "Disabled");
            }

            $StatusResponse .= "<b>" . str_ireplace("%s", $CurrentLocalizationValue, $CurrentLocalizationText) . "</b>";

            $ResponseMessage = [
                "chat_id" => $callbackQuery->getMessage()->getChat()->getId(),
                "message_id" => $callbackQuery->getMessage()->getMessageId(),
                "parse_mode" => "html",
                "disable_web_page_preview" => true,
                "text" =>
                    "\u{1F310} <b>" . self::localizeChatText($whoisCommand, "Bot Language (Beta)") . " </b>\n\n".
                    self::localizeChatText($whoisCommand,
                        "SpamProtectionBot is using an experimental localization method, so the localization may not always be accurate ".
                        "but nevertheless you can change the language of this bot and SpamProtectionBot will respond in said language ".
                        "(Including alerts) in this chat.") . "\n\n" .
                    self::localizeChatText($whoisCommand,
                        "With strict localization when disabled, SpamProtectionBot will respond to the user in the " .
                        "language they understand, if enabled the bot will only respond in the language configured in the chat") . "\n\n".
                    self::localizeChatText($whoisCommand,
                        "If you use 'Automatic' as the configured language, the bot will use the language that the chat is currently based in automatically") . "\n\n".
                    $StatusResponse,
                "reply_markup" => new InlineKeyboard(
                    [
                        ["text" => "\u{1F310} " . self::localizeChatText($whoisCommand, "Automatic"), "callback_data" => "0106auto"]
                    ],
                    [
                        ["text" => "\u{1F1EC}\u{1F1E7}", "callback_data" => "0106en"],
                        ["text" => "\u{1F32E}", "callback_data" => "0106es"],
                        ["text" => "\u{1F1EF}\u{1F1F5}", "callback_data" => "0106ja"],
                        ["text" => "\u{1F1E8}\u{1F1F3}", "callback_data" => "0106zh"],
                        ["text" => "\u{1F1E9}\u{1F1EA}", "callback_data" => "0106de"],
                        ["text" => "\u{1F404}", "callback_data" => "0106pl"],
                    ],
                    [
                        ["text" => "\u{1F1EB}\u{1F1F7}", "callback_data" => "0106fr"],
                        ["text" => "\u{1F1F3}\u{1F1F1}", "callback_data" => "0106nl"],
                        ["text" => "\u{1F1F0}\u{1F1F7}", "callback_data" => "0106ko"],
                        ["text" => "\u{1F1EE}\u{1F1F9}", "callback_data" => "0106it"],
                        ["text" => "\u{1F1F9}\u{1F1F7}", "callback_data" => "0106tr"],
                        ["text" => "\u{1F1F7}\u{1F1FA}", "callback_data" => "0106ru"],
                    ],
                    [
                        $StrictLocalizationButton
                    ],
                    [
                        [
                            "text" => "\u{1F519} " . self::localizeChatText($whoisCommand, "Go back"),
                            "callback_data" => "0100"
                        ]
                    ]
                )
            ];

            return Request::editMessageText($ResponseMessage);
        }


        /**
         * Handles language localization changes for users
         *
         * @param CallbackQuery|null $callbackQuery
         * @param WhoisCommand $whoisCommand
         * @param bool $edit
         * @return ServerResponse
         * @throws DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         */
        public function handleUserLanguageChange(?CallbackQuery $callbackQuery, WhoisCommand $whoisCommand, bool $edit=True): ServerResponse
        {
            $TelegramClientManager = SpamProtectionBot::getTelegramClientManager();

            if($callbackQuery !== null)
            {
                $ChatID = $whoisCommand->CallbackQueryChatObject->ID;
                $UserStatus = SettingsManager::getUserStatus($whoisCommand->CallbackQueryUserClient);
            }
            else
            {
                $ChatID = $whoisCommand->ChatObject->ID;
                $UserStatus = SettingsManager::getUserStatus($whoisCommand->UserClient);
            }

            $StatusResponse = (string)null;

            if($callbackQuery !== null)
            {
                switch($callbackQuery->getData())
                {
                    case "1206auto":
                        $UserStatus->ConfiguredLanguage = "auto";

                        $whoisCommand->CallbackQueryUserClient = SettingsManager::updateUserStatus(
                            $whoisCommand->CallbackQueryUserClient, $UserStatus
                        );
                        $TelegramClientManager->getTelegramClientManager()->updateClient($whoisCommand->CallbackQueryUserClient);
                        $callbackQuery->answer(["text" => self::localizeChatText($whoisCommand, "Current language set to automatic")]);
                        break;
                }
            }

            $CodeToLanguage = [
                "en" => "English",
                "es" => "Spanish",
                "ja" => "Japanese",
                "zh" => "Chinese (simplified)",
                "de" => "German",
                "pl" => "Polish",
                "fr" => "French",
                "nl" => "Dutch",
                "ko" => "Korean",
                "it" => "Italian",
                "tr" => "Turkish",
                "ru" => "Russian",
                "auto" => "Automatic"
            ];

            if($callbackQuery !== null)
            {
                if(strlen($callbackQuery->getData()) == 8 || strlen($callbackQuery->getData()) == 6)
                {
                    if($callbackQuery->getData() == "1206auto")
                    {
                        $UserStatus->ConfiguredLanguage = "auto";

                        $whoisCommand->CallbackQueryUserClient = SettingsManager::updateUserStatus(
                            $whoisCommand->CallbackQueryUserClient, $UserStatus
                        );
                        $TelegramClientManager->getTelegramClientManager()->updateClient($whoisCommand->CallbackQueryUserClient);
                        $callbackQuery->answer(["text" => self::localizeChatText($whoisCommand, "Current language set to " . $CodeToLanguage["auto"])]);
                    }
                    else
                    {
                        if(in_array(substr(strtolower($callbackQuery->getData()), -2), array_keys($CodeToLanguage)) == false)
                        {

                            $callbackQuery->answer(["show_alert" => true, "text" => self::localizeChatText($whoisCommand, substr($callbackQuery->getData(), -2) . " is not a supported language")]);
                        }
                        else
                        {
                            $selected = strtolower(substr($callbackQuery->getData(), -2));
                            $UserStatus->ConfiguredLanguage = $selected;

                            $whoisCommand->CallbackQueryUserClient = SettingsManager::updateUserStatus(
                                $whoisCommand->CallbackQueryUserClient, $UserStatus
                            );
                            $TelegramClientManager->getTelegramClientManager()->updateClient($whoisCommand->CallbackQueryUserClient);
                            $callbackQuery->answer(["text" => self::localizeChatText($whoisCommand, "Current language set to " . $CodeToLanguage[$selected])]);
                        }
                    }
                }
            }

            if($UserStatus->ConfiguredLanguage == "auto")
            {
                $CurrentLanguageText = self::localizeChatText($whoisCommand, "Current Language: %s", ['s']);
                $CurrentLanguageValue = self::localizeChatText($whoisCommand, "Automatic depending on your 
                current language");

            }
            else
            {
                $CurrentLanguageText = self::localizeChatText($whoisCommand, "Current Language: %s", ['s']);
                $CurrentLanguageValue = self::localizeChatText($whoisCommand, $CodeToLanguage[$UserStatus->ConfiguredLanguage]);
            }

            $StatusResponse .= "<b>" . str_ireplace("%s", $CurrentLanguageValue, $CurrentLanguageText) . "</b>\n";

            $ResponseMessage = [
                "chat_id" => $ChatID,
                "parse_mode" => "html",
                "disable_web_page_preview" => true,
                "text" =>
                    "\u{1F310} <b>" . self::localizeChatText($whoisCommand, "Bot Language (Beta)") . " </b>\n\n".
                    self::localizeChatText($whoisCommand,
                        "SpamProtectionBot is using an experimental localization method, so the localization may not always be accurate ".
                        "but nevertheless you can change the language of this bot and SpamProtectionBot will respond in said language ".
                        "(Including alerts) in this chat.") . "\n\n" .
                    self::localizeChatText($whoisCommand,
                        "If you use 'Automatic' as the configured language, the bot will use the language you are currently using") . "\n\n".
                    $StatusResponse,
                "reply_markup" => new InlineKeyboard(
                    [
                        ["text" => "\u{1F310} " . self::localizeChatText($whoisCommand, "Automatic"), "callback_data" => "1206auto"]
                    ],
                    [
                        ["text" => "\u{1F1EC}\u{1F1E7}", "callback_data" => "1206en"],
                        ["text" => "\u{1F32E}", "callback_data" => "1206es"],
                        ["text" => "\u{1F1EF}\u{1F1F5}", "callback_data" => "1206ja"],
                        ["text" => "\u{1F1E8}\u{1F1F3}", "callback_data" => "1206zh"],
                        ["text" => "\u{1F1E9}\u{1F1EA}", "callback_data" => "1206de"],
                        ["text" => "\u{1F404}", "callback_data" => "1206pl"],
                    ],
                    [
                        ["text" => "\u{1F1EB}\u{1F1F7}", "callback_data" => "1206fr"],
                        ["text" => "\u{1F1F3}\u{1F1F1}", "callback_data" => "1206nl"],
                        ["text" => "\u{1F1F0}\u{1F1F7}", "callback_data" => "1206ko"],
                        ["text" => "\u{1F1EE}\u{1F1F9}", "callback_data" => "1206it"],
                        ["text" => "\u{1F1F9}\u{1F1F7}", "callback_data" => "1206tr"],
                        ["text" => "\u{1F1F7}\u{1F1FA}", "callback_data" => "1206ru"],
                    ],
                    [
                        [
                            "text" => LanguageCommand::localizeChatText($whoisCommand, "Close Menu"),
                            "callback_data" => "03"
                        ]
                    ]
                )
            ];

            if($edit)
            {
                $ResponseMessage["message_id"] = $callbackQuery->getMessage()->getMessageId();
                return Request::editMessageText($ResponseMessage);
            }

            return Request::sendMessage($ResponseMessage);
        }

        /**
         * Localizes the chat text output
         *
         * @param WhoisCommand $whoisCommand
         * @param string $input
         * @param array $specials
         * @param bool|null $strictOverride
         * @return string|string[]|null
         */
        public static function localizeChatText(WhoisCommand $whoisCommand, string $input, array $specials=[], bool $strictOverride=null)
        {
            $targetChatClient = $whoisCommand->ChatClient;
            $targetUserClient = $whoisCommand->UserClient;

            if($whoisCommand->CallbackQueryChatClient !== null)
                $targetChatClient = $whoisCommand->CallbackQueryChatClient;

            if($whoisCommand->CallbackQueryUserClient !== null)
                $targetUserClient = $whoisCommand->CallbackQueryUserClient;

            if($targetChatClient == null)
                return $input;

            if($targetUserClient == null)
                return $input;

            try
            {
                $ChatSettings = SettingsManager::getChatSettings($targetChatClient);
                $UserStatus = SettingsManager::getUserStatus($targetUserClient);
            }
            catch(Exception $e)
            {
                SpamProtectionBot::getLogHandler()->log(EventType::WARNING, "There was an error while trying to process localization (Generic)", "localizeChatText");
                SpamProtectionBot::getLogHandler()->logException($e, "localizeChatText");

                return $input;
            }

            $TargetLanguage = null;

            if($strictOverride !== null) $ChatSettings->StrictLocalization = $strictOverride;

            if($ChatSettings->StrictLocalization == false)
            {
                if($UserStatus->ConfiguredLanguage == "en")
                    return $input;

                if($UserStatus->ConfiguredLanguage == "auto")
                {
                    if(Validation::supportedLanguage($UserStatus->GeneralizedLanguage) == false)
                        return $input;

                    $TargetLanguage = $UserStatus->GeneralizedLanguage;
                }
                else
                {
                    $TargetLanguage = $UserStatus->ConfiguredLanguage;
                }
            }
            else
            {
                if($ChatSettings->ConfiguredLanguage == "en")
                    return $input;

                if($ChatSettings->ConfiguredLanguage == "auto")
                {
                    if(Validation::supportedLanguage($ChatSettings->GeneralizedLanguage) == false)
                        return $input;

                    $TargetLanguage = $ChatSettings->GeneralizedLanguage;
                }
                else
                {
                    $TargetLanguage = $ChatSettings->ConfiguredLanguage;
                }
            }

            $CoffeeHouse = SpamProtectionBot::getCoffeeHouse();

            try
            {
                $TranslationResults = $CoffeeHouse->getTranslator()->translate($input, $TargetLanguage, "en");
            }
            catch(Exception $e)
            {
                return $input;
            }

            foreach($specials as $special)
            {
                // Fix the translation specials into normalized entities
                $TranslationResults->Output = str_ireplace("％$special", "%$special", $TranslationResults->Output); // Chinese
                $TranslationResults->Output = str_ireplace("‰$special", "%$special", $TranslationResults->Output);
                $TranslationResults->Output = str_ireplace("‱$special", "%$special", $TranslationResults->Output);
                $TranslationResults->Output = str_ireplace("﹪$special", "%$special", $TranslationResults->Output);
                $TranslationResults->Output = str_ireplace("% $special", "%$special", $TranslationResults->Output);
                $TranslationResults->Output = str_ireplace("٪$special", "%$special", $TranslationResults->Output);
                $TranslationResults->Output = str_ireplace(":%$special", ": %$special", $TranslationResults->Output);
                $TranslationResults->Output = str_ireplace("% $special", "%$special", $TranslationResults->Output);
                $TranslationResults->Output = str_ireplace("：", ":", $TranslationResults->Output); // Japanese

                // Fix missing ":"
                if(stripos($TranslationResults->Output, ": %$special") == false)
                    $TranslationResults->Output .= ": %$special";

                // Fix broken spaces
                if(stripos($TranslationResults->Output, ":% $special: %$special") !== false)
                {
                    $TranslationResults->Output = str_ireplace(":% $special: %$special", ": %$special", $TranslationResults->Output);
                }

                // Fix broken spaces
                if(stripos($TranslationResults->Output, ":%$special: %$special") !== false)
                {
                    $TranslationResults->Output = str_ireplace(":%$special: %$special", ": %$special", $TranslationResults->Output);
                }

                // Fix double periods
                if(stripos($TranslationResults->Output, "%$special.: %$special") !== false)
                {
                    $TranslationResults->Output = str_ireplace("%$special.: %$special", ": %$special", $TranslationResults->Output);
                }

                if($input[stripos($input, "%$special") -1] == " " && $TranslationResults->Output[stripos($TranslationResults->Output, "%$special") -1] !== " ")
                {
                    $TranslationResults->Output = str_ireplace("%$special", " %$special", $TranslationResults->Output);
                }

                if(stripos($TranslationResults->Output, ":  %$special") !== false)
                {
                    $TranslationResults->Output = str_ireplace(":  %$special", "", $TranslationResults->Output);
                }

            }

            $TranslationResults->Output = str_ireplace("</ ", "</", $TranslationResults->Output);
            $TranslationResults->Output = str_ireplace("\ n", "\n", $TranslationResults->Output);
            $TranslationResults->Output = str_ireplace("\ ", "\\", $TranslationResults->Output);

            return $TranslationResults->Output;
        }

    }