<x-mail::message>
# {{ $purpose === 'reset' ? 'Reset your password' : 'Confirm your email' }}

Hi{{ $name ? ' '.$name : '' }},

{{ $purpose === 'reset'
    ? 'Use this code in the MedAlbumin app to choose a new password:'
    : 'Use this code in the MedAlbumin app to finish creating your account:' }}

<x-mail::panel>
<div style="font-size: 32px; font-weight: 700; letter-spacing: 8px; text-align: center;">{{ $code }}</div>
</x-mail::panel>

The code expires in {{ $minutes }} minutes. If you didn’t ask for it, you can ignore this email — nobody can sign in without it.

Thanks,<br>
The {{ config('app.name') }} team
</x-mail::message>
