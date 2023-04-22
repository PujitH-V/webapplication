<?php

$db_host = "localhost";
$db_user = "root";
$db_pass = "pass%";
$db_name = "tdg_vinay";

$conn = new mysqli(
  $db_host,
  $db_user,
  $db_pass,
  $db_name
);

if ($conn->connect_error)
  echo "connection error: " . $conn->connect_error;
