<html>
<head>
    <title>{{$title}}</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css" integrity="sha384-WskhaSGFgHYWDcbwN70/dfYBj47jz9qbsMId/iRN3ewGhXQFZCSftd1LZCfmhktB" crossorigin="anonymous">
    <link rel="stylesheet" href="{{asset('vendor/ramenauth/css/app.css')}}">
    <style>
        .full-height{
            min-height: 100vh; 
        }
    </style>
</head>
<body>
    <div class="container full-height">
        <div class="col-sm">
        @yield('body')  
        </div>
    </div>
</body>
</html>