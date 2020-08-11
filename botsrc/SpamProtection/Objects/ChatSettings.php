<?php


    namespace SpamProtection\Objects;

    use SpamProtection\Abstracts\DetectionAction;
    use SpamProtection\Exceptions\TemporaryVerificationCodeExpiredException;
    use SpamProtection\Exceptions\TemporaryVerificationCodeNotSetException;
    use SpamProtection\Objects\TelegramObjects\ChatMember;
    use SpamProtection\Utilities\Hashing;
    use TelegramClientManager\Objects\TelegramClient\Chat;

    /**
     * Class ChatSettings
     * @package SpamProtection\Objects
     */
    class ChatSettings
    {
        /**
         * The chat that these settings are configured for
         *
         * @var Chat
         */
        public $Chat;

        /**
         * Indicates if the positive spam predictions are logged publicly
         *
         * @var bool
         */
        public $LogSpamPredictions;

        /**
         * When enabled, forwarded content does not affect the
         * users who forward content, rather the original author
         * of the content is affected
         *
         * @var bool
         */
        public $ForwardProtectionEnabled;

        /**
         * Indicates if the bot is configured to detect spam by predicting
         * if the content being sent is spam or not
         *
         * @var bool
         */
        public $DetectSpamEnabled;

        /**
         * The action to take of DetectSpam is enabled, by default only the
         * content is removed
         *
         * @var string|DetectionAction
         */
        public $DetectSpamAction;

        /**
         * When enabled, the bot will ban blacklisted users from the chat
         *
         * @var bool
         */
        public $BlacklistProtectionEnabled;

        /**
         * When enabled, the bot will ban users that are considered to be active spammers from the chat
         *
         * @var bool
         */
        public $ActiveSpammerProtectionEnabled;

        /**
         * When enabled, new users who join the chat who are recognized to be
         * an active spammer will cause an alert to be shown
         *
         * @var bool
         * @deprecated
         */
        public $ActiveSpammerAlertEnabled;

        /**
         * When enabled, when an active spammer is detected the bot will ban the spammer
         *
         * @var bool
         */
        public $BanActiveSpammer;

        /**
         * When enabled, any spam detection alerts will be shown.
         *
         * @var bool
         */
        public $GeneralAlertsEnabled;

        /**
         * Indicates if this chat is verified by Intellivoid to be official
         *
         * @var bool
         */
        public $IsVerified;

        /**
         * Indicates if the request for verification is being processed
         *
         * @var bool
         */
        public $VerificationReviewInProgress;

        /**
         * The chat administrators in the chat
         *
         * @var ChatMember[]
         */
        public $Administrators;

        /**
         * The Unix Timestamp of when this administrator cache was last updated
         *
         * @var int
         */
        public $AdminCacheLastUpdated;

        /**
         * Indicates if the bot should delete older messages
         *
         * @var bool
         */
        public $DeleteOlderMessages;

        /**
         * The last ID of the message that was sent by the bot
         *
         * @var bool
         */
        public $LastMessageID;

        /**
         * Chat client parameters obtained from an agent
         *
         * @var ChatClientParameters
         */
        public $ChatClientParameters;

        /**
         * The generalized language prediction of this user
         *
         * @var string
         */
        public $GeneralizedLanguage;

        /**
         * The probability of the language prediction generalization
         *
         * @var float|int
         */
        public $GeneralizedLanguageProbability;

        /**
         * The ID of the large generalization of the language
         *
         * @var string|null
         */
        public $LargeLanguageGeneralizedID;

        /**
         * The generated unique temporary verification code for linking a chat to a log channel
         *
         * @var string
         */
        public $TemporaryVerificationCode;

        /**
         * The Unix Timestamp for when this code expires
         *
         * @var int
         */
        public $TemporaryVerificationCodeExpires;

        /**
         * Generates a temporary verification code that lasts for 10 minutes.
         *
         * @return string
         * @noinspection PhpUnused
         */
        public function generateTemporaryVerificationCode(): string
        {
            $this->TemporaryVerificationCode = Hashing::temporaryVerificationCode($this->Chat);
            $this->TemporaryVerificationCodeExpires = (int)time() + 600;

            return $this->TemporaryVerificationCode;
        }

        /**
         * Validates the temporary verification code and invalidates it if the option is enabled
         *
         * @param string $code
         * @param bool $invalidate
         * @return bool
         * @throws TemporaryVerificationCodeExpiredException
         * @throws TemporaryVerificationCodeNotSetException
         * @noinspection PhpUnused
         */
        public function verifyTemporaryVerificationCode(string $code, bool $invalidate=true): bool
        {
            if($this->TemporaryVerificationCode == null)
            {
                throw new TemporaryVerificationCodeNotSetException();
            }

            if((int)time() > $this->TemporaryVerificationCodeExpires)
            {
                throw new TemporaryVerificationCodeExpiredException();
            }

            if($this->TemporaryVerificationCode == $code)
            {
                if($invalidate)
                {
                    $this->TemporaryVerificationCode = null;
                    $this->TemporaryVerificationCodeExpires = 0;
                }

                return true;
            }

            return false;
        }

        /**
         * Constructs the configuration array for this object
         *
         * @return array
         */
        public function toArray(): array
        {
            $AdminResults = array();

            foreach($this->Administrators as $administrator)
            {
                $AdminResults[] = $administrator->toArray();
            }

            return array(
                '0x000' => (int)$this->LogSpamPredictions,
                '0x001' => (int)$this->ForwardProtectionEnabled,
                '0x002' => (int)$this->DetectSpamEnabled,
                '0x003' => $this->DetectSpamAction,
                '0x004' => (int)$this->BlacklistProtectionEnabled,
                '0x005' => (int)$this->ActiveSpammerAlertEnabled,
                '0x006' => (int)$this->GeneralAlertsEnabled,
                '0x007' => (int)$this->IsVerified,
                '0x008' => (int)$this->VerificationReviewInProgress,
                '0x009' => (int)$this->BanActiveSpammer,
                '0x010' => (int)$this->DeleteOlderMessages,
                '0x011' => $this->LastMessageID,
                '0x012' => $this->ChatClientParameters->toArray(),
                '0x013' => $this->GeneralizedLanguage,
                '0x014' => $this->GeneralizedLanguageProbability,
                '0x015' => $this->LargeLanguageGeneralizedID,
                '0x016' => $this->TemporaryVerificationCode,
                '0x017' => $this->TemporaryVerificationCodeExpires,
                '0x018' => (bool)$this->ActiveSpammerProtectionEnabled,
                'Ax000' => $AdminResults,
                'Ax001' => (int)$this->AdminCacheLastUpdated
            );
        }

        /**
         * Constructs configuration object from configuration array
         *
         * @param Chat $chat
         * @param array $data
         * @return ChatSettings
         */
        public static function fromArray(Chat $chat, array $data): ChatSettings
        {
            $ChatSettingsObject = new ChatSettings();
            $ChatSettingsObject->Chat = $chat;

            if(isset($data['0x000']))
            {
                $ChatSettingsObject->LogSpamPredictions = (bool)$data['0x000'];
            }
            else
            {
                $ChatSettingsObject->LogSpamPredictions = true;
            }

            if(isset($data['0x001']))
            {
                $ChatSettingsObject->ForwardProtectionEnabled = (bool)$data['0x001'];
            }
            else
            {
                $ChatSettingsObject->ForwardProtectionEnabled = false;
            }

            if(isset($data['0x002']))
            {
                $ChatSettingsObject->DetectSpamEnabled = (bool)$data['0x002'];
            }
            else
            {
                $ChatSettingsObject->DetectSpamEnabled = true;
            }

            if(isset($data['0x003']))
            {
                $ChatSettingsObject->DetectSpamAction = $data['0x003'];
            }
            else
            {
                $ChatSettingsObject->DetectSpamAction = DetectionAction::DeleteMessage;
            }

            if(isset($data['0x004']))
            {
                $ChatSettingsObject->BlacklistProtectionEnabled = (bool)$data['0x004'];
            }
            else
            {
                $ChatSettingsObject->BlacklistProtectionEnabled = true;
            }

            if(isset($data['0x005']))
            {
                $ChatSettingsObject->ActiveSpammerAlertEnabled = (bool)$data['0x005'];
            }
            else
            {
                $ChatSettingsObject->ActiveSpammerAlertEnabled = true;
            }

            if(isset($data['0x006']))
            {
                $ChatSettingsObject->GeneralAlertsEnabled = (bool)$data['0x006'];
            }
            else
            {
                $ChatSettingsObject->GeneralAlertsEnabled = true;
            }

            if(isset($data['0x007']))
            {
                $ChatSettingsObject->IsVerified = (bool)$data['0x007'];
            }
            else
            {
                $ChatSettingsObject->IsVerified = false;
            }

            if(isset($data['0x008']))
            {
                $ChatSettingsObject->VerificationReviewInProgress = (bool)$data['0x008'];
            }
            else
            {
                $ChatSettingsObject->VerificationReviewInProgress = false;
            }

            if(isset($data['0x009']))
            {
                $ChatSettingsObject->BanActiveSpammer = (bool)$data['0x009'];
            }
            else
            {
                $ChatSettingsObject->BanActiveSpammer = true;
            }

            if(isset($data['0x010']))
            {
                $ChatSettingsObject->DeleteOlderMessages = (bool)$data['0x010'];
            }
            else
            {
                $ChatSettingsObject->DeleteOlderMessages = true;
            }

            if(isset($data['0x011']))
            {
                $ChatSettingsObject->LastMessageID = $data['0x011'];
            }
            else
            {
                $ChatSettingsObject->LastMessageID = null;
            }

            if(isset($data['0x012']))
            {
                $ChatSettingsObject->ChatClientParameters = ChatClientParameters::fromArray($data['0x012']);
            }
            else
            {
                $ChatSettingsObject->ChatClientParameters = new ChatClientParameters();
            }

            if(isset($data['0x013']))
            {
                $ChatSettingsObject->GeneralizedLanguage = $data['0x013'];
            }
            else
            {
                $ChatSettingsObject->GeneralizedLanguage = "Unknown";
            }

            if(isset($data['0x014']))
            {
                $ChatSettingsObject->GeneralizedLanguageProbability = (float)$data['0x014'];
            }
            else
            {
                $ChatSettingsObject->GeneralizedLanguageProbability = 0;
            }

            if(isset($data['0x015']))
            {
                $ChatSettingsObject->LargeLanguageGeneralizedID = $data['0x015'];
            }
            else
            {
                $ChatSettingsObject->LargeLanguageGeneralizedID = null;
            }

            if(isset($data['0x016']))
            {
                $ChatSettingsObject->TemporaryVerificationCode = $data['0x016'];
            }
            else
            {
                $ChatSettingsObject->TemporaryVerificationCode = null;
            }

            if(isset($data['0x017']))
            {
                $ChatSettingsObject->TemporaryVerificationCodeExpires = $data['0x017'];
            }
            else
            {
                $ChatSettingsObject->TemporaryVerificationCodeExpires = null;
            }

            if(isset($data['0x018']))
            {
                $ChatSettingsObject->ActiveSpammerProtectionEnabled = (bool)$data['0x018'];
            }
            else
            {
                $ChatSettingsObject->ActiveSpammerProtectionEnabled = true;
            }

            $ChatSettingsObject->Administrators = array();

            if(isset($data['Ax000']))
            {
                foreach($data['Ax000'] as $datum)
                {
                    $ChatSettingsObject->Administrators[] = ChatMember::fromArray($datum);
                }
            }

            if(isset($data['Ax001']))
            {
                $ChatSettingsObject->AdminCacheLastUpdated = (int)$data['Ax001'];
            }
            else
            {
                $ChatSettingsObject->AdminCacheLastUpdated = 0;
            }

            return $ChatSettingsObject;
        }
    }