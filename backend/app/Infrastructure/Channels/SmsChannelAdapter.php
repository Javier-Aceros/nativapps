<?php

namespace App\Infrastructure\Channels;

use App\Application\DTOs\MessagePayload;
use App\Contracts\NotificationProvider;
use App\Domain\Enums\Channel;
use App\Models\DeliveryLog;
use Illuminate\Support\Facades\Log;

/**
 * Simulates a SOAP SMS dispatch.
 * Builds the XML envelope and logs it to laravel.log as evidence.
 */
class SmsChannelAdapter implements NotificationProvider
{
    public function __construct(
        private readonly string $destination,
    ) {}

    public function supports(): Channel
    {
        return Channel::Sms;
    }

    public function send(MessagePayload $payload, DeliveryLog $log): void
    {
        $xml = $this->buildSoapEnvelope($payload);

        $log->update(['payload' => $payload->toSmsArray()]);

        Log::info('[SMS] Simulated SOAP dispatch', [
            'destination' => $this->destination,
            'xml' => $xml,
        ]);

        $log->markSuccess('SMS SOAP envelope simulated — XML logged to laravel.log.');
    }

    private function buildSoapEnvelope(MessagePayload $payload): string
    {
        $destination = htmlspecialchars($this->destination, ENT_XML1 | ENT_QUOTES);
        $message = htmlspecialchars($payload->summary, ENT_XML1 | ENT_QUOTES);
        $reference = htmlspecialchars($payload->title, ENT_XML1 | ENT_QUOTES);

        return <<<XML
<soapenv:Envelope xmlns:soapenv="[http://schemas.xmlsoap.org/soap/envelope/](http://schemas.xmlsoap.org/soap/envelope/)" xmlns:sms="[http://ultracem.com/sms](http://ultracem.com/sms)">
  <soapenv:Header/>
  <soapenv:Body>
    <sms:SendSmsRequest>
      <sms:destination>{$destination}</sms:destination>
      <sms:message>{$message}</sms:message>
      <sms:reference>{$reference}</sms:reference>
    </sms:SendSmsRequest>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }
}
