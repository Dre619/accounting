<?php

return [
    'bank_name'       => env('BANK_NAME', ''),
    'account_name'    => env('BANK_ACCOUNT_NAME', ''),
    'account_number'  => env('BANK_ACCOUNT_NUMBER', ''),
    'branch'          => env('BANK_BRANCH', ''),
    'swift_code'      => env('BANK_SWIFT_CODE', ''),
    'sort_code'       => env('BANK_SORT_CODE', ''),
    'mobile_money'    => env('BANK_MOBILE_MONEY', ''),
    'instructions'    => env('BANK_INSTRUCTIONS', 'Use your company name as the payment reference.'),
];
