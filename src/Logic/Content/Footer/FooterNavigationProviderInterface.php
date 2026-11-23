<?php

namespace App\Logic\Content\Footer;

use App\Logic\Content\Footer\Model\FooterNavigation;

interface FooterNavigationProviderInterface
{
    /** `null`, solange der Footer noch nie gespeichert wurde. */
    public function get(): ?FooterNavigation;
}
