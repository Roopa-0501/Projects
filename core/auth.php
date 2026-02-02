<?php
// core/auth.php
require_once __DIR__ . "/db.php";

function registerUser($name, $email, $password, $phone, $location, $role = 'parent') {
    global $pdo;

    // check duplicate email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ["status" => "error", "message" => "Email already registered"];
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, location, role) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, $hashedPassword, $phone, $location, $role]);

    return ["status" => "success", "message" => "User registered successfully as $role"];
}
?>