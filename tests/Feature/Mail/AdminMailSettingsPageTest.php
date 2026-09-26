<?php

use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Mail\Role as MailRole;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\User;
use App\Settings\AdminMailSettings;
use App\Settings\MarketingMailSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Livewire\Livewire;
use Spatie\LaravelSettings\Exceptions\MissingSettings;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
    config([
        'admin.mail.mailer' => 'resend_admin',
        'marketing.mail.mailer' => 'resend_marketing',
        'mail.mailers.resend_admin.key' => '',
        'mail.mailers.resend_marketing.key' => '',
    ]);
});

function mailSettingsOperator(): User
{
    $operator = User::factory()->create();
    $operator->assignRole([AdminRole::Access->value, MailRole::Operator->value]);

    return $operator;
}

/** @return array<string, mixed> */
function storedMailSender(string $group): array
{
    return DB::table('settings')
        ->where('group', $group)
        ->pluck('payload', 'name')
        ->map(fn (string $payload): mixed => json_decode($payload, true))
        ->all();
}

test('guests are sent to sign in before the mail settings page', function (): void {
    $this->get('http://admin.birdcar.test/mail')->assertRedirect('/login');
});

test('the mail settings page is forbidden without the sender configuration capability', function (User $user): void {
    $this->actingAs($user)
        ->get('http://admin.birdcar.test/mail')
        ->assertForbidden();

    Livewire::actingAs($user)->test('admin.mail.settings')->assertForbidden();
})->with([
    'admin access only' => fn (): User => tap(User::factory()->create())->assignRole(AdminRole::Access->value),
    'another module\'s capabilities but not mail' => fn (): User => tap(User::factory()->create())->assignRole([AdminRole::Access->value, PublishingRole::Author->value]),
]);

test('operators see both surfaces with their saved senders and the mail navigation current', function (): void {
    $response = $this->actingAs(mailSettingsOperator())
        ->get('http://admin.birdcar.test/mail')
        ->assertOk()
        ->assertSee('Mail settings')
        ->assertSee('Admin mail')
        ->assertSee('Marketing mail')
        ->assertSee('Admin mail is sent from this address. It must be at admin.birdcar.dev or birdcar.dev.')
        ->assertSee('Marketing mail is sent from this address. It must be at birdcar.dev.')
        ->assertSee('resend_admin')
        ->assertSee('resend_marketing');

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $current = $xpath->query('//nav[@aria-label="Admin modules"]//a[@aria-current="page"]');
    $savedSender = fn (string $surface): string => preg_replace('/\s+/', ' ', trim($xpath->query("//*[@data-mail-surface=\"{$surface}\"]//*[@data-saved-sender]")->item(0)->textContent));

    expect($current->length)->toBe(1)
        ->and($current->item(0)->getAttribute('href'))->toBe('http://admin.birdcar.test/mail')
        ->and($savedSender('admin'))->toBe('Saved: Birdcar, from noreply@admin.birdcar.dev. Replies go to the From address.')
        ->and($savedSender('marketing'))->toBe('Saved: Birdcar, from hello@birdcar.dev. Replies go to the From address.');
});

test('the form starts from each surface\'s saved sender', function (): void {
    app(MarketingMailSettings::class)->updateSender('Birdcar News', 'news@birdcar.dev', 'replies@example.com')->save();

    Livewire::actingAs(mailSettingsOperator())->test('admin.mail.settings')
        ->assertSet('senders', [
            'admin' => ['from_name' => 'Birdcar', 'from_address' => 'noreply@admin.birdcar.dev', 'reply_to' => ''],
            'marketing' => ['from_name' => 'Birdcar News', 'from_address' => 'news@birdcar.dev', 'reply_to' => 'replies@example.com'],
        ]);
});

test('the admin sidebar links to mail only for sender configurers', function (): void {
    $withoutMail = tap(User::factory()->create())->assignRole([AdminRole::Access->value, PublishingRole::Author->value]);

    $this->actingAs($withoutMail)
        ->get('http://admin.birdcar.test/')
        ->assertOk()
        ->assertDontSee('http://admin.birdcar.test/mail');

    $this->actingAs(mailSettingsOperator())
        ->get('http://admin.birdcar.test/')
        ->assertOk()
        ->assertSee('http://admin.birdcar.test/mail');
});

