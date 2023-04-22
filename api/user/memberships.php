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
      if (isset($_GET['added_by']) && !empty($_GET['added_by'])) {
        if ($_GET['added_by'] == 'garden_manager') {
          $query = "SELECT id FROM `garden_members` WHERE member_id=$user_id";
        }
      } else {
        $query = "SELECT id FROM `pool_members` WHERE member_id=$user_id";
      }

      $query_result = mysqli_query($conn, $query);
      $memberships = $query_result->fetch_all();

      $payload = array();
      foreach ($memberships as $key => $membership) {
        array_push($payload, [
          'id'  => intval($membership[0]),
        ]);
      }
      echo json_encode([
        'data' => $payload
      ]);

      return http_response_code(200);

    case 'DELETE':
      if (isset($_GET['membership_id'])) {
        $membership_id = intval($_GET['membership_id']);

        $query = '';
        if (isset($_GET['added_by']) && !empty($_GET['added_by'])) {
          if ($_GET['added_by'] == 'garden_manager') {
            $query = "DELETE FROM `garden_members` WHERE id=$membership_id";
          }
        } else {
          $query = "DELETE FROM `pool_members` WHERE id=$membership_id";
        }

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
