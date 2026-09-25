<?php

namespace App\Support;

use App\Services\OfferLetter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Organisation-wide settings, stored as key/value rows and read through a
 * declared schema.
 *
 * Adding a setting means one entry in schema() plus a field on the admin form.
 * The schema is the single source of truth for a key's type, default and group,
 * so nothing here is stringly-typed at the call site.
 */
class Settings
{
    private const CACHE_KEY = 'app.settings';

    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    /**
     * Every known setting: its type, default, and which card it belongs to.
     *
     * @return array<string, array{type: string, default: mixed, group: string}>
     */
    public static function schema(): array
    {
        return [
            // Identity
            'company.name' => ['type' => 'string', 'default' => 'HRMS Task', 'group' => 'company'],
            'company.legal_name' => ['type' => 'string', 'default' => '', 'group' => 'company'],
            'company.tax_id' => ['type' => 'string', 'default' => '', 'group' => 'company'],

            // Contact
            'company.email' => ['type' => 'string', 'default' => '', 'group' => 'contact'],
            'company.phone' => ['type' => 'string', 'default' => '', 'group' => 'contact'],
            'company.website' => ['type' => 'string', 'default' => '', 'group' => 'contact'],
            'company.address' => ['type' => 'string', 'default' => '', 'group' => 'contact'],

            // Branding — a path relative to the `uploads` disk, never a URL.
            'company.logo' => ['type' => 'string', 'default' => '', 'group' => 'branding'],

            /*
             * Display-only regional settings. These deliberately do NOT touch
             * config('app.timezone'): timestamps are stored in the app timezone,
             * so changing it would silently reinterpret every existing row.
             * These affect rendering and nothing else.
             */
            'display.timezone' => ['type' => 'string', 'default' => 'UTC', 'group' => 'regional'],
            'display.date_format' => ['type' => 'string', 'default' => 'dmy', 'group' => 'regional'],
            'display.currency' => ['type' => 'string', 'default' => 'INR', 'group' => 'regional'],

            // Offer letter template (Configuration hub → Offer letter). The body
            // is plain text with {placeholders}; see App\Services\OfferLetter.
            'offer.title' => ['type' => 'string', 'default' => 'Offer of Employment', 'group' => 'offer'],
            'offer.body' => ['type' => 'string', 'default' => OfferLetter::DEFAULT_BODY, 'group' => 'offer'],
            'offer.signatory_name' => ['type' => 'string', 'default' => '', 'group' => 'offer'],
            'offer.signatory_title' => ['type' => 'string', 'default' => 'Human Resources', 'group' => 'offer'],
            'offer.valid_days' => ['type' => 'int', 'default' => 7, 'group' => 'offer'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        $defaults = array_map(fn (array $spec) => $spec['default'], static::schema());

        return $this->loaded = array_merge($defaults, $this->stored());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * Write a batch of settings. Unknown keys are ignored rather than stored,
     * so a stray form field can never create a phantom setting.
     *
     * @param  array<string, mixed>  $values
     */
    public function set(array $values): void
    {
        $schema = static::schema();

        foreach ($values as $key => $value) {
            if (! isset($schema[$key])) {
                continue;
            }

            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $this->serialize($value), 'updated_at' => now(), 'created_at' => now()],
            );
        }

        $this->flush();
    }

    public function flush(): void
    {
        $this->loaded = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Public URL for the company logo, or null when none is set.
     */
    public function logoUrl(): ?string
    {
        $path = (string) $this->get('company.logo');

        return $path === '' ? null : '/uploads/'.ltrim($path, '/');
    }

    /**
     * The subset shared with every Inertia page.
     *
     * @return array<string, mixed>
     */
    public function forSharing(): array
    {
        return [
            'company' => [
                'name' => $this->get('company.name'),
                'logo' => $this->logoUrl(),
            ],
            'display' => [
                'timezone' => $this->get('display.timezone'),
                'dateFormat' => $this->get('display.date_format'),
                'currency' => $this->get('display.currency'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        try {
            $rows = Cache::rememberForever(
                self::CACHE_KEY,
                fn () => DB::table('settings')->pluck('value', 'key')->all(),
            );
        } catch (Throwable) {
            // Before the first migration there is no table to read; fall back
            // to defaults rather than breaking every request and artisan call.
            return [];
        }

        $schema = static::schema();
        $out = [];

        foreach ($rows as $key => $value) {
            if (isset($schema[$key])) {
                $out[$key] = $this->cast($value, $schema[$key]['type']);
            }
        }

        return $out;
    }

    private function cast(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
            'int' => (int) $value,
            'array' => json_decode($value, true) ?? [],
            default => $value,
        };
    }

    private function serialize(mixed $value): ?string
    {
        if (is_array($value)) {
            return json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $value === null ? null : (string) $value;
    }

    /**
     * True once the settings table exists — used to keep boot-time reads safe
     * on a database that has not been migrated yet.
     */
    public static function tableExists(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (Throwable) {
            return false;
        }
    }
}
