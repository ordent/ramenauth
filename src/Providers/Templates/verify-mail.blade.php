@component('mail::message')
# Email Verification

You got this mail because you are asking for verification via email.

Enter this code to your application verification form 

## {{$value['code']}} {style=text-align:center}

Or you can use this button to go to native verification form.

@component('mail::button', ['url' => $url])
    Verify Email
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent