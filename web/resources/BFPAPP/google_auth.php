<?php
// google_auth.php
require_once 'config.php';

// ============================================
// IMPORTANT: REPLACE WITH YOUR CREDENTIALS
// ============================================
define('GOOGLE_CLIENT_ID', '530231436071-c4cuenb6gv81dl1hvt983tnslhp41c8p.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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'); // <-- GET THIS FROM GOOGLE CONSOLE
define('GOOGLE_REDIRECT_URI', 'http://localhost:3000/google_auth.php'); // <-- MAKE SURE THIS MATCHES YOUR SETUP

// Step 1: Redirect to Google for authentication
if (!isset($_GET['code'])) {
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'email profile',
        'access_type' => 'online',
        'prompt' => 'select_account'
    ];
    
    $google_auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    header('Location: ' . $google_auth_url);
    exit();
}

// Step 2: Handle the callback from Google
$code = $_GET['code'] ?? '';

if (empty($code)) {
    die('Error: No authorization code received from Google.');
}

// Exchange authorization code for access token
$token_url = 'https://oauth2.googleapis.com/token';
$token_data = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$token_response = curl_exec($ch);

if (curl_errno($ch)) {
    die('Curl error: ' . curl_error($ch));
}
curl_close($ch);

$token_info = json_decode($token_response, true);

// Check for token errors
if (isset($token_info['error'])) {
    die('Token Error: ' . $token_info['error'] . '<br>Description: ' . ($token_info['error_description'] ?? 'Unknown'));
}

if (!isset($token_info['access_token'])) {
    die('Error: No access token received. Response: ' . print_r($token_info, true));
}

// Get user info from Google
$user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $token_info['access_token'];
$user_info_response = file_get_contents($user_info_url);
$user_info = json_decode($user_info_response, true);

if (!$user_info || !isset($user_info['email'])) {
    die('Error: Could not retrieve user information from Google.');
}

try {
    // Check if user exists in database
    $sql = "SELECT user_ID, role FROM users WHERE email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_info['email']]);
    $existing_user = $stmt->fetch();
    
    if ($existing_user) {
        // User exists - log them in
        $_SESSION['user_id'] = $existing_user['user_ID'];
        $_SESSION['user_role'] = $existing_user['role'];
    } else {
        // Create new user
        $insert_sql = "INSERT INTO users (email, first_name, last_name, auth_provider, role, date_created) 
                       VALUES (?, ?, ?, 'google', 'resident', NOW())";
        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute([
            $user_info['email'],
            $user_info['given_name'] ?? 'User',
            $user_info['family_name'] ?? ''
        ]);
        
        $new_user_id = $pdo->lastInsertId();
        $_SESSION['user_id'] = $new_user_id;
        $_SESSION['user_role'] = 'resident';
        
        // Create default location for new user
        $loc_sql = "INSERT INTO location (FK_user_ID, location_name, address, permission_level, date_created) 
                    VALUES (?, 'Primary Residence', 'Not set yet', 'private', NOW())";
        $loc_stmt = $pdo->prepare($loc_sql);
        $loc_stmt->execute([$new_user_id]);
    }
    
    // Redirect to main app (index.php) after successful login/registration
    header('Location: index.php'); 
    exit();
    
} catch (PDOException $e) {
    error_log('Google Auth Database Error: ' . $e->getMessage());
    die('Database error occurred during authentication. Please try again.');
}
?>