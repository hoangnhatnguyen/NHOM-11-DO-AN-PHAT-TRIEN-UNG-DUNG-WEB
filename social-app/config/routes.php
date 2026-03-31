<?php
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); 
error_reporting(E_ALL);
return [
    'GET' => [
    '/' => 'HomeController@index',

    '/login' => 'AuthController@showLogin',
    '/register' => 'AuthController@showRegister',

    '/messages' => 'MessageController@index',

    '/users/finder' => 'UserController@finder',

    '/user/{username}' => 'UserController@profile',

    '/settings' => 'SettingController@index',

    // AJAX
    '/user-api/posts' => 'UserController@apiPosts',
        '/user-api/followers' => 'UserController@apiFollowers',
        '/user-api/following' => 'UserController@apiFollowing',
        '/user-api/activity' => 'UserController@apiActivity',

        '/hashtag-api/search' => 'SearchController@apiHashtag',
    ],

    'POST' => [
        '/login' => 'AuthController@login',
        '/register' => 'AuthController@register',
        '/logout' => 'AuthController@logout',

        // USER
        '/user-api/update-profile' => 'UserController@updateProfile',
        '/user-api/upload-avatar' => 'UserController@uploadAvatar',
        '/user-api/remove-follower' => 'UserController@removeFollower',
        '/user-api/unfollow' => 'UserController@unfollow',
        '/user-api/remove-badge' => 'UserController@removeBadge',
'/user-api/search-badge' => 'UserController@searchBadge',
'/user-api/add-badge' => 'UserController@addBadge',
        // SETTINGS
        '/setting-api/update-privacy' => 'SettingController@updatePrivacy',
        '/setting-api/unblock' => 'SettingController@unblock',
    ]
];
