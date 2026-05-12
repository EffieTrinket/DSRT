<?php
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Only allow Admins to access this page
if ($_SESSION['role_id'] != 1) {
    header('Location: dashboard.php');
    exit;
}

require_once '../config/database.php';
require_once '../classes/UserModel.php';
require_once '../classes/EmailHelper.php';

$db = new Database();
$conn = $db->connect();
$userModel = new UserModel($conn);

$success_msg = "";
$error_msg = "";

// Handle Deleting/Rejecting Volunteer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $delete_id = $_POST['delete_user_id'];
    if ($userModel->deleteUser($delete_id)) {
        $success_msg = "Volunteer application removed.";
    } else {
        $error_msg = "Cannot delete this user.";
    }
}

// Handle Toggling Active Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_id'])) {
    $toggle_id = $_POST['toggle_user_id'];
    if ($userModel->toggleUserStatus($toggle_id)) {
        $success_msg = "Volunteer status updated.";
    } else {
        $error_msg = "Cannot update status for this user.";
    }
}

// Handle Approving Volunteer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_user_id'])) {
    $approve_id = $_POST['approve_user_id'];
    
    $pendingUser = $userModel->getUserById($approve_id);
    
    if ($pendingUser) {
        $tempPassword = $userModel->approveUser($approve_id);
        if ($tempPassword) {
            $name = !empty($pendingUser['first_name']) ? $pendingUser['first_name'] : $pendingUser['username'];
            EmailHelper::sendApprovalEmail($pendingUser['email'], $name, $tempPassword);
            
            $success_msg = "Volunteer approved. A temporary password was emailed to them.";
        } else {
            $error_msg = "Cannot approve this user.";
        }
    } else {
        $error_msg = "User not found.";
    }
}

// Fetch volunteers
$pendingVolunteers = $userModel->getPendingVolunteers();
$activeVolunteers = $userModel->getActiveVolunteers();

$activePage = 'volunteers';
$sidebarBase = '';

// Helper to format name
function formatUserName($u) {
    $name = (isset($u['first_name']) ? $u['first_name'] : $u['username']) . ' ';
    if (!empty($u['middle_name'])) $name .= substr($u['middle_name'], 0, 1) . '. ';
    if (!empty($u['last_name'])) $name .= $u['last_name'];
    if (!empty($u['suffix'])) $name .= ' ' . $u['suffix'];
    return htmlspecialchars(trim($name));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteers - Disaster Relief Tracker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../style/dashboard.css">
    <link rel="stylesheet" href="../style/tables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .settings-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .success-msg {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid #28a745;
        }
        .error-msg {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid #dc3545;
        }
    </style>
