<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once '../../middleware/auth_required.php'; // Any logged-in user
require_once '../../config/db_connect.php'; // Add database connection
require_once '../../includes/header.php';

// Protect: If customer somehow reaches employee pages
if (get_user_role() === ROLE_CUSTOMER) {
    header('Location: /dashboards/customer/dashboard.php');
    exit();
}

// Get user position for dynamic sidebar
$user_id = $_SESSION['user_id'];
try {
    $positionSql = "
        SELECT p.position_name 
        FROM employees e
        JOIN positions p ON e.position_id = p.position_id
        WHERE e.user_id = ?
    ";
    $positionStmt = $pdo->prepare($positionSql);
    $positionStmt->execute([$user_id]);
    $positionData = $positionStmt->fetch();
    $positionName = $positionData ? $positionData['position_name'] : ''; // Load employee sidebar (now handles position-specific menus)
    require_once '../../includes/sidebar_employee.php';
} catch (PDOException $e) {
    // Default to employee sidebar if there's an error
    require_once '../../includes/sidebar_employee.php';
}

// Get currently logged in user's ID
$user_id = $_SESSION['user_id'];

try {
    // Get user information
    $userSql = "
        SELECT u.*, e.position_id, e.hire_date, e.branch_id
        FROM users u
        LEFT JOIN employees e ON u.user_id = e.user_id
        WHERE u.user_id = ?
    ";
    $userStmt = $pdo->prepare($userSql);
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch();

    // Get position name
    $positionSql = 'SELECT position_name FROM positions WHERE position_id = ?';
    $positionStmt = $pdo->prepare($positionSql);
    $positionStmt->execute([$user['position_id'] ?? 0]);
    $position = $positionStmt->fetch();
    $positionName = $position ? $position['position_name'] : 'Tailor';

    // Get branch name
    $branchSql = 'SELECT branch_name FROM branches WHERE branch_id = ?';
    $branchStmt = $pdo->prepare($branchSql);
    $branchStmt->execute([$user['branch_id'] ?? 0]);
    $branch = $branchStmt->fetch();
    $branchName = $branch ? $branch['branch_name'] : 'Main Branch';

    // Get work statistics
    $startOfWeek = date('Y-m-d', strtotime('monday this week'));
    $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
    $startOfMonth = date('Y-m-01');
    $endOfMonth = date('Y-m-t');

    // Weekly stats
    $weekStatsSql = "
        SELECT 
            COUNT(CASE WHEN ow.assigned_employee = ? THEN 1 END) AS assigned_count,
            COUNT(CASE WHEN ow.assigned_employee = ? AND o.status = 'Completed' THEN 1 END) AS completed_count
        FROM order_workflow ow
        JOIN orders o ON ow.order_id = o.order_id
        WHERE o.order_date BETWEEN ? AND ?
    ";
    $weekStatsStmt = $pdo->prepare($weekStatsSql);
    $weekStatsStmt->execute([$user_id, $user_id, $startOfWeek, $endOfWeek]);
    $weekStats = $weekStatsStmt->fetch();

    // Monthly stats
    $monthStatsSql = "
        SELECT 
            COUNT(CASE WHEN ow.assigned_employee = ? THEN 1 END) AS assigned_count,
            COUNT(CASE WHEN ow.assigned_employee = ? AND o.status = 'Completed' THEN 1 END) AS completed_count
        FROM order_workflow ow
        JOIN orders o ON ow.order_id = o.order_id
        WHERE o.order_date BETWEEN ? AND ?
    ";
    $monthStatsStmt = $pdo->prepare($monthStatsSql);
    $monthStatsStmt->execute([$user_id, $user_id, $startOfMonth, $endOfMonth]);
    $monthStats = $monthStatsStmt->fetch();

    // Quality rate
    $qualitySql = "
        SELECT 
            COUNT(ws.submission_id) AS total_submissions,
            COUNT(CASE WHEN ws.status = 'Passed' THEN 1 END) AS passed_count
        FROM work_submissions ws
        WHERE ws.employee_id = ?
        AND ws.submission_date >= ?
    ";
    $qualityStmt = $pdo->prepare($qualitySql);
    $qualityStmt->execute([$user_id, $startOfMonth]);
    $qualityStats = $qualityStmt->fetch();

    $totalSubmissions = $qualityStats['total_submissions'] ?? 0;
    $passRate = $totalSubmissions > 0 ? round(($qualityStats['passed_count'] / $totalSubmissions) * 100) : 95; // Default to 95% if no submissions
} catch (PDOException $e) {
    error_log('Profile error: ' . $e->getMessage());
    $user = [];
    $positionName = 'Tailor';
    $branchName = 'Main Branch';
    $weekStats = ['assigned_count' => 0, 'completed_count' => 0];
    $monthStats = ['assigned_count' => 0, 'completed_count' => 0];
    $passRate = 0;
}

