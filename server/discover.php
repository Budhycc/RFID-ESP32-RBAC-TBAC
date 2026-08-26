<?php
// Endpoint khusus untuk fitur Auto-Discovery (Network Sweep) dari ESP32
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo json_encode([
    'status' => 'rfid_server_ok',
    'message' => 'RFID Server is running'
]);
