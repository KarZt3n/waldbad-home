<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260824180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds unique, content-specific SEO titles and descriptions to every CMS page.';
    }

    public function up(Schema $schema): void
    {
        $seoByPage = $this->seoByPage();
        $pageIds = $this->connection->fetchFirstColumn('SELECT id FROM cms_page');
        if ($pageIds === []) {
            return;
        }
        foreach ($pageIds as $pageId) {
            if (!is_string($pageId)) {
                throw new \RuntimeException('Eine Seitenkennung konnte nicht gelesen werden.');
            }
            if (!isset($seoByPage[$pageId])) {
                throw new \RuntimeException(sprintf('Für die Seite %s fehlen SEO-Daten.', $pageId));
            }
        }
        $missingPageIds = array_diff(array_keys($seoByPage), $pageIds);
        if ($missingPageIds !== []) {
            throw new \RuntimeException(sprintf('Die erwarteten Seiten %s wurden nicht gefunden.', implode(', ', $missingPageIds)));
        }

        $timestamp = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        foreach ($seoByPage as $pageId => $seo) {
            $this->validateSeo($pageId, $seo['title'], $seo['description']);
            $parameters = [
                'id' => $pageId,
                'seoTitle' => $seo['title'],
                'seoDescription' => $seo['description'],
                'updatedAt' => $timestamp,
            ];
            $this->addSql(
                'UPDATE cms_page SET seo_title = :seoTitle, seo_description = :seoDescription, version = version + 1, updated_at = :updatedAt WHERE id = :id',
                $parameters,
            );
            $this->addSql(
                'UPDATE cms_page_publication SET seo_title = :seoTitle, seo_description = :seoDescription, page_version = page_version + 1, updated_at = :updatedAt WHERE page_id = :id',
                $parameters,
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Die individuellen vorherigen SEO-Texte lassen sich nicht zuverlässig rekonstruieren.');
    }

    private function validateSeo(string $pageId, string $title, string $description): void
    {
        if (mb_strlen($title.' – Waldbad Borkheide') > 65) {
            throw new \RuntimeException(sprintf('Der vollständige SEO-Titel der Seite %s ist länger als 65 Zeichen.', $pageId));
        }
        $descriptionLength = mb_strlen($description);
        if ($descriptionLength < 70 || $descriptionLength > 160) {
            throw new \RuntimeException(sprintf('Die SEO-Beschreibung der Seite %s muss zwischen 70 und 160 Zeichen lang sein.', $pageId));
        }
    }

    /** @return array<string, array{title: string, description: string}> */
    private function seoByPage(): array
    {
        return [
            'c8e11a2e-864b-41de-9cb9-361e492d72b0' => [
                'title' => 'Fotos & Videos aus dem Waldbad',
                'description' => 'Entdecken Sie Fotos und Videos von Veranstaltungen, Aktionen und besonderen Momenten aus dem Waldbad Borkheide – übersichtlich nach Jahren sortiert.',
            ],
            '0205fdd8-9839-40db-9335-c8d505f65b73' => [
                'title' => 'Fotos & Veranstaltungen 2025',
                'description' => 'Bilder und Videos aus dem Waldbad-Jahr 2025: Eisbaden, Waldlauf, Flohmarkt, Partys, Beachvolleyball und Weihnachtsmarkt in Borkheide.',
            ],
            '058aa2f1-50b1-47ac-9da3-0a6c6a8d86cd' => [
                'title' => 'Fotos & Veranstaltungen 2026',
                'description' => 'Bilder und Videos aus dem Waldbad-Jahr 2026: Eisbaden, Eisrettungsübung, Flohmarkt, Waldlauf und weitere Veranstaltungen in Borkheide.',
            ],
            'afc640ca-490f-45f1-b759-174a2db2e2d9' => [
                'title' => 'Gästebuch',
                'description' => 'Lesen Sie Grüße und Erinnerungen unserer Gäste oder hinterlassen Sie selbst einen Eintrag im moderierten Gästebuch des Waldbads Borkheide.',
            ],
            '1245f069-6ab4-4118-90aa-c401be92c56c' => [
                'title' => 'Gemeinsam anpacken',
                'description' => 'Helfen Sie bei gemeinsamen Arbeitseinsätzen mit und unterstützen Sie Pflege, Vorbereitung und Weiterentwicklung des Waldbads Borkheide.',
            ],
            'c579dfec-63e3-4cde-8ffa-1796e32b6a0d' => [
                'title' => 'Arbeitseinsätze 2026',
                'description' => 'Alle Arbeitseinsätze 2026 im Waldbad Borkheide mit Terminen, Aufgaben und benötigten Helferinnen und Helfern auf einen Blick.',
            ],
            'f71aa58c-33ba-4b74-8c88-73f78040b2a6' => [
                'title' => 'Impressum',
                'description' => 'Impressum und Anbieterkennzeichnung des Naturbad Borkheide e.V. mit Anschrift, Kontakt, Vereinsregister und Angaben zur Website.',
            ],
            'fff9c96d-4997-45ed-9049-b2f5196807ca' => [
                'title' => 'Kontakt & Anfahrt',
                'description' => 'Kontaktieren Sie den Naturbad Borkheide e.V. und finden Sie alle Informationen zur Erreichbarkeit und Anfahrt zum Waldbad Borkheide.',
            ],
            'b93bed58-32f9-4afc-bce6-cbc14a165d45' => [
                'title' => 'Mitmachen im Waldbad',
                'description' => 'Mitglied werden, bei Aktionen anpacken oder als Rettungsschwimmer helfen: Entdecken Sie Ihre Möglichkeiten, das Waldbad Borkheide zu unterstützen.',
            ],
            '5d32a13f-e461-4292-8628-80ce3f39f21b' => [
                'title' => 'Mitglied werden',
                'description' => 'Werden Sie Mitglied im Naturbad Borkheide e.V., unterstützen Sie den Erhalt des Waldbads und stellen Sie Ihren Mitgliedsantrag direkt online.',
            ],
            '6081828f-f760-4927-9661-0ed3c1c1f46d' => [
                'title' => 'Beitragsordnung',
                'description' => 'Die Beitragsordnung des Naturbad Borkheide e.V. informiert über Jahresbeiträge, Familienrabatt, Aufnahmegebühren und gemeinnützige Arbeitsstunden.',
            ],
            '790da2ad-2beb-4ded-9120-a45023524017' => [
                'title' => 'Einwilligungserklärung',
                'description' => 'Informationen zur Einwilligung in die Verarbeitung personenbezogener Daten bei einer Mitgliedschaft im Naturbad Borkheide e.V.',
            ],
            '24f4583e-af48-4f31-8926-8d1617027271' => [
                'title' => 'Rettungsschwimmer werden',
                'description' => 'Unterstützen Sie die Badeaufsicht im Waldbad Borkheide. Hier finden Sie Informationen zu Einsatz, Voraussetzungen und Ausbildung als Rettungsschwimmer.',
            ],
            '96e11ee1-a0c9-4980-8fcb-a97c46268cb5' => [
                'title' => 'Rettungsschwimmer-Ausbildung',
                'description' => 'Erfahren Sie, wie die Ausbildung zum Rettungsschwimmer bei der DLRG in Borkheide abläuft und wie das Waldbad Ihre Qualifikation unterstützt.',
            ],
            '0966e964-b8a6-458a-8baa-473790a3a962' => [
                'title' => 'Rettungsschwimmer gesucht',
                'description' => 'Das Waldbad Borkheide sucht ausgebildete Rettungsschwimmer für die Badeaufsicht. Informieren Sie sich über Einsatzzeiten und Vergütung.',
            ],
            '66af0495-8d6f-4f7b-9766-1b958571250f' => [
                'title' => 'Öffnungszeiten & Eintrittspreise',
                'description' => 'Aktuelle Öffnungszeiten, Eintrittspreise, Wochenkarten und Hinweise zum Kassenautomaten und Einlass im Waldbad Borkheide.',
            ],
            '2bf09f32-13cf-4bd3-9ff4-34473aa2db8a' => [
                'title' => 'Naturbad in Borkheide',
                'description' => 'Natürlich baden ohne Chlor: Entdecken Sie das chemie- und barrierefreie Waldbad Borkheide mit Badeteich, Liegewiesen und Angeboten für Familien.',
            ],
            'c837d71c-1b42-44b8-9200-860c228b963d' => [
                'title' => 'Nächste Veranstaltung',
                'description' => 'Die nächste Veranstaltung im Waldbad Borkheide mit Termin, Uhrzeit und allen wichtigen Informationen zur Teilnahme und Helferanmeldung.',
            ],
            '1c79c834-fc4d-4f7d-9ac0-2c45a9e96542' => [
                'title' => 'Ausstattung & Angebote',
                'description' => 'Badeteich, Kleinkinderbecken, Rutsche, Sprungturm, Spielangebote und Liegewiesen: Das bietet das Waldbad Borkheide seinen Gästen.',
            ],
            '78b370fd-3698-4574-bb5c-a94afd546079' => [
                'title' => 'Unterstützer & Partner',
                'description' => 'Lernen Sie Unternehmen, Vereine und Organisationen kennen, die das Waldbad Borkheide und das Vereinsleben als Unterstützer und Partner begleiten.',
            ],
            '89951d1b-9f82-4d57-a5ad-68bf6ec523f4' => [
                'title' => 'Veranstaltungen im Waldbad',
                'description' => 'Entdecken Sie Eisbaden, Waldlauf, Flohmarkt, Konzerte, Beachvolleyball und weitere Veranstaltungen im Waldbad Borkheide.',
            ],
            'e3540efe-2bc5-4d6f-80ae-ccc2023b4bfd' => [
                'title' => 'Veranstaltungen 2026',
                'description' => 'Alle Veranstaltungen 2026 im Waldbad Borkheide mit Datum und Uhrzeit: von Eisbaden und Waldlauf bis Flohmarkt, Partys und Weihnachtsmarkt.',
            ],
            '76242dff-654f-45e4-9654-f8fa972fe789' => [
                'title' => 'Verein & Vorstand',
                'description' => 'Lernen Sie den Naturbad Borkheide e.V., den Vorstand und seine Aufgaben kennen und finden Sie Satzung, Ordnungen und Vereinsdokumente.',
            ],
            '4f627108-7e30-4ad6-9441-aab4d60571dc' => [
                'title' => 'Christian Tippmann | Vorstand',
                'description' => 'Christian Tippmann verantwortet im Vorstand des Naturbad Borkheide e.V. die Außen- und Grünanlagen sowie die Gebäude des Waldbads.',
            ],
            '3b10120a-c286-4a76-a11c-a753cc8ba920' => [
                'title' => 'Enrico Kaiser | Vorstand',
                'description' => 'Enrico Kaiser ist im Vorstand des Naturbad Borkheide e.V. für die Planung und den Einsatz der Badeaufsicht im Waldbad zuständig.',
            ],
            '651e5fbe-ef78-4df9-bc98-f746231bbb2d' => [
                'title' => 'Jörg Liebing | Vereinsvorsitzender',
                'description' => 'Jörg Liebing ist Vereinsvorsitzender des Naturbad Borkheide e.V. und Ansprechpartner für die Leitung und Vertretung des Vereins.',
            ],
            'a4a96330-682f-4072-8bee-2f29a8e7dfe0' => [
                'title' => 'Karsten Kuck | Vorstand',
                'description' => 'Karsten Kuck betreut im Naturbad Borkheide e.V. Zugangstechnik, Hard- und Software, Veranstaltungstechnik und Mitgliederverwaltung.',
            ],
            'cdfa8795-3187-4bb8-84e2-274d7c9cca5f' => [
                'title' => 'Marion Naumann | Vorstand',
                'description' => 'Marion Naumann verantwortet im Naturbad Borkheide e.V. die Presse- und Öffentlichkeitsarbeit sowie Vereinsleben und Veranstaltungen.',
            ],
            '44cde9c4-c67f-4473-a63a-c524a61d849f' => [
                'title' => 'Martin Fischer | Vorstand',
                'description' => 'Martin Fischer ist im Naturbad Borkheide e.V. für Sanitär- und Hygienetechnik sowie die Wasserqualität des Naturbads zuständig.',
            ],
            'be65812a-7151-44c4-a107-cf4686f2e5c9' => [
                'title' => 'Milena Schneider | Schatzmeisterin',
                'description' => 'Milena Schneider ist Schatzmeisterin des Naturbad Borkheide e.V. und verantwortet die finanziellen Angelegenheiten des Vereins.',
            ],
            'd490913c-6e72-4f53-9b58-b8ae05021d57' => [
                'title' => 'Ronny Höltz | Stellvertretender Vorsitzender',
                'description' => 'Ronny Höltz ist stellvertretender Vereinsvorsitzender des Naturbad Borkheide e.V. und unterstützt die Leitung und Vertretung des Vereins.',
            ],
            '774222ee-11cd-4297-9e69-0a31ec6d4b48' => [
                'title' => 'Sandra Albertziok-Skambraks | Vorstand',
                'description' => 'Sandra Albertziok-Skambraks ist Schriftführerin des Naturbad Borkheide e.V. und betreut außerdem die Bereiche Ordnung und Sauberkeit.',
            ],
        ];
    }
}
