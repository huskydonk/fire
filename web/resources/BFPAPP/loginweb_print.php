<?php
// login.php
require_once 'db_config.php';

// // 2. IMPROVEMENT: Check if the database connection was established
// if (!isset($conn) || $conn->connect_error) {
//     // This assumes db_config.php sets $conn and handles its own errors (like die()).
//     // If we reach here and $conn is still not set, it's a file inclusion or scope issue.
//     $error = "CRITICAL ERROR: Database connection configuration failed. Check 'db_config.php'.";
//     // We will still render the HTML below so the user sees the error.
// }

// Initialize variables
$error = $error ?? ''; // Use the potential error from the connection check
$success = '';

if (!isset($_SESSION['user_id'])) {
    
    $is_logged_in = false;
    $current_user_role = 'bfp_assigned_at_desk'; // Default value
    // header('Location: loginweb.php');
    // exit;
} else {
    // If logged in, skip the login screen
    $is_logged_in = true;
    $current_user_role = $_SESSION['user_role'] ?? 'bfp_assigned_at_desk'; // Store role in a variable for reference
    header('Location: dashboard.php');
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only proceed if the database connection is valid
    if (isset($conn) && !$conn->connect_error) {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validate inputs
        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            // Prepare SQL statement to prevent SQL injection
            $stmt = $conn->prepare("SELECT user_ID, first_name, last_name, email, enc_password, role, auth_provider FROM users WHERE email = ? LIMIT 1");
            
            // Handle case where prepare fails (e.g., table missing)
            if ($stmt === false) {
                 $error = 'Database query preparation failed. Check your SQL tables.';
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    
                    // Check if this is a Google account
                    if ($user['auth_provider'] === 'google') {
                        $error = 'This account uses Google Sign-In. Please use the Google login option.';
                    } else {
                        // Verify password
                        if (password_verify($password, $user['enc_password'])) {
                            // Password correct - create session
                            $_SESSION['user_id'] = $user['user_ID'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['first_name'] = $user['first_name'];
                            $_SESSION['last_name'] = $user['last_name'];
                            $_SESSION['role'] = $user['role'];
                            $_SESSION['login_time'] = time();
                            
                            // Redirect all successfully logged-in users to the main dashboard
                            header('Location: dashboard.php');
                            exit();
                        } else {
                            $error = 'Invalid email or password.';
                        }
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
                
                $stmt->close();
            }
        }
    } else {
        // If the form submitted but the database connection was already known to be bad
        $error = $error . " Cannot process login due to configuration error.";
    }
}

// Closing $conn here can cause issues if the login succeeded and a redirect happened,
// so it's generally better to close it in db_config.php or before exit(). 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BFP Early Alert - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom BFP colors */
        .bg-bfp-blue {
            background-color: #1a5fb4;
        }
        .btn-bfp-blue {
            background-color: #1a5fb4;
        }
        .btn-bfp-blue:hover {
            background-color: #155291;
        }
        .bg-login-light {
            background-color: #f5f5f5;
        }
        body {
            background-color: #f5f5f5;
            font-family: 'Inter', sans-serif;
        }
        /* Ensure the illustration is centered and visible */
        .bfppic {
             /* Adjustments for better visibility on small screens */
            position: relative !important;
            transform: none !important;
            left: auto !important;
            top: auto !important;
            max-height: 400px;
            margin: auto;
        }
        @media (min-width: 768px) {
             .bfppic {
                position: absolute !important;
                left: 40% !important;
                top: 50% !important;
                transform: translate(-50%, -50%) !important;
                max-height: 600px;
            }
        }
    </style>
</head>
<body class="min-h-screen">
    
    <div class="flex flex-col md:flex-row w-full min-h-screen">
        
        <!-- Left Side - Blue Section (Hero) -->
        <div class="w-full md:w-1/2 bg-bfp-blue p-8 md:p-12 text-white flex flex-col justify-between min-h-[600px] rounded-b-xl md:rounded-b-none md:rounded-r-none">
            <div class="flex items-center gap-3">
                <!-- Icon for Early Alert -->
                <svg class="w-10 h-10 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.017 5.454 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0l-3.143-3.143m3.143 3.143l4.45-4.45" />
                </svg>
                <span class="text-2xl font-bold tracking-wide">EARLY ALERT</span>
            </div>
            
            <div class="flex-grow flex items-center justify-center my-8 relative">
                <!-- Placeholder for the firefighter illustration -->
                <img src="hero.png" alt="Firefighter Illustration" 
   
                >
            </div>
            
            <p class="text-sm text-blue-100 text-center md:text-left">
                Enhancing fire safety by preventing man-made incidents and responding to emergencies.
            </p>
        </div>
        
        <!-- Right Side - Login Form -->
        <div class="w-full md:w-1/2 bg-login-light flex items-center justify-center p-8 md:p-12">
            <div class="max-w-sm w-full bg-white p-8 rounded-xl shadow-2xl">
                
                <!-- Home Icon -->
                <div class="flex justify-center mb-6">
                    <div class="bg-blue-100 p-4 rounded-full shadow-inner">
                        <svg class="w-8 h-8 text-bfp-blue" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M11.47 3.84a.75.75 0 011.06 0l8.69 8.69a.75.75 0 101.06-1.06l-8.689-8.69a2.25 2.25 0 00-3.182 0l-8.69 8.69a.75.75 0 001.061 1.06l8.69-8.69z" />
                            <path d="M12 5.432l8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 01-.75-.75v-4.5a.75.75 0 00-.75-.75h-3a.75.75 0 00-.75.75V21a.75.75 0 01-.75.75H5.625a1.875 1.875 0 01-1.875-1.875v-6.198a2.29 2.29 0 00.091-.086L12 5.43z" />
                        </svg>
                    </div>
                </div>
                
                <h2 class="text-3xl font-bold text-center text-gray-900 mb-1">Welcome Back</h2>
                <p class="text-gray-600 text-center mb-8">Smart IOT Fire Safety Solution</p>
                
                <?php if (!empty($error)): ?>
                <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="mb-4">
                        <label for="email" class="sr-only">Email</label>
                        <input 
                            type="email" 
                            name="email"
                            placeholder="Email" 
                            id="email"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                            class="w-full px-4 py-3 border border-gray-300 bg-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            required
                        >
                    </div>
                    
                    <div class="mb-6 relative">
                         <label for="password" class="sr-only">Password</label>
                        <input 
                            type="password" 
                            id="password"
                            name="password"
                            placeholder="Password" 
                            class="w-full px-4 py-3 border border-gray-300 bg-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition pr-12"
                            required
                        >
                        <div class="absolute right-4 top-1/2 transform -translate-y-1/2 cursor-pointer text-gray-500 hover:text-gray-700" onclick="togglePassword()">
                            <svg id="eyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </div>
                    </div>
                    
                    <button 
                        type="submit"
                        class="w-full btn-bfp-blue text-white font-semibold py-3 rounded-lg transition duration-200 shadow-lg hover:shadow-xl hover:bg-[#155291]"
                    >
                        Log in
                    </button>
                </form>
            </div>
        </div>
        
    </div>

    <script>
        // SVG paths for the eye icons
        const eyeIconSvg = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
        const eyeSlashIconSvg = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>';

        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = eyeIconSvg; // Change to visible icon
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = eyeSlashIconSvg; // Change to slashed icon
            }
        }
    </script>
</body>
</html>