</head>
<body>

    <?php include '../includes/sidebar.php'; ?>

    <div class="main">
        <div class="header">
            <div>
                <h1><i class="fa-solid fa-user-plus"></i> Volunteer Management</h1>
                <p>Review and manage community volunteers</p>
            </div>
        </div>

        <?php if ($success_msg): ?>
            <div class="success-msg"><i class="fa-solid fa-circle-check"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- Pending Volunteers -->
        <div class="settings-card">
            <h3 style="margin-top: 0; margin-bottom: 20px; color: #2c3e50;"><i class="fa-solid fa-clock-rotate-left"></i> Pending Applications</h3>
            
            <?php if (empty($pendingVolunteers)): ?>
                <p style="color: #7f8c8d; font-style: italic;">No pending volunteer applications at this time.</p>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; background: #f8f9fa;">
                            <th style="padding: 12px; border-bottom: 2px solid #ecf0f1;">Applicant Name</th>
                            <th style="padding: 12px; border-bottom: 2px solid #ecf0f1;">Email</th>
                            <th style="padding: 12px; border-bottom: 2px solid #ecf0f1;">Reason / Notes</th>
                            <th style="padding: 12px; border-bottom: 2px solid #ecf0f1;">Submitted ID</th>
                            <th style="padding: 12px; border-bottom: 2px solid #ecf0f1;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingVolunteers as $u): ?>
                        <tr>
                            <td style="padding: 12px; border-bottom: 1px solid #f1f2f6;">
                                <div style="font-weight: bold; color: #2c3e50;"><?php echo formatUserName($u); ?></div>
                                <div style="font-size: 11px; color: #7f8c8d;">@<?php echo htmlspecialchars($u['username']); ?></div>
                                <div style="margin-top: 4px;"><span style="background: #fef3c7; color: #d97706; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold;">Pending Review</span></div>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #f1f2f6;"><?php echo htmlspecialchars($u['email']); ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid #f1f2f6; color: #475569; font-size: 13px; max-width: 300px;">
                                "<?php echo htmlspecialchars($u['volunteer_notes'] ?? 'No notes provided.'); ?>"
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #f1f2f6;">
                                <?php if (!empty($u['id_front_path']) || !empty($u['id_back_path'])): ?>
                                    <button type="button" onclick="viewId('<?php echo htmlspecialchars($u['id_front_path'] ?? ''); ?>', '<?php echo htmlspecialchars($u['id_back_path'] ?? ''); ?>')" style="background: #3b82f6; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: bold;"><i class="fa-solid fa-id-card"></i> View ID</button>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 12px; font-style: italic;">No ID uploaded</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #f1f2f6;">
                                <form method="POST" onsubmit="return confirm('Approve this volunteer? They will receive an email with their password.');" style="display: inline;">
                                    <input type="hidden" name="approve_user_id" value="<?php echo $u['user_id']; ?>">
                                    <button type="submit" style="color: #10b981; background: none; border: none; cursor: pointer; margin-right: 15px; font-size: 16px;" title="Approve"><i class="fa-solid fa-check"></i></button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to reject and remove this application?');" style="display: inline;">
                                    <input type="hidden" name="delete_user_id" value="<?php echo $u['user_id']; ?>">
                                    <button type="submit" style="color: #e74c3c; background: none; border: none; cursor: pointer; font-size: 16px;" title="Reject"><i class="fa-solid fa-xmark"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Active Volunteers -->
        <div class="settings-card">
            <h3 style="margin-top: 0; margin-bottom: 20px; color: #2c3e50;"><i class="fa-solid fa-users"></i> Active Volunteers</h3>
            
            <?php if (empty($activeVolunteers)): ?>
                <p style="color: #7f8c8d; font-style: italic;">No active volunteers found.</p>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; background: #f8f9fa;">
                            <th style="padding: 12px; border-bottom: 2px solid #ecf0f1;">Volunteer Name</th>
                            <th style="padding: 12px; border-bottom: 2px solid #ecf0f1;">Email</th>
                            <th style="padding: 12px; border-bottom: 2px solid #ecf0f1;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeVolunteers as $u): ?>
                        <tr>
                            <td style="padding: 12px; border-bottom: 1px solid #f1f2f6;">
                                <div style="font-weight: bold; color: #2c3e50;"><?php echo formatUserName($u); ?></div>
                                <div style="font-size: 11px; color: #7f8c8d;">@<?php echo htmlspecialchars($u['username']); ?></div>
                                <?php if ($u['is_active']): ?>
                                    <span style="background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-top: 5px; display: inline-block;">Active</span>
                                <?php else: ?>
                                    <span style="background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-top: 5px; display: inline-block;">Deactivated</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #f1f2f6;"><?php echo htmlspecialchars($u['email']); ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid #f1f2f6;">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="toggle_user_id" value="<?php echo $u['user_id']; ?>">
                                    <?php if ($u['is_active']): ?>
                                        <button type="submit" onclick="return confirm('Deactivate this volunteer? They will no longer be able to log in.');" style="color: #10b981; background: none; border: none; cursor: pointer; font-size: 18px; margin-right: 15px;" title="Account is Active. Click to Deactivate."><i class="fa-solid fa-toggle-on"></i></button>
                                    <?php else: ?>
                                        <button type="submit" onclick="return confirm('Reactivate this volunteer?');" style="color: #e74c3c; background: none; border: none; cursor: pointer; font-size: 18px; margin-right: 15px;" title="Account is Deactivated. Click to Reactivate."><i class="fa-solid fa-toggle-off"></i></button>
                                    <?php endif; ?>
                                </form>
                                <form method="POST" onsubmit="return confirm('Permanently delete this volunteer? This cannot be undone.');" style="display: inline;">
                                    <input type="hidden" name="delete_user_id" value="<?php echo $u['user_id']; ?>">
                                    <button type="submit" style="color: #94a3b8; background: none; border: none; cursor: pointer; font-size: 16px; transition: color 0.3s;" onmouseover="this.style.color='#e74c3c'" onmouseout="this.style.color='#94a3b8'" title="Delete Permanently"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>

</body>

<!-- ID Viewer Modal -->
<div id="idViewerModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); z-index:9999; align-items:center; justify-content:center; padding: 20px;" onclick="if(event.target===this)closeIdModal()">
    <div style="background: white; border-radius: 14px; padding: 28px; width: 100%; max-width: 800px; margin: auto; box-shadow: 0 20px 50px rgba(0,0,0,0.3); position: relative;">
        <button onclick="closeIdModal()" style="position: absolute; top: 15px; right: 15px; background: #f1f5f9; border: none; border-radius: 50%; width: 32px; height: 32px; cursor: pointer; font-size: 16px; color: #64748b; display: flex; align-items: center; justify-content: center;">&times;</button>
        <h3 style="margin: 0 0 20px 0; color: #0f172a; font-size: 18px;"><i class="fa-solid fa-id-card"></i> Applicant ID Photos</h3>
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px;">
                <p style="margin: 0 0 8px 0; font-size: 13px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Front Side</p>
                <img id="idFrontImg" src="" alt="ID Front" style="width: 100%; border-radius: 8px; border: 1.5px solid #e2e8f0; object-fit: contain; max-height: 300px; background: #f8fafc;">
            </div>
            <div style="flex: 1; min-width: 250px;">
                <p style="margin: 0 0 8px 0; font-size: 13px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Back Side</p>
                <img id="idBackImg" src="" alt="ID Back" style="width: 100%; border-radius: 8px; border: 1.5px solid #e2e8f0; object-fit: contain; max-height: 300px; background: #f8fafc;">
            </div>
        </div>
        <p style="margin: 16px 0 0 0; font-size: 12px; color: #94a3b8; text-align: center;"><i class="fa-solid fa-lock"></i> These images are only visible to Administrators.</p>
    </div>
</div>

<script>
function viewId(frontFile, backFile) {
    var baseUrl = '../actions/view_id.php?file=';
    document.getElementById('idFrontImg').src = frontFile ? baseUrl + encodeURIComponent(frontFile) : '';
    document.getElementById('idBackImg').src = backFile ? baseUrl + encodeURIComponent(backFile) : '';
    document.getElementById('idViewerModal').style.display = 'flex';
}
function closeIdModal() {
    document.getElementById('idViewerModal').style.display = 'none';
    document.getElementById('idFrontImg').src = '';
    document.getElementById('idBackImg').src = '';
}
</script>

</html>
