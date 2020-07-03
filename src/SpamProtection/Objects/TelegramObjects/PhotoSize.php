<?php


    namespace SpamProtection\Objects\TelegramObjects;


    class PhotoSize
    {
        /**
         * 	Identifier for this file, which can be used to download or reuse the file
         *
         * @var string
         */
        public $FileID;

        /**
         * Unique identifier for this file, which is supposed to be the same over time
         * and for different bots. Can't be used to download or reuse the file.
         *
         * @var string
         */
        public $FileUniqueID;

        /**
         * Photo width
         *
         * @var int
         */
        public $Width;

        /**
         * Photo height
         *
         * @var int
         */
        public $Height;

        /**
         * Optional. File size
         *
         * @var int|null
         */
        public $FileSize;

        /**
         * Direct URL for obtaining the file to this photo
         *
         * @var string|null
         */
        public $URL;

        /**
         * The ham prediction of the image
         *
         * @var float|null
         */
        public $HamPrediction;

        /**
         * The spam prediction of the image
         *
         * @var float|null
         */
        public $SpamPrediction;

        /**
         * Returns an array which represents this object.
         *
         * @return array
         */
        public function toArray(): array
        {
            return array(
                'file_id' => (string)$this->FileID,
                'file_unique_id' => (string)$this->FileUniqueID,
                'width' => (int)$this->Width,
                'height' => (int)$this->Height,
                'file_size' => $this->FileSize,
                'url' => $this->URL,
                'ham_prediction' => $this->HamPrediction,
                'spam_prediction' => $this->SpamPrediction
            );
        }

        /**
         * Constructs object from array
         *
         * @param array $data
         * @return PhotoSize
         */
        public static function fromArray(array $data): PhotoSize
        {
            $PhotoSizeObject = new PhotoSize();

            if(isset($data['file_id']))
            {
                $PhotoSizeObject->FileID = $data['file_id'];
            }

            if(isset($data['file_unique_id']))
            {
                $PhotoSizeObject->FileUniqueID = $data['file_unique_id'];
            }

            if(isset($data['width']))
            {
                $PhotoSizeObject->Width = (int)$data['width'];
            }

            if(isset($data['height']))
            {
                $PhotoSizeObject->Height = (int)$data['height'];
            }

            if(isset($data['file_size']))
            {
                $PhotoSizeObject->FileSize = (int)$data['file_size'];
            }

            if(isset($data['url']))
            {
                $PhotoSizeObject->URL = $data['url'];
            }

            if(isset($data['ham_prediction']))
            {
                $PhotoSizeObject->HamPrediction = (float)$data['ham_prediction'];
            }

            if(isset($data['spam_prediction']))
            {
                $PhotoSizeObject->SpamPrediction = (float)$data['spam_prediction'];
            }

            return $PhotoSizeObject;
        }
    }