<?php

namespace App\Data\Rental\Sauna\Booking\Adapter;

use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\SaunaBookingNotifierInterface;
use App\Logic\Settings\Email\Model\NotificationEvent;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;

/**
 * Bindet den Mailversand der Einstellungen an die Vermietung an (Adapter-Prinzip,
 * `architektur.md` Abschnitt 2.4): Empfänger kommen aus „Einstellungen“ → „E-Mail-Einstellungen“
 * → „Benachrichtigungen“, der Text aus der Mailvorlage. Ohne hinterlegte Empfänger wird nichts
 * versendet (siehe `NotificationMailer::notify()`).
 */
readonly class SettingsSaunaBookingNotifier implements SaunaBookingNotifierInterface
{
    public function __construct(private NotificationMailer $mailer)
    {
    }

    public function bookingSubmitted(SaunaBooking $booking): void
    {
        $this->mailer->notify(NotificationEvent::SaunaBookingSubmitted, MailTemplateKey::SaunaBookingSubmittedNotification, [
            'vorname' => $booking->firstName,
            'nachname' => $booking->lastName,
            'datum' => $booking->date->format('d.m.Y'),
            'uhrzeit' => $booking->startTime.'–'.$booking->endTime,
            'anfrageart' => $booking->individual ? 'individuelle Wunschzeit' : 'Zeitfenster aus dem Kalender',
            'personenzahl' => (string) $booking->personCount,
            'preis' => number_format($booking->priceCents / 100, 2, ',', '.').' €',
            'email' => $booking->email ?? '–',
            'mitglied' => $booking->memberNumber ?? 'nicht zugeordnet',
            'nachricht' => trim($booking->message) === '' ? '–' : $booking->message,
        ]);
    }
}
