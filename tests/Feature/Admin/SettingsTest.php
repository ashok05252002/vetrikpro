<?php

namespace Tests\Feature\Admin;

use App\Mail\TestMail;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Vetrik Private Limited',
            'display_timezone' => 'Asia/Kolkata',
            'display_date_format' => 'dmy',
            'display_currency' => 'INR',
        ], $overrides);
    }

    public function test_only_an_administrator_can_open_settings()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.settings.edit'))
            ->assertForbidden();

        // HR reaches the rest of /admin but not this.
        $this->actingAs(User::factory()->hr()->create())
            ->get(route('admin.settings.edit'))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.settings.edit'))
            ->assertOk();
    }

    public function test_hr_cannot_change_settings_either()
    {
        $this->actingAs(User::factory()->hr()->create())
            ->post(route('admin.settings.update'), $this->payload())
            ->assertForbidden();
    }

    public function test_an_admin_can_set_the_company_name()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.settings.update'), $this->payload())
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertSame('Vetrik Private Limited', app(Settings::class)->get('company.name'));
        $this->assertDatabaseHas('settings', ['key' => 'company.name', 'value' => 'Vetrik Private Limited']);
    }

    public function test_the_company_name_cannot_be_blank()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.settings.update'), $this->payload(['company_name' => '']))
            ->assertSessionHasErrors('company_name');
    }

    public function test_the_company_name_is_shared_with_every_page()
    {
        $admin = User::factory()->admin()->create();

        app(Settings::class)->set(['company.name' => 'Acme Foods']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('company.name', 'Acme Foods')
                ->where('company.logo', null));
    }

    public function test_the_company_name_drives_the_app_name()
    {
        app(Settings::class)->set(['company.name' => 'Acme Foods']);

        // Every request boots the providers afresh, so re-running boot here is
        // what a real request does. (refreshApplication() would drop the
        // in-memory test database along with it.)
        (new AppServiceProvider(app()))->boot();

        $this->assertSame('Acme Foods', config('app.name'));
    }

    public function test_the_company_name_reaches_the_browser_title()
    {
        app(Settings::class)->set(['company.name' => 'Acme Foods']);
        (new AppServiceProvider(app()))->boot();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<title inertia>Acme Foods</title>', false);
    }

    public function test_settings_fall_back_to_defaults_when_nothing_is_stored()
    {
        $settings = app(Settings::class);

        $this->assertSame('HRMS Task', $settings->get('company.name'));
        $this->assertSame('UTC', $settings->get('display.timezone'));
        $this->assertSame('INR', $settings->get('display.currency'));
        $this->assertNull($settings->logoUrl());
    }

    public function test_unknown_keys_are_ignored_rather_than_stored()
    {
        app(Settings::class)->set(['company.name' => 'Acme', 'company.secret_backdoor' => 'yes']);

        $this->assertDatabaseHas('settings', ['key' => 'company.name']);
        $this->assertDatabaseMissing('settings', ['key' => 'company.secret_backdoor']);
    }

    public function test_an_invalid_timezone_is_rejected()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.settings.update'), $this->payload(['display_timezone' => 'Mars/Olympus']))
            ->assertSessionHasErrors('display_timezone');
    }

    public function test_a_currency_code_must_be_three_letters()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.settings.update'), $this->payload(['display_currency' => 'RUPEES']))
            ->assertSessionHasErrors('display_currency');
    }

    public function test_a_currency_code_is_stored_uppercased()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.settings.update'), $this->payload(['display_currency' => 'eur']));

        $this->assertSame('EUR', app(Settings::class)->get('display.currency'));
    }

    public function test_a_logo_can_be_uploaded_and_is_served_from_public_uploads()
    {
        Storage::fake('uploads');

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.settings.update'), $this->payload([
                'logo' => UploadedFile::fake()->image('logo.png'),
            ]))
            ->assertRedirect();

        $path = app(Settings::class)->get('company.logo');

        $this->assertNotSame('', $path);
        Storage::disk('uploads')->assertExists($path);

        // Stored as a path relative to the disk, exposed as a URL only on read.
        $this->assertStringStartsWith('company/', $path);
        $this->assertSame('/uploads/'.$path, app(Settings::class)->logoUrl());
    }

    public function test_replacing_a_logo_deletes_the_old_file()
    {
        Storage::fake('uploads');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.settings.update'), $this->payload([
            'logo' => UploadedFile::fake()->image('first.png'),
        ]));
        $first = app(Settings::class)->get('company.logo');

        $this->actingAs($admin)->post(route('admin.settings.update'), $this->payload([
            'logo' => UploadedFile::fake()->image('second.png'),
        ]));
        $second = app(Settings::class)->get('company.logo');

        $this->assertNotSame($first, $second);
        Storage::disk('uploads')->assertMissing($first);
        Storage::disk('uploads')->assertExists($second);
    }

    public function test_a_logo_can_be_removed()
    {
        Storage::fake('uploads');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.settings.update'), $this->payload([
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]));
        $path = app(Settings::class)->get('company.logo');

        $this->actingAs($admin)->post(route('admin.settings.update'), $this->payload(['remove_logo' => true]));

        $this->assertSame('', app(Settings::class)->get('company.logo'));
        Storage::disk('uploads')->assertMissing($path);
        $this->assertNull(app(Settings::class)->logoUrl());
    }

    public function test_a_non_image_is_rejected_as_a_logo()
    {
        Storage::fake('uploads');

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.settings.update'), $this->payload([
                'logo' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
            ]))
            ->assertSessionHasErrors('logo');
    }

    public function test_dark_logo_and_favicon_are_stored_separately_from_the_logo()
    {
        Storage::fake('uploads');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.settings.update'), $this->payload([
            'logo' => UploadedFile::fake()->image('light.png'),
            'logo_dark' => UploadedFile::fake()->image('dark.png'),
            'favicon' => UploadedFile::fake()->image('icon.png', 32, 32),
        ]))->assertSessionHasNoErrors();

        $settings = app(Settings::class);
        $paths = [$settings->get('company.logo'), $settings->get('company.logo_dark'), $settings->get('company.favicon')];

        $this->assertCount(3, array_unique($paths));
        foreach ($paths as $path) {
            Storage::disk('uploads')->assertExists($path);
        }

        // Every page gets the dark logo; the favicon goes in the document head.
        $this->actingAs($admin)->get(route('dashboard'))
            ->assertSee('/uploads/'.$paths[2], false)
            ->assertInertia(fn ($page) => $page->where('company.logo_dark', '/uploads/'.$paths[1]));

        // Removing one leaves the others alone.
        $this->actingAs($admin)->post(route('admin.settings.update'), $this->payload(['remove_logo_dark' => true]));
        $this->assertSame('', app(Settings::class)->get('company.logo_dark'));
        Storage::disk('uploads')->assertMissing($paths[1]);
        Storage::disk('uploads')->assertExists($paths[0]);
    }

    public function test_an_ico_file_is_accepted_as_a_favicon_but_a_pdf_is_not()
    {
        Storage::fake('uploads');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.settings.update'), $this->payload([
            'favicon' => UploadedFile::fake()->createWithContent('favicon.ico', file_get_contents(public_path('favicon.ico'))),
        ]))->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.settings.update'), $this->payload([
            'favicon' => UploadedFile::fake()->create('icon.pdf', 10, 'application/pdf'),
        ]))->assertSessionHasErrors('favicon');
    }

    public function test_an_administrator_can_send_a_test_email()
    {
        Mail::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.settings.edit'))
            ->post(route('admin.settings.test-mail'), ['test_email' => 'someone@example.com'])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('success');

        Mail::assertSent(TestMail::class, fn (TestMail $mail) => $mail->hasTo('someone@example.com'));
    }

    public function test_a_refused_test_email_shows_the_reason_instead_of_failing()
    {
        // Nothing listens on port 1, so the SMTP connection is refused.
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.settings.edit'))
            ->post(route('admin.settings.test-mail'), ['test_email' => 'someone@example.com'])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'could not be sent'));
    }

    public function test_hr_cannot_send_a_test_email()
    {
        Mail::fake();

        $this->actingAs(User::factory()->hr()->create())
            ->post(route('admin.settings.test-mail'), ['test_email' => 'someone@example.com'])
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_the_test_email_needs_a_valid_address()
    {
        Mail::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.settings.test-mail'), ['test_email' => 'not-an-email'])
            ->assertSessionHasErrors('test_email');

        Mail::assertNothingSent();
    }

    public function test_the_test_email_renders_with_the_branded_layout()
    {
        $html = (new TestMail('Asha Admin'))->render();

        $this->assertStringContainsString('Mail is working', $html);
        $this->assertStringContainsString('Asha Admin', $html);
    }
}
