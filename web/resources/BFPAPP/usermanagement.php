<?php
// --- Essential Configuration (MUST BE CONFIGURED) ---
// Note: These constants should typically be in a separate, secure 'db_config.php' file.
    ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
// Set up for password hashing and verification
function hash_password($password) {
    // PASSWORD_BCRYPT is secure and generally recommended
    return password_hash($password, PASSWORD_BCRYPT);
}

// --- CRUD Operations Handler ---
$message = '';
$message_type = ''; // 'success' or 'error'

// Handle CREATE, UPDATE, DELETE actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize common POST data (using prepared statements for safe execution)
    $action = $_POST['action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'resident';

    // IMPORTANT: Note the change to stop using $mysqli->real_escape_string
    // since we are using prepared statements which handle sanitation automatically.

    try {
        if ($action === 'create' || $action === 'update') {
            // Validate required fields
            if (empty($first_name) || empty($last_name) || empty($email)) {
                throw new Exception("Please fill in all required fields (Name and Email).");
            }

            // --- 1. UNIQUE EMAIL CHECK (Create + Update) ---
            // Check if email exists for any user OTHER than the one being edited.
            // If creating ($user_id is 0), it checks all users. If updating, it ignores self.
            $check_sql = "SELECT user_ID FROM users WHERE email = ? AND user_ID != ?";
            $check_stmt = $mysqli->prepare($check_sql);
            if (!$check_stmt) throw new Exception("Prepare failed: " . $mysqli->error);
            
            $exclude_id = ($action === 'update') ? $user_id : 0;
            
            $check_stmt->bind_param("si", $email, $exclude_id);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows > 0) {
                throw new Exception("Action failed: The email address '$email' is already in use.");
            }
            $check_stmt->close();
            // -----------------------------------------------

            if ($action === 'create') {
                if (empty($password)) {
                    throw new Exception("Password is required for new users.");
                }
                
                // REMOVED HASHING: Store plain text password directly
                $plain_password = $password;

                $sql = "INSERT INTO users (first_name, last_name, email, phone_number, enc_password, role) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $mysqli->prepare($sql);
                if (!$stmt) throw new Exception("Prepare failed: " . $mysqli->error);
                
                // Bind plain password
                $stmt->bind_param("ssssss", $first_name, $last_name, $email, $phone_number, $plain_password, $role);
                
                if ($stmt->execute()) {
                    $message = "User '{$first_name} {$last_name}' created successfully.";
                    $message_type = 'success';
                } else {
                    throw new Exception("Error executing query: " . $stmt->error);
                }
                $stmt->close();

            } elseif ($action === 'update' && $user_id > 0) {
                // Building the SQL query for update
                $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone_number = ?, role = ?";
                $types = "sssss";
                $params = [$first_name, $last_name, $email, $phone_number, $role];

                // Only update password if a new one is provided
                if (!empty($password)) {
                    // REMOVED HASHING: Update with plain text
                    $sql .= ", enc_password = ?";
                    $types .= "s";
                    $params[] = $password;
                }
                
                $sql .= " WHERE user_ID = ?";
                $types .= "i";
                $params[] = $user_id;

                $stmt = $mysqli->prepare($sql);
                if (!$stmt) throw new Exception("Prepare failed: " . $mysqli->error);
                $stmt->bind_param($types, ...$params);

                if ($stmt->execute()) {
                    $message = "User ID: {$user_id} updated successfully.";
                    $message_type = 'success';
                } else {
                    throw new Exception("Error executing query: " . $stmt->error);
                }
                $stmt->close();
            }

        } elseif ($action === 'delete' && $user_id > 0) {
            // DELETE operation (unchanged)
            $sql = "DELETE FROM users WHERE user_ID = ?";
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) throw new Exception("Prepare failed: " . $mysqli->error);
            $stmt->bind_param("i", $user_id);

            if ($stmt->execute()) {
                $message = "User ID: {$user_id} deleted successfully.";
                $message_type = 'success';
            } else {
                throw new Exception("Error executing query: " . $stmt->error);
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        $message = "Operation failed: " . $e->getMessage();
        $message_type = 'error';
    }
}

