<?php

namespace App\UI\IdentityAccess\Security;

enum Permission: string
{
    case CmsRead = 'ROLE_CMS_READ';
    case ContentEdit = 'ROLE_CONTENT_EDIT';
    case ContentPublish = 'ROLE_CONTENT_PUBLISH';
    case GuestbookModerate = 'ROLE_GUESTBOOK_MODERATE';
    case ContactManage = 'ROLE_CONTACT_MANAGE';
    case MembershipManage = 'ROLE_MEMBERSHIP_MANAGE';
    case EventHelpManage = 'ROLE_EVENT_HELP_MANAGE';
    case UserManage = 'ROLE_USER_MANAGE';
    case RoleManage = 'ROLE_ROLE_MANAGE';
    case SystemManage = 'ROLE_SYSTEM_MANAGE';
    case AuditRead = 'ROLE_AUDIT_READ';
}
