<?php

return [
    'manager' => 'AuthManager',
    'login_rules' => [
        'email' => 'requiredWithoutAll:phone,username',
        'username' => 'requiredWithoutAll:phone,email',
        'phone' => 'requiredWithoutAll:email,username',
        'password' => 'required'
    ],
    'check_rules' => [
        'type' => 'required',
        'username' => 'requiredWithoutAll:phone,email',
        'email' => 'requiredWithoutAll:phone,username',
        'phone' => 'requiredWithoutAll:email,username',
    ],
    'refresh_rules' => [
        'token' => 'required'
    ],
    'register_rules' => [
        'email' => 'required|unique:users.id', 
        'password' => 'required|min:6|confirmation'
    ],
    'roles_assign_rules' => [
        'role_name' => 'required_without:role_id', 
        'role_id' => 'required_without:role_name'
    ],
    'roles_remove_rules' => [
        'role_name' => 'required_without:role_id', 
        'role_id' => 'required_without:role_name'
    ],
    'permission_assign_rules' => [
        'permission_name' => 'required_without:permission_id', 
        'permission_id' => 'required_without:permission_name'
    ],
    'permission_remove_rules' => [
        'permission_name' => 'required_without:permission_id', 
        'permission_id' => 'required_without:permission_name'
    ],
    'verification_rules' => [
        'identity' => 'required'
    ],
    'model' => '\App\User',
    'uri' => '/users/',
    'controller' => '\Ordent\RamenAuth\Controllers\AuthController',
    'verification' => env('AUTH_VERIFICATION', false),
    'primary_verification' => env('PRIMARY_VERIFICATION', 'phone'),
    'sms_verification_vendor' => env('SMS_VERIFICATION_VENDOR', 'nexmo'),
    'sms_verification_request_url' => env('SMS_VERIFICATION_REQUEST_URL', 'https://api.nexmo.com/verify/json'),
    'sms_verification_verify_url' => env('SMS_VERIFICATION_VERIFY_URL', 'https://api.nexmo.com/verify/check/json '),
    'sms_verification_cancel_url' => env('SMS_VERIFICATION_CANCEL_URL', 'https://api.nexmo.com/verify/json'),
    'sms_verification_api_key' => env('SMS_VERIFICATION_API_KEY', env('NEXMO_KEY')),
    'sms_verification_api_secret' => env('SMS_VERIFICATION_API_SECRET', env('NEXMO_SECRET')),
    'sms_verification_title' => env('SMS_VERIFICATION_TITLE'),
];