// --- Pagination and Read Operation (WITH SERVER-SIDE SEARCH) ---

// Get current page from GET parameter
$current_page = (int)($_GET['page'] ?? 1);
if ($current_page < 1) $current_page = 1;

// Get search term from GET parameter
$search_term = trim($_GET['search'] ?? '');
$search_query = $mysqli->real_escape_string($search_term); // Escape for LIKE clause
$search_like = "%" . $search_query . "%";

// Base WHERE clause for search
$where_clause = '';
$search_params = [];
$search_types = '';

if (!empty($search_term)) {
    // Search is performed across multiple columns
    $where_clause = " WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone_number LIKE ? OR role LIKE ?";
    $search_params = array_fill(0, 5, $search_like);
    $search_types = str_repeat('s', 5);
}

// 1. Get total number of records (must account for search term)
$total_sql = "SELECT COUNT(user_ID) AS total FROM users" . $where_clause;
$total_stmt = $mysqli->prepare($total_sql);
if (!$total_stmt) die("Total count prepare failed: " . $mysqli->error);

if (!empty($search_term)) {
    $total_stmt->bind_param($search_types, ...$search_params);
}

$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_records = $total_result->fetch_assoc()['total'] ?? 0;
$total_pages = ceil($total_records / RECORDS_PER_PAGE);
$total_stmt->close();

// Calculate OFFSET
$offset = ($current_page - 1) * RECORDS_PER_PAGE;

// Ensure current_page is not greater than total_pages (if data exists)
if ($total_records > 0 && $current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * RECORDS_PER_PAGE;
}

// 2. Fetch data for the current page (also accounts for search term)
$read_sql = "SELECT user_ID, first_name, last_name, email, phone_number, date_created, role 
             FROM users 
             " . $where_clause . "
             ORDER BY user_ID DESC
             LIMIT ? OFFSET ?";
$stmt = $mysqli->prepare($read_sql);
if (!$stmt) die("Read query prepare failed: " . $mysqli->error);

// Dynamic binding for LIMIT and OFFSET after search parameters
$limit = RECORDS_PER_PAGE;
$types = $search_types . "ii";
$params = array_merge($search_params, [$limit, $offset]);

// Execute the statement
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);

// Calculate showing range for the footer message
$start_record = min($total_records, $offset + 1);
$end_record = min($total_records, $offset + RECORDS_PER_PAGE);

