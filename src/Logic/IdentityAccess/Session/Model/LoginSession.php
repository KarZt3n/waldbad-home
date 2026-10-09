<?php

namespace App\Logic\IdentityAccess\Session\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Eine Anmeldung an der Redaktion per Magic Link, über alle Rotationen ihrer Refresh-Tokens hinweg
 * (siehe `RefreshToken::$sessionId`, `SessionTokenIssuer`). Dient der Sitzungshistorie: wer sich
 * wann angemeldet hat, wann die Sitzung zuletzt verlängert wurde und wie sie endete.
 *
 * Eine Sitzung, die nie ausdrücklich beendet wurde, gilt als abgelaufen, sobald ihr letztes
 * Refresh-Token abgelaufen ist (`$expiresAt`) — dafür ist kein Hintergrundprozess nötig.
 */
readonly class LoginSession
{
    public const int MAX_USER_AGENT_LENGTH = 255;

    public function __construct(
        public string $id,
        public string $userId,
        public \DateTimeImmutable $startedAt,
        public \DateTimeImmutable $lastActivityAt,
        public \DateTimeImmutable $expiresAt,
        public ?string $userAgent = null,
        public ?\DateTimeImmutable $endedAt = null,
        public ?LoginSessionEndReason $endReason = null,
    ) {
        if (trim($this->userId) === '') {
            throw new BusinessRuleViolationException('Die Benutzer-ID ist erforderlich.');
        }
        if ($this->userAgent !== null && mb_strlen($this->userAgent) > self::MAX_USER_AGENT_LENGTH) {
            throw new BusinessRuleViolationException('Die Browserkennung ist zu lang.');
        }
        if (($this->endedAt === null) !== ($this->endReason === null)) {
            throw new BusinessRuleViolationException('Eine beendete Sitzung benötigt Zeitpunkt und Grund.');
        }
    }

    public function isEnded(): bool
    {
        return $this->endedAt !== null;
    }

    /** `null`, solange die Sitzung noch läuft. */
    public function effectiveEndReason(\DateTimeImmutable $at): ?LoginSessionEndReason
    {
        if ($this->endReason !== null) {
            return $this->endReason;
        }

        return $at >= $this->expiresAt ? LoginSessionEndReason::Expired : null;
    }

    public function effectiveEndedAt(\DateTimeImmutable $at): ?\DateTimeImmutable
    {
        if ($this->endedAt !== null) {
            return $this->endedAt;
        }

        return $at >= $this->expiresAt ? $this->expiresAt : null;
    }

    public function extend(\DateTimeImmutable $at, \DateTimeImmutable $expiresAt): self
    {
        if ($this->isEnded()) {
            return $this;
        }

        return new self($this->id, $this->userId, $this->startedAt, $at, $expiresAt, $this->userAgent);
    }

    public function end(\DateTimeImmutable $at, LoginSessionEndReason $reason): self
    {
        if ($this->isEnded()) {
            return $this;
        }

        return new self($this->id, $this->userId, $this->startedAt, $this->lastActivityAt, $this->expiresAt, $this->userAgent, $at, $reason);
    }
}
