<?php

require_once '../../utils/cors.php';
require_once '../../utils/constants.php';
require_once '../../utils/database.php';
require_once '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if (!preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
  echo json_encode(['error' => 'unauthorized']);
  return http_response_code(401);
}

$token = $matches[1];
if (!$token) {
  echo json_encode(['error' => 'invalid token']);
  return http_response_code(400);
}

try {
  $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS512'));
  $user_id = $decoded->id;

  switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
      $query = "SELECT * FROM `events`";
      $query_result = mysqli_query($conn, $query);
      $events = $query_result->fetch_all();

      $payload = array();
      foreach ($events as $key => $event) {
        array_push($payload, [
          'id'  => intval($event[0]),
          'name'  => $event[1],
          'date'  => $event[2],
          'time'  => $event[3],
        ]);
      }
      echo json_encode([
        'data' => $payload
      ]);

      return http_response_code(200);

    default:
      echo json_encode([
        'message' => 'invalid method'
      ]);
      return http_response_code(405);
  }
} catch (Exception $e) {
  echo json_encode(['error' => $e->getMessage()]);
  exit(1);
}
