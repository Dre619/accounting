<?php

return [
    'public_key'  => env('LENCO_PUBLIC_KEY', ''),
    'secret_key'  => env('LENCO_SECRET_KEY', ''),
    'base_url'    => env('LENCO_BASE_URL', 'https://api.lenco.co/access/v1'),
    'verify_ssl'  => env('LENCO_VERIFY_SSL', true),
];
