<?php
session_start();
require_once 'db_config.php';
$url = firestore_base_url() . ':runQuery';
$body = [
    'structuredQuery' => [
        'from' => [['collectionId' => 'ambulance_reports']],
        'limit' => 2
    ]
];
$res = firestore_rest_request('POST', $url, $body);
print_r($res);
