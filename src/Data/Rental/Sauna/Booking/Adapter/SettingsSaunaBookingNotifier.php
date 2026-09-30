<?php

namespace App\Data\Rental\Sauna\Booking\Adapter;

use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingParticipant;
use App\Logic\Rental\Sauna\Booking\SaunaBookingNotifierInterface;
use App\Logic\Settings\Email\Model\AssociationName;
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
        $this->notify([$booking], 'Zeitfenster aus dem Kalender');
    }

    /**
     * `datum`/`uhrzeit` nennen den ersten Wunschtag; alle Tage stehen je als eigener Block in `termine`.
     */
    public function individualRequestSubmitted(array $bookings): void
    {
        $this->notify($bookings, sprintf('individuelle Anfrage mit %d %s', count($bookings), count($bookings) === 1 ? 'Wunschtag' : 'Wunschtagen'));
    }

    /**
     * `datum` nennt den ersten angenommenen Tag; alle Tage stehen je als eigener Block in `termine`.
     * Stammen die Tage aus mehreren Anfragen, stehen alle unterschiedlichen Nachrichten in `nachricht`.
     */
    public function bookingsAccepted(array $bookings, string $recipientEmail): void
    {
        $booking = $bookings[0];
        $messages = array_values(array_unique(array_filter(
            array_map(static fn (SaunaBooking $day): string => trim($day->message), $bookings),
            static fn (string $message): bool => $message !== '',
        )));
        $this->mailer->sendTo($recipientEmail, MailTemplateKey::SaunaBookingAccepted, [
            'vorname' => $booking->firstName,
            'nachname' => $booking->lastName,
            'datum' => $booking->date->format('d.m.Y'),
            'termine' => implode("\n\n", array_map($this->dayBlock(...), $bookings)),
            'preis' => $this->euro(array_sum(array_map(static fn (SaunaBooking $day): int => $day->priceCents, $bookings))),
            'nachricht' => $messages === [] ? 'keine' : implode("\n", $messages),
            'vereinsname' => AssociationName::CURRENT,
        ]);
    }

    public function bookingCancelled(SaunaBooking $booking, string $recipientEmail): void
    {
        $this->mailer->sendTo($recipientEmail, MailTemplateKey::SaunaBookingCancelled, [
            'vorname' => $booking->firstName,
            'nachname' => $booking->lastName,
            'datum' => $booking->date->format('d.m.Y'),
            'termin' => $this->dayBlock($booking),
            'vereinsname' => AssociationName::CURRENT,
        ]);
    }

    /**
     * @param non-empty-list<SaunaBooking> $bookings
     */
    private function notify(array $bookings, string $requestType): void
    {
        $booking = $bookings[0];
        $personCounts = array_unique(array_map(static fn (SaunaBooking $day): int => $day->personCount, $bookings));
        $this->mailer->notify(NotificationEvent::SaunaBookingSubmitted, MailTemplateKey::SaunaBookingSubmittedNotification, [
            'vorname' => $booking->firstName,
            'nachname' => $booking->lastName,
            'datum' => $booking->date->format('d.m.Y'),
            'uhrzeit' => $booking->startTime.'–'.$booking->endTime,
            'anfrageart' => $requestType,
            'termine' => implode("\n\n", array_map($this->dayBlock(...), $bookings)),
            'personenzahl' => count($personCounts) === 1 ? (string) $booking->personCount : 'je Tag unterschiedlich',
            'preis' => $this->euro(array_sum(array_map(static fn (SaunaBooking $day): int => $day->priceCents, $bookings))),
            'email' => $booking->email ?? '–',
            'mitglied' => $booking->memberNumber ?? 'nicht zugeordnet',
            'nachricht' => trim($booking->message) === '' ? 'keine' : $booking->message,
        ]);
    }

    /** Ein Termin als Block, wie er in den Sauna-Mails je Tag erscheint. */
    private function dayBlock(SaunaBooking $booking): string
    {
        return sprintf(
            "%s, %s–%s Uhr,\n- %d Personen%s\n- Preis: %s",
            $booking->date->format('d.m.Y'),
            $booking->startTime,
            $booking->endTime,
            $booking->personCount,
            $booking->participants === [] ? '' : ' ('.$this->participantNames($booking).')',
            $this->euro($booking->priceCents),
        );
    }

    private function participantNames(SaunaBooking $booking): string
    {
        return implode(', ', array_map(
            static fn (SaunaBookingParticipant $participant): string => $participant->firstName.' '.$participant->lastName,
            $booking->participants,
        ));
    }

    private function euro(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' €';
    }
}
