@php /** @var string $email */ @endphp
@php /** @var string $exportUrl */ @endphp

<x-mail::message>
# {{ __('Your Personal Data Export') }}

{{ __('Hello') }},

{{ __('We received a request to export the personal data associated with :email.', ['email' => $email]) }}

{{ __('Click the button below to download your data.') }}

<x-mail::button :url="$exportUrl">
{{ __('Download My Data') }}
</x-mail::button>

{{ __('This link will expire in 24 hours.') }}

{{ __('If you did not request this, please ignore this email.') }}
</x-mail::message>
