<x-mail::message>
@if(!empty($brandEmailLogoUrl))
<img src="{{ $brandEmailLogoUrl }}" alt="{{ config('app.name') }}" style="max-height:40px;margin-bottom:16px">
@endif
# You're invited

You've been invited to join **{{ $projectName }}** on {{ config('app.name') }}.

<x-mail::button :url="$acceptUrl">
Accept invitation
</x-mail::button>

This invitation link expires in 7 days. If you weren't expecting this, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
