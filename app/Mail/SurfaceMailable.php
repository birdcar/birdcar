<?php

namespace App\Mail;

use App\Settings\SurfaceMailSettings;
use Illuminate\Mail\Mailable;
use LogicException;

/**
 * Mail that belongs to one surface. The surface, not the subclass, chooses the mailer and sender on every send.
 */
abstract class SurfaceMailable extends Mailable
{
    /**
     * The mailer name for this surface, from config.
     *
     * @throws LogicException when the configured mailer is blank or not defined in mail.mailers.
     */
    abstract public static function surfaceMailer(): string;

    abstract protected function senderSettings(): SurfaceMailSettings;

    /**
     * Runs before delivery resolves the mailer, so the surface mailer and settings sender always win over the subclass.
     */
    final public function build(): void
    {
        $settings = $this->senderSettings();
        $sender = $settings->sender();
        $replyTo = $settings->replyToAddress();

        $this->mailer(static::surfaceMailer());
        $this->from = [];
        $this->from($sender->address, $sender->name);

        if ($replyTo !== null) {
            $this->replyTo($replyTo->address, $replyTo->name);
        }
    }

    /**
     * @throws LogicException when the configured mailer is blank or not defined in mail.mailers.
     */
    protected static function configuredMailer(string $configKey): string
    {
        $mailer = config($configKey);

        if (! is_string($mailer) || trim($mailer) === '') {
            throw new LogicException("Configuration [{$configKey}] must name a mailer.");
        }

        $mailer = trim($mailer);

        if (! is_array(config("mail.mailers.{$mailer}"))) {
            throw new LogicException("Configuration [{$configKey}] names mailer [{$mailer}], which is not defined in [mail.mailers].");
        }

        return $mailer;
    }
}
