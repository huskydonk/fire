<?php
// 1. Start the session
session_start();

// 2. Include Composer's Autoloader
// (Make sure you've run "composer require google/apiclient:^2.0")
require_once 'vendor/autoload.php';
require_once 'db_config.php';

// $db_host = 'localhost';
// $db_port = '3307'; // Define your port
// $db_name = 'bfp-ea';
// $db_user = 'root';
// $db_pass = '';

// try {
//     // Add the port to the "host" part
//     // Correct PDO string
// $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;", $db_user, $db_pass);
//     $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// } catch (PDOException $e) {
//     die(json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]));
// }

// 4. Get the ID token from the POST request
$json_str = file_get_contents('php://input');
$data = json_decode($json_str);
$id_token = $data->token ?? null;

if (!$id_token) {
    echo json_encode(['success' => false, 'error' => 'No token provided']);
    exit;
}

// 5. Verify the ID token with Google
// We use YOUR Client ID here
$client = new Google_Client(['client_id' => '530231436071-c4cuenb6gv81dl1hvt983tnslhp41c8p.apps.googleusercontent.com']); 
try {
    $payload = $client->verifyIdToken($id_token);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Invalid token: ' . $e->getMessage()]);
    exit;
}

if ($payload) {
    // 6. Token is valid, get user info
    $google_id = $payload['sub']; // This is the unique Google User ID
    $email = $payload['email'];
    $first_name = $payload['given_name'] ?? 'User';
    $last_name = $payload['family_name'] ?? '';

    try {
        // 7. Check if user already exists
        $stmt = $pdo->prepare("SELECT * FROM user WHERE google_id = ?");
        $stmt->execute([$google_id]);
        $user = $stmt->fetch();

        if ($user) {
            // User exists - just get their ID
            $user_id = $user['user_ID'];
        } else {
            // User is new - INSERT them into the 'user' table
            $stmt = $pdo->prepare(
                "INSERT INTO user (google_id, auth_provider, first_name, last_name, email, role) 
                 VALUES (?, 'google', ?, ?, ?, 'user')"
            );
            $stmt->execute([$google_id, $first_name, $last_name, $email]);
            $user_id = $pdo->lastInsertId(); // Get the new user_ID
        }

        // 8. Create a session to log the user in
        $_SESSION['user_id'] = $user_id;
        $_SESSION['email'] = $email;
        $_SESSION['first_name'] = $first_name;
        $_SESSION['logged_in'] = true;

        // 9. Send success response
        echo json_encode(['success' => true]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    // Invalid ID token
    echo json_encode(['success' => false, 'error' => 'Invalid token.']);
}
?>