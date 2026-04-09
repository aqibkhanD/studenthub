<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SSL Wireless SMS Gateway integration.
 *
 * Docs: https://www.sslwireless.com/smsapi/
 * Env vars required:
 *   SMS_API_TOKEN   — API token from SSL Wireless dashboard
 *   SMS_SID         — Sender ID registered with BTRC (e.g. "DIU-SVC")
 *   SMS_ENDPOINT    — e.g. https://sms.sslwireless.com/pushapi/dynamic/server.php
 */
class SmsService
{
    private string $endpoint;
    private string $apiToken;
    private string $sid;

    public function __construct()
    {
        $this->endpoint = config('notifications.sms.endpoint');
        $this->apiToken = config('notifications.sms.api_token');
        $this->sid      = config('notifications.sms.sid');
    }

    /**
     * Send a single SMS.
     *
     * @param  string $phone   Bangladesh mobile number (+880XXXXXXXXXX or 01XXXXXXXXX)
     * @param  string $message Max 160 chars for single SMS, 306 for concatenated
     * @return array{success: bool, message_id: ?string, error: ?string}
     */
    public function send(string $phone, string $message): array
    {
        $phone = $this->normalisePhone($phone);

        if (app()->isLocal() || app()->runningUnitTests()) {
            Log::channel('sms')->info('[SMS SANDBOX]', compact('phone', 'message'));
            return ['success' => true, 'message_id' => 'sandbox-' . uniqid(), 'error' => null];
        }

        try {
            $csmsId = 'DIU-' . now()->format('ymdHis') . '-' . random_int(100, 999);

            $response = Http::timeout(10)->post($this->endpoint, [
                'api_token' => $this->apiToken,
                'sid'       => $this->sid,
                'msisdn'    => $phone,
                'sms'       => $message,
                'csms_id'   => $csmsId,
            ]);

            if ($response->successful()) {
                $body = $response->json();
                // SSL Wireless returns {"status":"SUCCESS","statuscode":"1011","csms_id":"..."}
                if (isset($body['status']) && $body['status'] === 'SUCCESS') {
                    return ['success' => true, 'message_id' => $csmsId, 'error' => null];
                }
                return ['success' => false, 'message_id' => null, 'error' => $body['status'] ?? 'Unknown gateway error'];
            }

            return ['success' => false, 'message_id' => null, 'error' => 'HTTP ' . $response->status()];
        } catch (\Throwable $e) {
            Log::error('[SmsService] Send failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            return ['success' => false, 'message_id' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Normalise a Bangladesh mobile number to 880XXXXXXXXXX format.
     */
    public function normalisePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '880')) {
            return $digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '88' . $digits;
        }
        return $digits;
    }

    /**
     * Build a short, SMS-safe status update message for a student.
     */
    public static function studentStatusMessage(string $studentName, string $ref, string $statusLabel, string $portalUrl): string
    {
        return "DIU Student Services: Dear {$studentName}, your request {$ref} has been updated to \"{$statusLabel}\". Log in to check: {$portalUrl}";
    }

    /**
     * Build an urgent action-required SMS.
     */
    public static function actionRequiredMessage(string $studentName, string $ref, string $deadline, string $portalUrl): string
    {
        return "DIU Student Services: ACTION REQUIRED — your form {$ref} was returned for changes. Deadline: {$deadline}. Log in: {$portalUrl}";
    }

    /**
     * Build an SLA warning SMS for admins.
     */
    public static function slaWarningMessage(string $ref, string $timeLeft, string $dashboardUrl): string
    {
        return "DIU Admin Alert: Submission {$ref} SLA expires in {$timeLeft}. Review now: {$dashboardUrl}";
    }
}
