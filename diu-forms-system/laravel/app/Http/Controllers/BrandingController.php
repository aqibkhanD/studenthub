<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBrandingRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles branding and portal configuration (super_admin only).
 *
 * Routes (all under middleware ['auth:sanctum', 'role:super_admin']):
 *   GET    /admin/settings/branding           → index()
 *   PUT    /admin/settings/branding           → update()
 *   POST   /admin/settings/branding/logo      → uploadLogo()
 *   DELETE /admin/settings/branding/logo      → deleteLogo()
 */
class BrandingController extends Controller
{
    // Keys stored in the `settings` table under the 'branding' / 'features' groups
    private const BRANDING_KEYS = [
        'university_name',
        'university_short_name',
        'portal_title',
        'contact_email',
        'contact_phone',
        'website_url',
        'address',
        'primary_color',
    ];

    private const FEATURE_KEYS = [
        'feature_self_registration',
        'feature_anonymous_complaints',
        'feature_sms_notifications',
        'feature_certificate_verify',
        'feature_maintenance_mode',
    ];

    private const SMS_KEYS = [
        'sms_api_token',
        'sms_sid',
        'sms_sender_id',
    ];

    // ------------------------------------------------------------------
    // GET /admin/settings/branding
    // ------------------------------------------------------------------

    public function index(): JsonResponse
    {
        $branding  = $this->resolveBranding();
        $features  = $this->resolveFeatures();
        $sms       = $this->resolveSms();

        return response()->json([
            'branding'  => $branding,
            'features'  => $features,
            'sms'       => $sms,
            'logo_url'  => $this->logoUrl(),
        ]);
    }

    // ------------------------------------------------------------------
    // PUT /admin/settings/branding
    // ------------------------------------------------------------------

    public function update(UpdateBrandingRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Separate into groups for cleaner DB organisation
        $brandingData = array_intersect_key($data, array_flip(self::BRANDING_KEYS));
        $featureData  = array_intersect_key($data, array_flip(self::FEATURE_KEYS));
        $smsData      = array_intersect_key($data, array_flip(self::SMS_KEYS));

        if (!empty($brandingData)) {
            Setting::setMany($brandingData, 'branding');
        }

        if (!empty($featureData)) {
            // Normalise to '1' / '0' strings for consistent storage
            $normalised = array_map(fn($v) => $v ? '1' : '0', $featureData);
            Setting::setMany($normalised, 'features');
        }

        if (!empty($smsData)) {
            Setting::setMany($smsData, 'sms');
        }

        return response()->json([
            'message'  => 'Settings saved successfully.',
            'branding' => $this->resolveBranding(),
            'features' => $this->resolveFeatures(),
            'sms'      => $this->resolveSms(),
        ]);
    }

    // ------------------------------------------------------------------
    // POST /admin/settings/branding/logo
    // ------------------------------------------------------------------

    /**
     * Accept a logo file upload (JPEG/PNG/SVG, max 2 MB).
     * Stores at storage/app/public/branding/logo.{ext}
     * and updates the `branding_logo_path` setting.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,svg',
                'max:2048', // 2 MB
            ],
        ]);

        // Delete previous logo if one exists
        $existing = Setting::get('branding_logo_path');
        if ($existing && Storage::disk('public')->exists($existing)) {
            Storage::disk('public')->delete($existing);
        }

        $file      = $request->file('logo');
        $ext       = $file->getClientOriginalExtension();
        $filename  = 'branding/logo.' . strtolower($ext);

        // store() preserves the exact path we specify
        Storage::disk('public')->putFileAs(
            'branding',
            $file,
            'logo.' . strtolower($ext)
        );

        Setting::set('branding_logo_path', $filename);

        return response()->json([
            'message'  => 'Logo uploaded successfully.',
            'logo_url' => $this->logoUrl(),
        ]);
    }

    // ------------------------------------------------------------------
    // DELETE /admin/settings/branding/logo
    // ------------------------------------------------------------------

    public function deleteLogo(): JsonResponse
    {
        $path = Setting::get('branding_logo_path');

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        Setting::set('branding_logo_path', '');

        return response()->json([
            'message'  => 'Logo removed.',
            'logo_url' => null,
        ]);
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    private function resolveBranding(): array
    {
        $defaults = [
            'university_name'       => 'Daffodil International University',
            'university_short_name' => 'DIU',
            'portal_title'          => 'Student Services Portal',
            'contact_email'         => '',
            'contact_phone'         => '',
            'website_url'           => 'https://daffodilvarsity.edu.bd',
            'address'               => '',
            'primary_color'         => '#0D2B4E',
        ];

        $stored = [];
        foreach (self::BRANDING_KEYS as $key) {
            $stored[$key] = Setting::get($key, $defaults[$key] ?? '');
        }

        return $stored;
    }

    private function resolveFeatures(): array
    {
        $defaults = [
            'feature_self_registration'    => false,
            'feature_anonymous_complaints' => true,
            'feature_sms_notifications'    => true,
            'feature_certificate_verify'   => true,
            'feature_maintenance_mode'     => false,
        ];

        $features = [];
        foreach (self::FEATURE_KEYS as $key) {
            $raw = Setting::get($key, null);
            $features[$key] = $raw === null
                ? ($defaults[$key] ?? false)
                : (bool) $raw;
        }

        return $features;
    }

    private function resolveSms(): array
    {
        return [
            'sms_api_token'  => Setting::get('sms_api_token', ''),
            'sms_sid'        => Setting::get('sms_sid', ''),
            'sms_sender_id'  => Setting::get('sms_sender_id', 'DIU'),
        ];
    }

    private function logoUrl(): ?string
    {
        $path = Setting::get('branding_logo_path', '');
        if (!$path) {
            return null;
        }

        return Storage::disk('public')->exists($path)
            ? Storage::disk('public')->url($path)
            : null;
    }
}
