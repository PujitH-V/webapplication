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
      $query = "SELECT `id`, `title`, `description` FROM `advertisements`";

      $query_result = mysqli_query($conn, $query);
      $advertisements = $query_result->fetch_all();

      $payload = array();
      foreach ($advertisements as $key => $advertisement) {
        array_push($payload, [
          'id'  => intval($advertisement[0]),
          'title'  => $advertisement[1],
          'description'  => $advertisement[2]
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
