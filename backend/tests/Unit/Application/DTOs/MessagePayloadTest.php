<?php

namespace Tests\Unit\Application\DTOs;

use App\Application\DTOs\MessagePayload;
use PHPUnit\Framework\TestCase;

class MessagePayloadTest extends TestCase
{
    private MessagePayload $payload;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payload = new MessagePayload(
            title: 'Breaking News',
            summary: 'Short executive summary',
            originalContent: 'Full article content goes here.',
        );
    }

    public function test_to_array_includes_all_three_fields(): void
    {
        $array = $this->payload->toArray();

        $this->assertSame('Breaking News', $array['title']);
        $this->assertSame('Short executive summary', $array['summary']);
        $this->assertSame('Full article content goes here.', $array['original_content']);
    }

    public function test_to_sms_array_omits_original_content(): void
    {
        $array = $this->payload->toSmsArray();

        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('summary', $array);
        $this->assertArrayNotHasKey('original_content', $array);
    }

    public function test_properties_are_accessible_and_match_constructor_values(): void
    {
        $this->assertSame('Breaking News', $this->payload->title);
        $this->assertSame('Short executive summary', $this->payload->summary);
        $this->assertSame('Full article content goes here.', $this->payload->originalContent);
    }

    public function test_readonly_property_cannot_be_mutated(): void
    {
        $this->expectException(\Error::class);

        /** @phpstan-ignore-next-line */
        $this->payload->title = 'modified'; // @phpstan-ignore-line
    }
}
