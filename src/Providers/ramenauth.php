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
    'controller' => '\Ordent\RamenAuth\Controllers\AuthController'
];