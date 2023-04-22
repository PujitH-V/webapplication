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
      $query = "SELECT `id`, `title`, `description` FROM `advertisements` 
                WHERE `adder_id`=$user_id";

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

    case 'POST':
      $post_body = file_get_contents("php://input");

      if (isset($post_body) && !empty($post_body)) {
        $data = json_decode($post_body);
        $title = $data->title;
        $description = $data->description;

        $query = "INSERT INTO `advertisements` (
            `title`, 
            `description`,
            `adder_id`,
            `updated_at`
          ) VALUES (
            '$title',
            '$description',
            $user_id,
            CURRENT_TIMESTAMP
          )";

        if (mysqli_query($conn, $query)) {
          $entry_id = $conn->insert_id;
          $entry_query = "SELECT `id`, `title`, `description` FROM `advertisements` 
                          WHERE `id`=$entry_id";

          $entry = mysqli_query(
            $conn,
            $entry_query
          )->fetch_row();

          echo json_encode([
            'data' => [
              'id'  => intval($entry[0]),
              'title'  => $entry[1],
              'description'  => $entry[2]
            ],
            'message' => 'advertisement added successfully'
          ]);
          return http_response_code(201);
        }
      }

    case 'DELETE':
      if (isset($_GET['ad_id'])) {
        $ad_id = intval($_GET['ad_id']);
        $query = "DELETE FROM `advertisements` WHERE id=$ad_id";

        if (mysqli_query($conn, $query)) {
          echo json_encode([
            'id' => $ad_id
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
