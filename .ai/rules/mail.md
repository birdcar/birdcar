---
paths:
  - '{app/Listeners/RejectUnscopedMail.php,app/Notifications/**,app/Settings/*MailSettings.php,config/mail.php,app/Mail/**}'
---

# Mail

## Surface-scoped mail
Every email is a Notification whose toMail() returns a class extending its surface's Mailable (AdminMailable or MarketingMailable). Never use Mail::, MailMessage, PendingMail, the mail contracts or PHP mail(); only InviteAdministrator may touch MailManager, to validate transports. tests/Arch/MailTest.php enforces this for PSR-4 app code but cannot see the global \Mail alias, routes, config, Blade or Livewire single-file components, so the RejectUnscopedMail MessageSending listener is the backstop: in every environment it throws on any send that is not a SurfaceMailable on its own surfaceMailer().
Never set from in envelope(): the sender comes from {Surface}MailSettings, and an envelope from with the same address still replaces the display name. Mailables never implement ShouldQueue; queued notifications implement ShouldQueueAfterCommit and use #[Queue]/#[Connection] for placement.
A new surface needs a mailer in config/mail.php, BIRDCAR_{SURFACE}_MAIL_MAILER, sender domains, a settings class and a base Mailable. Framework or package mail (stock auth notifications, backup, health) is blocked by design; wrap it in a surface notification.
