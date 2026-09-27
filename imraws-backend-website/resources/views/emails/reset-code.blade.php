@component('mail::message')
# Your password reset code

Use this code to choose a new password for your IMRAWS account:

**{{ $code }}**

It expires in {{ $expiresInMinutes }} minutes. If you did not ask to reset your
password you can ignore this email — nothing has changed.

If you did not request this, or you did not expect to receive it, no action is
needed.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
