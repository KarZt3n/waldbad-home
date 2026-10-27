<?php

namespace App\Logic\Settings\Email\Model;

/**
 * Versandweg einer Mail. `Express` ist für zeitkritische Mails gedacht, auf die jemand gerade wartet
 * (Anmelde-/Zugangslinks) und läuft über einen schnelleren Versanddienst mit begrenztem Kontingent;
 * alles andere geht `Standard`. Welcher Dienst dahintersteht, legt `config/packages/mailer.yaml` fest —
 * der Wert ist der Name des dortigen Transports.
 */
enum MailDelivery: string
{
    case Standard = 'main';
    case Express = 'express';
}
