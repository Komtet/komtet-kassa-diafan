<?php

if (!defined('DIAFAN')) {
    include dirname(__file__) . '/../../includes/404.php';
}

function sendResponse($statusCode, $message, array $headers = []) {
    switch ($statusCode) {
        case 200:
            $statusText = 'OK';
            break;
        case 400:
            $statusText = 'Bad Request';
            break;
        case 401:
            $statusText = 'Unauthorized';
            break;
        case 403:
            $statusText = 'Forbidden';
        case 405:
            $statusText = 'Method Not Allowed';
            break;
        case 500:
            $statusText = 'Internal Server Error';
            break;
        default:
            $statusCode = 500;
            $statusText = 'Internal Server Error';
            $headers = [];
            $message = 'Status code is not specified';
            break;
    }
    header(sprintf('HTTP/1.1 %s %s', $statusCode, $statusText));
    foreach ($headers as $key => $value) {
        header(sprintf('%s: %s', $key, $value));
    }
    echo $message;
    exit();
}

if (!array_key_exists('HTTP_X_HMAC_SIGNATURE', $_SERVER)) {
    sendResponse(401, 'HMAC Signature is not specified');
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    sendResponse(405, 'Method not allowed', ['Allow' => 'POST']);
}

$secretKey = $this->diafan->configmodules('secret_key', 'komtetkassa');
if (empty($secretKey)) {
    error_log('Unable to handle KOMTET Kassa report: secret key is not specified');
    sendResponse(500, 'KOMTET Kassa module is not configured');
}

$url = trim(BASE_PATH, '/') . $_SERVER['REQUEST_URI'];
$data = file_get_contents('php://input');
$signature = hash_hmac('md5', $_SERVER['REQUEST_METHOD'] . $url . $data, $secretKey);
if ($signature != $_SERVER['HTTP_X_HMAC_SIGNATURE']) {
    sendResponse(403, 'Invalid HMAC signature');
}

$data = json_decode($data, true);
if (!is_array($data)) {
    sendResponse(400, 'Payload is not a valid JSON object');
}

$errors = '';
foreach (['external_id', 'state'] as $key) {
    if (!array_key_exists($key, $data)) {
        $errors .= '"' . $key . '" is missing' . "\n";
    }
}
if ($errors) {
    sendResponse(400, $errors);
}

$orderID = $data['external_id'];
$success = $data['state'] == 'done';
$errorDescription = !$success ? $data['error_description'] : '';
$this->diafan->_komtetkassa->createReport($orderID, $success, $errorDescription);
sendResponse(200, 'SUCCESS');
