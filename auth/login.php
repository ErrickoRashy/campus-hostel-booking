<?php
session_start();

require_once __DIR__ . '/../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = "Enter a valid email and password.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT user_id, full_name, email, password_hash, role
                 FROM users
                 WHERE email = :email"
            );

            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {

                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                switch ($user['role']) {
                    case 'student':
                        header("Location: ../student/dashboard.php");
                        break;

                    case 'officer':
                        header("Location: ../officer/dashboard.php");
                        break;

                    case 'caretaker':
                        header("Location: ../caretaker/dashboard.php");
                        break;

                    default:
                        session_destroy();
                        header("Location: login.php");
                        break;
                }

                exit;

            } else {
                $error = "Incorrect email or password.";
            }

        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = "Login is temporarily unavailable. Please try again.";
        }
    }
}

$registered = isset($_GET['registered']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Campus Hostel Booking System</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 30px 16px;
    color: #19334d;
    background-color: #000000;
    background-image: none;
}
        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 40px 38px;
            background: rgba(255, 255, 255, 0.96);
            border-radius: 22px;
            border: 1px solid rgba(255, 255, 255, 0.9);
            box-shadow: 0 20px 55px rgba(35, 91, 130, 0.16);
        }

        .brand {
            text-align: center;
            margin-bottom: 28px;
        }

        .brand img {
            width: 100px;
            height: 100px;
            object-fit: contain;
            display: block;
            margin: 0 auto 14px;
            padding: 7px;
            background: #ffffff;
            border: 2px solid #d7eaf9;
            border-radius: 20px;
            box-shadow: 0 5px 18px rgba(35, 91, 130, 0.10);
        }

        .brand h2 {
            color: #123b60;
            font-size: 23px;
            font-weight: 750;
            letter-spacing: 0.3px;
        }

        .brand p {
            color: #71869a;
            font-size: 13px;
            margin-top: 6px;
        }

        h1 {
            text-align: center;
            color: #153d60;
            font-size: 29px;
            margin-bottom: 9px;
        }

        .subtitle {
            text-align: center;
            color: #75889b;
            font-size: 14px;
            margin-bottom: 28px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 9px;
            color: #294963;
            font-size: 14px;
            font-weight: 650;
        }

        input {
            width: 100%;
            padding: 15px 16px;
            background: #f5faff;
            color: #19334d;
            border: 1px solid #d6e4f0;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            transition: 0.25s ease;
        }

        input::placeholder {
            color: #a1b1c0;
        }

        input:focus {
            background: #ffffff;
            border-color: #368bd0;
            box-shadow: 0 0 0 4px rgba(54, 139, 208, 0.12);
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 75px;
        }

        .toggle-password {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            width: auto;
            margin: 0;
            padding: 5px;
            background: transparent;
            border: none;
            color: #2877b5;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .toggle-password:hover {
            color: #145080;
        }

        .login-button {
            width: 100%;
            padding: 15px;
            margin-top: 8px;
            background: linear-gradient(135deg, #1769aa, #278bd0);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 7px 18px rgba(30, 112, 175, 0.22);
            transition: 0.3s ease;
        }

        .login-button:hover {
            background: linear-gradient(135deg, #12578f, #1978bd);
            transform: translateY(-2px);
            box-shadow: 0 10px 22px rgba(30, 112, 175, 0.28);
        }

        .error {
            background: #fff0f0;
            color: #b42332;
            border: 1px solid #f5c6cb;
            padding: 13px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .success {
            background: #edfff3;
            color: #187442;
            border: 1px solid #b8e8c9;
            padding: 13px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .footer {
            text-align: center;
            margin-top: 26px;
            color: #71869a;
            font-size: 14px;
        }

        a {
            color: #1976b9;
            text-decoration: none;
            font-weight: 700;
        }

        a:hover {
            text-decoration: underline;
        }

        .home-link {
            display: block;
            text-align: center;
            margin-top: 23px;
            color: #6d8295;
            font-size: 13px;
        }

        .home-link:hover {
            color: #1769aa;
        }

        .security-note {
            text-align: center;
            color: #91a2b1;
            font-size: 11px;
            margin-top: 22px;
        }

        @media (max-width: 500px) {
            .login-container {
                padding: 32px 23px;
                border-radius: 17px;
            }

            .brand img {
                width: 85px;
                height: 85px;
            }

            h1 {
                font-size: 25px;
            }
        }
    </style>
</head>

<body>

<div class="login-container">

    <div class="brand">
        <img src="../campus-hostel-logo.png"
             alt="Campus Hostel Logo">

        <h2>Campus Hostel</h2>

        <p>Student Accommodation Management System</p>
    </div>

    <h1>Welcome Back!</h1>

    <p class="subtitle">
        Sign in to access your hostel account.
    </p>

    <?php if ($registered): ?>
        <div class="success">
            Account created successfully. You can now login.
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">

        <div class="form-group">
            <label for="email">Email Address</label>

            <input
                type="email"
                id="email"
                name="email"
                placeholder="Enter your email address"
                autocomplete="email"
                value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="password">Password</label>

            <div class="password-wrapper">
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >

                <button
                    type="button"
                    class="toggle-password"
                    id="togglePassword"
                    aria-label="Show password"
                    aria-pressed="false"
                >
                    Show
                </button>
            </div>
        </div>

        <button type="submit" class="login-button">
            Login to Your Account
        </button>

    </form>

    <div class="footer">
        Don't have an account?
        <a href="register.php">Create Account</a>
    </div>

    <a class="home-link" href="../index.php">
        &#8592; Back to Homepage
    </a>

    <p class="security-note">
        Secure access to your Campus Hostel account.
    </p>

</div>

<script>
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');

    togglePassword.addEventListener('click', function () {

        const isHidden = passwordInput.type === 'password';

        passwordInput.type = isHidden ? 'text' : 'password';

        togglePassword.textContent = isHidden ? 'Hide' : 'Show';

        togglePassword.setAttribute('aria-pressed', isHidden);

        togglePassword.setAttribute(
            'aria-label',
            isHidden ? 'Hide password' : 'Show password'
        );
    });
</script>

</body>
</html>
