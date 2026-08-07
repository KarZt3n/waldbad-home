<?php

namespace App\Logic\Content\Site\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Model\ContentBlockType;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Content\Page\Model\PageStatus;
use App\Logic\Content\Site\Definition\OpeningHoursPageDefinition;
use App\Logic\Content\Site\Definition\MembershipInformationDefinition;

readonly class InitializeSiteUseCase
{
    public function __construct(
        private PageManagerInterface $pageManager,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function execute(): int
    {
        $existingSlugs = array_map(static fn (Page $page): string => $page->slug, $this->pageManager->all());
        $created = 0;

        foreach ($this->definitions() as $definition) {
            if (in_array($definition['slug'], $existingSlugs, true)) {
                continue;
            }

            $now = $this->clock->now();
            $page = new Page(
                id: $this->identifierGenerator->generate(),
                title: $definition['title'],
                slug: $definition['slug'],
                navigationLabel: $definition['navigationLabel'],
                parentId: null,
                blocks: $definition['blocks'],
                status: PageStatus::Published,
                visible: true,
                showInNavigation: $definition['showInNavigation'] ?? true,
                navigationPosition: $definition['position'],
                seoTitle: $definition['title'],
                seoDescription: $definition['description'],
                version: 0,
                createdAt: $now,
                updatedAt: $now,
                publishedAt: $now,
            );
            $this->pageManager->save($page);
            ++$created;
        }

        return $created;
    }

    /**
     * @return list<array{
     *     title: string,
     *     slug: string,
     *     navigationLabel: string,
     *     position: int,
     *     description: string,
     *     showInNavigation?: bool,
     *     blocks: list<ContentBlock>
     * }>
     */
    private function definitions(): array
    {
        return [
            [
                'title' => 'Natürlich baden im Wald',
                'slug' => 'startseite',
                'navigationLabel' => 'Startseite',
                'position' => 0,
                'description' => 'Das chemie- und barrierefreie Naturbad in Borkheide.',
                'blocks' => [
                    new ContentBlock(
                        ContentBlockType::Alert,
                        'Aktuelle Öffnungszeiten und Hinweise werden hier von der Redaktion veröffentlicht.',
                    ),
                    new ContentBlock(
                        ContentBlockType::Heading,
                        'Willkommen im Waldbad Borkheide',
                    ),
                    new ContentBlock(
                        ContentBlockType::RichText,
                        'Seit 2003 lädt das Waldbad Borkheide zum natürlichen Baden ein. Das Wasser wird ohne Chlor durch Wasserpflanzen und Filtersedimente gereinigt.',
                    ),
                    new ContentBlock(
                        ContentBlockType::RichText,
                        "Freuen Sie sich auf einen großen Badeteich, Kleinkinderbereich, Rutsche, Sprungturm, Beachvolleyball und weitläufige Liegewiesen mitten im Grünen.",
                    ),
                ],
            ],
            [
                'title' => OpeningHoursPageDefinition::TITLE,
                'slug' => OpeningHoursPageDefinition::SLUG,
                'navigationLabel' => OpeningHoursPageDefinition::NAVIGATION_LABEL,
                'position' => 10,
                'description' => OpeningHoursPageDefinition::SEO_DESCRIPTION,
                'blocks' => OpeningHoursPageDefinition::blocks(),
            ],
            [
                'title' => 'Veranstaltungen',
                'slug' => 'veranstaltungen',
                'navigationLabel' => 'Veranstaltungen',
                'position' => 20,
                'description' => 'Feste, Sportveranstaltungen und Vereinsleben im Waldbad.',
                'blocks' => [
                    new ContentBlock(ContentBlockType::Heading, 'Gemeinsam am Wasser'),
                    new ContentBlock(ContentBlockType::RichText, 'Eisbaden, Waldlauf, Flohmarkt, Konzerte und Weihnachtsmarkt: Hier erscheinen künftig alle Termine in chronologischer Reihenfolge.'),
                ],
            ],
            [
                'title' => 'Verein und Vorstand',
                'slug' => 'verein-und-vorstand',
                'navigationLabel' => 'Verein',
                'position' => 30,
                'description' => 'Menschen, Aufgaben und Dokumente des Naturbad Borkheide e.V.',
                'blocks' => [
                    new ContentBlock(ContentBlockType::Heading, 'Naturbad Borkheide e.V.'),
                    new ContentBlock(ContentBlockType::RichText, 'Der Verein betreibt das Waldbad mit großem ehrenamtlichem Engagement. Vorstand, Zuständigkeiten und Vereinsdokumente werden in diesem Bereich veröffentlicht.'),
                ],
            ],
            [
                'title' => 'Mitglied werden',
                'slug' => 'mitglied-werden',
                'navigationLabel' => 'Mitglied werden',
                'position' => 40,
                'description' => 'Mitgliedschaft im Naturbad Borkheide e.V.',
                'blocks' => [
                    new ContentBlock(ContentBlockType::Heading, 'Das Waldbad gemeinsam erhalten'),
                    new ContentBlock(ContentBlockType::RichText, 'Mit Ihrer Mitgliedschaft unterstützen Sie den Betrieb und die Weiterentwicklung des Waldbads. Den Antrag können Sie direkt online ausfüllen.'),
                    new ContentBlock(ContentBlockType::Extension, '', extensionKey: 'membership_application'),
                    ...MembershipInformationDefinition::blocks(),
                ],
            ],
            [
                'title' => 'Gästebuch',
                'slug' => 'gaestebuch',
                'navigationLabel' => 'Gästebuch',
                'position' => 50,
                'description' => 'Grüße und Erinnerungen unserer Gäste.',
                'blocks' => [
                    new ContentBlock(ContentBlockType::Heading, 'Grüße aus dem Waldbad'),
                    new ContentBlock(ContentBlockType::RichText, 'Neue Beiträge werden vor der Veröffentlichung von unserem Team geprüft.'),
                ],
            ],
            [
                'title' => 'Kontakt und Anfahrt',
                'slug' => 'kontakt',
                'navigationLabel' => 'Kontakt',
                'position' => 60,
                'description' => 'So erreichen Sie das Waldbad Borkheide.',
                'blocks' => [
                    new ContentBlock(ContentBlockType::Heading, 'Wir freuen uns auf Ihre Nachricht'),
                    new ContentBlock(ContentBlockType::RichText, "Naturbad Borkheide e.V.\nKirchanger 14\n14822 Borkheide"),
                    new ContentBlock(ContentBlockType::CallToAction, 'Schreiben Sie uns per E-Mail.', linkUrl: 'mailto:info@waldbad-borkheide.de', linkLabel: 'E-Mail öffnen'),
                ],
            ],
            [
                'title' => 'Unterstützer',
                'slug' => 'unterstuetzer',
                'navigationLabel' => 'Unterstützer',
                'position' => 70,
                'description' => 'Organisationen und Unternehmen, die das Waldbad und das Vereinsleben unterstützen.',
                'blocks' => [
                    new ContentBlock(ContentBlockType::Heading, 'Gemeinsam für das Waldbad'),
                    new ContentBlock(ContentBlockType::RichText, '<p>Wir danken unseren Unterstützern und Kooperationspartnern. Mit einem Klick auf das jeweilige Logo gelangen Sie zur zugehörigen Website.</p>'),
                    new ContentBlock(
                        type: ContentBlockType::ImageText,
                        content: '<h3>DLRG Ortsgruppe Borkheide e.V.</h3><p>Nach der Neuwahl des Vorstands im September 2022 wurden die Aufgaben neu geordnet. Es folgten Kooperationen mit der Bundeswehrverwaltung und der Haveltherme in Werder sowie Angebote für Seepferdchenkurse und das Schwimmtraining von Jugend und Rettungsschwimmern. Gemeinsam mit der Gemeinde und dem Waldbad setzt sich die Ortsgruppe für einen langfristig gesicherten Standort und weitere Schwimmangebote in Borkheide ein.</p>',
                        mediaUrl: '/uploads/media/supporter-dlrg-borkheide.png',
                        mediaAlt: 'Logo der DLRG Ortsgruppe Borkheide e.V.',
                        linkUrl: 'https://borkheide.dlrg.de/',
                        layout: 'image_left',
                        imageWidthPercent: 30,
                        verticalAlignment: 'center',
                        textAlignment: 'left',
                        imageFit: 'contain',
                    ),
                    new ContentBlock(
                        type: ContentBlockType::ImageText,
                        content: '<h3>Hotel und Restaurant Fliegerheim</h3><p>Das traditionsreiche, familiengeführte Haus in Borkheide verbindet seine Geschichte rund um den Motorflieger Hans Grade mit modernem Hotelbetrieb, Gastronomie sowie Räumen für Feiern und Tagungen. Von Borkheide aus sind Beelitz, Potsdam und Berlin gut erreichbar.</p>',
                        mediaUrl: '/uploads/media/supporter-hotel-fliegerheim.png',
                        mediaAlt: 'Logo des Hotels und Restaurants Fliegerheim',
                        linkUrl: 'https://www.fliegerheim.de/',
                        layout: 'image_right',
                        imageWidthPercent: 30,
                        verticalAlignment: 'center',
                        textAlignment: 'left',
                        imageFit: 'contain',
                    ),
                    new ContentBlock(
                        type: ContentBlockType::ImageText,
                        content: '<h3>THW Ortsverband Berlin-Neukölln</h3><p>Der Ortsverband unterstützt Bevölkerungsschutz und örtliche Gefahrenabwehr mit universellen Bergungsgruppen und spezialisierten Fachgruppen. Zum Technischen Zug gehören der Zugtrupp, zwei Bergungsgruppen sowie Fachgruppen für Ortung, Infrastruktur und Wassergefahren.</p>',
                        mediaUrl: '/uploads/media/supporter-thw-berlin-neukoelln.png',
                        mediaAlt: 'Logo des THW Ortsverbands Berlin-Neukölln',
                        linkUrl: 'https://ov-neukoelln.thw.de/',
                        layout: 'image_left',
                        imageWidthPercent: 30,
                        verticalAlignment: 'center',
                        textAlignment: 'left',
                        imageFit: 'contain',
                    ),
                    new ContentBlock(
                        type: ContentBlockType::ImageText,
                        content: '<h3>HDsports</h3><p>HDsports verbindet jährlich zahlreiche redaktionelle Beiträge mit einem umfangreichen Lauf- und Triathlonkalender. Mehr als 12.000 Veranstaltungen, Bewertungen und eine interaktive Karte unterstützen Sportlerinnen und Sportler bei der Suche nach passenden Events.</p>',
                        mediaUrl: '/uploads/media/supporter-hdsports.png',
                        mediaAlt: 'Logo von HDsports',
                        linkUrl: 'https://www.hdsports.de/',
                        layout: 'image_right',
                        imageWidthPercent: 30,
                        verticalAlignment: 'center',
                        textAlignment: 'left',
                        imageFit: 'contain',
                    ),
                ],
            ],
            [
                'title' => 'Impressum',
                'slug' => 'impressum',
                'navigationLabel' => 'Impressum',
                'position' => 1000,
                'description' => 'Anbieterkennzeichnung des Naturbad Borkheide e.V.',
                'showInNavigation' => false,
                'blocks' => [
                    new ContentBlock(ContentBlockType::Heading, 'Anbieterkennzeichnung'),
                    new ContentBlock(
                        ContentBlockType::RichText,
                        '<p><strong>Naturbad Borkheide e.V.</strong><br>Kirchanger 14<br>14822 Borkheide</p><p>Telefon: (033845) 90941<br>Fax: (033845) 90948<br>E-Mail: <a href="mailto:info@waldbad-borkheide.de">info@waldbad-borkheide.de</a></p><p>Eingetragen beim Amtsgericht Potsdam im Vereinsregister unter VR 3768 P.</p>',
                    ),
                ],
            ],
        ];
    }
}
