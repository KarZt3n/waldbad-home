<?php

namespace App\Logic\IdentityAccess\User\Model;

enum CmsModule: string
{
    case Pages = 'pages';
    case Activities = 'activities';
    case Guestbook = 'guestbook';
    case ContactRequests = 'contact_requests';
    case Events = 'events';
    case EventHelpers = 'event_helpers';
    case MembershipApplications = 'membership_applications';
    case UserManagement = 'user_management';
}
