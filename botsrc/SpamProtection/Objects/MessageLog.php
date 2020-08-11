<?php


    namespace SpamProtection\Objects;


    use SpamProtection\Objects\TelegramObjects\PhotoSize;
    use TelegramClientManager\Objects\TelegramClient\Chat;
    use TelegramClientManager\Objects\TelegramClient\User;

    /**
     * Class MessageLog
     * @package SpamProtection\Objects
     */
    class MessageLog
    {
        /**
         * Unique Internal Database ID of this message record
         *
         * @var int
         */
        public $ID;

        /**
         * Unique message has of the message structure
         *
         * @var string
         */
        public $MessageHash;

        /**
         * The unique message ID related to the chat
         *
         * @var int
         */
        public $MessageID;

        /**
         * The photo size entity
         *
         * @var PhotoSize|null
         */
        public $PhotoSize;

        /**
         * The ID of the chat this message is housed in
         *
         * @var int
         */
        public $ChatID;

        /**
         * The Chat that this message is housed in
         *
         * @var Chat
         */
        public $Chat;

        /**
         * The ID of the user for indexing
         *
         * @var int
         */
        public $UserID;

        /**
         * The user that sent this message
         *
         * @var User
         */
        public $User;

        /**
         * For forwarded messages, sender of the original message
         *
         * @var User
         */
        public $ForwardFrom;

        /**
         * For messages forwarded from channels, information about the original channel
         *
         * @var Chat
         */
        public $ForwardFromChat;

        /**
         * For messages forwarded from channels, identifier of the original message in the channel
         *
         * @var int
         */
        public $ForwardFromMessageID;

        /**
         * The hash of the content
         *
         * @var string
         */
        public $ContentHash;

        /**
         * The prediction of the content being spam
         *
         * @var float|int
         */
        public $SpamPrediction;

        /**
         * The prediction of the content being ham
         *
         * @var float|int
         */
        public $HamPrediction;

        /**
         * The Unix Timestamp for when this record was created
         *
         * @var int
         */
        public $Timestamp;

        /**
         * Returns an array for which represents this object
         *
         * @return array
         */
        public function toArray(): array
        {
            $Results = array(
                'id' => (int)$this->ID,
                'message_hash' => (string)$this->MessageHash,
                'chat_id' => (int)$this->ChatID,
                'chat' => $this->Chat->toArray(),
                'user_id' => (int)$this->UserID,
                'user' => $this->User->toArray(),
            );

            if($this->PhotoSize !== null)
            {
                $Results['photo_size'] = $this->PhotoSize->toArray();
            }

            if($this->ForwardFrom !== null)
            {
                $Results['forward_from'] = $this->ForwardFrom->toArray();
            }
            else
            {
                $Results['forward_from'] = null;
            }

            if($this->ForwardFromChat !== null)
            {
                $Results['forward_from_chat'] = $this->ForwardFromChat->toArray();
            }
            else
            {
                $Results['forward_from_chat'] = null;
            }

            if($this->ForwardFromMessageID !== null)
            {
                $Results['forward_from_message_id'] = $this->ForwardFromMessageID;
            }
            else
            {
                $Results['forward_from_message_id'] = null;
            }

            $Results['content_hash'] = $this->ContentHash;
            $Results['spam_prediction'] = (float)$this->SpamPrediction;
            $Results['ham_prediction'] = (float)$this->HamPrediction;
            $Results['timestamp'] = (int)$this->Timestamp;

            return $Results;
        }

        /**
         * Constructs object from an array structure
         *
         * @param array $data
         * @return MessageLog
         */
        public static function fromArray(array $data): MessageLog
        {
            $MessageLogObject = new MessageLog();

            if(isset($data['id']))
            {
                $MessageLogObject->ID = (int)$data['id'];
            }

            if(isset($data['message_hash']))
            {
                $MessageLogObject->MessageHash = $data['message_hash'];
            }

            if(isset($data['message_id']))
            {
                $MessageLogObject->MessageID = (int)$data['message_id'];
            }

            if(isset($data['photo_size']))
            {
                $MessageLogObject->PhotoSize = PhotoSize::fromArray($data['photo_size']);
            }

            if(isset($data['chat_id']))
            {
                $MessageLogObject->ChatID = (int)$data['chat_id'];
            }

            if(isset($data['chat']))
            {
                $MessageLogObject->Chat = Chat::fromArray($data['chat']);
            }

            if(isset($data['user_id']))
            {
                $MessageLogObject->UserID = (int)$data['user_id'];
            }

            if(isset($data['user']))
            {
                $MessageLogObject->User = User::fromArray($data['user']);
            }

            if(isset($data['forward_from']))
            {
                $MessageLogObject->ForwardFrom = User::fromArray($data['forward_from']);
            }

            if(isset($data['forward_from_chat']))
            {
                $MessageLogObject->ForwardFromChat = Chat::fromArray($data['forward_from_chat']);
            }

            if(isset($data['forward_from_message_id']))
            {
                $MessageLogObject->ForwardFromMessageID = (int)$data['forward_from_message_id'];
            }

            if(isset($data['content_hash']))
            {
                $MessageLogObject->ContentHash = $data['content_hash'];
            }

            if(isset($data['spam_prediction']))
            {
                $MessageLogObject->SpamPrediction = (float)$data['spam_prediction'];
            }

            if(isset($data['ham_prediction']))
            {
                $MessageLogObject->HamPrediction = (float)$data['ham_prediction'];
            }

            if(isset($data['timestamp']))
            {
                $MessageLogObject->Timestamp = (int)$data['timestamp'];
            }

            return $MessageLogObject;
        }
    }