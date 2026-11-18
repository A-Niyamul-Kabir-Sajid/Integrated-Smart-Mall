<?php

namespace App\Modules\ChatbotRag\Enums;

/**
 * KNOWLEDGE_DOCUMENT.source_kind. Mall guides, FAQs and internal notes are written by mall
 * administrators; shop profiles, product listings and the floor guide are captured from the mall's
 * public records and cannot be edited by hand.
 */
enum SourceKind: string
{
    case MallGuide = 'mall_guide';
    case ShopProfile = 'shop_profile';
    case ProductListing = 'product_listing';
    case Faq = 'faq';
    case AdminReport = 'admin_report';

    /**
     * The kinds a mall administrator may write.
     *
     * @return list<self>
     */
    public static function writable(): array
    {
        return [self::Faq, self::MallGuide, self::AdminReport];
    }

    /**
     * Internal notes are never public, whatever the form sent.
     */
    public function isAlwaysRestricted(): bool
    {
        return $this === self::AdminReport;
    }

    /**
     * The English label, translated in views with __().
     */
    public function label(): string
    {
        return match ($this) {
            self::MallGuide => 'Mall guide or policy',
            self::ShopProfile => 'Shop profile',
            self::ProductListing => 'Product offer',
            self::Faq => 'Frequently asked questions',
            self::AdminReport => 'Internal note',
        };
    }
}
