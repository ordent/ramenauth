@extends("ramenauth::app")

@section("body")
<div class="content">
<h1>Start Verify your Account</h1>
<form>
    <div class="form-group">
        <label for="">Enter Email, Phone or Username</label>
        <input type="text" class="form-control" id="formInput">
    </div>
    <div class="form-group">
        <button class="btn btn-primary btn-lg" role="button" id="formCheck">Check</button>
        <button class="btn btn-primary btn-lg" role="button" id="formStart">Start</button>
    </div>
</form>
</div>
@endsection