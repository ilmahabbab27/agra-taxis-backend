<?php
header('Content-Type: application/json');

try {
    echo json_encode([
        'status' => 'ok',
        'message' => 'PHP is working',
        'vendor_path' => file_exists(__DIR__.'/../vendor/autoload.php') ? 'exists' : 'missing',
        'bootstrap_path' => file_exists(__DIR__.'/../bootstrap/app.php') ? 'exists' : 'missing'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
