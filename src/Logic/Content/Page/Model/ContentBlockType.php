<?php

namespace App\Logic\Content\Page\Model;

enum ContentBlockType: string
{
    case Heading = 'heading';
    case RichText = 'rich_text';
    case Image = 'image';
    case ImageText = 'image_text';
    case FeatureCollection = 'feature_collection';
    case Alert = 'alert';
    case CallToAction = 'call_to_action';
    case CustomHtml = 'custom_html';
    case EmbeddedPage = 'embedded_page';
    case PageTeaser = 'page_teaser';
    case Event = 'event';
    case EventReference = 'event_reference';
    case Extension = 'extension';
}
