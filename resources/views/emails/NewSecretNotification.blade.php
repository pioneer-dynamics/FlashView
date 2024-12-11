<x-mail::message>
# You have received a new secret from {{ $user->email }}.

You have received an end-to-end encrypted secret that can be viewed by clicking the button below.

<x-mail::panel>
> NOTE: This secret is encrypted using a password. When you attempt to retrieve the secret, it will be destroyed from our server even if you enter the wrong password. So please make sure you have the correct password to decrypt the message. The sender would have shared the password with you separately.
</x-mail::panel>

> You get only one attempt at decrypting the message.

*If you do not identify the sender ({{ $user->email }}) and if you would like to report spam or abuse, email us the details at {{ config('support.abuse') }}*

<x-mail::button :url="$url">
View Secret
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
