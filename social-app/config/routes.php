<?php

return [
    'GET' => [
        '/' => 'HomeController@index',
        '/login' => 'AuthController@showLogin',
        '/register' => 'AuthController@showRegister',
        '/forgot-password' => 'AuthController@showForgotPassword',
        '/reset-password/{token}' => 'AuthController@showResetPassword',
    ],
    'POST' => [
        '/login' => 'AuthController@login',
        '/register' => 'AuthController@register',
        '/logout' => 'AuthController@logout',
        '/forgot-password' => 'AuthController@sendResetLink',
        '/reset-password/{token}' => 'AuthController@resetPassword',
    ],
];
