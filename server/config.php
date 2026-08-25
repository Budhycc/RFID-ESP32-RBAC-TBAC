<?php

$host = "localhost";
$user = "root";
$pass = "1234567890";
$db   = "rfid";

$conn = new mysqli($host,$user,$pass,$db);

if($conn->connect_error){
    die("Koneksi gagal : ".$conn->connect_error);
}

// Sinkronisasi Timezone MySQL dengan PHP (WITA / +08:00)
$conn->query("SET time_zone = '+08:00'");

$conn->set_charset("utf8");