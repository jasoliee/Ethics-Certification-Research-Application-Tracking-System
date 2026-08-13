<?php

namespace Tests\Unit;

use App\Enums\ReviewFormType;
use App\Support\ReviewFormCatalog;
use Tests\TestCase;

class ReviewFormCatalogTest extends TestCase
{
    public function test_official_source_page_and_option_mappings_are_stable(): void
    {
        $protocol = ReviewFormCatalog::items(ReviewFormType::Protocol);
        $consent = ReviewFormCatalog::items(ReviewFormType::InformedConsent);

        $this->assertSame([1, 2, 3], ReviewFormCatalog::template(ReviewFormType::Protocol)['source_pages']);
        $this->assertSame([7, 8], ReviewFormCatalog::template(ReviewFormType::InformedConsent)['source_pages']);
        $this->assertSame(['no', 'yes', 'unable_to_assess'], array_keys(ReviewFormCatalog::answers(ReviewFormType::Protocol)));
        $this->assertSame(['yes', 'no'], array_keys(ReviewFormCatalog::answers(ReviewFormType::InformedConsent)));
        $this->assertCount(15, $protocol);
        $this->assertCount(15, $consent);
        $this->assertSame(14, $protocol['protocol_14']['printed_number']);
        $this->assertSame(15, $protocol['protocol_15']['printed_number']);
        $this->assertSame(3, $protocol['protocol_15']['source_page']);
        $this->assertNull($consent['consent_01']['printed_number'] ?? null);
        $this->assertSame(7, $consent['consent_12']['source_page']);
        $this->assertSame(8, $consent['consent_13']['source_page']);
        $this->assertSame(
            hash_file('sha256', ReviewFormCatalog::templatePath()),
            ReviewFormCatalog::TEMPLATE_SHA256,
        );
    }
}
