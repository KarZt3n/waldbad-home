<?php

namespace App\Logic\Content\SocialMedia\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Einzeiliger Einstellungs-Datensatz (Singleton) für die Social-Media-Leiste am rechten Rand aller
 * öffentlichen Seiten. Die Reihenfolge der Links ist die Anzeigereihenfolge; jede Plattform darf nur
 * einmal vorkommen.
 */
readonly class SocialMediaSettings
{
    /**
     * @param list<SocialMediaLink> $links
     */
    public function __construct(
        public bool $visible,
        public array $links,
    ) {
        $platforms = [];
        foreach ($links as $link) {
            if (isset($platforms[$link->platform->value])) {
                throw new BusinessRuleViolationException(sprintf('%s ist mehrfach eingetragen.', $link->platform->label()));
            }
            $platforms[$link->platform->value] = true;
        }
    }

    /**
     * @return list<SocialMediaLink>
     */
    public function publicLinks(): array
    {
        return $this->visible ? $this->links : [];
    }
}