// Handle password change
$passwordMessage = '';
$passwordError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $passwordError = 'All fields are required';
    } elseif ($newPassword !== $confirmPassword) {
        $passwordError = 'New passwords do not match';
    } elseif (strlen($newPassword) < 8) {
        $passwordError = 'Password must be at least 8 characters long';
    } else {
        try {
            // Verify current password
            $checkPasswordSql = 'SELECT password FROM users WHERE user_id = ?';
            $checkPasswordStmt = $pdo->prepare($checkPasswordSql);
            $checkPasswordStmt->execute([$user_id]);
            $storedPassword = $checkPasswordStmt->fetchColumn();

            if (!password_verify($currentPassword, $storedPassword)) {
                $passwordError = 'Current password is incorrect';
            } else {
                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updatePasswordSql = 'UPDATE users SET password = ? WHERE user_id = ?';
                $updatePasswordStmt = $pdo->prepare($updatePasswordSql);
                $updatePasswordStmt->execute([$hashedPassword, $user_id]);

                $passwordMessage = 'Password changed successfully';
            }
        } catch (PDOException $e) {
            error_log('Password change error: ' . $e->getMessage());
            $passwordError = 'An error occurred. Please try again.';
        }
    }
}

// Handle profile update
$profileMessage = '';
$profileError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';

    if (empty($email)) {
        $profileError = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $profileError = 'Please enter a valid email address';
    } else {
        try {
            // Update profile information
            $updateProfileSql = 'UPDATE users SET email = ?, phone = ? WHERE user_id = ?';
            $updateProfileStmt = $pdo->prepare($updateProfileSql);
            $updateProfileStmt->execute([$email, $phone, $user_id]);

            // Update user variable with new values
            $user['email'] = $email;
            $user['phone'] = $phone;

            $profileMessage = 'Profile updated successfully';
        } catch (PDOException $e) {
            error_log('Profile update error: ' . $e->getMessage());
            $profileError = 'An error occurred. Please try again.';
        }
    }
}

// Get full name or name from user data
$fullName = '';
if (isset($user['first_name']) && isset($user['last_name'])) {
    $fullName = $user['first_name'] . ' ' . $user['last_name'];
} elseif (isset($user['full_name'])) {
    $fullName = $user['full_name'];
} elseif (isset($user['name'])) {
    $fullName = $user['name'];
} else {
    $fullName = 'Employee User';
}

// Format hire date
$hireDate = isset($user['hire_date']) ? date('m/d/Y', strtotime($user['hire_date'])) : '';

// Generate employee ID
$employeeId = 'EMP-' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
?>

