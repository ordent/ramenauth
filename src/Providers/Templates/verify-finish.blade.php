@extends("ramenauth::app")

@section("body")
<div class="jumbotron">
  <h1 class="display-4">Congratulation, Verification Successful</h1>
  <p class="lead">You have successfully verify your account, please click the button below to start login </p>
  @if(isset($loginURL))
    <a class="btn btn-primary btn-lg" href="{{$loginURL}}" role="button">Login</a>
  @endif
</div>
@endsection