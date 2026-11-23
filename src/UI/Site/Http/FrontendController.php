<?php

namespace App\UI\Site\Http;

use App\Logic\Content\Branding\Query\GetSiteBrandingQuery;
use App\Logic\Content\Page\Query\GetPublicPageMetadataQuery;
use App\Logic\Content\Page\Query\GetPublishedPageQuery;
use App\UI\Content\Page\Http\PublicPageResponseFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FrontendController extends AbstractController
{
    public function __construct(
        private readonly GetSiteBrandingQuery $branding,
        private readonly GetPublicPageMetadataQuery $metadata,
        private readonly PublicSiteChromeResponseFactory $chrome,
        private readonly GetPublishedPageQuery $publishedPage,
        private readonly PublicPageResponseFactory $publicPage,
    ) {
    }

    #[Route('/', name: 'public_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->renderCmsPage('startseite', $this->generateUrl('public_home', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    #[Route('/seite/{slug}', name: 'public_page', requirements: ['slug' => '.+'], methods: ['GET'])]
    public function page(string $slug): Response
    {
        // `/seite/startseite` zeigt dieselbe Seite wie `/` und verweist deshalb auf diese als Canonical-URL.
        return $this->renderCmsPage($slug, $slug === 'startseite'
            ? $this->generateUrl('public_home', [], UrlGeneratorInterface::ABSOLUTE_URL)
            : $this->generateUrl('public_page', ['slug' => $slug], UrlGeneratorInterface::ABSOLUTE_URL));
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
        return $this->renderPublic('meine-mitgliedschaft', 'Meine Mitgliedschaft');
    }

    /**
     * Bestätigungsseite für die E-Mail-Einwilligung (siehe `PublicEmailConsentController`,
     * `MemberEmailConsentLinkBuilder`) — aus demselben Grund wie `memberAccess()` eine eigene, feste
     * Route statt einer CMS-Seite.
     */
    #[Route('/e-mail-einwilligung', name: 'public_email_consent', methods: ['GET'])]
    public function emailConsent(): Response
    {
        return $this->renderPublic('e-mail-einwilligung', 'E-Mail-Einwilligung');
    }

    /**
     * „Sauna-Anfrage annehmen/ablehnen“ aus der Benachrichtigungsmail (siehe `PublicSaunaBookingApprovalController`,
     * `SaunaBookingApprovalLinkBuilder`) — ohne Anmeldung, nur mit dem Token aus dem Link.
     */
    #[Route('/sauna-anfrage', name: 'public_sauna_booking_approval', methods: ['GET'])]
    public function saunaBookingApproval(): Response
    {
        return $this->renderPublic('sauna-anfrage', 'Sauna-Anfrage');
    }

    #[Route('/admin/{path}', name: 'admin_app', requirements: ['path' => '.*'], defaults: ['path' => ''], methods: ['GET'])]
    public function admin(): Response
    {
        return $this->render('site/admin.html.twig');
    }

    /**
     * Titel, Beschreibung und Canonical-URL stehen schon im ausgelieferten HTML, damit Suchmaschinen und
     * Link-Vorschauen (Messenger, soziale Netzwerke) sie ohne JavaScript sehen. Unbekannte, nicht
     * veröffentlichte oder ausgeblendete Seiten antworten mit 404 statt mit einer leeren 200-Seite.
     */
    private function renderCmsPage(string $slug, string $canonicalUrl): Response
    {
        $metadata = $this->metadata->execute($slug);
        if ($metadata === null) {
            return $this->renderPublic($slug, 'Seite nicht gefunden', status: Response::HTTP_NOT_FOUND);
        }

        return $this->renderPublic(
            $slug,
            $metadata->title,
            $metadata->description,
            $canonicalUrl,
            indexable: true,
            page: $this->publicPage->pageData($this->publishedPage->execute($slug)),
        );
    }

    /**
     * Das Logo kommt direkt mit der HTML-Seite, damit die Kopfzeile ohne zusätzliche Anfrage und ohne
     * Flackern das gepflegte Logo zeigt (siehe `buildSiteHeader`); ebenso Navigation, Footer und
     * Social-Media-Leiste, damit keine eigenen API-Anfragen nötig sind — auf dem Prod-Hosting kostet
     * jede zusätzliche Anfrage einen eigenen PHP-Prozess. Persönliche Sonderseiten (Token-Links)
     * und Fehlerseiten werden nicht indexiert.
     *
     * @param array<string, mixed>|null $page Seitendaten wie `GET /api/public/v1/pages/{slug}`
     */
    private function renderPublic(
        string $slug,
        string $title,
        ?string $description = null,
        ?string $canonicalUrl = null,
        bool $indexable = false,
        int $status = Response::HTTP_OK,
        ?array $page = null,
    ): Response {
        return $this->render('site/public.html.twig', [
            'slug' => $slug,
            'logoUrl' => $this->branding->execute()->logoUrl,
            'metaTitle' => $title,
            'metaDescription' => $description,
            'canonicalUrl' => $canonicalUrl,
            'indexable' => $indexable,
            // JSON_HEX_* verhindert, dass Inhalte das umgebende `<script>` beenden können.
            'siteChrome' => json_encode([
                'navigation' => $this->chrome->navigation(),
                'footerLinks' => $this->chrome->footerLinks(),
                'socialMediaLinks' => $this->chrome->socialMediaLinks(),
                'page' => $page,
            ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE),
        ], new Response(status: $status));
    }
}
