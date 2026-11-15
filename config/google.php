<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google Sheets & Drive Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for Google Sheets API v4 and Google Drive API v3.
    | Superadmin can also change these dynamically from the web interface.
    |
    */

    'spreadsheet_id' => env('GOOGLE_SPREADSHEET_ID', '14-xI_m24eXSj1L2aiX_gytMWrrS9HnjH5HzcRgWV_9g'),

    'drive_folder_id' => env('GOOGLE_DRIVE_FOLDER_ID', '1zB2loWV14fCpLWMCnNiEa4dVKAvhLZYE'),

    'appscript_url' => env('GOOGLE_APPSCRIPT_URL', 'https://script.google.com/macros/s/AKfycbwDK80WrKfr7iKOp7awaqhf1ezP7A-xREMbbSuuaGMlhHJJrPuGF5VXHnedi8v2ZIRE/exec'),

    'service_account_path' => env('GOOGLE_SERVICE_ACCOUNT_JSON', storage_path('app/google-service-account.json')),

    'service_account_json' => env('GOOGLE_SERVICE_ACCOUNT_RAW', null),

    'cache_ttl' => env('GOOGLE_CACHE_TTL', 60), // seconds
];

