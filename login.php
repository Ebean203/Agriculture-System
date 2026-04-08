<?php
session_start();
require_once 'conn.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $isAjax = (
        (isset($_POST['ajax']) && $_POST['ajax'] === '1') ||
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    );

    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    // Get user from database
    $sql = "SELECT s.*, r.role 
            FROM mao_staff s 
            JOIN roles r ON s.role_id = r.role_id 
            WHERE username = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $row['password'])) {
            // Password is correct, create session
            $_SESSION['user_id'] = $row['staff_id'];
            $_SESSION['staff_id'] = $row['staff_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['full_name'] = $row['first_name'] . ' ' . $row['last_name'];
            
            // Log the login activity with user name and role
            require_once 'includes/activity_logger.php';
            $userLabel = trim($row['first_name'] . ' ' . $row['last_name']);
            $roleLabel = isset($row['role']) ? $row['role'] : '';
            $activityMsg = $userLabel;
            if ($roleLabel) {
                $activityMsg .= " (" . $roleLabel . ")";
            }
            $activityMsg .= " logged in";
            logActivity($conn, $activityMsg, 'login');

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'ok' => true,
                    'type' => 'success',
                    'message' => 'Login successful',
                    'redirect' => 'index.php'
                ]);
                exit();
            }

            $_SESSION['success_message'] = "Login successful";

            // Redirect to dashboard
            header("Location: index.php");
            exit();
        } else {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'ok' => false,
                    'type' => 'error',
                    'message' => 'Invalid password'
                ]);
                exit();
            }

            $_SESSION['error_message'] = "Invalid password";
            header("Location: login.php");
            exit();
        }
    } else {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => false,
                'type' => 'error',
                'message' => 'Invalid username'
            ]);
            exit();
        }

        $_SESSION['error_message'] = "Invalid username";
        header("Location: login.php");
        exit();
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Lagonglong FARMS</title>
    <link rel="icon" type="image/png" href="assets/Logo/E1361954-133F-4560-86CA-E4E3A2D916B8-removebg-preview.png">
    <?php include 'includes/assets.php'; ?>
    <style>
        :root {
            --agri-green: #15803d;
            --agri-dark: #166534;
            --agri-light: #dcfce7;
            --agri-gray: #6b7280;
        }
        
        body {
            background: linear-gradient(135deg, var(--agri-dark) 0%, var(--agri-green) 100%);
            height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        .login-container {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            padding: 1rem;
        }
        
        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--agri-dark) 0%, var(--agri-green) 100%);
            padding: 1.5rem 2rem;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }
        
        .login-header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 50%;
        }
        
        .logo-wrapper {
            position: relative;
            z-index: 1;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 145px;
            height: 145px;
            background: white;
            border-radius: 50%;
            margin-left: auto;
            margin-right: auto;
            box-shadow: 0 4px 12px rgba(255, 255, 255, 0.2);
        }
        
        .logo-wrapper img {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            border: 1px solid white;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
            object-fit: cover;
            object-position: center;
            display: block;
            margin: 0 auto;
        }
        
        .header-title {
            position: relative;
            z-index: 1;
        }
        
        .header-title h1 {
            font-size: 1.6rem;
            font-weight: 700;
            margin: 0;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .header-title p {
            font-size: 0.8rem;
            margin: 0.25rem 0 0 0;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }
        
        .login-body {
            padding: 1.5rem 2rem;
        }
        
        .form-group {
            margin-bottom: 1.2rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--agri-green);
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .input-wrapper i {
            position: absolute;
            left: 1rem;
            color: var(--agri-green);
            font-size: 1.1rem;
            z-index: 1;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.65rem 1rem 0.65rem 3rem;
            border: 2px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.85rem;
            color: #1f2937;
            transition: all 0.3s ease;
            background: #ffffff;
        }

        .form-group input::placeholder {
            color: #94a3b8;
        }
        
        .form-group input:hover {
            border-color: var(--agri-green);
            background: #ffffff;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--agri-green);
            background: white;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.14);
        }
        
        .submit-btn {
            width: 100%;
            padding: 0.8rem;
            background: linear-gradient(135deg, var(--agri-dark) 0%, var(--agri-green) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(21, 128, 61, 0.3);
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(21, 128, 61, 0.4);
        }
        
        .submit-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 10px rgba(21, 128, 61, 0.3);
        }
        
        .login-footer {
            text-align: center;
            padding: 1rem 2rem;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 0.75rem;
        }
        
        .login-footer strong {
            color: var(--agri-green);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-container {
                padding: 0.5rem;
                height: auto;
                min-height: 100vh;
            }

            .login-card {
                max-width: 100%;
            }

            .login-header {
                padding: 1.2rem 1.5rem;
            }

            .logo-wrapper {
                width: 125px;
                height: 125px;
            }

            .logo-wrapper img {
                width: 110px;
                height: 110px;
                border: 1px solid white;
                object-position: center;
            }

            .header-title h1 {
                font-size: 1.4rem;
            }

            .header-title p {
                font-size: 0.75rem;
            }

            .login-body {
                padding: 1.2rem 1.5rem;
            }

            .form-group {
                margin-bottom: 1rem;
            }

            .form-group label {
                font-size: 0.8rem;
            }

            .form-group input {
                padding: 0.6rem 0.8rem 0.6rem 2.5rem;
                font-size: 0.8rem;
            }

            .input-wrapper i {
                left: 0.75rem;
                font-size: 1rem;
            }

            .submit-btn {
                padding: 0.7rem;
                font-size: 0.85rem;
            }

            .login-footer {
                padding: 0.8rem 1.5rem;
                font-size: 0.7rem;
            }
        }

        @media (max-width: 480px) {
            body {
                height: auto;
                min-height: 100vh;
            }

            .login-container {
                padding: 0.5rem;
            }

            .login-header {
                padding: 1rem 1rem;
            }

            .logo-wrapper {
                width: 125px;
                height: 125px;
            }

            .logo-wrapper img {
                width: 95px;
                height: 95px;
                border: 1px solid white;
                object-position: center;
            }

            .header-title h1 {
                font-size: 1.2rem;
            }

            .header-title p {
                font-size: 0.7rem;
                margin: 0.15rem 0 0 0;
            }

            .login-body {
                padding: 1rem 1rem;
            }

            .form-group {
                margin-bottom: 0.9rem;
            }

            .form-group label {
                font-size: 0.75rem;
                margin-bottom: 0.35rem;
            }

            .form-group input {
                padding: 0.55rem 0.7rem 0.55rem 2.3rem;
                font-size: 0.75rem;
            }

            .input-wrapper i {
                left: 0.6rem;
                font-size: 0.9rem;
            }

            .submit-btn {
                padding: 0.65rem;
                font-size: 0.8rem;
            }

            .login-footer {
                padding: 0.7rem 1rem;
                font-size: 0.65rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Header Section with Logo -->
            <div class="login-header">
                <div class="logo-wrapper">
                    <img src="assets/Logo/E1361954-133F-4560-86CA-E4E3A2D916B8-removebg-preview.png" alt="Municipality of Lagonglong Agriculture Logo">
                </div>
                <div class="header-title">
                    <h1>Lagonglong FARMS</h1>
                    <p>Agriculture Management System</p>
                </div>
            </div>

            <!-- Form Section -->
            <div class="login-body">
                <form id="loginForm" method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" id="username" name="username" required placeholder="Enter your username">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" required placeholder="Enter your password">
                        </div>
                    </div>

                    <button id="loginSubmitBtn" type="submit" class="submit-btn">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Sign In</span>
                    </button>
                </form>
            </div>

            <!-- Footer -->
            <div class="login-footer">
                &copy; <strong><?php echo date('Y'); ?> Lagonglong FARMS</strong> | Agriculture Management System
            </div>
        </div>
    </div>
    <?php include 'includes/toast_flash.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.getElementById('loginForm');
            var submitBtn = document.getElementById('loginSubmitBtn');
            if (!form || !submitBtn) return;

            var icon = submitBtn.querySelector('i');
            var label = submitBtn.querySelector('span');
            var defaultLabel = label ? label.textContent : 'Sign In';

            form.addEventListener('submit', function (event) {
                event.preventDefault();

                var username = document.getElementById('username').value;
                var password = document.getElementById('password').value;
                var payload = 'username=' + encodeURIComponent(username) +
                    '&password=' + encodeURIComponent(password) +
                    '&ajax=1';

                submitBtn.disabled = true;
                if (label) label.textContent = 'Signing in...';
                if (icon) icon.className = 'fas fa-spinner fa-spin';

                fetch('login.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: payload
                })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (window.AgriToast && data && data.message) {
                        if (data.type === 'success') {
                            AgriToast.success(data.message, 2000);
                        } else {
                            AgriToast.error(data.message, 2000);
                        }
                    }

                    if (data && data.ok && data.redirect) {
                        setTimeout(function () {
                            window.location.href = data.redirect;
                        }, 450);
                        return;
                    }

                    submitBtn.disabled = false;
                    if (label) label.textContent = defaultLabel;
                    if (icon) icon.className = 'fas fa-sign-in-alt';
                })
                .catch(function () {
                    if (window.AgriToast) {
                        AgriToast.error('Unable to process login request. Please try again.', 2000);
                    }
                    submitBtn.disabled = false;
                    if (label) label.textContent = defaultLabel;
                    if (icon) icon.className = 'fas fa-sign-in-alt';
                });
            });
        });
    </script>
</body>
</html>
