<?php

return [
    'GET' => [
        '/' => 'HomeController@index',
        '/post/create' => 'PostController@create',
        '/post/{id}' => 'PostController@detail',
        '/login' => 'AuthController@showLogin',
        '/register' => 'AuthController@showRegister',
        '/forgot-password' => 'AuthController@showForgotPassword',
        '/reset-password/{token}' => 'AuthController@showResetPassword',
    ],
    'POST' => [
        '/post/{postId}/comment/{commentId}/reply' => 'PostController@reply',
        '/post/{id}/like' => 'PostController@like',
        '/post/{id}/save' => 'PostController@save',
        '/post/{id}/share' => 'PostController@share',
        '/post/{id}/comment' => 'PostController@comment',
        '/login' => 'AuthController@login',
        '/register' => 'AuthController@register',
        '/logout' => 'AuthController@logout',
        '/forgot-password' => 'AuthController@sendResetLink',
        '/reset-password/{token}' => 'AuthController@resetPassword',
    ],
];
