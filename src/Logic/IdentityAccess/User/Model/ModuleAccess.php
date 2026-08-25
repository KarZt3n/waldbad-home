<?php

namespace App\Logic\IdentityAccess\User\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

readonly class ModuleAccess
{
    public function __construct(
        public CmsModule $module,
        public ModuleRole $role,
    ) {
        if ($this->module !== CmsModule::Pages && !in_array($this->role, [ModuleRole::Viewer, ModuleRole::Editor], true)) {
            throw new BusinessRuleViolationException(sprintf(
                'Das Modul "%s" unterstützt nur die Rollen Viewer und Editor.',
                $this->module->value,
            ));
        }
    }
}
