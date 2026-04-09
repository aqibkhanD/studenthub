<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('group', 50)->default('general');
            $table->timestamps();

            $table->index('group');
        });

        // Seed default settings
        $defaults = [
            // Branding
            ['key' => 'university_name',       'value' => 'Daffodil International University', 'group' => 'branding'],
            ['key' => 'university_short_name',  'value' => 'DIU',                                'group' => 'branding'],
            ['key' => 'portal_title',           'value' => 'Student Services Portal',            'group' => 'branding'],
            ['key' => 'contact_email',          'value' => '',                                   'group' => 'branding'],
            ['key' => 'contact_phone',          'value' => '',                                   'group' => 'branding'],
            ['key' => 'website_url',            'value' => 'https://daffodilvarsity.edu.bd',     'group' => 'branding'],
            ['key' => 'address',                'value' => '',                                   'group' => 'branding'],
            ['key' => 'primary_color',          'value' => '#0D2B4E',                            'group' => 'branding'],
            ['key' => 'branding_logo_path',     'value' => '',                                   'group' => 'branding'],

            // Feature flags (stored as '1' / '0')
            ['key' => 'feature_self_registration',    'value' => '0', 'group' => 'features'],
            ['key' => 'feature_anonymous_complaints', 'value' => '1', 'group' => 'features'],
            ['key' => 'feature_sms_notifications',    'value' => '1', 'group' => 'features'],
            ['key' => 'feature_certificate_verify',   'value' => '1', 'group' => 'features'],
            ['key' => 'feature_maintenance_mode',     'value' => '0', 'group' => 'features'],

            // SMS gateway (SSL Wireless)
            ['key' => 'sms_api_token',  'value' => '', 'group' => 'sms'],
            ['key' => 'sms_sid',        'value' => '', 'group' => 'sms'],
            ['key' => 'sms_sender_id',  'value' => 'DIU',  'group' => 'sms'],
        ];

        foreach ($defaults as $setting) {
            DB::table('settings')->insertOrIgnore($setting + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
