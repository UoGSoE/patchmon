<?php

return [
    'enabled' => env('SSO_ENABLED', true),
    'autocreate_new_users' => env('SSO_AUTOCREATE_NEW_USERS', true),
    'allow_students' => env('SSO_ALLOW_STUDENTS', true),
    'admins_only' => env('SSO_ADMINS_ONLY', false),
];
