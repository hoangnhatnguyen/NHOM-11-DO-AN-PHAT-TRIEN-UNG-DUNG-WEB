<?php
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); 
error_reporting(E_ALL);

return [
    'GET' => [
        // ============ HOME & FEED ============
        '/' => 'HomeController@index',

        // ============ AUTHENTICATION ============
        '/login' => 'AuthController@showLogin',
        '/register' => 'AuthController@showRegister',
        '/forgot-password' => 'AuthController@showForgotPassword',
        '/reset-password/{token}' => 'AuthController@showResetPassword',

        // ============ MESSAGES & CHAT ============
        '/messages' => 'MessageController@index',

        // Chat API 
        '/chat-api/bootstrap' => 'MessageController@apiBootstrap',
        '/chat-api/users' => 'MessageController@apiUsers',
        '/chat-api/users/{id}' => 'MessageController@apiUser',

        // ============ USERS & DISCOVERY ============
        '/users/finder' => 'UserController@finder',

        '/user/{username}' => 'UserController@profile',

        '/settings' => 'SettingController@index',

        // ============ AJAX / USER API ============
        '/user-api/posts' => 'UserController@apiPosts',
        '/user-api/followers' => 'UserController@apiFollowers',
        '/user-api/following' => 'UserController@apiFollowing',
        '/user-api/activity' => 'UserController@apiActivity',

        '/hashtag-api/search' => 'SearchController@apiHashtag',
    ],

    'POST' => [
        // ============ AUTHENTICATION ============
        '/login' => 'AuthController@login',
        '/register' => 'AuthController@register',
        '/logout' => 'AuthController@logout',

        // Forgot & Reset Password
        '/forgot-password' => 'AuthController@sendResetLink',
        '/reset-password/{token}' => 'AuthController@resetPassword',

        // ============ USERS & DISCOVERY ============
        '/user-api/follow' => 'UserController@apiFollow',   

        // USER API 
        '/user-api/update-profile' => 'UserController@updateProfile',
        '/user-api/upload-avatar' => 'UserController@uploadAvatar',
        '/user-api/remove-follower' => 'UserController@removeFollower',
        '/user-api/unfollow' => 'UserController@unfollow',
        '/user-api/remove-badge' => 'UserController@removeBadge',
        '/user-api/search-badge' => 'UserController@searchBadge',
        '/user-api/add-badge' => 'UserController@addBadge',

        // ============ SETTINGS ============
        '/setting-api/update-privacy' => 'SettingController@updatePrivacy',
        '/setting-api/unblock' => 'SettingController@unblock',

        // ============ CHAT ============
        '/chat-api/upload' => 'MessageController@apiUpload',  
    ]
];