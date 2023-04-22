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
          $query = "SELECT sm.id, u.name, u.email, u.phone FROM `security_manager_members` AS sm
                    JOIN `users` AS u WHERE u.id = sm.member_id 
                    AND u.role = 'visitor' AND sm.adder_id = $user_id";
        }
        if ($_GET['role'] == 'security') {
          $query = "SELECT sm.id, u.name, u.email, u.phone FROM `security_manager_members` AS sm
                    JOIN `users` AS u WHERE u.id = sm.member_id 
                    AND u.role = 'security' AND sm.adder_id = $user_id";
        }
      } else {
        $query = "SELECT sm.id, u.name, u.email, u.phone FROM `security_manager_members` AS sm
                  JOIN `users` AS u WHERE u.id = sm.member_id 
                  AND u.role = 'resident' AND sm.adder_id = $user_id";
      }

      $query_result = mysqli_query($conn, $query);
      $security_manager_members = $query_result->fetch_all();

      $payload = array();
      foreach ($security_manager_members as $key => $security_manager_member) {
        array_push($payload, [
          'id'  => intval($security_manager_member[0]),
          'name'  => $security_manager_member[1],
          'email'  => $security_manager_member[2],
          'phone'  => $security_manager_member[3],
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
          if ($_GET['role'] == 'security') {
            $get_user_query = "SELECT id from `users` WHERE email='$email' AND role='security'";
          }
        } else {
          $get_user_query = "SELECT id from `users` WHERE email='$email' AND role='resident'";
        }

        $get_user_result = mysqli_query($conn, $get_user_query);
        if (mysqli_num_rows($get_user_result) > 0) {
          $member_id = $get_user_result->fetch_row()[0];

          // checking if the user is already registered
          $alt_query = "SELECT * FROM `security_manager_members` WHERE member_id=$member_id
                        AND adder_id=$user_id";
          if (mysqli_num_rows(mysqli_query($conn, $alt_query)) > 0) {
            echo json_encode([
              'error' => 'member already registered.'
            ]);
            return http_response_code(400);
          }

          $query = "INSERT INTO `security_manager_members` (
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
            $entry_query = "SELECT sm.id, u.name, u.email, u.phone FROM `security_manager_members` 
                            AS sm JOIN `users` AS u WHERE u.id = sm.member_id AND sm.id=$entry_id";

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
        $query = "DELETE FROM `security_manager_members` WHERE id=$membership_id";

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
