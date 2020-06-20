<?php


    namespace SpamProtection\Objects;

    use SpamProtection\Abstracts\DetectionAction;
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
         * When enabled, new users who join the chat who are recognized to be
         * an active spammer will cause an alert to be shown
         *
         * @var bool
         */
        public $ActiveSpammerAlertEnabled;

        /**
         * When enabled, any spam detection alerts will be shown.
         *
         * @var bool
         */
        public $GeneralAlertsEnabled;

        /**
         * Constructs the configuration array for this object
         *
         * @return array
         */
        public function toArray(): array
        {
            return array(
                '0x000' => (int)$this->LogSpamPredictions,
                '0x001' => (int)$this->ForwardProtectionEnabled,
                '0x002' => (int)$this->DetectSpamEnabled,
                '0x003' => $this->DetectSpamAction,
                '0x004' => (int)$this->BlacklistProtectionEnabled,
                '0x005' => (int)$this->ActiveSpammerAlertEnabled,
                '0x006' => (int)$this->GeneralAlertsEnabled
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

            return $ChatSettingsObject;
        }
    }