<?php

namespace App\Data\Membership\Application\Adapter;

use App\Logic\Content\Extension\Model\PageExtension;
use App\Logic\Content\Extension\Query\GetPageExtensionTextsQuery;
use App\Logic\Membership\Application\MembershipDeclarationProviderInterface;
use App\Logic\Membership\Application\Model\MembershipDeclaration;

/** Adapter auf die Erweiterungstexte des Moduls Content (Vorbild: `MembershipSaunaGuestDirectory`). */
readonly class ContentMembershipDeclarationProvider implements MembershipDeclarationProviderInterface
{
    public function __construct(private GetPageExtensionTextsQuery $texts)
    {
    }

    public function current(): MembershipDeclaration
    {
        $response = $this->texts->execute(PageExtension::MembershipApplication);
        $text = static fn (string $key): string => $response->texts[$key] ?? throw new \LogicException(sprintf('Textfeld "%s" fehlt im Mitgliedsantrag.', $key));

        return new MembershipDeclaration(
            version: $response->consentVersion ?? throw new \LogicException('Der Mitgliedsantrag hat keine Zustimmungstexte.'),
            terms: $text('consentTerms'),
            privacy: $text('consentPrivacy'),
            sepa: $text('consentSepa'),
            emailConsent: $text('consentEmail'),
        );
    }
}
