<?php

namespace App\Logic\Settings\MailTemplate\Service;

/**
 * Umschließt das aus einer Mailvorlage gerenderte HTML-Fragment (siehe `MailTemplateRenderer`,
 * `MailContentRenderer`) mit einem einfachen, tabellenbasierten Layout im Design des Waldbad
 * Borkheide e.V. (Vereinsfarben, Logo im Kopfbereich). Tabellen statt Flexbox/Grid und ausschließlich
 * inline gesetzte Styles, da HTML-Mails (insb. Outlook) modernes CSS größtenteils ignorieren; eine
 * Breite von 600px ist der verbreitete Kompromiss zwischen Desktop- und Mobil-Postfächern.
 *
 * Reine, von außen konfigurierbare Funktion ohne eigenen Dateizugriff — das Logo (bereits als
 * `data:`-URI) kommt über `EmailLogoProviderInterface` von außen, siehe `NotificationMailer`.
 */
readonly class BrandedEmailLayout
{
    private const string FOREST_900 = '#123f2c';
    private const string FOREST_100 = '#e9f3ed';
    private const string LIME_400 = '#c9dd58';
    private const string SAND_50 = '#fbfaf5';
    private const string WHITE = '#ffffff';
    private const string INK = '#17221d';
    private const string MUTED = '#5c6a62';
    private const string LINE = '#dce5df';
    private const string FONT_STACK = "'Segoe UI', Helvetica, Arial, sans-serif";

    public function wrap(string $subject, string $bodyHtmlFragment, ?string $logoDataUri, string $associationName): string
    {
        $escapedSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $escapedAssociationName = htmlspecialchars($associationName, ENT_QUOTES, 'UTF-8');
        $logoHtml = $logoDataUri !== null
            ? sprintf(
                '<img src="%s" alt="%s" width="120" style="display:block;border:0;outline:none;max-width:120px;height:auto;">',
                htmlspecialchars($logoDataUri, ENT_QUOTES, 'UTF-8'),
                $escapedAssociationName,
            )
            : sprintf(
                '<span style="font-family:%s;font-size:20px;font-weight:bold;color:%s;">%s</span>',
                self::FONT_STACK,
                self::WHITE,
                $escapedAssociationName,
            );

        // Heredoc kann keine Klassenkonstanten interpolieren — daher als lokale Variablen.
        $sand50 = self::SAND_50;
        $white = self::WHITE;
        $line = self::LINE;
        $forest900 = self::FOREST_900;
        $lime400 = self::LIME_400;
        $ink = self::INK;
        $forest100 = self::FOREST_100;
        $muted = self::MUTED;
        $fontStack = self::FONT_STACK;

        return <<<HTML
            <!doctype html>
            <html lang="de">
            <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{$escapedSubject}</title>
            </head>
            <body style="margin:0;padding:0;background-color:{$sand50};">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{$sand50};padding:24px 12px;">
            <tr>
            <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:100%;background-color:{$white};border-radius:16px;overflow:hidden;border:1px solid {$line};">
            <tr>
            <td style="background-color:{$forest900};padding:24px 32px;">
            {$logoHtml}
            </td>
            </tr>
            <tr>
            <td style="height:4px;background-color:{$lime400};line-height:4px;font-size:0;">&nbsp;</td>
            </tr>
            <tr>
            <td style="padding:32px;font-family:{$fontStack};font-size:15px;line-height:1.6;color:{$ink};">
            {$bodyHtmlFragment}
            </td>
            </tr>
            <tr>
            <td style="padding:20px 32px;background-color:{$forest100};font-family:{$fontStack};font-size:12px;line-height:1.5;color:{$muted};border-top:1px solid {$line};">
            {$escapedAssociationName}
            </td>
            </tr>
            </table>
            </td>
            </tr>
            </table>
            </body>
            </html>
            HTML;
    }
}
