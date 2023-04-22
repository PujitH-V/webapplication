<?php

require_once '../utils/cors.php';
require_once '../utils/constants.php';
require_once '../utils/database.php';
require_once '../vendor/autoload.php';

use Firebase\JWT\JWT;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $post_body = file_get_contents("php://input");

  if (isset($post_body) && !empty($post_body)) {
    $data = json_decode($post_body);

    $email = $data->email;
    $password = $data->password;

    // validations
    $errors = array();
    if (!isset($email))
      array_push($errors, ['email' => 'email is a required field']);
    if (!isset($password))
      array_push($errors, ['password' => 'password is a required field']);

    if (count($errors) > 0) {
      echo json_encode(['errors' => $errors]);
      http_response_code(400);
      exit(1);
    }

    // check if user exists
    $check_user = "SELECT * FROM users WHERE email='$email'";
    $check_user_result = mysqli_query($conn, $check_user);
    if (mysqli_num_rows($check_user_result) > 0) {
      $user_payload = $check_user_result->fetch_row();

      // check password
      if (!password_verify($password, $user_payload[5])) {
        echo json_encode(['error' => 'incorrect password']);
        http_response_code(400);
        exit(1);
      }

      // assign token
      $issued_at = new DateTimeImmutable();
      $jwt_payload = [
        'iat' => $issued_at->getTimestamp(),
        'iss' => 'localhost',
        'nbf' => $issued_at->getTimestamp(),
        'exp' => $issued_at->modify('+1 day')->getTimestamp(),
        'id' => $user_payload[0],
      ];
      $token = JWT::encode($jwt_payload, JWT_SECRET, 'HS512');
      echo json_encode([
        'token' => $token,
        'user' => [
          'id' => $user_payload[0],
          'name' => $user_payload[1],
          'email' => $user_payload[2],
          'phone' => $user_payload[3],
          'role' => $user_payload[4]
        ]
      ]);
      http_response_code(201);
      exit(0);
    } else {
      echo json_encode(['error' => 'user not registered']);
      http_response_code(405);
      exit(1);
    }
  }
} else {
  echo json_encode(['error' => 'invalid method']);
  http_response_code(400);
  exit(1);
}