test('saving the admin sender stores it for the next send and leaves marketing unchanged', function (): void {
    Livewire::actingAs(mailSettingsOperator())->test('admin.mail.settings')
        ->set('senders.admin.from_name', '  Birdcar Ops ')
        ->set('senders.admin.from_address', 'ops@birdcar.dev')
        ->set('senders.admin.reply_to', 'owner@example.com')
        ->call('save', 'admin')
        ->assertHasNoErrors()
        ->assertSet('senders.admin.from_name', 'Birdcar Ops')
        ->assertSee('Admin sender saved.');

    expect(storedMailSender('admin_mail'))->toEqual(['from_name' => 'Birdcar Ops', 'from_address' => 'ops@birdcar.dev', 'reply_to' => 'owner@example.com'])
        ->and(storedMailSender('marketing_mail'))->toEqual(['from_name' => 'Birdcar', 'from_address' => 'hello@birdcar.dev', 'reply_to' => null]);

    app()->forgetScopedInstances();
    expect(app(AdminMailSettings::class)->sender()->address)->toBe('ops@birdcar.dev');
});

test('saving the marketing sender with a blank reply-to stores no reply-to', function (): void {
    app(MarketingMailSettings::class)->updateSender('Birdcar', 'hello@birdcar.dev', 'replies@example.com')->save();

    Livewire::actingAs(mailSettingsOperator())->test('admin.mail.settings')
        ->set('senders.marketing.from_address', 'news@birdcar.dev')
        ->set('senders.marketing.reply_to', '')
        ->call('save', 'marketing')
        ->assertHasNoErrors()
        ->assertSee('Marketing sender saved.');

    expect(storedMailSender('marketing_mail'))->toEqual(['from_name' => 'Birdcar', 'from_address' => 'news@birdcar.dev', 'reply_to' => null]);
});

test('invalid sender input is rejected with a field message and nothing is saved', function (string $surface, string $field, mixed $value, string $message): void {
    Livewire::actingAs(mailSettingsOperator())->test('admin.mail.settings')
        ->set("senders.{$surface}.{$field}", $value)
        ->call('save', $surface)
        ->assertHasErrors("senders.{$surface}.{$field}")
        ->assertSee($message)
        ->assertSee('Nothing was saved.');

    expect(storedMailSender('admin_mail'))->toEqual(['from_name' => 'Birdcar', 'from_address' => 'noreply@admin.birdcar.dev', 'reply_to' => null])
        ->and(storedMailSender('marketing_mail'))->toEqual(['from_name' => 'Birdcar', 'from_address' => 'hello@birdcar.dev', 'reply_to' => null]);
})->with([
    'marketing on the admin domain' => ['marketing', 'from_address', 'news@admin.birdcar.dev', 'Use an address at birdcar.dev.'],
    'admin on an unrelated domain' => ['admin', 'from_address', 'ops@example.com', 'Use an address at admin.birdcar.dev or birdcar.dev.'],
    'malformed from address' => ['admin', 'from_address', 'not-an-email', 'Enter a valid email address.'],
    'missing from address' => ['admin', 'from_address', '', 'Enter the address mail is sent from.'],
    'blank sender name' => ['admin', 'from_name', '   ', 'Enter the name recipients see.'],
    'multi-line sender name' => ['marketing', 'from_name', "Birdcar\r\nBcc: victim@example.com", 'Keep the sender name on one line.'],
    'overlong sender name' => ['admin', 'from_name', str_repeat('a', 101), 'Keep the sender name to 100 characters or fewer.'],
    'reply-to without a public domain' => ['admin', 'reply_to', 'owner@localhost', 'Enter a valid reply-to address, or leave it blank.'],
    'non-string reply-to' => ['marketing', 'reply_to', ['owner@example.com'], 'Enter a valid reply-to address, or leave it blank.'],
]);

test('an invalid marketing draft keeps its error and does not block saving the admin sender', function (): void {
    Livewire::actingAs(mailSettingsOperator())->test('admin.mail.settings')
        ->set('senders.marketing.from_address', 'news@admin.birdcar.dev')
        ->call('save', 'marketing')
        ->assertHasErrors('senders.marketing.from_address')
        ->set('senders.admin.from_address', 'not-an-email')
        ->call('save', 'admin')
        ->assertHasErrors(['senders.admin.from_address', 'senders.marketing.from_address'])
        ->set('senders.admin.from_address', 'ops@birdcar.dev')
        ->call('save', 'admin')
        ->assertHasNoErrors('senders.admin.from_address')
        ->assertHasErrors('senders.marketing.from_address')
        ->assertSee('Admin sender saved.');

    expect(storedMailSender('admin_mail')['from_address'])->toBe('ops@birdcar.dev')
        ->and(storedMailSender('marketing_mail')['from_address'])->toBe('hello@birdcar.dev');
});

