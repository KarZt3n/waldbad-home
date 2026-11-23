<?php

namespace App\UI\Site\Http;

use App\Logic\Content\Page\Dto\SitemapPageResponse;
use App\Logic\Content\Page\Query\ListSitemapPagesQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * `robots.txt` und `sitemap.xml` für Suchmaschinen, jeweils mit der Adresse, unter der die Website
 * gerade aufgerufen wird. `/api/public` bleibt bewusst erlaubt: Suchmaschinen rendern die Seiten
 * mit JavaScript und laden die Inhalte von dort.
 */
readonly class SeoController
{
    public function __construct(private UrlGeneratorInterface $urls)
    {
    }

    #[Route('/robots.txt', name: 'seo_robots', methods: ['GET'])]
    public function robots(): Response
    {
        $sitemap = $this->urls->generate('seo_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return new Response(implode("\n", [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /api/admin/',
            'Disallow: /api/auth/',
            '',
            'Sitemap: '.$sitemap,
            '',
        ]), headers: ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=86400']);
    }

    #[Route('/sitemap.xml', name: 'seo_sitemap', methods: ['GET'])]
    public function sitemap(ListSitemapPagesQuery $query): Response
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $urlset = $document->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset');
        $document->appendChild($urlset);
        foreach ($query->execute() as $page) {
            $url = $document->createElement('url');
            $loc = $document->createElement('loc');
            $loc->textContent = $this->pageUrl($page);
            $lastModified = $document->createElement('lastmod');
            $lastModified->textContent = $page->lastModified->format(\DateTimeInterface::ATOM);
            $url->append($loc, $lastModified);
            $urlset->appendChild($url);
        }

        return new Response((string) $document->saveXML(), headers: ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    private function pageUrl(SitemapPageResponse $page): string
    {
        return $page->slug === 'startseite'
            ? $this->urls->generate('public_home', [], UrlGeneratorInterface::ABSOLUTE_URL)
            : $this->urls->generate('public_page', ['slug' => $page->slug], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
