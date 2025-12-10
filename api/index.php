<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../../includes/CoreFunction.php';
$db = composables('useDB')['connect']();
header('Content-Type: application/json; charset=utf-8');

$arr=[
    "status"=>'success',
    "msg"=>'Hello world'
];
echo json_encode($arr, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE);