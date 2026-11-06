<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | Store third-party service credentials and provider configuration here.
    | Sensitive values should remain in the .env file and must never be
    | committed directly into source control.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Postmark
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],


    /*
    |--------------------------------------------------------------------------
    | Resend
    |--------------------------------------------------------------------------
    */

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],


    /*
    |--------------------------------------------------------------------------
    | Amazon SES
    |--------------------------------------------------------------------------
    */

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],


    /*
    |--------------------------------------------------------------------------
    | Slack
    |--------------------------------------------------------------------------
    */

    'slack' => [

        'notifications' => [

            'bot_user_oauth_token' =>
                env('SLACK_BOT_USER_OAUTH_TOKEN'),

            'channel' =>
                env('SLACK_BOT_USER_DEFAULT_CHANNEL'),

        ],

    ],


    /*
    |--------------------------------------------------------------------------
    | Restaurant AI / Chatbot
    |--------------------------------------------------------------------------
    |
    | The restaurant chatbot retrieves live restaurant information from the
    | database and then uses the configured AI provider to generate a natural
    | language response.
    |
    | Gemini is the default provider.
    |
    */

    'restaurant_ai' => [

        /*
        |--------------------------------------------------------------------------
        | Enable / Disable AI
        |--------------------------------------------------------------------------
        */

        'enabled' =>
            env(
                'RESTAURANT_AI_ENABLED',
                false
            ),


        /*
        |--------------------------------------------------------------------------
        | Active AI Provider
        |--------------------------------------------------------------------------
        */

        'provider' =>
            env(
                'RESTAURANT_AI_PROVIDER',
                'gemini'
            ),


        /*
        |--------------------------------------------------------------------------
        | Request Timeout
        |--------------------------------------------------------------------------
        */

        'timeout' =>
            (int) env(
                'RESTAURANT_AI_TIMEOUT',
                45
            ),


        /*
        |--------------------------------------------------------------------------
        | Temperature
        |--------------------------------------------------------------------------
        |
        | Keep this low because restaurant answers should stay grounded in
        | live database information.
        |
        */

        'temperature' =>
            (float) env(
                'RESTAURANT_AI_TEMPERATURE',
                0.2
            ),


        /*
        |--------------------------------------------------------------------------
        | Knowledge Retrieval Limit
        |--------------------------------------------------------------------------
        |
        | Maximum number of matching restaurant records retrieved from each
        | knowledge area before sending context to the AI provider.
        |
        */

        'knowledge_limit' =>
            (int) env(
                'RESTAURANT_AI_KNOWLEDGE_LIMIT',
                20
            ),


        /*
        |--------------------------------------------------------------------------
        | Gemini
        |--------------------------------------------------------------------------
        */

        'gemini' => [

            /*
            | Gemini API key.
            */

            'key' =>
                env('GEMINI_API_KEY'),


            /*
            | Gemini model.
            */

            'model' =>
                env(
                    'GEMINI_MODEL',
                    'gemini-2.5-flash'
                ),


            /*
            | Gemini API base URL.
            */

            'base_url' =>
                env(
                    'GEMINI_API_URL',
                    'https://generativelanguage.googleapis.com/v1beta'
                ),

        ],

    ],


    /*
    |--------------------------------------------------------------------------
    | SMS Provider
    |--------------------------------------------------------------------------
    */

    'sms' => [

        'provider' =>
            env(
                'SMS_PROVIDER',
                'africastalking'
            ),

        'enabled' =>
            env(
                'SMS_ENABLED',
                false
            ),

        'sender_id' =>
            env('SMS_SENDER_ID'),

        'api_url' =>
            env('SMS_API_URL'),

        'username' =>
            env('SMS_USERNAME'),

        'api_key' =>
            env('SMS_API_KEY'),

    ],


    /*
    |--------------------------------------------------------------------------
    | Africa's Talking
    |--------------------------------------------------------------------------
    */

    'africastalking' => [

        'username' =>
            env(
                'AFRICASTALKING_USERNAME',
                'sandbox'
            ),

        'api_key' =>
            env('AFRICASTALKING_API_KEY'),

        'sender_id' =>
            env('AFRICASTALKING_SENDER_ID'),

    ],


    /*
    |--------------------------------------------------------------------------
    | Twilio
    |--------------------------------------------------------------------------
    */

    'twilio' => [

        'sid' =>
            env('TWILIO_SID'),

        'token' =>
            env('TWILIO_AUTH_TOKEN'),

        'from' =>
            env('TWILIO_FROM'),

    ],


    /*
    |--------------------------------------------------------------------------
    | Flutterwave
    |--------------------------------------------------------------------------
    */

    'flutterwave' => [

        'public_key' =>
            env('FLUTTERWAVE_PUBLIC_KEY'),

        'secret_key' =>
            env('FLUTTERWAVE_SECRET_KEY'),

        'encryption_key' =>
            env('FLUTTERWAVE_ENCRYPTION_KEY'),

        'webhook_hash' =>
            env('FLUTTERWAVE_WEBHOOK_HASH'),

    ],


    /*
    |--------------------------------------------------------------------------
    | Pesapal
    |--------------------------------------------------------------------------
    */

    'pesapal' => [

        'consumer_key' =>
            env('PESAPAL_CONSUMER_KEY'),

        'consumer_secret' =>
            env('PESAPAL_CONSUMER_SECRET'),

        'base_url' =>
            env(
                'PESAPAL_BASE_URL',
                'https://pay.pesapal.com/v3'
            ),

        'callback_url' =>
            env('PESAPAL_CALLBACK_URL'),

        'ipn_url' =>
            env('PESAPAL_IPN_URL'),

    ],


    /*
    |--------------------------------------------------------------------------
    | MTN Mobile Money
    |--------------------------------------------------------------------------
    */

    'mtn_momo' => [

        'enabled' =>
            env(
                'MTN_MOMO_ENABLED',
                false
            ),

        'environment' =>
            env(
                'MTN_MOMO_ENVIRONMENT',
                'sandbox'
            ),

        'subscription_key' =>
            env('MTN_MOMO_SUBSCRIPTION_KEY'),

        'api_user' =>
            env('MTN_MOMO_API_USER'),

        'api_key' =>
            env('MTN_MOMO_API_KEY'),

        'currency' =>
            env(
                'MTN_MOMO_CURRENCY',
                'UGX'
            ),

        'callback_url' =>
            env('MTN_MOMO_CALLBACK_URL'),

    ],


    /*
    |--------------------------------------------------------------------------
    | Airtel Money
    |--------------------------------------------------------------------------
    */

    'airtel_money' => [

        'enabled' =>
            env(
                'AIRTEL_MONEY_ENABLED',
                false
            ),

        'environment' =>
            env(
                'AIRTEL_MONEY_ENVIRONMENT',
                'sandbox'
            ),

        'client_id' =>
            env('AIRTEL_MONEY_CLIENT_ID'),

        'client_secret' =>
            env('AIRTEL_MONEY_CLIENT_SECRET'),

        'country' =>
            env(
                'AIRTEL_MONEY_COUNTRY',
                'UG'
            ),

        'currency' =>
            env(
                'AIRTEL_MONEY_CURRENCY',
                'UGX'
            ),

        'callback_url' =>
            env('AIRTEL_MONEY_CALLBACK_URL'),

    ],

];