<!doctype html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Welcome To HospitAll</title>

        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css?family=Raleway:100,600" rel="stylesheet" type="text/css">
        <link href="{{ asset('css/bootstrap.css') }}" rel="stylesheet" type="text/css">
        <script src="{{ asset('js/jquery-3.3.1.min.js') }}" type="text/javascript" ></script>
        <script src="{{ asset('js/bootstrap.min.js') }}" type="text/javascript" ></script>
    
    </head>
    <body>
        <div class="flex-center position-ref full-height">
            @yield('content')
            
        </div>
    </body>
</html>
