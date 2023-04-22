<?php

require_once '../../utils/cors.php';
require_once '../../utils/constants.php';
require_once '../../utils/database.php';
require_once '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if (!preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
  echo json_encode(['error' => 'unauthorized']);
  http_response_code(405);
  exit(1);
}

$token = $matches[1];
if (!$token) {
  echo json_encode(['error' => 'invalid token']);
  http_response_code(405);
  exit(1);
}

try {
  $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS512'));
  $user_id = $decoded->id;

  // READ
  if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $query = "SELECT id, name, role FROM `users` WHERE id!=$user_id";
    $query_result = mysqli_query($conn, $query);
    $users = $query_result->fetch_all();

    $payload = array();
    foreach ($users as $key => $user) {
      array_push($payload, [
        'id' => intval($user[0]),
        'name' => $user[1],
        'role' => $user[2]
      ]);
    }

    echo json_encode([
      'data' => $payload
    ]);
    return http_response_code(200);
  }
} catch (Exception $e) {
  echo json_encode(['error' => $e->getMessage()]);
  exit(1);
}
