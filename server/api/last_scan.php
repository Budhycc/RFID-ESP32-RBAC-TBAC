<?php

header("Content-Type: application/json");

$file = __DIR__ . "/last_uid.json";

if (!file_exists($file)) {

    echo json_encode([
        "uid" => "",
        "time" => ""
    ]);

    exit;
}

readfile($file); 