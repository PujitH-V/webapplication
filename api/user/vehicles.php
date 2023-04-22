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
      $query = "SELECT * FROM `vehicles` WHERE user_id=$user_id";
      $query_result = mysqli_query($conn, $query);
      $vehicles = $query_result->fetch_all();

      $payload = array();
      foreach ($vehicles as $key => $vehicle) {
        array_push($payload, [
          'id'  => $vehicle[0],
          'license'  => $vehicle[2],
          'registration'  => $vehicle[3],
        ]);
      }

      echo json_encode([
        'data' => $payload
      ]);
      return http_response_code(200);

    case 'POST':
      $post_body = file_get_contents("php://input");

      if (isset($post_body) && !empty($post_body)) {
        $data = json_decode($post_body);
        $license = $data->license;
        $registration = $data->registration;

        // check if vehicle already exists
        $check_registration = "SELECT * FROM `vehicles` WHERE registration='$registration'";
        $result_check_registration = mysqli_query($conn, $check_registration);
        if (mysqli_num_rows($result_check_registration) > 0) {
          echo json_encode(['error' => 'vehicle already registered']);
          return http_response_code(400);
        }

        $query = "INSERT INTO `vehicles` (
          license, 
          registration,
          user_id,
          updated_at
        ) VALUES (
          '$license',
          '$registration',
          $user_id,
          CURRENT_TIMESTAMP
        )";

        if (mysqli_query($conn, $query)) {
          $vehicle_id = $conn->insert_id;
          $vehicle = mysqli_query($conn, "SELECT * FROM `vehicles` WHERE id=$vehicle_id")->fetch_row();

          echo json_encode([
            'data' => [
              'id' => $vehicle[0],
              'license' => $vehicle[2],
              'registration' => $vehicle[3]
            ],
            'message' => 'vehicle registered successfully'
          ]);
          return http_response_code(201);
        }
      }

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
