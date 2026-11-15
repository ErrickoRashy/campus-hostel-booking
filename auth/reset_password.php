<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

$userId = $_SESSION['pending_reset_user_id'] ?? null;
$email = $_SESSION['pending_reset_email'] ?? '';

if (
    empty($userId) ||
    empty($email) ||
    ($_SESSION['reset_otp_verified'] ?? false) !== true
) {
    header("Location: forgot_password.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {

        $error = 'Your new password must contain at least 8 characters.';

    } elseif ($password !== $confirmPassword) {

        $error = 'The passwords do not match.';

    } else {

        try {

            $pdo->beginTransaction();

            // Confirm the student account still exists.
            $stmt = $pdo->prepare(
                "SELECT user_id
                 FROM users
                 WHERE user_id = :user_id
                   AND email = :email
                   AND role = 'student'
                 LIMIT 1
                 FOR UPDATE"
            );

            $stmt->execute([
                'user_id' => $userId,
                'email' => $email
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {

                $pdo->rollBack();

                $error = 'Student account not found. Please contact support.';

            } else {

                // Hash the new password.
                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Update the password in Supabase.
                $update = $pdo->prepare(
                    "UPDATE users
                     SET password_hash = :password_hash
                     WHERE user_id = :user_id
                       AND email = :email"
                );

                $update->execute([
                    'password_hash' => $passwordHash,
                    'user_id' => $userId,
                    'email' => $email
                ]);

                // Invalidate any other outstanding password-reset codes.
                $invalidate = $pdo->prepare(
                    "UPDATE otp_verifications
                     SET used_at = NOW()
                     WHERE user_id = :user_id
                       AND purpose = 'password_reset'
                       AND used_at IS NULL"
                );

                $invalidate->execute([
                    'user_id' => $userId
                ]);

                $pdo->commit();

                // Clear temporary reset information.
                unset(
                    $_SESSION['pending_reset_user_id'],
                    $_SESSION['pending_reset_email'],
                    $_SESSION['reset_otp_verified']
                );

                header("Location: login.php?password_reset=1");
                exit;
            }

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Password update error: ' . $e->getMessage());

            $error = 'Unable to update your password. Please try again.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create New Password | Campus Hostel</title>

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
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 19px;
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
            margin-top: 8px;
            background: linear-gradient(135deg, #1769aa, #278bd0);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }

        button:hover {
            background: linear-gradient(135deg, #12578f, #1978bd);
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

    <h1>Create New Password</h1>

    <p class="subtitle">
        Your email has been verified.
        Enter and confirm your new password below.
    </p>

    <?php if ($error): ?>

        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php endif; ?>

    <form method="POST" action="reset_password.php">

        <div class="form-group">

            <label for="password">New Password</label>

            <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter your new password"
                minlength="8"
                autocomplete="new-password"
                required
            >

        </div>

        <div class="form-group">

            <label for="confirm_password">Confirm New Password</label>

            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                placeholder="Confirm your new password"
                minlength="8"
                autocomplete="new-password"
                required
            >

        </div>

        <button type="submit">
            Update Password
        </button>

    </form>

    <div class="footer">
        <a href="login.php">Back to Login</a>
    </div>

</div>

</body>
</html>