// Close the statement and connection
$stmt->close();
$mysqli->close(); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - BFP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // --- Modal Logic (Kept and slightly cleaned up) ---
        // Get modals after the document loads
        document.addEventListener('DOMContentLoaded', function() {
            const userModal = document.getElementById('user-modal');
            const deleteModal = document.getElementById('delete-modal');

            // Expose functions globally for HTML buttons
            window.openUserModal = function(mode, userData = {}) {
                const title = document.getElementById('modal-title');
                const actionInput = document.getElementById('modal-action');
                const userIdInput = document.getElementById('modal-user-id');
                const submitBtn = document.getElementById('modal-submit-btn');
                const passwordInput = document.getElementById('password');
                const passwordRequired = document.getElementById('password-required');
                const passwordHint = document.getElementById('password-hint');

                document.getElementById('user-form').reset();
                passwordInput.removeAttribute('required');
                passwordRequired.classList.remove('hidden');
                passwordHint.classList.add('hidden');

                if (mode === 'create') {
                    title.textContent = 'Add New Account';
                    actionInput.value = 'create';
                    userIdInput.value = '';
                    submitBtn.textContent = 'Create Account';
                    passwordInput.setAttribute('required', 'required');
                } else if (mode === 'edit') {
                    title.textContent = `Edit Account: ${userData.first_name} ${userData.last_name}`;
                    actionInput.value = 'update';
                    userIdInput.value = userData.user_ID;
                    submitBtn.textContent = 'Save Changes';
                    
                    document.getElementById('first_name').value = userData.first_name;
                    document.getElementById('last_name').value = userData.last_name;
                    document.getElementById('email').value = userData.email;
                    document.getElementById('phone_number').value = userData.phone_number;
                    document.getElementById('role').value = userData.role;

                    passwordRequired.classList.add('hidden');
                    passwordHint.classList.remove('hidden');
                    passwordInput.value = '';
                }

                userModal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            };

            window.closeUserModal = function() {
                userModal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            };

            window.showDeleteConfirmation = function(userId, fullName) {
                document.getElementById('delete-user-id').value = userId;
                document.getElementById('delete-user-name').textContent = fullName;
                deleteModal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            };

            window.closeDeleteConfirmation = function() {
                deleteModal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            };

            userModal.addEventListener('click', (e) => {
                if (e.target === userModal) closeUserModal();
            });

            deleteModal.addEventListener('click', (e) => {
                if (e.target === deleteModal) closeDeleteConfirmation();
            });
        });
    </script>
    <style>
        /* ... (Your original CSS styles are kept here, as they are mostly visual) ... */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .user-row {
            animation: fadeIn 0.4s ease-out forwards;
        }
        
        /* Staggered animation for the first few rows */
        <?php for ($i = 0; $i < RECORDS_PER_PAGE; $i++): ?>
        .user-row:nth-child(<?php echo $i + 1; ?>) { animation-delay: <?php echo 0.1 + ($i * 0.1); ?>s; }
        <?php endfor; ?>

        tbody tr:hover {
            background: #f8fafc;
            transform: translateX(4px);
        }

        .action-btn:hover {
            transform: scale(1.1);
        }

        .modal-overlay {
            z-index: 1000;
        }

        .message-box {
            animation: fadeIn 0.5s ease forwards;
        }

        /* Force text color in all input fields */
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="password"],
        select,
        textarea {
            color: #1e293b !important;
            background-color: #ffffff !important;
        }

        input::placeholder,
        textarea::placeholder {
            color: #94a3b8 !important;
            opacity: 1;
        }

        input:focus,
        select:focus,
        textarea:focus {
            color: #1e293b !important;
            background-color: #ffffff !important;
        }

        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-text-fill-color: #1e293b !important;
            -webkit-box-shadow: 0 0 0px 1000px #ffffff inset !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        label, p, span, div, td, th {
            color: inherit;
        }

        table {
            color: #1e293b;
        }

        tbody tr {
            background-color: #ffffff;
        }

        .modal-overlay .bg-white * {
            color: #1e293b;
        }

        .modal-overlay input,
        .modal-overlay select,
        .modal-overlay textarea {
            color: #1e293b !important;
            background-color: #ffffff !important;
        }

        /* Search input enhancements */
        #user-search-input {
            transition: all 0.3s ease;
        }

        #user-search-input:focus {
            width: 100%;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.1);
        }

        mark {
            background-color: #fef08a;
            padding: 2px 4px;
            border-radius: 2px;
            font-weight: 500;
        }

        .clear-search-btn {
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            background: transparent;
            border: none;
        }

        .clear-search-btn:hover {
            background-color: rgba(148, 163, 184, 0.1);
        }

        .user-row,
        .mobile-card {
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .user-row[style*="display: none"],
        .mobile-card[style*="display: none"] {
            opacity: 0;
            transform: scale(0.95);
        }

        #user-search-input:not(:placeholder-shown) {
            border-color: #ef4444;
            background-color: #fef2f2;
        }

        @keyframes spin {
            to { transform: translateY(-50%) rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-800 to-slate-900 min-h-screen p-4 md:p-8 font-[Inter]">
    <div class="max-w-7xl mx-auto bg-white rounded-3xl p-6 md:p-10 shadow-2xl">
        
        <?php if (!empty($message)): 
            $bg_color = $message_type === 'success' ? 'bg-green-500' : 'bg-red-500';
            $icon = $message_type === 'success' ? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' : 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z';
        ?>
        <div id="message-box" class="message-box fixed top-4 right-4 max-w-sm w-full p-4 rounded-xl shadow-lg text-white <?php echo $bg_color; ?> flex items-start gap-3 z-50">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $icon; ?>"></path>
            </svg>
            <p class="text-sm font-medium flex-grow"><?php echo htmlspecialchars($message); ?></p>
            <button onclick="document.getElementById('message-box').remove()" class="text-white opacity-70 hover:opacity-100 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <script>
            setTimeout(() => {
                const msgBox = document.getElementById('message-box');
                if (msgBox) msgBox.remove();
            }, 5000);
        </script>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-8">
            <h1 class="text-2xl md:text-3xl font-bold text-slate-800">BFP User Accounts</h1>
            <div class="flex flex-col sm:flex-row gap-3 md:gap-4 items-stretch sm:items-center">
                <button onclick="openUserModal('create')" class="bg-red-500 text-white px-6 py-3 rounded-lg font-medium hover:bg-red-600 transition flex items-center justify-center gap-2 shadow-lg -500/50">
                    <span class="text-xl">+</span>
                    Add New Account
                </button>
                <form method="GET" action="usermanagement.php" class="relative">
                    <input 
                        type="text" 
                        id="user-search-input"
                        name="search"
                        placeholder="Search by name, email, phone, or role..." 
                        class="pl-10 pr-10 py-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent w-full sm:w-80 transition-all"
                        autocomplete="off"
                        value="<?php echo htmlspecialchars($search_term); ?>"
                    >
                    <svg class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 transform -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <?php if (!empty($search_term)): ?>
                        <a href="usermanagement.php<?php echo $current_page > 1 ? '?page=' . $current_page : ''; ?>"
                            class="clear-search-btn absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-slate-600 transition"
                            title="Clear Search"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </a>
                    <?php endif; ?>
                    <button type="submit" style="display:none;"></button>
                </form>
            </div>
        </div>

        <div class="hidden md:block overflow-x-auto border border-slate-200 rounded-xl shadow-inner">
            <table class="w-full">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="text-left py-4 px-6 text-xs font-semibold text-slate-500 uppercase tracking-wider">User ID</th>
                        <th class="text-left py-4 px-6 text-xs font-semibold text-slate-500 uppercase tracking-wider">Full Name</th>
                        <th class="text-left py-4 px-6 text-xs font-semibold text-slate-500 uppercase tracking-wider">Contact Number</th>
                        <th class="text-left py-4 px-6 text-xs font-semibold text-slate-500 uppercase tracking-wider">Email</th>
                        <th class="text-left py-4 px-6 text-xs font-semibold text-slate-500 uppercase tracking-wider">Role</th>
                        <th class="text-left py-4 px-6 text-xs font-semibold text-slate-500 uppercase tracking-wider">Date Created</th>
                        <th class="text-center py-4 px-6 text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="user-table-body">
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                        <tr class="border-b border-slate-100 user-row">
                            <td class="py-3 px-6 text-sm text-slate-800 font-semibold"><?php echo htmlspecialchars($user['user_ID']); ?></td>
                            <td class="py-3 px-6 text-sm text-slate-700"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                            <td class="py-3 px-6 text-sm text-slate-700"><?php echo htmlspecialchars($user['phone_number'] ?? 'N/A'); ?></td>
                            <td class="py-3 px-6 text-sm text-slate-700"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td class="py-3 px-6">
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full 
                                    <?php 
                                        $role_class = [
                                            'resident' => 'bg-blue-100 text-blue-800', 
                                            'bfp_officer' => 'bg-red-100 text-red-800',
                                            'bfp_assigned_at_desk' => 'bg-orange-100 text-orange-800'
                                        ][$user['role']] ?? 'bg-slate-100 text-slate-800';
                                        echo $role_class;
                                    ?>">
                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $user['role']))); ?>
                                </span>
                            </td>
                            <td class="py-3 px-6 text-sm text-slate-500"><?php echo date('M d, Y', strtotime($user['date_created'])); ?></td>
                            <td class="py-3 px-6 flex items-center justify-center gap-1">
                                <button 
                                    onclick='openUserModal("edit", <?php echo json_encode($user); ?>)'
                                    class="action-btn p-2 text-orange-600 rounded-full hover:bg-orange-100 transition"
                                    title="Edit User"
                                >
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path></svg>
                                </button>
                                <button 
                                    onclick="showDeleteConfirmation(<?php echo $user['user_ID']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES); ?>')"
                                    class="action-btn p-2 text-red-600 rounded-full hover:bg-red-100 transition"
                                    title="Delete User"
                                >
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-slate-500">
                                <svg class="w-12 h-12 mx-auto mb-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                <p class="font-medium">
                                    <?php echo !empty($search_term) ? "No users found matching \"{$search_term}\"." : "No user accounts found."; ?>
                                </p>
                                <p class="text-sm text-slate-400 mt-1">
                                    <?php echo !empty($search_term) ? "Try a different search term or " : ""; ?>
                                    Try adding a new user.
                                </p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="md:hidden space-y-4">
            <?php if (count($users) > 0): ?>
                <?php foreach ($users as $user): ?>
                    <div class="mobile-card bg-slate-50 rounded-xl p-4 border border-slate-200">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <div class="text-xs text-slate-500 uppercase font-semibold mb-1">User ID</div>
                                <div class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($user['user_ID']); ?></div>
                            </div>
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full 
                                <?php 
                                    $role_class = [
                                        'resident' => 'bg-blue-100 text-blue-800', 
                                        'bfp_officer' => 'bg-red-100 text-red-800',
                                        'bfp_assigned_at_desk' => 'bg-orange-100 text-orange-800'
                                    ][$user['role']] ?? 'bg-slate-100 text-slate-800';
                                    echo $role_class;
                                ?>">
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $user['role']))); ?>
                            </span>
                        </div>
                        <div class="space-y-2 mb-3">
                            <div>
                                <div class="text-xs text-slate-500">Full Name</div>
                                <div class="text-sm text-slate-700 font-medium"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                            </div>
                            <div class="flex gap-4">
                                <div class="flex-1">
                                    <div class="text-xs text-slate-500">Contact Number</div>
                                    <div class="text-sm text-slate-700"><?php echo htmlspecialchars($user['phone_number'] ?? 'N/A'); ?></div>
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500">Email</div>
                                <div class="text-sm text-slate-700"><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500">Date Created</div>
                                <div class="text-sm text-slate-700"><?php echo date('M d, Y', strtotime($user['date_created'])); ?></div>
                            </div>
                        </div>
                        <div class="flex gap-2 pt-3 border-t border-slate-200">
                            <button 
                                onclick='openUserModal("edit", <?php echo json_encode($user); ?>)'
                                class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-orange-50 text-orange-600 rounded-lg hover:bg-orange-100 transition"
                            >
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path></svg>
                                Edit
                            </button>
                            <button 
                                onclick="showDeleteConfirmation(<?php echo $user['user_ID']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES); ?>')"
                                class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-slate-100 text-slate-600 rounded-lg hover:bg-red-50 hover:text-red-600 transition"
                            >
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-8 text-slate-500 bg-slate-50 rounded-xl p-4 border border-slate-200">
                    <svg class="w-12 h-12 mx-auto mb-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <p class="font-medium">
                        <?php echo !empty($search_term) ? "No users found matching \"{$search_term}\"." : "No user accounts found."; ?>
                    </p>
                    <p class="text-sm text-slate-400 mt-1">
                        <?php echo !empty($search_term) ? "Try a different search term or " : ""; ?>
                        Try adding a new user.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-8 flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="text-sm text-slate-600">
                <?php if ($total_records > 0): ?>
                    Showing <span class="font-semibold"><?php echo $start_record; ?></span> to <span class="font-semibold"><?php echo $end_record; ?></span> of <span class="font-semibold"><?php echo $total_records; ?></span> results
                <?php else: ?>
                    No results to show.
                <?php endif; ?>
            </div>
            
            <div class="flex items-center gap-2">
                <?php
                function getPaginationLink($page, $search) {
                    $link = '?page=' . $page;
                    if (!empty($search)) {
                        $link .= '&search=' . urlencode($search);
                    }
                    return $link;
                }
                ?>

                <a href="<?php echo $current_page > 1 ? getPaginationLink($current_page - 1, $search_term) : '#'; ?>" 
                   class="p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition <?php echo $current_page === 1 ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                
                <?php 
                    $range = 2;
                    $start = max(1, $current_page - $range);
                    $end = min($total_pages, $current_page + $range);
                    
                    if ($total_pages > 0) {
                        if ($start > 1) { echo '<span class="text-slate-400">...</span>'; }
                        for ($i = $start; $i <= $end; $i++):
                            $active_class = $i === $current_page ? 'bg-red-500 text-white' : 'text-slate-600 hover:bg-slate-100';
                    ?>
                        <a href="<?php echo getPaginationLink($i, $search_term); ?>" 
                           class="w-10 h-10 <?php echo $active_class; ?> rounded-lg font-medium flex items-center justify-center transition">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; 
                        if ($end < $total_pages) { echo '<span class="text-slate-400">...</span>'; }
                    }
                ?>

                <a href="<?php echo $current_page < $total_pages ? getPaginationLink($current_page + 1, $search_term) : '#'; ?>" 
                   class="p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition <?php echo $current_page === $total_pages || $total_records === 0 ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
        </div>
    </div>

    <div id="user-modal" class="modal-overlay hidden fixed inset-0 bg-slate-900 bg-opacity-75 flex items-center justify-center p-4 transition-opacity duration-300">
        <div class="bg-white rounded-xl w-full max-w-lg p-6 shadow-2xl transform scale-100 transition-transform duration-300" onclick="event.stopPropagation()">
            <h2 id="modal-title" class="text-2xl font-bold text-slate-800 mb-6">Add New Account</h2>
            
            <form id="user-form" method="POST" action="usermanagement.php">
                <input type="hidden" name="action" id="modal-action">
                <input type="hidden" name="user_id" id="modal-user-id">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-slate-700 mb-1">First Name <span class="text-red-500">*</span></label>
                        <input type="text" id="first_name" name="first_name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-slate-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                        <input type="text" id="last_name" name="last_name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email <span class="text-red-500">*</span></label>
                        <input type="email" id="email" name="email" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                    </div>
                    <div>
                        <label for="phone_number" class="block text-sm font-medium text-slate-700 mb-1">Contact Number</label>
                        <input type="tel" id="phone_number" name="phone_number" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="role" class="block text-sm font-medium text-slate-700 mb-1">Role <span class="text-red-500">*</span></label>
                    <select id="role" name="role" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="resident">Resident</option>
                        <option value="bfp_officer">BFP Officer</option>
                        <option value="bfp_assigned_at_desk">BFP Assigned at Desk</option>
                    </select>
                </div>

                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password <span id="password-required" class="text-red-500">*</span></label>
                    <input type="password" id="password" name="password" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                    <p id="password-hint" class="text-xs text-slate-500 mt-1 hidden">Leave blank to keep current password.</p>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeUserModal()" class="px-5 py-2 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 transition">
                        Cancel
                    </button>
                    <button style="color:white" type="submit" id="modal-submit-btn" class="px-5 py-2 bg-red-500 text-white rounded-lg font-medium hover:bg-red-600 transition">
                        Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="delete-modal" class="modal-overlay hidden fixed inset-0 bg-slate-900 bg-opacity-75 flex items-center justify-center p-4 transition-opacity duration-300">
        <div class="bg-white rounded-xl w-full max-w-sm p-6 shadow-2xl transform scale-100 transition-transform duration-300" onclick="event.stopPropagation()">
            <h3 class="text-xl font-bold text-red-600 mb-4">Confirm Account Deletion</h3>
            <p class="text-slate-700 mb-6">Are you sure you want to delete the user account for: <strong id="delete-user-name"></strong>?</p>
            
            <form method="POST" action="usermanagement.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="delete-user-id">
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeDeleteConfirmation()" class="px-5 py-2 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 transition">
                        Cancel
                    </button>
                    <button  style="color:white" type="submit" class="px-5 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition">
                        Delete Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>