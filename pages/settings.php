<?php
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/database.php';
require_once '../classes/UserModel.php';
require_once '../classes/EmailHelper.php';

$db = new Database();
$conn = $db->connect();
$userModel = new UserModel($conn);

$currentUser = $userModel->getUserById($_SESSION['user_id']);
$allUsers = $userModel->getStaffAndAdmins();
$allRoles = $userModel->getAllRoles();

$success_msg = "";
$error_msg = "";

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    // Support both hashed and legacy plain text passwords for verification
    if (!password_verify($current_pass, $currentUser['password']) && $current_pass !== $currentUser['password']) {
        $error_msg = "Incorrect current password.";
    } elseif (strlen($new_pass) < 6) {
        $error_msg = "New password must be at least 6 characters.";
    } elseif ($new_pass !== $confirm_pass) {
        $error_msg = "New passwords do not match.";
    } else {
        if ($userModel->changePassword($_SESSION['user_id'], $new_pass)) {
            $success_msg = "Password changed successfully!";
            $currentUser = $userModel->getUserById($_SESSION['user_id']); // Refresh
        } else {
            $error_msg = "Failed to update password.";
        }
    }
}

// Handle Adding New Staff (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    if ($_SESSION['role_id'] == 1) {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $role_id = $_POST['role_id'];
        $fname = $_POST['first_name'];
        $mname = $_POST['middle_name'];
        $lname = $_POST['last_name'];
        $suffix = $_POST['suffix'];

        if (!EmailHelper::isValidEmailDomain($email)) {
            $error_msg = "Please enter a valid, working email address.";
        } else {
            $existingUser = $userModel->checkUserExists($username, $email);
            if ($existingUser) {
                if ($existingUser['email'] === $email) {
                    $error_msg = "This email is already registered.";
                } else {
                    $error_msg = "This username is already taken.";
                }
            } else {
                $tempPassword = $userModel->addStaff($username, $email, $role_id, $fname, $mname, $lname, $suffix);
                if ($tempPassword) {
                    $name = !empty($fname) ? $fname : $username;
                    EmailHelper::sendStaffWelcomeEmail($email, $name, $tempPassword);
                    $success_msg = "Staff account created! A welcome email with their temporary password has been sent to $email.";
                    $allUsers = $userModel->getStaffAndAdmins();
                } else {
                    $error_msg = "Failed to create account. Please try again.";
                }
            }
        }
    }
}

// Helper to format name
function formatUserName($u) {
    $name = (isset($u['first_name']) ? $u['first_name'] : $u['username']) . ' ';
    if (!empty($u['middle_name'])) $name .= substr($u['middle_name'], 0, 1) . '. ';
    if (!empty($u['last_name'])) $name .= $u['last_name'];
    if (!empty($u['suffix'])) $name .= ' ' . $u['suffix'];
    return htmlspecialchars(trim($name));
}

// Handle Deleting User (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    if ($_SESSION['role_id'] == 1) {
        $delete_id = $_POST['delete_user_id'];
        if ($userModel->deleteUser($delete_id)) {
            $success_msg = "User removed.";
            $allUsers = $userModel->getStaffAndAdmins(); // Refresh
        } else {
            $error_msg = "Cannot delete this user.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Disaster Relief Tracker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../style/dashboard.css">
    <link rel="stylesheet" href="../style/tables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ecf0f1;
        }
        .tab-btn {
            padding: 10px 20px;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: bold;
            color: #7f8c8d;
            border-bottom: 3px solid transparent;
            transition: 0.3s;
        }
        .tab-btn.active {
            color: #3498db;
            border-bottom: 3px solid #3498db;
        }
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        .tab-content.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .settings-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            max-width: 800px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #34495e;
            font-size: 14px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #bdc3c7;
            border-radius: 6px;
            font-size: 14px;
        }
    </style>
</head>
<body>

    <?php $activePage = 'settings'; $sidebarBase = ''; include '../includes/sidebar.php'; ?>

    <div class="main">
        <div class="header">
            <div>
                <h1>System Settings</h1>
                <p>Manage your account and system users.</p>
            </div>
        </div>

        <?php if ($success_msg): ?>
            <div style="background: #27ae60; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fa-solid fa-circle-check"></i> <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div style="background: #e74c3c; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fa-solid fa-circle-xmark"></i> <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-btn active" onclick="openTab(event, 'profileTab')"><i class="fa-solid fa-user"></i> My Profile</button>
            <?php if ($_SESSION['role_id'] == 1): ?>
            <button class="tab-btn" onclick="openTab(event, 'userTab')"><i class="fa-solid fa-user-shield"></i> User Management</button>
            <?php endif; ?>
        </div>

        <!-- My Profile Tab -->
        <div id="profileTab" class="tab-content active">
            <div class="settings-card">
                <h3 style="margin-top: 0; margin-bottom: 20px; color: #2c3e50; border-bottom: 2px solid #f1f2f6; padding-bottom: 10px;">Account Information</h3>
                
                <div style="display: flex; gap: 40px; margin-bottom: 30px; align-items: center;">
                    <div style="width: 80px; height: 80px; background: #3498db; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: bold;">
                        <?php echo strtoupper(substr($currentUser['username'], 0, 1)); ?>
                    </div>
                    <div>
                        <p style="margin: 0; color: #7f8c8d; font-size: 13px;">Full Name</p>
                        <h2 style="margin: 5px 0; color: #2c3e50;"><?php echo formatUserName($currentUser); ?></h2>
                        <p style="margin: 0; color: #95a5a6; font-size: 12px;">@<?php echo htmlspecialchars($currentUser['username']); ?></p>
                        <div style="margin-top: 8px;">
                            <span style="background: #ecf0f1; padding: 4px 12px; border-radius: 15px; font-size: 12px; font-weight: bold; color: #34495e;">
                                <i class="fa-solid fa-shield"></i> <?php echo htmlspecialchars($currentUser['role_name']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <h3 style="margin-top: 40px; margin-bottom: 20px; color: #2c3e50; border-bottom: 2px solid #f1f2f6; padding-bottom: 10px;">Change Password</h3>
                <form method="POST">
                    <input type="hidden" name="change_password" value="1">
                    <div class="form-group" style="max-width: 400px;">
                        <label>Current Password</label>
                        <div class="password-toggle">
                            <input type="password" name="current_password" required placeholder="Enter current password">
                            <i class="fa-solid fa-eye-slash toggle-password"></i>
                        </div>
                    </div>
                    <div class="form-group" style="max-width: 400px;">
                        <label>New Password <span style="color:#94a3b8;font-weight:400;font-size:12px;">(min. 6 characters)</span></label>
                        <div class="password-toggle">
                            <input type="password" name="new_password" id="new_password" required minlength="6" placeholder="Enter new password">
                            <i class="fa-solid fa-eye-slash toggle-password"></i>
                        </div>
                    </div>
                    <div class="form-group" style="max-width: 400px;">
                        <label>Confirm New Password</label>
                        <div class="password-toggle">
                            <input type="password" name="confirm_password" id="confirm_password" required minlength="6" placeholder="Confirm new password">
                            <i class="fa-solid fa-eye-slash toggle-password"></i>
                        </div>
                        <span id="pw_match_msg" style="font-size:12px; color:#ef4444; display:none;">Passwords do not match.</span>
                    </div>
                    <button type="submit" style="padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; margin-top: 10px;">
                        Update Password
                    </button>
                </form>
            </div>
        </div>

        <!-- User Management Tab (Admin Only) -->
        <?php if ($_SESSION['role_id'] == 1): ?>
        <div id="userTab" class="tab-content">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                <!-- User List -->
                <div class="settings-card" style="max-width: none;">
                    <h3 style="margin-top: 0; margin-bottom: 20px; color: #2c3e50;">Active System Users</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="text-align: left; background: #f8f9fa;">
                                <th style="padding: 12px; border-bottom: 2px solid #ecf0f1;">User</th>
                                <th style="padding: 12px; border-bottom: 2px solid #ecf0f1;">Email</th>
                                <th style="padding: 12px; border-bottom: 2px solid #ecf0f1;">Role</th>
                                <th style="padding: 12px; border-bottom: 2px solid #ecf0f1;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <?php foreach ($allUsers as $u): ?>
                            <tr>
                                <td style="padding: 12px; border-bottom: 1px solid #f1f2f6;">
                                    <div style="font-weight: bold; color: #2c3e50;"><?php echo formatUserName($u); ?></div>
                                    <div style="font-size: 11px; color: #7f8c8d;">@<?php echo htmlspecialchars($u['username']); ?></div>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #f1f2f6;"><?php echo htmlspecialchars($u['email']); ?></td>
                                <td style="padding: 12px; border-bottom: 1px solid #f1f2f6;">
                                    <span class="role-badge <?php echo ($u['role_id'] == 1) ? 'role-admin' : 'role-staff'; ?>">
                                        <?php echo htmlspecialchars($u['role_name']); ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #f1f2f6;">
                                    <?php if ($u['user_id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to remove this user?');" style="display: inline;">
                                            <input type="hidden" name="delete_user_id" value="<?php echo $u['user_id']; ?>">
                                            <button type="submit" style="color: #e74c3c; background: none; border: none; cursor: pointer;" title="Remove Staff"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #95a5a6; font-size: 12px; font-style: italic;">(You)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div id="usersTablePagination" class="pagination-controls"></div>
                </div>

                <!-- Add User Form -->
                <div class="settings-card">
                    <h3 style="margin-top: 0; margin-bottom: 20px; color: #2c3e50;">Add New Staff</h3>
                    <form method="POST">
                        <input type="hidden" name="add_staff" value="1">
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" name="first_name" required>
                            </div>
                            <div class="form-group">
                                <label>Middle Name</label>
                                <input type="text" name="middle_name" placeholder="Optional">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px;">
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" name="last_name" required>
                            </div>
                            <div class="form-group">
                                <label>Suffix</label>
                                <input type="text" name="suffix" placeholder="e.g. Jr.">
                            </div>
                        </div>

                        <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">

                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" required placeholder="e.g. jdoe">
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" required placeholder="e.g. john@example.com">
                        </div>
                        <div class="form-group">
                            <label>System Role</label>
                            <select name="role_id" required>
                                <?php foreach ($allRoles as $role): ?>
                                    <?php if ($role['role_id'] != 3): // Exclude Volunteer role ?>
                                    <option value="<?php echo $role['role_id']; ?>" <?php echo ($role['role_id'] == 2) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 12px; margin-bottom: 10px; font-size: 13px; color: #166534;">
                            <i class="fa-solid fa-envelope"></i> A temporary password will be <strong>auto-generated</strong> and emailed to this staff member automatically.
                        </div>
                        <button type="submit" style="width: 100%; padding: 12px; background: #27ae60; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; margin-top: 10px;">
                            <i class="fa-solid fa-user-plus"></i> Create Account & Send Email
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    </script>

    <script src="../style/paginate.js"></script>
    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            tablinks = document.getElementsByClassName("tab-btn");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (document.getElementById('usersTableBody')) {
                paginateTable('usersTableBody', 'usersTablePagination', 10);
            }

            // Client-side password match check
            const newPw = document.getElementById('new_password');
            const confirmPw = document.getElementById('confirm_password');
            const msg = document.getElementById('pw_match_msg');
            if (newPw && confirmPw) {
                function checkMatch() {
                    if (confirmPw.value && newPw.value !== confirmPw.value) {
                        msg.style.display = 'block';
                        confirmPw.setCustomValidity('Passwords do not match.');
                    } else {
                        msg.style.display = 'none';
                        confirmPw.setCustomValidity('');
                    }
                }
                newPw.addEventListener('input', checkMatch);
                confirmPw.addEventListener('input', checkMatch);
            }

            // Password toggle logic
            document.querySelectorAll('.toggle-password').forEach(icon => {
                icon.addEventListener('click', function() {
                    const input = this.parentElement.querySelector('input');
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            });
        });
    </script>

</body>
</html>
