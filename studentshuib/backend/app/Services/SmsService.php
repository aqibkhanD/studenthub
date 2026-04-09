<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SSL Wireless SMS Gateway (Bangladesh)
 * Docs: https://sms.sslwireless.com
 *
 * Swap the send() implementation for any other SMS provider
 * without changing the rest of the codebase.
 */
class SmsService
{
    private string $apiUrl;
    private string $apiKey;
    private string $senderId;

    public function __construct()
    {
        $this->apiUrl   = config('services.sms.url',       env('SMS_GATEWAY_URL', ''));
        $this->apiKey   = config('services.sms.key',       env('SMS_GATEWAY_KEY', ''));
        $this->senderId = config('services.sms.sender_id', env('SMS_SENDER_ID', 'DIUSMS'));
    }

    /**
     * Send an SMS message.
     *
     * @param string $phone  BD mobile number: +8801XXXXXXXXX or 01XXXXXXXXX
     * @param string $message
     * @throws \Exception on failure
     */
    public function send(string $phone, string $message): bool
    {
        // Normalise phone number to 01XXXXXXXXX format for SSL Wireless
        $phone = $this->normalise($phone);

        if (empty($this->apiKey)) {
            Log::warning('SmsService: SMS_GATEWAY_KEY not configured. Message not sent.', [
                'phone'   => $phone,
                'message' => substr($message, 0, 50) . '...',
            ]);
            return false;
        }

        // Truncate to 160 chars (1 SMS credit); longer messages cost more
        $message = mb_substr($message, 0, 160);

        $response = Http::timeout(15)->post($this->apiUrl, [
            'api_token' => $this->apiKey,
            'sid'       => $this->senderId,
            'msisdn'    => $phone,
            'sms'       => $message,
            'csms_id'   => uniqid('DIU_', true),
        ]);

        if (!$response->successful()) {
            throw new \Exception("SMS gateway returned HTTP {$response->status()}: {$response->body()}");
        }

        $body = $response->json();

        // SSL Wireless returns {"status":"ACCEPTED"} on success
        if (($body['status'] ?? '') !== 'ACCEPTED') {
            throw new \Exception("SMS gateway rejected message: " . json_encode($body));
        }

        Log::info('SMS sent', ['phone' => $phone, 'status' => $body['status']]);

        return true;
    }

    /**
     * Normalise Bangladeshi mobile numbers to 01XXXXXXXXX
     */
    private function normalise(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone); // strip non-digits

        if (str_starts_with($phone, '880')) {
            $phone = '0' . substr($phone, 3); // +8801... → 01...
        }

        return $phone;
    }
}
