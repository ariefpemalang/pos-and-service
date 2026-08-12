<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mobile / External API Key
    |--------------------------------------------------------------------------
    |
    | Setiap request ke endpoint API harus mengirim header X-API-Key
    | yang cocok dengan nilai ini.
    |
    */

    'key' => env('API_KEY', 'pos-service-mobile-dev-key'),

    /*
    |--------------------------------------------------------------------------
    | Token abilities & name
    |--------------------------------------------------------------------------
    */

    'token_name' => env('API_TOKEN_NAME', 'mobile'),

];
