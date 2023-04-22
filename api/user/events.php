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
      $query = "SELECT * FROM `events` WHERE user_id=$user_id";
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

    case 'POST':
      $post_body = file_get_contents("php://input");

      if (isset($post_body) && !empty($post_body)) {
        $data = json_decode($post_body);
        $name = $data->name;
        $date = $data->date;
        $time = $data->time;

        $query = "INSERT INTO `events` (
          name, 
          date,
          time,
          user_id,
          updated_at
        ) VALUES (
          '$name',
          '$date',
          '$time',
          $user_id,
          CURRENT_TIMESTAMP
        )";

        if (mysqli_query($conn, $query)) {
          $event_id = $conn->insert_id;
          $event = mysqli_query($conn, "SELECT * FROM `events` WHERE id=$event_id")->fetch_row();

          echo json_encode([
            'data' => [
              'id'  => intval($event[0]),
              'name'  => $event[1],
              'date'  => $event[2],
              'time'  => $event[3],
            ],
            'message' => 'event added successfully'
          ]);
          return http_response_code(201);
        }
      }

    case 'DELETE':
      if (isset($_GET['event_id'])) {
        $event_id = intval($_GET['event_id']);
        $query = "DELETE FROM `events` WHERE id=$event_id";

        if (mysqli_query($conn, $query)) {
          echo json_encode([
            'id' => $event_id
          ]);
          return http_response_code(200);
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
