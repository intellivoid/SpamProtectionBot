<?php


    namespace SpamProtection\Objects;

    use SpamProtection\Abstracts\BlacklistFlag;
    use TelegramClientManager\Objects\TelegramClient\Chat;

    /**
     * Class ChannelStatus
     * @package SpamProtection\Objects
     */
    class ChannelStatus
    {
        /**
         * The chat that these settings are configured for
         *
         * @var Chat
         */
        public $Chat;

        /**
         * Indicates if this channel is blacklisted or not
         *
         * @var bool
         */
        public $IsBlacklisted;

        /**
         * If the channel is blacklisted, the blacklist flag is applicable here
         *
         * @var string|BlacklistFlag
         */
        public $BlacklistFlag;

        /**
         * Indicates if this channel is whitelisted
         *
         * @var bool
         */
        public $IsWhitelisted;

        /**
         * Indicates if this channel is marked as official by Intellivoid
         *
         * @var bool
         */
        public $IsOfficial;

        /**
         * The generalized ID of the channel
         *
         * @var string|null
         */
        public $GeneralizedID;

        /**
         * The generalized ham prediction of the channel
         *
         * @var float|int
         */
        public $GeneralizedHam;

        /**
         * The generalized spam prediction of the channel
         *
         * @var float|int
         */
        public $GeneralizedSpam;

        /**
         * A note placed by the operator
         *
         * @var string
         */
        public $OperatorNote;

        /**
         * The generalized language prediction of this channel
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
         * Linked of linked chats
         *
         * @var string[]
         */
        public $LinkedChats;

        /**
         * Links a chat to the channel
         *
         * @param string $public_id
         * @return bool
         * @noinspection PhpUnused
         */
        public function linkChat(string $public_id): bool
        {
            if(in_array($public_id, $this->LinkedChats))
            {
                return false;
            }

            $this->LinkedChats[] = $public_id;
            return true;
        }

        /**
         * Unlinks a chat from the channel
         *
         * @param string $public_id
         * @return bool
         * @noinspection PhpUnused
         */
        public function unlinkChat(string $public_id): bool
        {
            if(in_array($public_id, $this->LinkedChats) == false)
            {
                return false;
            }

            $this->LinkedChats = array_diff($this->LinkedChats, [$public_id]);
            return true;
        }

        /**
         * Returns an array which represents this object
         *
         * @return array
         */
        public function toArray(): array
        {
            return array(
                '0x000' => (int)$this->IsBlacklisted,
                '0x001' => $this->BlacklistFlag,
                '0x002' => (int)$this->IsWhitelisted,
                '0x003' => (int)$this->IsOfficial,
                '0x004' => $this->GeneralizedID,
                '0x005' => (float)$this->GeneralizedHam,
                '0x006' => (float)$this->GeneralizedSpam,
                '0x007' => $this->OperatorNote,
                '0x008' => $this->GeneralizedLanguage,
                '0x009' => $this->GeneralizedLanguageProbability,
                '0x010' => $this->LargeLanguageGeneralizedID,
                '0x011' => $this->LinkedChats
            );
        }

        /**
         * Constructs object from array
         *
         * @param Chat $chat
         * @param array $data
         * @return ChannelStatus
         */
        public static function fromArray(Chat $chat, array $data): ChannelStatus
        {
            $ChannelStatusObject = new ChannelStatus();
            $ChannelStatusObject->Chat = $chat;

            if(isset($data['0x000']))
            {
                $ChannelStatusObject->IsBlacklisted = (bool)$data['0x000'];
            }
            else
            {
                $ChannelStatusObject->IsBlacklisted = false;
            }

            if(isset($data['0x001']))
            {
                $ChannelStatusObject->BlacklistFlag = $data['0x001'];
            }
            else
            {
                $ChannelStatusObject->BlacklistFlag = BlacklistFlag::None;
            }

            if(isset($data['0x002']))
            {
                $ChannelStatusObject->IsWhitelisted = (bool)$data['0x002'];
            }
            else
            {
                $ChannelStatusObject->IsWhitelisted = false;
            }

            if(isset($data['0x003']))
            {
                $ChannelStatusObject->IsOfficial = (bool)$data['0x003'];
            }
            else
            {
                $ChannelStatusObject->IsOfficial = false;
            }

            if(isset($data['0x004']))
            {
                $ChannelStatusObject->GeneralizedID = $data['0x004'];
            }
            else
            {
                $ChannelStatusObject->GeneralizedID = null;
            }

            if(isset($data['0x005']))
            {
                $ChannelStatusObject->GeneralizedHam = (float)$data['0x005'];
            }
            else
            {
                $ChannelStatusObject->GeneralizedHam = (float)0;
            }

            if(isset($data['0x006']))
            {
                $ChannelStatusObject->GeneralizedSpam = (float)$data['0x006'];
            }
            else
            {
                $ChannelStatusObject->GeneralizedSpam = (float)0;
            }

            if(isset($data['0x007']))
            {
                $ChannelStatusObject->OperatorNote = $data['0x007'];
            }
            else
            {
                $ChannelStatusObject->OperatorNote = null;
            }

            if(isset($data['0x008']))
            {
                $ChannelStatusObject->GeneralizedLanguage = $data['0x008'];
            }
            else
            {
                $ChannelStatusObject->GeneralizedLanguage = "Unknown";
            }

            if(isset($data['0x009']))
            {
                $ChannelStatusObject->GeneralizedLanguageProbability = (float)$data['0x009'];
            }
            else
            {
                $ChannelStatusObject->GeneralizedLanguageProbability = 0;
            }

            if(isset($data['0x010']))
            {
                $ChannelStatusObject->LargeLanguageGeneralizedID = $data['0x010'];
            }
            else
            {
                $ChannelStatusObject->LargeLanguageGeneralizedID = null;
            }

            if(isset($data['0x011']))
            {
                $ChannelStatusObject->LinkedChats = $data['0x011'];
            }
            else
            {
                $ChannelStatusObject->LinkedChats = [];
            }

            return $ChannelStatusObject;
        }
    }