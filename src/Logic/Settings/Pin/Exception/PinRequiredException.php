<?php

namespace App\Logic\Settings\Pin\Exception;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Eigene Exception-Klasse (statt einer generischen `BusinessRuleViolationException`), damit die
 * Oberfläche sie am `error.code` („pinrequiredexception“, siehe `DomainExceptionSubscriber`)
 * eindeutig erkennt und statt eines einfachen Fehler-Toasts die PIN-Abfrage anzeigt.
 */
class PinRequiredException extends BusinessRuleViolationException
{
}
