<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/otp.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } else {

        try {

            $stmt = $pdo->prepare(
                "SELECT user_id, email
                 FROM users
                 WHERE LOWER(email) = :email
                   AND role = 'student'
                 LIMIT 1"
            );

            $stmt->execute(['email' => $email]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {

                $error = 'If an account exists with this email, a verification code can be requested.';

            } else {

                $result = createAndSendOtp(
                    $pdo,
                    $user['email'],
                    'password_reset',
                    (int) $user['user_id']
                );

                if (!$result['success']) {

                    $error = $result['message'];

                } else {

                    $_SESSION['pending_reset_user_id'] =
                        (int) $user['user_id'];

                    $_SESSION['pending_reset_email'] =
                        $user['email'];

                    header("Location: verify_reset_otp.php");
                    exit;
                }
            }

        } catch (Throwable $e) {

            error_log('Password reset request error: ' . $e->getMessage());

            $error = 'Unable to process your request. Please try again.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Forgot Password | Campus Hostel</title>

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
            background: #000000;
            color: #19334d;
        }

        .container {
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
            font-size: 27px;
            margin-bottom: 12px;
        }

        .subtitle {
            text-align: center;
            color: #75889b;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 26px;
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
        }

        input:focus {
            background: #ffffff;
            border-color: #368bd0;
            box-shadow: 0 0 0 4px rgba(54, 139, 208, 0.12);
        }

        button {
            width: 100%;
            padding: 15px;
            margin-top: 22px;
            background: linear-gradient(135deg, #1769aa, #278bd0);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s ease;
        }

        button:hover {
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

        @media (max-width: 500px) {
            .container {
                padding: 30px 22px;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <div class="brand">

        <img src="../campus-hostel-logo.png"
             alt="Campus Hostel Logo">

        <h2>Campus Hostel</h2>

        <p>Student Accommodation Management System</p>

    </div>

    <h1>Forgot Password?</h1>

    <p class="subtitle">
        Enter your registered student email address.
        We will send you a verification code to reset your password.
    </p>

    <?php if ($error): ?>

        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php endif; ?>

    <form method="POST" action="forgot_password.php">

        <label for="email">Registered Email Address</label>

        <input
            type="email"
            id="email"
            name="email"
            placeholder="Enter your registered email"
            value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            autocomplete="email"
            required
        >

        <button type="submit">
            Send Verification Code
        </button>

    </form>

    <div class="footer">

        Remember your password?
        <a href="login.php">Back to Login</a>

    </div>

</div>

</body>
</html>
