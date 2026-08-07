<?php

namespace App\Logic\Content\Page\Model;

enum PageStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Published = 'published';
    case Archived = 'archived';
}
