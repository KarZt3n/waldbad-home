<?php

namespace App\UI\Membership\Member\Cli;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class SageGsXmlParser
{
    /** @return list<array<string, string>> */
    public function parse(string $content): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument();
            if (!$document->loadXML($content, LIBXML_NONET) || $document->doctype !== null
                || $document->documentElement?->tagName !== 'DATAPACKET') {
                throw new BadRequestHttpException('Ungültiges Sage-DATAPACKET; DTDs sind nicht erlaubt.');
            }
            $nodes = (new \DOMXPath($document))->query('/DATAPACKET/ROWDATA/ROW');
            if ($nodes === false || $nodes->length === 0) {
                throw new BadRequestHttpException('Das Sage-DATAPACKET enthält keine Datensätze.');
            }
            $rows = [];
            foreach ($nodes as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }
                $row = [];
                foreach ($node->attributes as $attribute) {
                    $row[$attribute->name] = trim($attribute->value);
                }
                $rows[] = $row;
            }

            return $rows;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
