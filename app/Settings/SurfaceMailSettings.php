<?php

namespace App\Settings;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\LaravelSettings\Settings;

/**
 * Sender identity for one mail surface. Stored payloads are not validated on load, so every read validates again.
 */
abstract class SurfaceMailSettings extends Settings
{
    public string $from_name;

    public string $from_address;

    public ?string $reply_to;

    /**
     * Lowercased domains this surface's Resend key may send from.
     *
     * @return list<string>
     */
    abstract public static function allowedSenderDomains(): array;

    /**
     * A valid email address whose domain exactly matches one of this surface's sender domains; subdomains do not match.
     */
    public static function allowsSenderAddress(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        return in_array(Str::lower(Str::afterLast($address, '@')), static::allowedSenderDomains(), true);
    }

    /**
     * @throws InvalidArgumentException when the stored name or address is empty, malformed, or off-domain.
     */
    public function sender(): Address
    {
        self::assertValidSenderName($this->from_name);
        self::assertAllowedSenderAddress($this->from_address);

        return new Address($this->from_address, $this->from_name);
    }

    /**
     * @throws InvalidArgumentException when the stored reply-to is not a valid email address.
     */
    public function replyToAddress(): ?Address
    {
        if ($this->reply_to === null || $this->reply_to === '') {
            return null;
        }

        self::assertValidReplyTo($this->reply_to);

        return new Address($this->reply_to);
    }

    /**
     * Validates, then assigns; the caller saves.
     *
     * @throws InvalidArgumentException when any value is invalid for this surface.
     */
    public function updateSender(string $name, string $address, ?string $replyTo): static
    {
        $name = trim($name);
        $address = trim($address);
        $replyTo = $replyTo === null || trim($replyTo) === '' ? null : trim($replyTo);

        self::assertValidSenderName($name);
        self::assertAllowedSenderAddress($address);

        if ($replyTo !== null) {
            self::assertValidReplyTo($replyTo);
        }

        $this->from_name = $name;
        $this->from_address = $address;
        $this->reply_to = $replyTo;

        return $this;
    }

    /**
     * Lowercases the configured sender domains and drops malformed entries, so a bad config rejects senders instead of widening the list.
     *
     * @return list<string>
     */
    protected static function configuredSenderDomains(string $configKey): array
    {
        $domains = config($configKey);

        if (! is_array($domains)) {
            return [];
        }

        $normalized = [];

        foreach ($domains as $domain) {
            if (is_string($domain) && trim($domain) !== '') {
                $normalized[] = Str::lower(trim($domain));
            }
        }

        return array_values(array_unique($normalized));
    }

    private static function assertValidSenderName(string $name): void
    {
        if (trim($name) === '' || preg_match('/[\r\n]/', $name) === 1) {
            throw new InvalidArgumentException('The mail sender name must be a non-empty single line.');
        }
    }

    private static function assertAllowedSenderAddress(string $address): void
    {
        if (! static::allowsSenderAddress($address)) {
            throw new InvalidArgumentException('The mail sender address must use one of this surface\'s sender domains.');
        }
    }

    private static function assertValidReplyTo(string $replyTo): void
    {
        if (filter_var($replyTo, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('The mail reply-to must be a valid email address.');
        }
    }
}
