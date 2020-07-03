<?php


    namespace SpamProtection\Objects\TelegramObjects;

    use TelegramClientManager\Objects\TelegramClient\Chat;
    use TelegramClientManager\Objects\TelegramClient\User;

    /**
     * Class Message
     * @package SpamProtection\Objects\TelegramObjects
     */
    class Message
    {
        /**
         * Unique message identifier inside this chat
         *
         * @var int
         */
        public $MessageID;

        /**
         * Optional. Sender, empty for messages sent to channels
         *
         * @var User
         */
        public $From;

        /**
         * Date the message was sent in Unix time
         *
         * @var int
         */
        public $Date;

        /**
         * Conversation the message belongs to
         *
         * @var Chat
         */
        public $Chat;

        /**
         * Optional. For forwarded messages, sender of the original message
         *
         * @var User
         */
        public $ForwardFrom;

        /**
         * Optional. For messages forwarded from channels, information about
         * the original channel
         *
         * @var Chat
         */
        public $ForwardFromChat;

        /**
         * Optional. For messages forwarded from channels, identifier of the
         * original message in the channel
         *
         * @var int
         */
        public $ForwardFromMessageID;

        /**
         * Optional. For messages forwarded from channels, signature of
         * the post author if present
         *
         * @var string
         */
        public $ForwardSignature;

        /**
         * Optional. Sender's name for messages forwarded from users who disallow adding
         * a link to their account in forwarded messages
         *
         * @var string
         */
        public $ForwardSenderName;

        /**
         * Optional. For forwarded messages, date the original message was sent in Unix time
         *
         * @var int
         */
        public $ForwardDate;

        /**
         * Optional. For replies, the original message. Note that the Message object in this
         * field will not contain further reply_to_message fields even if it itself is a reply.
         *
         * @var Message
         */
        public $ReplyToMessage;

        /**
         * Optional. Bot through which the message was sent
         *
         * @var User
         */
        public $ViaBot;

        /**
         * Optional. For text messages, the actual UTF-8 text of the message, 0-4096 characters
         *
         * @var string
         */
        public $Text;

        /**
         * Optional. Caption for the animation, audio, document, photo, video or voice, 0-1024 characters
         *
         * @var string
         */
        public $Caption;

        /**
         * Optional. Message is a photo, available sizes of the photo
         *
         * @var PhotoSize[]
         */
        public $Photo;

        /**
         * Attempts to get the text of the message either from the text itself of caption
         * of the media object
         *
         * @return string|null
         */
        public function getText()
        {
            if($this->Text !== null)
            {
                return $this->Text;
            }

            if($this->Caption !== null)
            {
                return $this->Caption;
            }

            return null;
        }

        /**
         * Returns an array of the photo size
         *
         * @return array
         */
        public function photosToArray(): array
        {
            if($this->Photo !== null)
            {
                $Results = [];

                foreach($this->Photo as $photoSize)
                {
                    $Results[] = $photoSize->toArray();
                }

                return $Results;
            }

            return [];
        }

        /**
         * Determines if this message was forwarded
         *
         * @return bool
         */
        public function isForwarded(): bool
        {
            if($this->ForwardFrom !== null)
            {
                return true;
            }

            if($this->ForwardFromChat !== null)
            {
                return true;
            }

            if($this->ForwardFromMessageID !== null)
            {
                return true;
            }

            if($this->ForwardSenderName !== null)
            {
                return true;
            }

            return false;
        }

        /**
         * Returns the original sender of the forwarded message, returns null
         * if the user is private or it's from a channel
         *
         * @return User|null
         * @noinspection PhpUnused
         */
        public function getForwardedOriginalUser()
        {
            if($this->isForwarded() == false)
            {
                return null;
            }

            if($this->ForwardFrom !== null)
            {
                return $this->ForwardFrom;
            }

            return null;
        }

        /**
         * Returns the array perspective of this object
         *
         * @return array
         */
        public function toArray(): array
        {
            $Results = array();

            $Results['message_id'] = $this->MessageID;
            $Results['date'] = $this->Date;
            $Results['chat'] = $this->Chat->toArray();

            if($this->From !== null)
            {
                $Results['from'] = $this->From->toArray();
            }

            if($this->ForwardFrom !== null)
            {
                $Results['forward_from'] = $this->ForwardFrom->toArray();
            }

            if($this->ForwardFromChat !== null)
            {
                $Results['forward_from_chat'] = $this->ForwardFromChat->toArray();
            }

            if($this->ForwardFromMessageID !== null)
            {
                $Results['forward_from_message_id'] = $this->ForwardFromMessageID;
            }
            
            if($this->ForwardSignature !== null)
            {
                $Results['forward_signature'] = $this->ForwardSignature;
            }

            if($this->ForwardSenderName !== null)
            {
                $Results['forward_sender_name'] = $this->ForwardSenderName;
            }

            if($this->ForwardDate !== null)
            {
                $Results['forward_date'] = $this->ForwardDate;
            }

            if($this->ReplyToMessage !== null)
            {
                $Results['reply_to_message'] = $this->ReplyToMessage->toArray();
            }

            if($this->ViaBot !== null)
            {
                $Results['via_bot'] = $this->ViaBot;
            }

            if($this->Text !== null)
            {
                $Results['text'] = $this->Text;
            }

            if($this->Photo !== null)
            {
                $Results['photo'] = [];

                foreach($this->Photo as $photoSize)
                {
                    $Results['photo'][] = $photoSize->toArray();
                }
            }

            if($this->Caption !== null)
            {
                $Results['caption'] = $this->Caption;
            }

            return $Results;
        }

        /**
         * Constructs object from array
         *
         * @param array $data
         * @return Message
         */
        public static function fromArray(array $data): Message
        {
            $MessageObject = new Message();

            if(isset($data['message_id']))
            {
                $MessageObject->MessageID = $data['message_id'];
            }

            if(isset($data['from']))
            {
                $MessageObject->From = User::fromArray($data['from']);
            }

            if(isset($data['date']))
            {
                $MessageObject->Date = $data['date'];
            }

            if(isset($data['chat']))
            {
                $MessageObject->Chat = Chat::fromArray($data['chat']);
            }

            if(isset($data['forward_from']))
            {
                $MessageObject->ForwardFrom = User::fromArray($data['forward_from']);
            }

            if(isset($data['forward_from_chat']))
            {
                $MessageObject->ForwardFromChat = Chat::fromArray($data['forward_from_chat']);
            }

            if(isset($data['forward_from_message_id']))
            {
                $MessageObject->ForwardFromMessageID = $data['forward_from_message_id'];
            }

            if(isset($data['forward_signature']))
            {
                $MessageObject->ForwardSignature = $data['forward_signature'];
            }

            if(isset($data['forward_sender_name']))
            {
                $MessageObject->ForwardSenderName = $data['forward_sender_name'];
            }

            if(isset($data['forward_date']))
            {
                $MessageObject->ForwardDate = $data['forward_date'];
            }

            if(isset($data['reply_to_message']))
            {
                $MessageObject->ReplyToMessage = Message::fromArray($data['reply_to_message']);
            }

            if(isset($data['via_bot']))
            {
                $MessageObject->ViaBot = $data['via_bot'];
            }

            if(isset($data['text']))
            {
                $MessageObject->Text = $data['text'];
            }

            if(isset($data['photo']))
            {
                $MessageObject->Photo = [];

                foreach($data['photo'] as $photoSize)
                {
                    $MessageObject->Photo[] = PhotoSize::fromArray($photoSize);
                }
            }

            if(isset($data['caption']))
            {
                $MessageObject->Caption = $data['caption'];
            }

            return $MessageObject;
        }
    }