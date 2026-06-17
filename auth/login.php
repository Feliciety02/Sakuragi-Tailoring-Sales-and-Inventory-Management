<?php
require_once '../config/db_connect.php';
require_once '../config/session_handler.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';

redirect_if_logged_in();  // ✅ Correctly placed at the top

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];

    // Validation
    if (empty($email) || empty($password)) {
        set_flash('error', 'Email and password are required.');
        header('Location: login.php');
        exit();
    }

    // Check if the user exists and is active
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = ?");
    $stmt->execute([$email, STATUS_ACTIVE]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && verify_password($password, $user['password'])) {
        // Successful login: set session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];

        // Role-based redirection
        switch ($user['role']) {
            case ROLE_ADMIN:
                header('Location: /dashboards/admin/dashboard.php');
                break;
            case ROLE_MANAGER:
            case ROLE_EMPLOYEE:
                header('Location: /dashboards/employee/dashboard.php');
                break;
            case ROLE_CUSTOMER:
            default:
                header('Location: /dashboards/customer/dashboard.php');
                break;
        }
        exit();
    } else {
        // Incorrect login
        set_flash('error', 'Invalid email or password.');
        header('Location: login.php');
        exit();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../public/assets/css/auth.css"></link>

    <meta charset="UTF-8">
    <title>Sakuragi Tailoring Shop - Login</title>
   
</head>
<body>
        <div class="particles">
            <span></span><span></span><span></span><span></span><span></span>
            <span></span><span></span><span></span><span></span><span></span>
            <span></span><span></span><span></span><span></span><span></span>
            <span></span><span></span><span></span><span></span><span></span>
            <span></span><span></span><span></span><span></span><span></span>
        </div>

    <div class="login-container">
      
    <div class="back-wrapper">
             <a href="../index.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
        </a></div>
        
        <div class="login-form">
    
            <h2>Welcome Back!</h2>
            <p>Sign in to continue to <strong>Sakuragi Tailoring Shop</strong>.</p>
            
            <?php if ($msg = get_flash('error')): ?>
                <p class="error-msg"><?= $msg ?></p>
            <?php endif; ?>

            <form method="POST" action="">
                <label for="email">E-Mail</label>
                <input type="email" name="email" id="email" placeholder="yourmail@example.com" required>

                <label for="password">Password</label>
                <input type="password" name="password" id="password" placeholder="Enter your password" required>

                <button type="submit">Sign In</button>
            </form>

            <div class="demo-accounts">
                <p class="demo-title">Demo Accounts</p>
                <div class="demo-buttons">
                    <button type="button" class="demo-btn admin" onclick="fillDemo('admin@sakuragi.ph','admin123')">
                        <i class="fas fa-user-shield"></i> Admin
                    </button>
                    <button type="button" class="demo-btn employee" onclick="fillDemo('employee@demo.ph','demo123')">
                        <i class="fas fa-user-tie"></i> Employee
                    </button>
                    <button type="button" class="demo-btn customer" onclick="fillDemo('customer@demo.ph','demo123')">
                        <i class="fas fa-user"></i> Customer
                    </button>
                </div>
            </div>

            <a href="register.php">Don't have an account? Sign Up here.</a>
        </div>
        <div class="illustration"></div>
    </div>
<script>
const demos = {
    admin: ['admin@sakuragi.ph', 'admin123'],
    employee: ['employee@demo.ph', 'demo123'],
    customer: ['customer@demo.ph', 'demo123']
};
function fillDemo(email, password) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = password;
    document.querySelector('.login-form form').submit();
}
const params = new URLSearchParams(window.location.search);
const demo = params.get('demo');
if (demo && demos[demo]) {
    fillDemo(demos[demo][0], demos[demo][1]);
}
</script>

<style>
.demo-accounts {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid #eee;
}
.demo-title {
    font-size: 0.85rem;
    color: #888;
    margin-bottom: 10px;
    text-align: center;
}
.demo-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
    flex-wrap: wrap;
}
.demo-btn {
    padding: 8px 16px;
    border: 1px solid #ddd;
    border-radius: 6px;
    background: #fff;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.demo-btn:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.demo-btn.admin { color: #dc3545; border-color: #dc3545; }
.demo-btn.admin:hover { background: #dc3545; color: #fff; }
.demo-btn.employee { color: #fd7e14; border-color: #fd7e14; }
.demo-btn.employee:hover { background: #fd7e14; color: #fff; }
.demo-btn.customer { color: #0d6efd; border-color: #0d6efd; }
.demo-btn.customer:hover { background: #0d6efd; color: #fff; }
</style>
</body>
</html>
