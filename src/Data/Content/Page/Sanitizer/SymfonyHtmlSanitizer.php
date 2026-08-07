<?php

namespace App\Data\Content\Page\Sanitizer;

use App\Logic\Content\Page\HtmlSanitizerInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final readonly class SymfonyHtmlSanitizer implements HtmlSanitizerInterface
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowElement('font', ['color', 'size'])
            ->allowElement('span', ['class'])
            ->allowElement('table', ['class'])
            ->allowElement('thead')
            ->allowElement('tbody')
            ->allowElement('tfoot')
            ->allowElement('tr')
            ->allowElement('th', ['colspan', 'rowspan', 'scope'])
            ->allowElement('td', ['colspan', 'rowspan'])
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
            ->allowMediaSchemes(['https', 'http']);

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function sanitize(string $html): string
    {
        return $this->sanitizer->sanitize($html);
    }

    public function sanitizeInline(string $html): string
    {
        return strip_tags(
            $this->sanitizer->sanitize($html),
            '<strong><b><em><i><u><s><a><span><font><br><sub><sup><code>',
        );
    }
}
