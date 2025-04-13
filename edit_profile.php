<?php
require_once 'session_check.php';
require_once 'config/db.php';

// Get user information from session
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? null;
$role = $_SESSION['role'] ?? null;

if (!$user_id) {
    header('Location: login.php');
    exit();
}

// Process form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $new_username = $_POST['username'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate data
    if (empty($new_username)) {
        $message = 'Username cannot be empty';
        $message_type = 'danger';
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $message = 'New passwords do not match';
        $message_type = 'danger';
    } else {
        // Update user information
        try {
            $conn = getConnection();
            
            // First verify current password if changing password
            if (!empty($new_password)) {
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    if (!password_verify($current_password, $user['password'])) {
                        $message = 'Current password is incorrect';
                        $message_type = 'danger';
                        $conn->close();
                        // Don't continue with update
                    } else {
                        // Current password verified, continue with update
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
                        $stmt->bind_param("ssi", $new_username, $hashed_password, $user_id);
                    }
                }
            } else {
                // Only update username
                $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
                $stmt->bind_param("si", $new_username, $user_id);
            }
            
            // Execute update if no errors occurred
            if (empty($message)) {
                if ($stmt->execute()) {
                    $_SESSION['username'] = $new_username;
                    $message = 'Profile updated successfully';
                    $message_type = 'success';
                } else {
                    $message = 'Error updating profile: ' . $stmt->error;
                    $message_type = 'danger';
                }
            }
            
            $conn->close();
        } catch (Exception $e) {
            $message = 'Database error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Times New Roman', serif;
        }
        .edit-profile-container {
            max-width: 700px;
            margin: 50px auto;
            padding: 20px;
        }
        .certificate-card {
            background: #fff;
            border: 3px double #8B4513;
            border-radius: 0;
            padding: 30px;
            position: relative;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .certificate-card::before {
            content: '';
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            border: 1px solid #8B4513;
            pointer-events: none;
        }
        .edit-profile-title {
            font-family: 'Old English Text MT', 'Times New Roman', serif;
            font-size: 36px;
            color: #8B4513;
            letter-spacing: 2px;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
            text-transform: uppercase;
        }
        .form-label {
            color: #8B4513;
            font-weight: bold;
        }
        .form-control {
            border: 1px solid #8B4513;
            border-radius: 0;
            padding: 10px;
        }
        .form-control:focus {
            border-color: #8B4513;
            box-shadow: 0 0 0 0.25rem rgba(139, 69, 19, 0.25);
        }
        .btn-certificate {
            background-color: #8B4513;
            color: white;
            border: 1px solid #8B4513;
            padding: 10px 25px;
            font-weight: bold;
            text-transform: uppercase;
            transition: all 0.3s ease;
        }
        .btn-certificate:hover {
            background-color: #704214;
        }
        .corner {
            position: absolute;
            width: 50px;
            height: 50px;
        }
        .corner-top-left {
            top: 15px;
            left: 15px;
            border-top: 2px solid #8B4513;
            border-left: 2px solid #8B4513;
        }
        .corner-top-right {
            top: 15px;
            right: 15px;
            border-top: 2px solid #8B4513;
            border-right: 2px solid #8B4513;
        }
        .corner-bottom-left {
            bottom: 15px;
            left: 15px;
            border-bottom: 2px solid #8B4513;
            border-left: 2px solid #8B4513;
        }
        .corner-bottom-right {
            bottom: 15px;
            right: 15px;
            border-bottom: 2px solid #8B4513;
            border-right: 2px solid #8B4513;
        }
    </style>
</head>
<body>
    <div class="edit-profile-container">
        <div class="certificate-card">
            <div class="corner corner-top-left"></div>
            <div class="corner corner-top-right"></div>
            <div class="corner corner-bottom-left"></div>
            <div class="corner corner-bottom-right"></div>
            
            <h2 class="edit-profile-title">Edit Profile</h2>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="current_password" class="form-label">Current Password</label>
                    <input type="password" class="form-control" id="current_password" name="current_password">
                    <small class="text-muted">Required only if changing password</small>
                </div>
                
                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password">
                    <small class="text-muted">Leave blank to keep current password</small>
                </div>
                
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <a href="user_profile.php" class="btn btn-secondary me-md-2">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-certificate">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 