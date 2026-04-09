<?php
// logout.php
require_once 'config.php'; // Required to start the session
// Unset all of the session variables
$_SESSION = array();


// If it is desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to the home page (index.php) after logout
header('Location: loginweb.php'); 
exit();
?>