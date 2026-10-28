<?php

namespace App\Logic\Overview\Model;

use App\Logic\IdentityAccess\User\Model\CmsModule;

/** Offene Aufgaben der Redaktion; die Reihenfolge der Fälle ist die Anzeigereihenfolge. */
enum OverviewTaskType: string
{
    case MembershipApplications = 'membership_applications';
    case MemberMessages = 'member_messages';
    case ContactRequests = 'contact_requests';
    case GuestbookEntries = 'guestbook_entries';
    case SaunaBookings = 'sauna_bookings';
    case SaunaDuties = 'sauna_duties';
    case EventHelpRequests = 'event_help_requests';
    case PagesInReview = 'pages_in_review';
    case PagesWithUnpublishedChanges = 'pages_with_unpublished_changes';

    public function module(): CmsModule
    {
        return match ($this) {
            self::MembershipApplications => CmsModule::MembershipApplications,
            self::MemberMessages => CmsModule::MemberMessages,
            self::ContactRequests => CmsModule::ContactRequests,
            self::GuestbookEntries => CmsModule::Guestbook,
            self::SaunaBookings, self::SaunaDuties => CmsModule::RentalSauna,
            self::EventHelpRequests => CmsModule::EventHelpers,
            self::PagesInReview, self::PagesWithUnpublishedChanges => CmsModule::Pages,
        };
    }
}
