<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

return [
    'GET' => [
        // ============ HOME & FEED ============
        '/' => 'HomeController@index',
        '/post/create' => 'PostController@create',
        '/post/{id}' => 'PostController@detail',

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
        '/media/view' => 'MessageController@mediaView',

        // ============ USERS & DISCOVERY ============
        '/users/finder' => 'UserController@finder',

        '/profile' => 'UserController@profileFromQuery',
        '/user/{username}' => 'UserController@profile',
        '/saved' => 'SavedPostController@index',
        '/admin' => 'AdminController@index',
        '/admin/users' => 'AdminUserController@index',
        '/admin/posts' => 'AdminPostController@index',

        '/settings' => 'SettingController@index',

        // ============ AJAX / USER API ============
        '/user-api/posts' => 'UserController@apiPosts',
        '/user-api/followers' => 'UserController@apiFollowers',
        '/user-api/following' => 'UserController@apiFollowing',
        '/user-api/activity' => 'UserController@apiActivity',
        '/user-api/search-badge' => 'UserController@searchBadge',

        // ============ NOTIFICATIONS ============
        '/notifications' => 'NotificationController@index',

        // ========== SEARCH ==========
        '/search' => 'SearchController@index',
        '/hashtag-api/search' => 'SearchController@apiHashtag',
    ],

    'POST' => [
        // ============ AUTHENTICATION ============
        '/post/{postId}/comment/{commentId}/reply' => 'PostController@reply',
        '/post/update/{id}' => 'PostController@update',
        '/post/{id}/like' => 'PostController@like',
        '/post/{id}/save' => 'PostController@save',
        '/post/{id}/share' => 'PostController@share',
        '/post/{id}/comment' => 'PostController@comment',
        '/post/{id}/delete' => 'PostController@delete',
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
        '/user-api/add-badge' => 'UserController@addBadge',
        '/user-api/notification-mark-read' => 'NotificationController@markReadApi',

        // ============ SETTINGS ============
        '/setting-api/update-privacy' => 'SettingController@updatePrivacy',
        '/setting-api/block' => 'SettingController@block',
        '/setting-api/unblock' => 'SettingController@unblock',
        '/saved/unsave' => 'SavedPostController@unsave',
        '/admin/users/toggle-status' => 'AdminUserController@toggleStatus',

        // ============ CHAT ============
        '/chat-api/upload' => 'MessageController@apiUpload',
    ]
];
