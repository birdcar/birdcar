<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Notifications\AdminInvitation;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\Models\Role as RoleModel;
use Throwable;

class InviteAdministrator
{
    public function handle(string $email, ?string $name = null): AdminInvitationResult
    {
        $normalizedEmail = $this->normalizedEmail($email);
        $adminUrl = $this->validatedAdminUrl();
        $this->assertSafeMailer();
        $roles = $this->configuredBootstrapRoles();
        $this->assertRolesExist($roles);

        $user = $this->provisionUser($normalizedEmail, $name, $roles);

        try {
            $status = Password::broker((string) config('fortify.passwords'))->sendResetLink(
                ['email' => $user->email],
                function (User $notifiable, #[\SensitiveParameter] string $token) use ($adminUrl): ?string {
                    $notifiable->notify(new AdminInvitation($token, $adminUrl));

                    return null;
                },
            );
        } catch (Throwable $exception) {
            throw new AdminInvitationDeliveryException(
                'Invitation delivery failed after account provisioning; retry after correcting the mail transport.',
                previous: $exception,
            );
        }

        if ($status !== PasswordBroker::RESET_LINK_SENT) {
            throw new AdminInvitationBrokerException($this->brokerFailureMessage($status));
        }

        return new AdminInvitationResult($user, $roles);
    }

    private function normalizedEmail(string $email): string
    {
        $normalized = Str::lower(trim($email));

        if ($normalized === '' || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new AdminInvitationException('Enter a valid invitation email address.');
        }

        return $normalized;
    }

    private function validatedAdminUrl(): string
    {
        $url = config('admin.url');

        if (! is_string($url) || trim($url) === '') {
            throw new AdminInvitationException('Configuration [admin.url] must be a non-empty Admin origin.');
        }

        $url = rtrim(trim($url), '/');
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($scheme) || ! in_array(Str::lower($scheme), ['http', 'https'], true) || ! is_string($host) || $host === '') {
            throw new AdminInvitationException('Configuration [admin.url] must be an HTTP(S) Admin origin.');
        }

        if (parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null || parse_url($url, PHP_URL_QUERY) !== null || parse_url($url, PHP_URL_FRAGMENT) !== null) {
            throw new AdminInvitationException('Configuration [admin.url] must not include credentials, query strings, or fragments.');
        }

        if (is_string($path) && ! in_array($path, ['', '/'], true)) {
            throw new AdminInvitationException('Configuration [admin.url] must be an origin without a path.');
        }

        if (Str::lower($scheme) !== 'https' && ! app()->environment(['local', 'testing'])) {
            throw new AdminInvitationException('Configuration [admin.url] must use HTTPS outside local and testing environments.');
        }

        $port = parse_url($url, PHP_URL_PORT);
        $defaultPort = Str::lower($scheme) === 'https' ? 443 : 80;
        $portSuffix = is_int($port) && $port !== $defaultPort ? ':'.$port : '';

