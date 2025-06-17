<?php
session_start();

// Admin credentials (you can change these)
$admin_email = 'admin@opticlook.com';
$admin_code = 'admin123';

$error = '';
$success = '';

// Check if admin is already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin_dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $code = trim($_POST['code']);
    
    if (empty($email) || empty($code)) {
        $error = 'Please fill in all fields.';
    } elseif ($email === $admin_email && $code === $admin_code) {
        // Successful login
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_email'] = $email;
        $_SESSION['admin_name'] = 'Administrator';
        $_SESSION['admin_id'] = 1;
        
        $success = 'Login successful! Redirecting...';
        header("refresh:1;url=admin_dashboard.php");
    } else {
        $error = 'Invalid email or code.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - OpticLook</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #2d3748;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .login-container {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }

        .login-header {
            background: #2d3748;
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .logo-icon {
            margin-right: 0.5rem;
        }

        .login-header h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: #e2e8f0;
            font-size: 0.875rem;
        }

        .login-form {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #4a5568;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f7fafc;
        }

        .form-control:focus {
            outline: none;
            border-color: #2d3748;
            background: white;
            box-shadow: 0 0 0 3px rgba(45, 55, 72, 0.1);
        }

        .btn-login {
            width: 100%;
            background: #2d3748;
            color: white;
            padding: 0.875rem 2rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .btn-login:hover {
            background: #1a202c;
            transform: translateY(-1px);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .alert-danger {
            background: #fed7d7;
            color: #9b2c2c;
            border: 1px solid #feb2b2;
        }

        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }

        .back-home {
            position: absolute;
            top: 1rem;
            left: 1rem;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .back-home:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
        }

        .back-home svg {
            margin-right: 0.5rem;
        }

        .admin-info {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            color: #4a5568;
        }

        .admin-info h4 {
            color: #2d3748;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .credentials {
            background: #edf2f7;
            padding: 0.75rem;
            border-radius: 0.375rem;
            margin-top: 0.5rem;
            font-family: 'Courier New', monospace;
            font-size: 0.75rem;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                margin: 1rem;
            }
            
            .login-form {
                padding: 1.5rem;
            }
            
            .login-header {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <a href="index.php" class="back-home">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M19 12H5"></path>
            <path d="M12 19l-7-7 7-7"></path>
        </svg>
        Back to Website
    </a>

    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <svg class="logo-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 12c0 6 4 10 10 10s10-4 10-10S18 6 12 6 2 6 2 12z"/>
                    <path d="M8 12h8"/>
                    <path d="M12 8v8"/>
                </svg>
                OpticLook
            </div>
            <h1>Admin Access</h1>
            <p>Enter your admin credentials to continue</p>
        </div>

        <div class="login-form">
            <div class="admin-info">
                <h4>Demo Admin Credentials:</h4>
                <p>Use these credentials to access the admin dashboard:</p>
                <div class="credentials">
                    <strong>Email:</strong> admin@opticlook.com<br>
                    <strong>Code:</strong> admin123
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Admin Email</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           placeholder="Enter admin email" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                           required>
                </div>

                <div class="form-group">
                    <label for="code">Admin Code</label>
                    <input type="password" id="code" name="code" class="form-control" 
                           placeholder="Enter admin code" required>
                </div>

                <button type="submit" class="btn-login">Access Dashboard</button>
            </form>
        </div>
    </div>

    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Focus first input
            document.getElementById('email').focus();
            
            // Add floating label effect
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    if (!this.value) {
                        this.parentElement.classList.remove('focused');
                    }
                });
            });

            // Auto-fill demo credentials on demo button click
            const credentials = document.querySelector('.credentials');
            if (credentials) {
                credentials.addEventListener('click', function() {
                    document.getElementById('email').value = 'admin@opticlook.com';
                    document.getElementById('code').value = 'admin123';
                });
                credentials.style.cursor = 'pointer';
                credentials.title = 'Click to auto-fill credentials';
            }
        });
    </script>
</body>
</html>