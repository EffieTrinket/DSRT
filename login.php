<?php
session_start();
require_once ('config/user.php');

require_once ('classes/UserModel.php');
require_once ('classes/EmailHelper.php');

$userObj = new User();
$dbObj = new Database();
$conn = $dbObj->connect();
$userModel = new UserModel($conn);

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['login'])) {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        $user = $userObj->login($email, $password);

        if ($user === 'deactivated') {
            $error = 'Your account has been deactivated. Please contact an administrator.';
        } elseif ($user === 'pending') {
            $error = 'Your volunteer account is pending admin approval.';
        } elseif ($user) {
            $_SESSION['user'] = $user['username'];
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = strtolower($user['role_name']);

            if ($_SESSION['role_name'] === 'volunteer') {
                header('Location: pages/disasters.php');
            } else {
                header('Location: pages/dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    } elseif (isset($_POST['register_volunteer'])) {
        $fname = trim($_POST['first_name']);
        $mname = trim($_POST['middle_name']);
        $lname = trim($_POST['last_name']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $notes = trim($_POST['notes']);

        $existingUser = $userModel->checkUserExists($username, $email);
        if ($existingUser) {
            if ($existingUser['email'] === $email) {
                $error = "This email is already registered.";
            } else {
                $error = "This username is already taken.";
            }
        } elseif (!EmailHelper::isValidEmailDomain($email)) {
            $error = "Please enter a valid, working email address.";
        } else {
            // Handle ID photo uploads
            $id_front_path = null;
            $id_back_path = null;
            $upload_dir = __DIR__ . '/uploads/ids/';
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

            function uploadIdPhoto($file_key, $upload_dir, $allowed_types, $username, $side) {
                if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
                    return ['error' => "$side ID photo is required."];
                }
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES[$file_key]['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, $allowed_types)) {
                    return ['error' => "$side ID must be a JPG, PNG, GIF, or WEBP image."];
                }
                $ext = pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION);
                $safe_name = preg_replace('/[^a-z0-9]/i', '_', $username);
                $filename = $safe_name . '_' . strtolower($side) . '_' . time() . '.' . strtolower($ext);
                if (!move_uploaded_file($_FILES[$file_key]['tmp_name'], $upload_dir . $filename)) {
                    return ['error' => "Failed to upload $side ID."];
                }
                return ['path' => $filename];
            }

            $front = uploadIdPhoto('id_front', $upload_dir, $allowed_types, $username, 'Front');
            $back  = uploadIdPhoto('id_back',  $upload_dir, $allowed_types, $username, 'Back');

            if (isset($front['error'])) {
                $error = $front['error'];
            } elseif (isset($back['error'])) {
                $error = $back['error'];
            } else {
                $id_front_path = $front['path'];
                $id_back_path  = $back['path'];
                try {
                    if ($userModel->registerVolunteer($username, $email, $fname, $mname, $lname, $notes, $id_front_path, $id_back_path)) {
                        $success = "Registration successful! Your application is pending admin approval. You will receive an email with your temporary password once approved.";
                    } else {
                        $error = "Failed to register. Please try again.";
                    }
                } catch (PDOException $e) {
                    $error = "An error occurred during registration.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disaster Relief Tracker - Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="style/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="page-wrapper">
<div class="login-container">

    <div class="left-panel">
        <div class="left-content">
            <!-- <div class="icon-box">🌊</div> -->
            <h1>Disaster Relief Tracker</h1>
            <p>
                A simple and reliable system for tracking disaster events,
                monitoring affected residents, and managing relief goods efficiently.
            </p>
        </div>
    </div>

    <div class="right-panel">
        <h2>Login</h2>
        <p>Access the system</p>

        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div style="background: #ecfdf5; color: #047857; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; border: 1px solid #10b981; display: flex; align-items: center; gap: 8px;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="login" value="1">
            <div class="input-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>

            <div class="input-group">
                <label>Password</label>
                <div class="password-toggle">
                    <input type="password" name="password" id="password" required>
                    <i class="fa-solid fa-eye-slash" id="togglePassword"></i>
                </div>
            </div>

            <button type="submit" class="btn">Login</button>
        </form>

        <div style="text-align: center; margin-top: 15px;">
            <p style="font-size: 14px; color: #64748b; margin-bottom: 8px;">Want to help the community?</p>
            <button onclick="document.getElementById('registerModal').style.display='flex'" style="background: none; border: 1.5px solid #3b82f6; color: #3b82f6; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; width: 100%; transition: all 0.2s;">
                Become a Volunteer
            </button>
        </div>

        <div class="footer-text">
            Simple and secure disaster response access
        </div>
    </div>

</div>
</div>

<!-- Register Volunteer Modal -->
<div id="registerModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; overflow-y: auto; padding: 20px 0;">
    <div style="background: white; border-radius: 12px; padding: 30px; width: 100%; max-width: 500px; margin: auto; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
        <h2 style="margin-top:0; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; font-size: 20px;">Volunteer Registration</h2>
        <form method="POST" id="registerForm" enctype="multipart/form-data">
            <input type="hidden" name="register_volunteer" value="1">
            
            <div style="display: flex; gap: 10px; margin-bottom: 12px;">
                <div style="flex: 1;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">First Name</label>
                    <input type="text" name="first_name" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px;">
                </div>
                <div style="flex: 1;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Last Name</label>
                    <input type="text" name="last_name" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px;">
                </div>
            </div>

            <div style="margin-bottom: 12px;">
                <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Middle Name (Optional)</label>
                <input type="text" name="middle_name" style="width: 100%; padding: 10px; border-radius: 8px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px;">
            </div>

            <div style="display: flex; gap: 10px; margin-bottom: 12px;">
                <div style="flex: 1;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Username</label>
                    <input type="text" name="username" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px;">
                </div>
                <div style="flex: 1;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Email</label>
                    <input type="email" name="email" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px;">
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Reason for Volunteering / Notes</label>
                <textarea name="notes" rows="3" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; resize: vertical;"></textarea>
            </div>

            <div style="background: #f8fafc; border: 1.5px dashed #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 20px;">
                <p style="margin: 0 0 12px 0; font-size: 13px; font-weight: 700; color: #0f172a;"><i class="fa-solid fa-id-card"></i> Government-Issued ID <span style="color: #ef4444;">*</span></p>
                <p style="margin: 0 0 12px 0; font-size: 12px; color: #64748b;">Please upload clear photos of your valid ID (front and back). Accepted formats: JPG, PNG, WEBP.</p>
                <div style="display: flex; gap: 10px;">
                    <div style="flex: 1;">
                        <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Front Side</label>
                        <input type="file" name="id_front" accept="image/*" required style="width: 100%; font-size: 12px; padding: 8px; border-radius: 6px; border: 1.5px solid #e2e8f0; background: white;">
                    </div>
                    <div style="flex: 1;">
                        <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Back Side</label>
                        <input type="file" name="id_back" accept="image/*" required style="width: 100%; font-size: 12px; padding: 8px; border-radius: 6px; border: 1.5px solid #e2e8f0; background: white;">
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1.5px solid #f1f5f9; padding-top: 15px;">
                <button type="button" onclick="document.getElementById('registerModal').style.display='none'" style="padding: 10px 18px; background: #f1f5f9; color: #475569; border: 1.5px solid #e2e8f0; border-radius: 8px; cursor: pointer; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif;">Cancel</button>
                <button type="submit" style="padding: 10px 22px; background: #3b82f6; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif;">Submit Application</button>
            </div>
        </form>
    </div>
</div>

<script>
const togglePassword = document.querySelector('#togglePassword');
const password = document.querySelector('#password');

togglePassword.addEventListener('click', function (e) {
    // toggle the type attribute
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);
    
    // toggle the eye slash icon
    this.classList.toggle('fa-eye');
    this.classList.toggle('fa-eye-slash');
});
</script>

</body>
</html>
