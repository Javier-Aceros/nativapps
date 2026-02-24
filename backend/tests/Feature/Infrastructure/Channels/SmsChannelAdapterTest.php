<?php

namespace Tests\Feature\Infrastructure\Channels;

use App\Application\DTOs\MessagePayload;
use App\Domain\Enums\Channel;
use App\Domain\Enums\DeliveryStatus;
use App\Infrastructure\Channels\SmsChannelAdapter;
use App\Models\DeliveryLog;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SmsChannelAdapterTest extends TestCase
{
    use RefreshDatabase;

    private const DESTINATION = '+570000000000';

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeLog(): DeliveryLog
    {
        return DeliveryLog::create([
            'message_id' => Message::factory()->create()->id,
            'channel' => Channel::Sms,
            'status' => DeliveryStatus::Pending,
        ]);
    }

    /**
     * Captures the XML string logged during a send() call.
     * Uses Log::listen so the real logger is not replaced.
     */
    private function captureLoggedXml(callable $callback): ?string
    {
        $xml = null;

        Log::listen(function (MessageLogged $event) use (&$xml) {
            if (str_contains($event->message, '[SMS]')) {
                $xml = $event->context['xml'] ?? null;
            }
        });

        $callback();

        return $xml;
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_marks_delivery_log_as_success(): void
    {
        Log::spy();

        $log = $this->makeLog();
        (new SmsChannelAdapter(self::DESTINATION))
            ->send(new MessagePayload('Title', 'Summary', 'Content'), $log);

        $this->assertSame(DeliveryStatus::Success, $log->fresh()->status);
    }

    public function test_xml_contains_correct_destination(): void
    {
        $xml = $this->captureLoggedXml(function () {
            (new SmsChannelAdapter(self::DESTINATION))
                ->send(new MessagePayload('T', 'S', 'C'), $this->makeLog());
        });

        $this->assertStringContainsString(
            '<sms:destination>'.self::DESTINATION.'</sms:destination>',
            $xml
        );
    }

    public function test_xml_uses_summary_as_message_body(): void
    {
        $xml = $this->captureLoggedXml(function () {
            (new SmsChannelAdapter(self::DESTINATION))
                ->send(new MessagePayload('Title', 'Short summary text', 'Long content'), $this->makeLog());
        });

        $this->assertStringContainsString(
            '<sms:message>Short summary text</sms:message>',
            $xml
        );
    }

    public function test_xml_uses_title_as_reference(): void
    {
        $xml = $this->captureLoggedXml(function () {
            (new SmsChannelAdapter(self::DESTINATION))
                ->send(new MessagePayload('My Reference Title', 'Summary', 'Content'), $this->makeLog());
        });

        $this->assertStringContainsString(
            '<sms:reference>My Reference Title</sms:reference>',
            $xml
        );
    }

    public function test_xml_escapes_special_html_characters(): void
    {
        $xml = $this->captureLoggedXml(function () {
            (new SmsChannelAdapter(self::DESTINATION))
                ->send(new MessagePayload('Title <&>', 'Summary & more', 'Content'), $this->makeLog());
        });

        // Verify escaping happened
        $this->assertStringContainsString('Title &lt;&amp;&gt;', $xml);
        $this->assertStringContainsString('Summary &amp; more', $xml);

        // Verify raw characters are NOT present inside tags
        $this->assertStringNotContainsString('<&>', $xml);
    }

    public function test_xml_conforms_to_ultracem_soap_schema(): void
    {
        $xml = $this->captureLoggedXml(function () {
            (new SmsChannelAdapter(self::DESTINATION))
                ->send(new MessagePayload('T', 'S', 'C'), $this->makeLog());
        });

        $this->assertStringContainsString(
            'xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"',
            $xml
        );
        $this->assertStringContainsString(
            'xmlns:sms="http://ultracem.com/sms"',
            $xml
        );
        $this->assertStringContainsString('<sms:SendSmsRequest>', $xml);
    }

    public function test_payload_stored_on_log_uses_sms_reduced_format(): void
    {
        Log::spy();

        $log = $this->makeLog();
        (new SmsChannelAdapter(self::DESTINATION))
            ->send(new MessagePayload('T', 'S', 'Original content must not appear'), $log);

        $stored = $log->fresh()->payload;
        $this->assertArrayHasKey('title', $stored);
        $this->assertArrayHasKey('summary', $stored);
        $this->assertArrayNotHasKey('original_content', $stored);
    }
}
