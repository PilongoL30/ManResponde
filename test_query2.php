<?php
require_once 'db_config.php';
$url = firestore_base_url() . ':runQuery';
$body = [
    'structuredQuery' => [
        'from' => [['collectionId' => 'ambulance_reports']],
        'where' => [
            'fieldFilter' => [
                'field' => ['fieldPath' => 'timestamp'],
                'op' => 'GREATER_THAN_OR_EQUAL',
                'value' => ['timestampValue' => '2020-01-01T00:00:00Z']
            ]
        ],
        'limit' => 2
    ]
];
$res = firestore_rest_request('POST', $url, $body);
print_r($res);
