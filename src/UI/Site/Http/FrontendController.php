<?php

namespace App\UI\Site\Http;

use App\Logic\Content\Branding\Query\GetSiteBrandingQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FrontendController extends AbstractController
{
    public function __construct(private readonly GetSiteBrandingQuery $branding)
    {
    }

    #[Route('/', name: 'public_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->renderPublic('startseite');
    }

    #[Route('/seite/{slug}', name: 'public_page', requirements: ['slug' => '.+'], methods: ['GET'])]
    public function page(string $slug): Response
    {
        return $this->renderPublic($slug);
    }

    /**
     * „Meine Mitgliedschaft“ (siehe `PublicMemberAccessController`, `RoutingMemberAccessLinkBuilder`)
     * — bewusst eine eigene, feste Route statt einer CMS-Seite (`/seite/{slug}`): die Ansicht hängt
     * nicht von redaktionell gepflegtem Inhalt ab und soll unter einer stabilen, per Mail
     * verschickbaren URL erreichbar sein, ohne dass dafür erst eine Seite mit passendem Slug
     * angelegt werden muss.
     */
    #[Route('/meine-mitgliedschaft', name: 'public_member_access', methods: ['GET'])]
    public function memberAccess(): Response
    {
        return $this->renderPublic('meine-mitgliedschaft');
    }

    /**
     * Bestätigungsseite für die E-Mail-Einwilligung (siehe `PublicEmailConsentController`,
     * `MemberEmailConsentLinkBuilder`) — aus demselben Grund wie `memberAccess()` eine eigene, feste
     * Route statt einer CMS-Seite.
     */
    #[Route('/e-mail-einwilligung', name: 'public_email_consent', methods: ['GET'])]
    public function emailConsent(): Response
    {
        return $this->renderPublic('e-mail-einwilligung');
    }

    /**
     * „Sauna-Anfrage annehmen/ablehnen“ aus der Benachrichtigungsmail (siehe `PublicSaunaBookingApprovalController`,
     * `SaunaBookingApprovalLinkBuilder`) — ohne Anmeldung, nur mit dem Token aus dem Link.
     */
    #[Route('/sauna-anfrage', name: 'public_sauna_booking_approval', methods: ['GET'])]
    public function saunaBookingApproval(): Response
    {
        return $this->renderPublic('sauna-anfrage');
    }

    #[Route('/admin/{path}', name: 'admin_app', requirements: ['path' => '.*'], defaults: ['path' => ''], methods: ['GET'])]
    public function admin(): Response
    {
        return $this->render('site/admin.html.twig');
    }

    /**
     * Das Logo kommt direkt mit der HTML-Seite, damit die Kopfzeile ohne zusätzliche Anfrage und ohne
     * Flackern das gepflegte Logo zeigt (siehe `buildSiteHeader`).
     */
    private function renderPublic(string $slug): Response
    {
        return $this->render('site/public.html.twig', ['slug' => $slug, 'logoUrl' => $this->branding->execute()->logoUrl]);
    }
}
