<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel CORS
    |--------------------------------------------------------------------------
    |
    | allowedOrigins, allowedHeaders and allowedMethods can be set to array('*')
    | to accept any value.
    |
    */
   
    'supportsCredentials' => false,
    'allowedOrigins' => ['http://192.168.1.232:3000', 'http://192.168.1.232:8000', 'http://192.168.1.241:3000', 'http://192.168.1.241:8000', 'http://hospitall.local', 'https://support.hospitall.tech', 'https://support.hospitall.tech:8000', 'http://localhost:3000'],
    'allowedOriginsPatterns' => [],
    'allowedHeaders' => ['*'],
    'allowedMethods' =>  ['GET', 'POST', 'PUT',  'DELETE'], //['*'],
    'exposedHeaders' => [],
    'maxAge' => 0,

];