<main class="main-content">
    <div class="container-fluid mb-4">
        <div class="row">
            <div class="col-12">
                <h1 class="fw-bold">Profile</h1>
                <p class="text-muted">View and manage your account information</p>
            </div>
        </div>
    </div>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Personal Information -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <div class="mb-4">
                            <div class="avatar-placeholder mx-auto mb-3">
                                <?php
                                $initials = '';
                                $nameParts = explode(' ', $fullName);
                                if (count($nameParts) >= 2) {
                                    $initials = strtoupper(
                                        substr($nameParts[0], 0, 1) . substr($nameParts[count($nameParts) - 1], 0, 1)
                                    );
                                } else {
                                    $initials = strtoupper(substr($fullName, 0, 2));
                                }
                                ?>
                                <span class="initials"><?= htmlspecialchars($initials) ?></span>
                            </div>
                            <h4 class="fw-bold mb-1"><?= htmlspecialchars($fullName) ?></h4>
                            <p class="text-muted mb-0"><?= htmlspecialchars($positionName) ?></p>
                        </div>
                        
                        <div class="text-start border-top pt-4">
                            <div class="mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-envelope text-muted me-3"></i>
                                    <div>
                                        <p class="mb-0"><?= htmlspecialchars(
                                            $user['email'] ?? 'No email provided'
                                        ) ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-id-card text-muted me-3"></i>
                                    <div>
                                        <p class="mb-0">ID: <?= htmlspecialchars($employeeId) ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-map-marker-alt text-muted me-3"></i>
                                    <div>
                                        <p class="mb-0">Section: Tailoring</p>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-0">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-calendar-alt text-muted me-3"></i>
                                    <div>
                                        <p class="mb-0">Joined: <?= !empty($hireDate)
                                            ? htmlspecialchars($hireDate)
                                            : 'Not specified' ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button class="btn btn-outline-dark w-100" data-bs-toggle="modal" data-bs-target="#updatePhotoModal">
                                <i class="fas fa-camera me-2"></i>Update Profile Photo
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Performance Statistics -->
            <div class="col-lg-8 mb-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Performance Statistics</h5>
                        <p class="text-muted small">Your work statistics for this week and month</p>
                        
                        <div class="row mb-4">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="card border-0 bg-light">
                                    <div class="card-body text-center p-4">
                                        <div class="icon-circle bg-white mb-3">
                                            <i class="fas fa-calendar-week text-primary"></i>
                                        </div>
                                        <h6 class="text-muted mb-2">This Week</h6>
                                        <div class="d-flex justify-content-around">
                                            <div>
                                                <h3 class="fw-bold mb-0"><?= $weekStats['assigned_count'] ?? 0 ?></h3>
                                                <small class="text-muted">Assigned</small>
                                            </div>
                                            <div>
                                                <h3 class="fw-bold mb-0"><?= $weekStats['completed_count'] ?? 0 ?></h3>
                                                <small class="text-muted">Completed</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="card border-0 bg-light">
                                    <div class="card-body text-center p-4">
                                        <div class="icon-circle bg-white mb-3">
                                            <i class="fas fa-calendar-alt text-primary"></i>
                                        </div>
                                        <h6 class="text-muted mb-2">This Month</h6>
                                        <div class="d-flex justify-content-around">
                                            <div>
                                                <h3 class="fw-bold mb-0"><?= $monthStats['assigned_count'] ?? 0 ?></h3>
                                                <small class="text-muted">Assigned</small>
                                            </div>
                                            <div>
                                                <h3 class="fw-bold mb-0"><?= $monthStats['completed_count'] ?? 0 ?></h3>
                                                <small class="text-muted">Completed</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 bg-light">
                                    <div class="card-body text-center p-4">
                                        <div class="icon-circle bg-white mb-3">
                                            <i class="fas fa-chart-line text-primary"></i>
                                        </div>
                                        <h6 class="text-muted mb-2">Quality Rate</h6>
                                        <div>
                                            <h3 class="fw-bold mb-0"><?= $passRate ?>%</h3>
                                            <small class="text-muted">Pass Rate</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card border-0 mb-0">
                            <div class="card-body p-0">
                                <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal-details" type="button" role="tab" aria-controls="personal-details" aria-selected="true">Personal Details</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab" aria-controls="password" aria-selected="false">Password</button>
                                    </li>
                                </ul>
                                
                                <div class="tab-content p-4" id="profileTabContent">
                                    <div class="tab-pane fade show active" id="personal-details" role="tabpanel" aria-labelledby="personal-tab">
                                        <?php if ($profileMessage): ?>
                                            <div class="alert alert-success"><?= htmlspecialchars(
                                                $profileMessage
                                            ) ?></div>
                                        <?php endif; ?>
                                        
                                        <?php if ($profileError): ?>
                                            <div class="alert alert-danger"><?= htmlspecialchars($profileError) ?></div>
                                        <?php endif; ?>
                                        
                                        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                                            <div class="mb-3">
                                                <label for="fullName" class="form-label">Full Name</label>
                                                <input type="text" class="form-control" id="fullName" value="<?= htmlspecialchars(
                                                    $fullName
                                                ) ?>" disabled>
                                            </div>
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Email Address</label>
                                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars(
                                                    $user['email'] ?? ''
                                                ) ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="phone" class="form-label">Phone Number</label>
                                                <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars(
                                                    $user['phone'] ?? ''
                                                ) ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label for="branch" class="form-label">Branch</label>
                                                <input type="text" class="form-control" id="branch" value="<?= htmlspecialchars(
                                                    $branchName
                                                ) ?>" disabled>
                                            </div>
                                            <div class="mb-3">
                                                <label for="position" class="form-label">Position</label>
                                                <input type="text" class="form-control" id="position" value="<?= htmlspecialchars(
                                                    $positionName
                                                ) ?>" disabled>
                                            </div>
                                            <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                                        </form>
                                    </div>
                                    <div class="tab-pane fade" id="password" role="tabpanel" aria-labelledby="password-tab">
                                        <?php if ($passwordMessage): ?>
                                            <div class="alert alert-success"><?= htmlspecialchars(
                                                $passwordMessage
                                            ) ?></div>
                                        <?php endif; ?>
                                        
                                        <?php if ($passwordError): ?>
                                            <div class="alert alert-danger"><?= htmlspecialchars(
                                                $passwordError
                                            ) ?></div>
                                        <?php endif; ?>
                                        
                                        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                                            <div class="mb-3">
                                                <label for="current_password" class="form-label">Current Password</label>
                                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="new_password" class="form-label">New Password</label>
                                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                                                <div class="form-text">Password must be at least 8 characters long</div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                                            </div>
                                            <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Update Photo Modal -->
    <div class="modal fade" id="updatePhotoModal" tabindex="-1" aria-labelledby="updatePhotoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updatePhotoModalLabel">Update Profile Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadPhotoForm" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="profile_photo" class="form-label">Choose Photo</label>
                            <input type="file" class="form-control" id="profile_photo" name="profile_photo" accept="image/*">
                            <div class="form-text">Please choose a square image for best results</div>
                        </div>
                        <div id="photoPreview" class="text-center mb-3" style="display: none;">
                            <img src="" alt="Preview" class="img-thumbnail rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="savePhotoBtn">Save Photo</button>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.avatar-placeholder {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background-color: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
}
.avatar-placeholder .initials {
    font-size: 48px;
    font-weight: bold;
    color: #6c757d;
}
.icon-circle {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}
.icon-circle i {
    font-size: 24px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Photo preview
    const photoInput = document.getElementById('profile_photo');
    const photoPreview = document.getElementById('photoPreview');
    const previewImg = photoPreview.querySelector('img');
    
    if (photoInput) {
        photoInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    photoPreview.style.display = 'block';
                }
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
    
    // Save photo button
    const savePhotoBtn = document.getElementById('savePhotoBtn');
    
    if (savePhotoBtn) {
        savePhotoBtn.addEventListener('click', function() {
            // Here you would normally submit the form with AJAX
            // For this example, we'll just show an alert
            alert('Profile photo updated successfully');
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('updatePhotoModal'));
            modal.hide();
        });
    }
    
    // Password validation
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    if (confirmPassword) {
        confirmPassword.addEventListener('input', function() {
            if (this.value !== newPassword.value) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    }
});

</script>

<?php require_once '../../includes/footer.php'; ?>
