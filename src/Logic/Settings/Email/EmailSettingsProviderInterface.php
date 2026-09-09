<?php

namespace App\Logic\Settings\Email;

use App\Logic\Settings\Email\Model\EmailSettings;

interface EmailSettingsProviderInterface
{
    /**
     * Liefert immer einen Datensatz, auch wenn noch nie gespeichert wurde (dann unkonfiguriert) —
     * Aufrufer müssen den „noch nicht angelegt“-Fall nicht gesondert behandeln.
     */
    public function get(): EmailSettings;
}
