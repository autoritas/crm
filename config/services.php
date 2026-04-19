<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Stockflow Core: clave maestra compartida entre apps del ecosistema
    // (se usa para cifrar/descifrar el secreto TOTP en stockflow_users.two_factor_secret)
    'stockflow' => [
        'app_key' => env('STOCKFLOW_APP_KEY'),
        // ID del CRM en stockflow_applications. Distinto por entorno:
        //   - desarrollo  (crm.klipea.com)    -> 11
        //   - produccion  (crm.app-util.com)  -> 10
        'app_id' => (int) env('STOCKFLOW_APP_ID', 10),
    ],

    // PLACSP — dos caminos de acceso, el primero que tenga configuracion
    // activa se usa. El provider web (user/password) es el camino rapido;
    // el de mTLS (cert .p12) es el oficial, pendiente de alta admin.
    'placsp' => [
        // Acceso via cert digital (sindicacion oficial). Requiere alta admin.
        'cert_path'     => env('PLACSP_CERT_PATH', storage_path('app/certs/placsp.p12')),
        'cert_password' => env('PLACSP_CERT_PASSWORD'),

        // Acceso via portal web con user/password (alternativa hasta tener
        // el cert autorizado). NUNCA committear estas credenciales.
        'user'     => env('PLACSP_USER'),
        'password' => env('PLACSP_PASSWORD'),
    ],

];
