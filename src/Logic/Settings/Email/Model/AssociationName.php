<?php

namespace App\Logic\Settings\Email\Model;

/**
 * Der Vereinsname, wie er in automatisch versendeten E-Mails auftaucht — als `{{vereinsname}}`-
 * Platzhalterwert (siehe `ReleaseMembershipApplicationUseCase`) und im Fußzeilen-/Kopfbereich jeder
 * gestalteten HTML-Mail (siehe `NotificationMailer`, `PreviewMailTemplateUseCase`). An einer Stelle
 * gepflegt, statt an jeder Verwendungsstelle einzeln wörtlich hinterlegt.
 */
final class AssociationName
{
    public const string CURRENT = 'Naturbad Borkheide e.V.';

    private function __construct()
    {
    }
}
