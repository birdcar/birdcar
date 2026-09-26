<x-mail::message>
# You have been invited to Birdcar Admin

An operator invited this account to the root Admin role bundle, including Admin access, publishing author, and mail sender configuration capabilities.

Use the secure setup link below to set a password for this account.

<x-mail::button :url="$setupUrl">
Set Admin password
</x-mail::button>

{{ __('This password setup link will expire in :count minutes.', ['count' => $expiresInMinutes]) }}

If this account already has a password, it remains usable until you change it. Existing two-factor authentication and other access are not removed by this invitation.

@lang('Regards,')<br>
{{ config('app.name') }}

<x-slot:subcopy>
@lang(
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n".
    'into your web browser:',
    [
        'actionText' => 'Set Admin password',
    ]
) <span class="break-all">[{{ $setupUrl }}]({{ $setupUrl }})</span>
</x-slot:subcopy>
</x-mail::message>
