<?php

namespace App\Logic\Settings\Email\Model;

/**
 * Versandweg einer Mail. `Express` ist für zeitkritische Mails gedacht, auf die jemand gerade wartet
 * (Anmelde-/Zugangslinks) und läuft über einen schnelleren Versanddienst mit begrenztem Kontingent;
 * alles andere geht `Standard`. Welcher Dienst dahintersteht, legt `config/packages/mailer.yaml` fest —
 * der Wert ist der Name des dortigen Transports.
 *
 * Derzeit nutzt keine Mail `Express`: Die Domain ist bei Brevo noch nicht authentifiziert (SPF/DKIM),
 * Empfänger wie T-Online stellen solche Mails zurück. Bis dahin gehen auch Anmelde- und Zugangslinks
 * über `Standard`.
 */
enum MailDelivery: string
{
    case Standard = 'main';
    case Express = 'express';
}
