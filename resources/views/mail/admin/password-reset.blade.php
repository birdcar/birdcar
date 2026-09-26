<x-mail::message>
# Reset your Birdcar Admin password

You are receiving this email because we received a password reset request for your Birdcar Admin account.

<x-mail::button :url="$resetUrl">
Reset password
</x-mail::button>

{{ __('This password reset link will expire in :count minutes.', ['count' => $expiresInMinutes]) }}

If you did not request a password reset, no further action is required.

@lang('Regards,')<br>
{{ config('app.name') }}

<x-slot:subcopy>
@lang(
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n".
    'into your web browser:',
    [
        'actionText' => 'Reset password',
    ]
) <span class="break-all">[{{ $resetUrl }}]({{ $resetUrl }})</span>
</x-slot:subcopy>
</x-mail::message>
