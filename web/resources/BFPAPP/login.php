<?php
require 'config.php';
header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// 1. Google Login Handler
if (isset($input['google_token'])) {
    $email = $input['email'];
    $google_id = $input['google_id'];
    $first_name = $input['first_name'] ?? 'Resident'; // Default if missing
    $last_name = $input['last_name'] ?? 'User';       // Default if missing

    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // User exists: Log them in & link Google ID if missing
        if (empty($user['google_id'])) {
            $upd = $pdo->prepare("UPDATE users SET google_id = ?, auth_provider = 'google' WHERE user_ID = ?");
            $upd->execute([$google_id, $user['user_ID']]);
        }
        $_SESSION['user_ID'] = $user['user_ID'];
        $_SESSION['role'] = $user['role'];
        
        $redirect = ($user['role'] === 'resident') ? 'resident_dashboard.php' : 'officer_dashboard.php';
        echo json_encode(['status' => 'success', 'redirect' => $redirect]);
    } else {
        // User does NOT exist: AUTO-REGISTER THEM
        try {
            $ins = $pdo->prepare("INSERT INTO users (google_id, auth_provider, first_name, last_name, email, role) VALUES (?, 'google', ?, ?, ?, 'resident')");
            $ins->execute([$google_id, $first_name, $last_name, $email]);
            
            // Get the ID of the new user and log them in
            $new_user_id = $pdo->lastInsertId();
            $_SESSION['user_ID'] = $new_user_id;
            $_SESSION['role'] = 'resident';

            echo json_encode(['status' => 'success', 'redirect' => 'resident_dashboard.php']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Registration failed: ' . $e->getMessage()]);
        }
    }
    exit;
}

// 2. Local Login Handler
if (isset($input['username']) && isset($input['password'])) {
    $email = $input['username'];
    $password = $input['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['enc_password'])) {
        $_SESSION['user_ID'] = $user['user_ID'];
        $_SESSION['role'] = $user['role'];
        
        $redirect = ($user['role'] === 'resident') ? 'resident_dashboard.php' : 'officer_dashboard.php';
        echo json_encode(['status' => 'success', 'redirect' => $redirect]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
    }
    exit;
}
?>