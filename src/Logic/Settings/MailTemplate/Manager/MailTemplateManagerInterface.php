<?php

namespace App\Logic\Settings\MailTemplate\Manager;

use App\Logic\Settings\MailTemplate\Model\MailTemplate;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;

interface MailTemplateManagerInterface
{
    /**
     * Liefert immer eine Vorlage, auch wenn für $key noch nie gespeichert wurde (dann mit dem
     * Standardtext aus `MailTemplateKey`) — Aufrufer müssen den „noch nicht angelegt"-Fall nicht
     * gesondert behandeln.
     */
    public function resolve(MailTemplateKey $key): MailTemplate;

    /**
     * @return list<MailTemplate> je `MailTemplateKey::cases()` genau eine, wie bei `resolve()` mit
     *         Standardtext aufgefüllt
     */
    public function resolveAll(): array;

    public function save(MailTemplate $template): MailTemplate;
}
