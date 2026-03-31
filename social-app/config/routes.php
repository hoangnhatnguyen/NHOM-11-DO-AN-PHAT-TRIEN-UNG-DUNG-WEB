<?php

return [
    'GET' => [
        // ============ HOME & FEED ============
        '/' => 'HomeController@index',

        // ============ MESSAGES & CHAT ============
        '/messages' => 'MessageController@index',
        '/chat-api/bootstrap' => 'MessageController@apiBootstrap',
        '/chat-api/users' => 'MessageController@apiUsers',
        '/chat-api/users/{id}' => 'MessageController@apiUser',

        // ============ USERS & DISCOVERY ============
        '/users/finder' => 'UserController@finder',

        // ============ AUTHENTICATION ============
        '/login' => 'AuthController@showLogin',
        '/register' => 'AuthController@showRegister',
        '/forgot-password' => 'AuthController@showForgotPassword',
        '/reset-password/{token}' => 'AuthController@showResetPassword',

        // ========== SEARCH ==========
        '/search' => 'SearchController@index',
    ],

    'POST' => [
        // ============ AUTHENTICATION ============
        '/login' => 'AuthController@login',
        '/register' => 'AuthController@register',
        '/logout' => 'AuthController@logout',
        '/forgot-password' => 'AuthController@sendResetLink',
        '/reset-password/{token}' => 'AuthController@resetPassword',

        // ============ MESSAGES & CHAT ============
        '/chat-api/upload' => 'MessageController@apiUpload',
    ],

    
];
