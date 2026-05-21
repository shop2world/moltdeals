<?php
return [
    'host' => 'smtp.gmail.com',
    'port' => 465,
    'encryption' => 'ssl',
    'username' => env('SMTP_USERNAME', 'orangedigm@gmail.com'),
    'password' => env('SMTP_PASSWORD'),
    'from_email' => 'orangedigm@gmail.com',
    'from_name' => 'MoltDeals',
];
