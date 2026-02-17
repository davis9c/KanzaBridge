<?php
header('Content-Type: application/json');
echo json_encode([
    'php_input' => file_get_contents('php://input'),
    'post' => $_POST,
    'server_ct' => $_SERVER['CONTENT_TYPE'] ?? null
]);
