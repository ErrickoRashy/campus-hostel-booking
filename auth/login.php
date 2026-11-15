<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/otp.php';

$error = '';
$success = '';

// Account registration success message.
if (isset($_GET['registered'])) {

    $success = 'Your account has been created successfully. Please log in.';

// Password reset success message.
} elseif (isset($_GET['password_reset'])) {

    $success = 'Your password has been reset successfully. Please log in using your new password.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {

        $error = 'Please enter your email and password.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } else {

        try {

            // Find the registered account.
            $stmt = $pdo->prepare(
                "SELECT user_id, full_name, email, password_hash, role
                 FROM users
                 WHERE LOWER(email) = :email
                 LIMIT 1"
            );

            $stmt->execute([
                'email' => $email
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify the password.
            if (
                !$user ||
                !password_verify($password, $user['password_hash'])
            ) {

                $error = 'Invalid email or password.';

            } else {

                // Clear previous temporary login information.
                unset(
                    $_SESSION['pending_login_user_id'],
                    $_SESSION['pending_login_email']
                );

                // Send a login verification OTP.
                $otpResult = createAndSendOtp(
                    $pdo,
                    $user['email'],
                    'login',
                    (int) $user['user_id']
                );

                if (!$otpResult['success']) {

                    $error = $otpResult['message'];

                } else {

                    // Store temporary login information.
                    // The user is NOT authenticated yet.
                    $_SESSION['pending_login_user_id'] =
                        (int) $user['user_id'];

                    $_SESSION['pending_login_email'] =
                        $user['email'];

                    // Redirect to OTP verification.
                    header("Location: verify_otp.php?purpose=login");
                    exit;
                }
            }

        } catch (Throwable $e) {

            error_log('Login error: ' . $e->getMessage());

            $error = 'Unable to process your login. Please try again.';
        }
    }
}

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
            padding: 25px 15px;
            background: #000000;
            color: #19334d;
        }

        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 40px 38px;
            background: #ffffff;
            border-radius: 22px;
            border: 1px solid #e1eaf2;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.45);
        }

        .brand {
            text-align: center;
            margin-bottom: 28px;
        }

        .brand img {
            width: 95px;
            height: 95px;
            object-fit: contain;
            display: block;
            margin: 0 auto 13px;
            padding: 7px;
            background: #ffffff;
            border: 2px solid #d7eaf9;
            border-radius: 20px;
        }

        .brand h2 {
            color: #123b60;
            font-size: 23px;
            font-weight: 750;
        }

        .brand p {
            color: #71869a;
            font-size: 13px;
            margin-top: 6px;
        }

        h1 {
            text-align: center;
            color: #153d60;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: #75889b;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 19px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #294963;
            font-size: 14px;
            font-weight: 650;
        }

        input {
            width: 100%;
            padding: 14px 15px;
            border: 1px solid #d6e4f0;
            border-radius: 10px;
            background: #f5faff;
            color: #19334d;
            outline: none;
            font-size: 14px;
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
            padding-right: 65px;
        }

        .toggle-password {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            color: #1976b9;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .forgot-password {
            display: block;
            text-align: right;
            margin-top: 9px;
            font-size: 12px;
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
            transition: 0.3s ease;
        }

        .login-button:hover {
            background: linear-gradient(135deg, #12578f, #1978bd);
            transform: translateY(-2px);
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
            background: #edfff2;
            color: #18743a;
            border: 1px solid #bce8c8;
            padding: 13px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .footer {
            text-align: center;
            margin-top: 25px;
            color: #71869a;
            font-size: 13px;
        }

        a {
            color: #1976b9;
            text-decoration: none;
            font-weight: 700;
        }

        a:hover {
            text-decoration: underline;
        }

        .security-note {
            text-align: center;
            color: #91a2b1;
            font-size: 11px;
            margin-top: 22px;
        }

        @media (max-width: 500px) {

            .login-container {
                padding: 30px 22px;
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

    <h1>Welcome Back</h1>

    <p class="subtitle">
        Sign in to continue to your account.
    </p>

    <?php if ($error): ?>

        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php endif; ?>

    <?php if ($success): ?>

        <div class="success">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php endif; ?>

    <form method="POST" action="login.php">

        <div class="form-group">

            <label for="email">Email Address</label>

            <input
                type="email"
                id="email"
                name="email"
                placeholder="Enter your registered email"
                value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="email"
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
                    onclick="togglePassword()"
                    id="toggleButton"
                >
                    Show
                </button>

            </div>

            <a href="forgot_password.php" class="forgot-password">
                Forgot Password?
            </a>

        </div>

        <button type="submit" class="login-button">
            Continue to Verification
        </button>

    </form>

    <div class="footer">

        Don't have an account?
        <a href="register.php">Create Account</a>

    </div>

    <p class="security-note">
        Your account is protected with email verification.
    </p>

</div>

<script>

    function togglePassword() {

        const password = document.getElementById('password');
        const button = document.getElementById('toggleButton');

        if (password.type === 'password') {

            password.type = 'text';
            button.textContent = 'Hide';

        } else {

            password.type = 'password';
            button.textContent = 'Show';

        }
    }

</script>

</body>
</html>
