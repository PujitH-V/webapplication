<?php

require_once '../utils/cors.php';
require_once '../utils/constants.php';
require_once '../utils/database.php';
require_once '../vendor/autoload.php';

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
    $query = "SELECT * FROM users WHERE id=$user_id";
    $query_result = mysqli_query($conn, $query);
    $user = $query_result->fetch_row();
    echo json_encode([
      'data' => [
        'id' => intval($user[0]),
        'name' => $user[1],
        'email' => $user[2],
        'phone' => $user[3],
        'role' => $user[4]
      ]
    ]);
    http_response_code(200);
    exit(0);
  }
  // UPDATE
  else if ($_SERVER['REQUEST_METHOD'] == 'PATCH') {
    $post_body = file_get_contents('php://input');

    if (isset($post_body) && !empty($post_body)) {
      $data = json_decode($post_body);
      $name = $data->name;
      $email = $data->email;
      $phone = $data->phone;
      $password = $data->password;

      // validations
      $errors = array();
      if (!isset($name))
        array_push($errors, ['name' => 'name is a required field']);
      if (!isset($email))
        array_push($errors, ['email' => 'email is a required field']);
      if (!isset($phone))
        array_push($errors, ['phone' => 'phone is a required field']);
      if (!isset($password))
        array_push($errors, ['password' => 'password is a required field']);

      if (count($errors) > 0) {
        echo json_encode(['error' => $errors]);
        http_response_code(400);
        exit(1);
      }

      $hashed_password = password_hash($password, PASSWORD_BCRYPT);
      $query = "UPDATE users 
                SET name='$name',
                    email='$email',
                    phone='$phone',
                    password='$hashed_password',
                    updated_at=CURRENT_TIMESTAMP
                WHERE id=$user_id
            ";

      if (mysqli_query($conn, $query)) {
        $updated_user = mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id")->fetch_row();
        echo json_encode([
          'data' => [
            'id' => $updated_user[0],
            'name' => $updated_user[1],
            'email' => $updated_user[2],
            'phone' => $updated_user[3],
            'role' => $updated_user[4]
          ],
          'message' => 'user updated successfully'
        ]);
        http_response_code(200);
        exit(0);
      }
    }
  }
} catch (Exception $e) {
  echo json_encode(['error' => $e->getMessage()]);
  exit(1);
}
