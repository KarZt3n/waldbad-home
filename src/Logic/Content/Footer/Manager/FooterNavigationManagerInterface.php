<?php

namespace App\Logic\Content\Footer\Manager;

use App\Logic\Content\Footer\Model\FooterNavigation;

interface FooterNavigationManagerInterface
{
    /** `null`, solange der Footer noch nie gespeichert wurde. */
    public function get(): ?FooterNavigation;

    public function save(FooterNavigation $navigation): FooterNavigation;
}
