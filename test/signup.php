<?php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'opti';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $telephone = trim($_POST['telephone']);
    $password_input = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($nom) || empty($prenom) || empty($email) || empty($telephone) || empty($password_input)) {
        $error = 'Please fill in all fields.';
    } elseif ($password_input !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password_input) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email address is already registered.';
        } else {
            // Create new user
            $hashed_password = password_hash($password_input, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (nom, prenom, email, telephone, password, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            
            try {
                $stmt->execute([$nom, $prenom, $email, $telephone, $hashed_password]);
                $success = 'Account created successfully! You can now sign in.';
                
                // Auto-login the user
                $user_id = $pdo->lastInsertId();
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $nom . ' ' . $prenom;
                $_SESSION['user_email'] = $email;
                
                // Redirect after 2 seconds
                header("refresh:2;url=index.php");
            } catch(PDOException $e) {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - OpticLook</title>
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
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .signup-container {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            margin: 2rem 0;
        }

        .signup-header {
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
            margin-bottom: 0.5rem;
        }

        .logo-icon {
            margin-right: 0.5rem;
        }

        .signup-header h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .signup-header p {
            color: #e2e8f0;
            font-size: 0.875rem;
        }

        .signup-form {
            padding: 2rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
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

        .btn-signup {
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

        .btn-signup:hover {
            background: #1a202c;
            transform: translateY(-1px);
        }

        .btn-signup:active {
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

        .login-link {
            text-align: center;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
            color: #718096;
            font-size: 0.875rem;
        }

        .login-link a {
            color: #2d3748;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .back-home {
            position: absolute;
            top: 1rem;
            left: 1rem;
            color: #2d3748;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .back-home:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .back-home svg {
            margin-right: 0.5rem;
        }

        .password-requirements {
            font-size: 0.75rem;
            color: #718096;
            margin-top: 0.25rem;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .signup-container {
                margin: 1rem;
            }
            
            .signup-form {
                padding: 1.5rem;
            }
            
            .signup-header {
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
        Back to Home
    </a>

    <div class="signup-container">
        <div class="signup-header">
            <div class="logo">
                <svg class="logo-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 12c0 6 4 10 10 10s10-4 10-10S18 6 12 6 2 6 2 12z"/>
                    <path d="M8 12h8"/>
                    <path d="M12 8v8"/>
                </svg>
                OpticLook
            </div>
            <h1>Join OpticLook</h1>
            <p>Create your account to get started</p>
        </div>

        <div class="signup-form">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                    <br><small>Redirecting to home page...</small>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nom">Last Name</label>
                        <input type="text" id="nom" name="nom" class="form-control" 
                               placeholder="Enter your last name" 
                               value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>" 
                               required>
                    </div>
                    <div class="form-group">
                        <label for="prenom">First Name</label>
                        <input type="text" id="prenom" name="prenom" class="form-control" 
                               placeholder="Enter your first name" 
                               value="<?php echo isset($_POST['prenom']) ? htmlspecialchars($_POST['prenom']) : ''; ?>" 
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           placeholder="Enter your email address" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                           required>
                </div>

                <div class="form-group">
                    <label for="telephone">Phone Number</label>
                    <input type="tel" id="telephone" name="telephone" class="form-control" 
                           placeholder="Enter your phone number" 
                           value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : ''; ?>" 
                           required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Create a password" required>
                    <div class="password-requirements">
                        Password must be at least 6 characters long
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                           placeholder="Confirm your password" required>
                </div>

                <button type="submit" class="btn-signup">Create Account</button>
            </form>

            <div class="login-link">
                Already have an account? <a href="login.php">Sign in here</a>
            </div>
        </div>
    </div>

    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Focus first input
            document.getElementById('nom').focus();
            
            // Password confirmation validation
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            function validatePasswords() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            password.addEventListener('input', validatePasswords);
            confirmPassword.addEventListener('input', validatePasswords);
            
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
        });
    </script>
</body>
</html>