@component('mail::message')
# Forget Password

You got this mail because you are asking for verification via email.

Enter this code to your application forgot reset form :

## {{$value['code']}}

@component('mail::button', ['url' => $url])
View Invoice
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent