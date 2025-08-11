<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Agricultural Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'agri-green': '#16a34a',
                        'agri-light': '#dcfce7',
                        'agri-dark': '#15803d'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full mx-4">
            <!-- Logo and Title -->
            <div class="text-center mb-8">
                <i class="fas fa-seedling text-agri-green text-5xl mb-4"></i>
                <h2 class="text-3xl font-bold text-gray-900">Agricultural Management System</h2>
                <p class="text-gray-600">Please sign in to continue</p>
            </div>

            <?php
            session_start();
            require_once 'conn.php';

            // Check if form is submitted
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
                        $_SESSION['username'] = $row['username'];
                        $_SESSION['role'] = $row['role'];
                        $_SESSION['full_name'] = $row['first_name'] . ' ' . $row['last_name'];
                        
                        // Redirect to dashboard
                        header("Location: index.php");
                        exit();
                    } else {
                        $error = "Invalid password";
                    }
                } else {
                    $error = "Invalid username";
                }
                mysqli_stmt_close($stmt);
            }
            ?>

            <!-- Login Form -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-6">
                        <label for="username" class="block text-gray-700 text-sm font-bold mb-2">
                            Username
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input type="text" id="username" name="username" required
                                class="appearance-none block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-agri-green focus:border-agri-green"
                                placeholder="Enter your username">
                        </div>
                    </div>

                    <div class="mb-6">
                        <label for="password" class="block text-gray-700 text-sm font-bold mb-2">
                            Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" id="password" name="password" required
                                class="appearance-none block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-agri-green focus:border-agri-green"
                                placeholder="Enter your password">
                        </div>
                    </div>

                    <div>
                        <button type="submit"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-agri-green hover:bg-agri-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-agri-green">
                            <i class="fas fa-sign-in-alt mr-2"></i> Sign In
                        </button>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="text-center mt-8 text-gray-600 text-sm">
                &copy; <?php echo date('Y'); ?> Agricultural Management System
            </div>
        </div>
    </div>
</body>
</html>
