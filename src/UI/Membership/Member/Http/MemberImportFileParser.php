<?php

namespace App\UI\Membership\Member\Http;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Liest eine hochgeladene Import-Datei (CSV/JSON/XML) rein syntaktisch in eine Liste
 * assoziativer Zeilen ein. Die fachliche Prüfung der Werte übernimmt anschließend
 * `MemberRequestMapper::fromImportRow()`.
 */
readonly class MemberImportFileParser
{
    /**
     * @return list<array<string, mixed>>
     */
    public function parse(string $content, string $format): array
    {
        return match ($format) {
            'csv' => $this->parseCsv($content),
            'json' => $this->parseJson($content),
            'xml' => $this->parseXml($content),
            default => throw new BadRequestHttpException('Das Import-Format wird nicht unterstützt.'),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseCsv(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if ($lines === false || $lines === [] || $lines === ['']) {
            return [];
        }
        $delimiter = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';
        $header = str_getcsv($lines[0], $delimiter, '"', '\\');
        $rows = [];
        foreach (array_slice($lines, 1) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line, $delimiter, '"', '\\');
            $row = [];
            foreach ($header as $index => $key) {
                $row[(string) $key] = $values[$index] ?? null;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseJson(string $content): array
    {
        try {
            $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new BadRequestHttpException('Die JSON-Datei konnte nicht gelesen werden.', previous: $exception);
        }
        if (is_array($decoded) && array_is_list($decoded)) {
            $rows = $decoded;
        } elseif (is_array($decoded) && is_array($decoded['members'] ?? null)) {
            $rows = $decoded['members'];
        } else {
            throw new BadRequestHttpException('Die JSON-Datei muss eine Liste von Mitgliedern (optional unter "members") enthalten.');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new BadRequestHttpException('Jede Zeile muss als Objekt übermittelt werden.');
            }
        }

        /** @var list<array<string, mixed>> $rows */
        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseXml(string $content): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($content, options: LIBXML_NOCDATA);
        libxml_use_internal_errors($previous);
        if ($document === false) {
            throw new BadRequestHttpException('Die XML-Datei konnte nicht gelesen werden.');
        }

        $rows = [];
        foreach ($document->member ?? [] as $memberNode) {
            $row = [];
            foreach ($memberNode as $field => $value) {
                if ($value->count() > 0) {
                    // Verschachtelte Elemente (z. B. exportierte Bemerkungen) sind für den Import
                    // nicht relevant und werden übersprungen.
                    continue;
                }
                $row[(string) $field] = (string) $value;
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
