<?php


    namespace SpamProtection\Objects\TelegramObjects;
    
    
    use SpamProtection\Abstracts\TelegramUserStatus;
    use TelegramClientManager\Objects\TelegramClient\User;

    /**
     * Class ChatMember
     * @package SpamProtection\Objects\TelegramObjects
     */
    class ChatMember
    {
        /**
         * Information about the user
         *
         * @var User
         */
        public $User;

        /**
         * Optional. Owner and administrators only. Custom title for this user
         *
         * @var string|TelegramUserStatus
         */
        public $Status;

        /**
         * Optional. Owner and administrators only. Custom title for this user
         *
         * @var string
         */
        public $CustomTitle;

        /**
         * Optional. Restricted and kicked only. Date when restrictions will be lifted for this user; unix time
         *
         * @var int
         */
        public $UntilDate;

        /**
         * Optional. Administrators only. True, if the bot is allowed to edit administrator privileges of that user
         *
         * @var bool
         */
        public $CanBeEdited;

        /**
         * Optional. Administrators only. True, if the administrator can post in the channel; channels only
         *
         * @var bool
         */
        public $CanPostMessages;

        /**
         * Optional. Administrators only. True, if the administrator can edit messages
         * of other users and can pin messages; channels only
         *
         * @var bool
         */
        public $CanEditMessages;

        /**
         * Optional. Administrators only. True, if the administrator can delete messages of other users
         *
         * @var bool
         */
        public $CanDeleteMessages;

        /**
         * Optional. Administrators only. True, if the administrator can restrict, ban or unban chat members
         *
         * @var bool
         */
        public $CanRestrictMembers;

        /**
         * Optional. Administrators only. True, if the administrator can add new administrators with a
         * subset of their own privileges or demote administrators that he has promoted, directly or
         * indirectly (promoted by administrators that were appointed by the user)
         *
         * @var bool
         */
        public $CanPromoteMembers;

        /**
         * Optional. Administrators and restricted only. True, if the user is allowed to change
         * the chat title, photo and other settings
         *
         * @var bool
         */
        public $CanChangeInfo;

        /**
         * Optional. Administrators and restricted only. True, if the user is allowed to invite new users to the chat
         *
         * @var bool
         */
        public $CanInviteUsers;

        /**
         * Optional. Administrators and restricted only. True, if the user is allowed to pin messages;
         * groups and supergroups only
         *
         * @var bool
         */
        public $CanPinMessages;

        /**
         * Optional. Restricted only. True, if the user is a member of the chat at the moment of the request
         *
         * @var bool
         */
        public $IsMember;

        /**
         * Optional. Restricted only. True, if the user is allowed to send text messages,
         * contacts, locations and venues
         *
         * @var bool
         */
        public $CanSendMessages;

        /**
         * Optional. Restricted only. True, if the user is allowed to send audios, documents,
         * photos, videos, video notes and voice notes
         *
         * @var bool
         */
        public $CanSendMediaMessages;

        /**
         * Optional. Restricted only. True, if the user is allowed to send polls
         *
         * @var bool
         */
        public $CanSendPolls;

        /**
         * Optional. Restricted only. True, if the user is allowed to send animations,
         * games, stickers and use inline bots
         *
         * @var bool
         */
        public $CanSendOtherMessages;

        /**
         * Optional. Restricted only. True, if the user is allowed to add web page previews to their messages
         *
         * @var bool
         */
        public $CanAddWebPagePreviews;

        /**
         * Returns an array which represents this object
         *
         * @return array
         */
        public function toArray(): array
        {
            return array(
                'user' => $this->User->toArray(),
                'status' => $this->Status,
                'custom_title' => $this->CustomTitle,
                'until_date' => $this->UntilDate,
                'can_be_edited' => $this->CanBeEdited,
                'can_post_messages' => $this->CanPostMessages,
                'can_edit_messages' => $this->CanEditMessages,
                'can_delete_messages' => $this->CanDeleteMessages,
                'can_restrict_members' => $this->CanRestrictMembers,
                'can_promote_members' => $this->CanPromoteMembers,
                'can_change_info' => $this->CanChangeInfo,
                'can_invite_users' => $this->CanInviteUsers,
                'can_pin_messages' => $this->CanPinMessages,
                'is_member' => $this->IsMember,
                'can_send_messages' => $this->CanSendMessages,
                'can_send_media_messages' => $this->CanSendMediaMessages,
                'can_send_polls' => $this->CanSendPolls,
                'can_send_other_messages' => $this->CanSendOtherMessages,
                'can_add_web_page_previews' => $this->CanAddWebPagePreviews
             );
        }

        /**
         * Constructs object from array
         *
         * @param array $data
         * @return ChatMember
         */
        public static function fromArray(array $data): ChatMember
        {
            $ChatMemberObject = new ChatMember();

            if(isset($data['user']))
            {
                $ChatMemberObject->User = User::fromArray($data['user']);
            }

            if(isset($data['status']))
            {
                $ChatMemberObject->Status = $data['status'];
            }

            if(isset($data['custom_title']))
            {
                $ChatMemberObject->CustomTitle = $data['custom_title'];
            }

            if(isset($data['until_date']))
            {
                $ChatMemberObject->UntilDate = (int)$data['until_date'];
            }

            if(isset($data['can_be_edited']))
            {
                $ChatMemberObject->CanBeEdited = (bool)$data['can_be_edited'];
            }

            if(isset($data['can_post_messages']))
            {
                $ChatMemberObject->CanPostMessages = (bool)$data['can_post_messages'];
            }

            if(isset($data['can_edit_messages']))
            {
                $ChatMemberObject->CanEditMessages = (bool)$data['can_edit_messages'];
            }

            if(isset($data['can_delete_messages']))
            {
                $ChatMemberObject->CanDeleteMessages = (bool)$data['can_delete_messages'];
            }

            if(isset($data['can_restrict_members']))
            {
                $ChatMemberObject->CanRestrictMembers = (bool)$data['can_restrict_members'];
            }

            if(isset($data['can_promote_members']))
            {
                $ChatMemberObject->CanPromoteMembers = (bool)$data['can_promote_members'];
            }

            if(isset($data['can_change_info']))
            {
                $ChatMemberObject->CanChangeInfo = (bool)$data['can_change_info'];
            }

            if(isset($data['can_invite_users']))
            {
                $ChatMemberObject->CanInviteUsers = (bool)$data['can_invite_users'];
            }

            if(isset($data['can_pin_messages']))
            {
                $ChatMemberObject->CanPinMessages = (bool)$data['can_pin_messages'];
            }

            if(isset($data['is_member']))
            {
                $ChatMemberObject->IsMember = (bool)$data['is_member'];
            }

            if(isset($data['can_send_messages']))
            {
                $ChatMemberObject->CanSendMessages = (bool)$data['can_send_messages'];
            }

            if(isset($data['can_send_media_messages']))
            {
                $ChatMemberObject->CanSendMediaMessages = (bool)$data['can_send_media_messages'];
            }

            if(isset($data['can_send_polls']))
            {
                $ChatMemberObject->CanSendPolls = (bool)$data['can_send_polls'];
            }

            if(isset($data['can_send_other_messages']))
            {
                $ChatMemberObject->CanSendOtherMessages = (bool)$data['can_send_other_messages'];
            }

            if(isset($data['can_add_web_page_previews']))
            {
                $ChatMemberObject->CanAddWebPagePreviews = (bool)$data['can_add_web_page_previews'];
            }

            return $ChatMemberObject;
        }
    }