<?php

namespace App\Logic\Settings\MailTemplate\Service;

use App\Logic\Settings\MailTemplate\Manager\MailTemplateManagerInterface;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;

/**
 * Ersetzt in einer Vorlage (siehe `MailTemplateKey`) die Platzhalter `{{name}}` durch die beim
 * Versand übergebenen Werte. Unbekannte Platzhalter im Text (Tippfehler, veraltete Vorlage nach
 * einer Änderung von `MailTemplateKey::placeholders()`) bleiben absichtlich unverändert im Text
 * stehen, statt eine Fehlermeldung auszulösen — eine E-Mail mit sichtbarem `{{tippfehler}}` ist
 * besser als ein fehlgeschlagener Versand.
 */
readonly class MailTemplateRenderer
{
    public function __construct(private MailTemplateManagerInterface $manager)
    {
    }

    /**
     * @param array<string, string> $placeholders
     *
     * @return array{subject: string, body: string}
     */
    public function render(MailTemplateKey $key, array $placeholders): array
    {
        $template = $this->manager->resolve($key);
        $search = array_map(static fn (string $name): string => '{{'.$name.'}}', array_keys($placeholders));

        return [
            'subject' => str_replace($search, array_values($placeholders), $template->subject),
            'body' => str_replace($search, array_values($placeholders), $template->body),
        ];
    }
}
