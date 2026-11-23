<?php

namespace App\Logic\Content\Footer;

use App\Logic\Content\Footer\Model\FooterNavigation;

interface FooterNavigationProcessorInterface
{
    public function save(FooterNavigation $navigation): FooterNavigation;
}
