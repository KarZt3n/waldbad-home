<?php

namespace App\UI\IdentityAccess\Security;

enum Permission: string
{
    case CmsRead = 'ROLE_CMS_READ';
    case RoleManage = 'ROLE_ROLE_MANAGE';
    case SystemManage = 'ROLE_SYSTEM_MANAGE';
    case PagesView = 'ROLE_MODULE_PAGES_VIEWER';
    case PagesEdit = 'ROLE_MODULE_PAGES_EDITOR';
    case PagesPublish = 'ROLE_MODULE_PAGES_PUBLISHER';
    case PagesModerate = 'ROLE_MODULE_PAGES_MODERATOR';
    case ActivitiesView = 'ROLE_MODULE_ACTIVITIES_VIEWER';
    case ActivitiesEdit = 'ROLE_MODULE_ACTIVITIES_EDITOR';
    case GuestbookView = 'ROLE_MODULE_GUESTBOOK_VIEWER';
    case GuestbookEdit = 'ROLE_MODULE_GUESTBOOK_EDITOR';
    case ContactRequestsView = 'ROLE_MODULE_CONTACT_REQUESTS_VIEWER';
    case ContactRequestsEdit = 'ROLE_MODULE_CONTACT_REQUESTS_EDITOR';
    case EventHelpersView = 'ROLE_MODULE_EVENT_HELPERS_VIEWER';
    case EventHelpersEdit = 'ROLE_MODULE_EVENT_HELPERS_EDITOR';
    case MembershipApplicationsView = 'ROLE_MODULE_MEMBERSHIP_APPLICATIONS_VIEWER';
    case MembershipApplicationsEdit = 'ROLE_MODULE_MEMBERSHIP_APPLICATIONS_EDITOR';
    case UserManagementView = 'ROLE_MODULE_USER_MANAGEMENT_VIEWER';
    case UserManagementEdit = 'ROLE_MODULE_USER_MANAGEMENT_EDITOR';
}
