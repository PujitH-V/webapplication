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
      $query = '';
      if (isset($_GET['role']) && !empty($_GET['role'])) {
        if ($_GET['role'] == 'visitor') {
          $query = "SELECT gm.id, u.name, u.email, u.phone FROM `garden_members` AS gm
                    JOIN `users` AS u WHERE u.id = gm.member_id 
                    AND u.role = 'visitor' AND gm.adder_id = $user_id";
        }
      } else {
        $query = "SELECT gm.id, u.name, u.email, u.phone FROM `garden_members` AS gm
                  JOIN `users` AS u WHERE u.id = gm.member_id 
                  AND u.role = 'resident' AND gm.adder_id = $user_id";
      }

      $query_result = mysqli_query($conn, $query);
      $garden_members = $query_result->fetch_all();

      $payload = array();
      foreach ($garden_members as $key => $garden_member) {
        array_push($payload, [
          'id'  => intval($garden_member[0]),
          'name'  => $garden_member[1],
          'email'  => $garden_member[2],
          'phone'  => $garden_member[3],
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
        $email = $data->email;

        // checking if the user exists
        $get_user_query = '';
        if (isset($_GET['role']) && !empty($_GET['role'])) {
          if ($_GET['role'] == 'visitor') {
            $get_user_query = "SELECT id from `users` WHERE email='$email' AND role='visitor'";
          }
        } else {
          $get_user_query = "SELECT id from `users` WHERE email='$email' AND role='resident'";
        }

        $get_user_result = mysqli_query($conn, $get_user_query);
        if (mysqli_num_rows($get_user_result) > 0) {
          $member_id = $get_user_result->fetch_row()[0];

          // checking if the user is already registered
          $alt_query = "SELECT * FROM `garden_members` WHERE member_id=$member_id
                        AND adder_id=$user_id";
          if (mysqli_num_rows(mysqli_query($conn, $alt_query)) > 0) {
            echo json_encode([
              'error' => 'member already registered.'
            ]);
            return http_response_code(400);
          }

          $query = "INSERT INTO `garden_members` (
            member_id, 
            adder_id,
            updated_at
          ) VALUES (
            $member_id,
            $user_id,
            CURRENT_TIMESTAMP
          )";

          if (mysqli_query($conn, $query)) {
            $entry_id = $conn->insert_id;
            $entry_query = "SELECT gm.id, u.name, u.email, u.phone FROM `garden_members` AS gm
                            JOIN `users` AS u WHERE u.id = gm.member_id 
                            AND gm.id=$entry_id";
            $entry = mysqli_query(
              $conn,
              $entry_query
            )->fetch_row();

            echo json_encode([
              'data' => [
                'id'  => intval($entry[0]),
                'name'  => $entry[1],
                'email'  => $entry[2],
                'phone'  => $entry[3],
              ],
              'message' => 'member added successfully'
            ]);
            return http_response_code(201);
          }
        } else {
          echo json_encode([
            'error' => "user doesn't exist"
          ]);

          return http_response_code(400);
        }
      }

    case 'DELETE':
      if (isset($_GET['membership_id'])) {
        $membership_id = intval($_GET['membership_id']);
        $query = "DELETE FROM `garden_members` WHERE id=$membership_id";

        if (mysqli_query($conn, $query)) {
          echo json_encode([
            'id' => $membership_id
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