        return Str::lower($scheme).'://'.Str::lower($host).$portSuffix;
    }

    private function assertSafeMailer(): void
    {
        $defaultMailer = config('mail.default');

        if (! is_string($defaultMailer) || trim($defaultMailer) === '') {
            throw new AdminInvitationException('Configuration [mail.default] must name a delivery-capable mailer.');
        }

        $this->effectiveTransportFor($defaultMailer, []);
    }

    /**
     * @param  list<string>  $seen
     */
    private function effectiveTransportFor(string $mailer, array $seen): string
    {
        if (in_array($mailer, $seen, true)) {
            throw new AdminInvitationException("Mailer [{$mailer}] cannot be used for invitations because its failover chain is cyclic.");
        }

        $config = config("mail.mailers.{$mailer}");

        if (! is_array($config)) {
            throw new AdminInvitationException("Mailer [{$mailer}] is not defined.");
        }

        $config = $this->parsedMailerConfig($mailer, $config);
        $transport = $config['transport'] ?? null;

        if (! is_string($transport) || trim($transport) === '') {
            throw new AdminInvitationException("Mailer [{$mailer}] must declare a supported transport.");
        }

        $transport = Str::lower($transport);

        if (in_array($transport, ['array', 'log'], true)) {
            throw new AdminInvitationException("Mailer [{$mailer}] uses the unsafe [{$transport}] transport for invitations.");
        }

        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $mailers = $config['mailers'] ?? null;

            if (! is_array($mailers) || $mailers === []) {
                throw new AdminInvitationException("Mailer [{$mailer}] must include delivery-capable child mailers.");
            }

            foreach ($mailers as $childMailer) {
                if (! is_string($childMailer) || trim($childMailer) === '') {
                    throw new AdminInvitationException("Mailer [{$mailer}] contains an invalid child mailer.");
                }

                $this->effectiveTransportFor($childMailer, [...$seen, $mailer]);
            }

            return $transport;
        }

        if (! in_array($transport, ['smtp', 'sendmail', 'mailgun', 'ses', 'ses-v2', 'postmark', 'resend', 'cloudflare'], true)) {
            throw new AdminInvitationException("Mailer [{$mailer}] uses unsupported transport [{$transport}] for invitations.");
        }

        return $transport;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function parsedMailerConfig(string $mailer, array $config): array
    {
        if (! array_key_exists('url', $config) || $config['url'] === null || $config['url'] === '') {
            return $config;
        }

        try {
            $config = array_merge($config, (new ConfigurationUrlParser)->parseConfiguration($config));
            $transport = Arr::pull($config, 'driver');
        } catch (InvalidArgumentException) {
            throw new AdminInvitationException("Mailer [{$mailer}] has an invalid URL configuration.");
        }

        if (is_string($transport)) {
            $config['transport'] = $transport;
        }

        return $config;
    }

    /**
     * @return list<string>
     */
    private function configuredBootstrapRoles(): array
    {
        $roles = config('admin.bootstrap_roles');

        if (! is_array($roles) || $roles === []) {
            throw new AdminInvitationException('Configuration [admin.bootstrap_roles] must list required web-guard roles.');
        }

        $roleNames = [];

        foreach ($roles as $role) {
            if (! is_string($role) || trim($role) === '') {
                throw new AdminInvitationException('Configuration [admin.bootstrap_roles] must contain only non-empty role names.');
            }

            $roleNames[] = trim($role);
        }

        return array_values(array_unique($roleNames));
    }

    /**
     * @param  list<string>  $roles
     */
    private function assertRolesExist(array $roles): void
    {
        $guard = 'web';
        $existing = RoleModel::query()
            ->where('guard_name', $guard)
            ->whereIn('name', $roles)
            ->pluck('name')
            ->all();

        $missing = array_values(array_diff($roles, array_map('strval', $existing)));

        if ($missing !== []) {
            throw new AdminInvitationException('Required Admin bootstrap roles are missing; run [php artisan authorization:sync --no-interaction] before inviting an administrator.');
        }
    }

    /**
     * @param  list<string>  $roles
     */
    private function provisionUser(string $normalizedEmail, ?string $name, array $roles): User
    {
        try {
            return DB::transaction(fn (): User => $this->findCreateAndAssign($normalizedEmail, $name, $roles, true));
        } catch (QueryException) {
            return DB::transaction(fn (): User => $this->findCreateAndAssign($normalizedEmail, $name, $roles, false));
        }
    }

    /**
     * @param  list<string>  $roles
     */
    private function findCreateAndAssign(string $normalizedEmail, ?string $name, array $roles, bool $createIfMissing): User
    {
        $matches = User::query()
            ->whereRaw('lower(email) = ?', [$normalizedEmail])
            ->lockForUpdate()
            ->get();

        if ($matches->count() > 1) {
            throw new AdminInvitationException('Multiple users match that normalized email address; resolve the duplicate identities before inviting.');
        }

        $user = $matches->first();

        if (! $user instanceof User) {
            if (! $createIfMissing) {
                throw new AdminInvitationException('A concurrent invitation changed the user record; retry the invitation.');
            }

            $user = User::query()->create([
                'name' => $this->newUserName($name, $normalizedEmail),
                'email' => $normalizedEmail,
                'password' => Str::random(64),
            ]);
        }

        $user->assignRole($roles);

        return $user->refresh();
    }

    private function newUserName(?string $name, string $email): string
    {
        $name = is_string($name) ? trim($name) : '';

        return $name !== '' ? $name : $email;
    }

    private function brokerFailureMessage(string $status): string
    {
        return match ($status) {
            PasswordBroker::RESET_THROTTLED => 'The password broker throttled this invitation; wait before retrying.',
            PasswordBroker::INVALID_USER => 'The password broker could not find the provisioned user.',
            default => 'The password broker did not accept the invitation request.',
        };
    }
}

final readonly class AdminInvitationResult
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public User $user,
        public array $roles,
    ) {}
}

class AdminInvitationException extends RuntimeException
{
    //
}

final class AdminInvitationBrokerException extends AdminInvitationException
{
    //
}

final class AdminInvitationDeliveryException extends AdminInvitationException
{
    //
}
