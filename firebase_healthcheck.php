<?php
require_once __DIR__ . '/db_config.php';

header('Content-Type: application/json');

$result = [
    'ok' => false,
    'timestamp' => date('c'),
    'expected' => [
        'project_id' => 'ibantayv2',
        'service_account_domain' => '@ibantayv2.iam.gserviceaccount.com',
    ],
    'checks' => [],
];

try {
    $path = service_account_path();
    $result['checks']['service_account_path'] = $path;
    $result['checks']['service_account_file_exists'] = is_file($path);

    $config = service_account_config();
    $result['checks']['project_id'] = $config['project_id'] ?? null;
    $result['checks']['client_email'] = $config['client_email'] ?? null;
    $result['checks']['private_key_id'] = $config['private_key_id'] ?? null;
    $result['checks']['project_id_matches_expected'] = (($config['project_id'] ?? '') === $result['expected']['project_id']);
    $result['checks']['client_email_matches_project_domain'] = (is_string($config['client_email'] ?? null) && substr($config['client_email'], -strlen($result['expected']['service_account_domain'])) === $result['expected']['service_account_domain']);
    $result['checks']['private_key_present'] = !empty($config['private_key']);

    $privateKeyParsed = openssl_pkey_get_private($config['private_key'] ?? '');
    $result['checks']['private_key_parse'] = (bool)$privateKeyParsed;

    try {
        $token = firestore_rest_token();
        $result['checks']['oauth_token_exchange'] = !empty($token);
    } catch (Throwable $tokenError) {
        $result['checks']['oauth_token_exchange'] = false;
        $result['checks']['oauth_error'] = $tokenError->getMessage();
    }

    $result['ok'] =
        !empty($result['checks']['service_account_file_exists'])
        && !empty($result['checks']['project_id_matches_expected'])
        && !empty($result['checks']['client_email_matches_project_domain'])
        && !empty($result['checks']['private_key_parse'])
        && !empty($result['checks']['oauth_token_exchange']);

    if (empty($result['checks']['project_id_matches_expected']) || empty($result['checks']['client_email_matches_project_domain'])) {
        $result['checks']['identity_hint'] = 'Credential file does not match the expected Firebase project/account identity.';
    }

    if (!$result['ok']) {
        http_response_code(500);
    }
} catch (Throwable $e) {
    http_response_code(500);
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