test('saving an unknown surface is rejected without saving anything', function (): void {
    Livewire::actingAs(mailSettingsOperator())->test('admin.mail.settings')
        ->set('senders.admin.from_address', 'ops@birdcar.dev')
        ->call('save', 'customer')
        ->assertHasErrors('senders');

    expect(storedMailSender('admin_mail')['from_address'])->toBe('noreply@admin.birdcar.dev');
});

test('a stored sender the settings class now rejects still loads so it can be corrected', function (): void {
    DB::table('settings')->where('group', 'admin_mail')->where('name', 'from_address')
        ->update(['payload' => json_encode('noreply@retired.example.com')]);
    app()->forgetScopedInstances();

    Livewire::actingAs(mailSettingsOperator())->test('admin.mail.settings')
        ->assertOk()
        ->assertSet('senders.admin.from_address', 'noreply@retired.example.com')
        ->set('senders.admin.from_address', 'noreply@admin.birdcar.dev')
        ->call('save', 'admin')
        ->assertHasNoErrors();

    app()->forgetScopedInstances();
    expect(app(AdminMailSettings::class)->sender()->address)->toBe('noreply@admin.birdcar.dev');
});

test('a save that fails after validation is reported and asks the operator to reload', function (): void {
    Exceptions::fake();
    $page = Livewire::actingAs(mailSettingsOperator())->test('admin.mail.settings')
        ->set('senders.admin.from_address', 'ops@birdcar.dev');
    DB::table('settings')->where('group', 'admin_mail')->where('name', 'from_name')->delete();

    $page->call('save', 'admin')
        ->assertHasNoErrors()
        ->assertSee('Reload the page to see the saved settings, then try again.')
        ->assertDontSee('Admin sender saved.');

    Exceptions::assertReported(MissingSettings::class);
    expect(storedMailSender('admin_mail')['from_address'])->toBe('noreply@admin.birdcar.dev');
});

test('revoked sender configuration access cannot save', function (string $revokedRole): void {
    $operator = mailSettingsOperator();
    $page = Livewire::actingAs($operator)->test('admin.mail.settings')
        ->set('senders.admin.from_address', 'ops@birdcar.dev');

    $operator->removeRole($revokedRole);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $page->call('save', 'admin')->assertForbidden();

    expect(storedMailSender('admin_mail')['from_address'])->toBe('noreply@admin.birdcar.dev');
})->with([
    'mail operator role' => MailRole::Operator->value,
    'admin access role' => AdminRole::Access->value,
]);

test('forged saves from users without the capability are forbidden before the surface is checked', function (string $surface): void {
    $page = Livewire::actingAs(mailSettingsOperator())->test('admin.mail.settings')
        ->set('senders.admin.from_address', 'ops@birdcar.dev');

    Livewire::actingAs(tap(User::factory()->create())->assignRole(AdminRole::Access->value));

    $page->call('save', $surface)->assertForbidden();

    expect(storedMailSender('admin_mail')['from_address'])->toBe('noreply@admin.birdcar.dev');
})->with(['admin', 'customer']);

test('the page and its livewire payloads never expose resend keys', function (): void {
    config([
        'mail.mailers.resend_admin.key' => 're_test_secret',
        'mail.mailers.resend_marketing.key' => 're_marketing_secret',
    ]);
    $operator = mailSettingsOperator();

    $this->actingAs($operator)
        ->get('http://admin.birdcar.test/mail')
        ->assertOk()
        ->assertSeeHtml('data-key-status="configured"')
        ->assertDontSeeHtml('data-key-status="missing"')
        ->assertDontSee('re_test_secret')
        ->assertDontSee('re_marketing_secret');

    $page = Livewire::actingAs($operator)->test('admin.mail.settings')->call('save', 'admin');
    $payloads = json_encode([$page->snapshot, $page->effects]);

    expect($payloads)->not->toContain('re_test_secret')
        ->and($payloads)->not->toContain('re_marketing_secret');
});

test('a missing resend key shows a notice naming its environment variable', function (mixed $missingKey): void {
    config([
        'mail.mailers.resend_admin.key' => 're_test_secret',
        'mail.mailers.resend_marketing.key' => $missingKey,
    ]);

    $this->actingAs(mailSettingsOperator())
        ->get('http://admin.birdcar.test/mail')
        ->assertOk()
        ->assertSee('No Resend API key is configured for marketing mail')
        ->assertSee('BIRDCAR_MARKETING_RESEND_API_KEY')
        ->assertDontSee('No Resend API key is configured for admin mail')
        ->assertDontSee('BIRDCAR_ADMIN_RESEND_API_KEY');
})->with([
    'unset' => [null],
    'empty' => [''],
]);
