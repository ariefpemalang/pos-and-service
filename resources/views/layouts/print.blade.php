<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Cetak') - {{ brand_name() }}</title>
    <link rel="stylesheet" href="{{ asset('css/invoice-print.css') }}?v={{ filemtime(public_path('css/invoice-print.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/invoice-dotmatrix.css') }}?v={{ file_exists(public_path('css/invoice-dotmatrix.css')) ? filemtime(public_path('css/invoice-dotmatrix.css')) : time() }}">
    <style id="dynamic-page-size"></style>
    @stack('css')
</head>
<body class="print-body @yield('body-class', 'format-third') orient-portrait">
    @yield('content')
    @stack('js')
</body>
</html>
