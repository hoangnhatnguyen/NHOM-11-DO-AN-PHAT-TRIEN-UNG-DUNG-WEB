<?php

return [
    'GET' => [
        '/' => 'HomeController@index',
        '/newfeed' => 'HomeController@newfeed',

        '/admin' => 'AdminController@index',
        '/admin/posts' => 'AdminPostController@index',
        '/admin/posts/delete/{id}' => 'AdminPostController@destroy',

        '/admin/users' => 'AdminUserController@index',
        '/admin/users/edit/{id}' => 'AdminUserController@edit',
        '/admin/users/delete/{id}' => 'AdminUserController@destroy',
    ],

    // Route dung cho moi HTTP method
    'ANY' => [
        // '/health' => 'HomeController@index',
    ],
];
