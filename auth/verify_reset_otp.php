<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$error = '';

$userId = $_SESSION['pending_reset_user_id'] ?? null;
$email = $_SESSION['pending_reset_email'] ?? '';

if (empty($userId) || empty($email)) {
    header("Location: forgot_password.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $otp = trim($_POST['otp'] ?? '');

    if (!preg_match('/^[0-9]{6}$/', $otp)) {

        $error = 'Enter a valid six-digit verification code.';

    } else {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT otp_id, otp_hash, attempts, expires_at
                 FROM otp_verifications
                 WHERE email = :email
                   AND user_id = :user_id
                   AND purpose = 'password_reset'
                   AND used_at IS NULL
                 ORDER BY created_at DESC
                 LIMIT 1
                 FOR UPDATE"
            );

            $stmt->execute([
                'email' => $email,
                'user_id' => $userId
            ]);

            $verification = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$verification) {

                $pdo->rollBack();

                $error = 'No active reset code was found. Please request another code.';

            } elseif ((int) $verification['attempts'] >= 5) {

                $pdo->prepare(
                    "UPDATE otp_verifications
                     SET used_at = NOW()
                     WHERE otp_id = :otp_id"
                )->execute([
                    'otp_id' => $verification['otp_id']
                ]);

                $pdo->commit();

                $error = 'Too many incorrect attempts. Please request another code.';

            } elseif (strtotime($verification['expires_at']) <= time()) {

                $pdo->prepare(
                    "UPDATE otp_verifications
                     SET used_at = NOW()
                     WHERE otp_id = :otp_id"
                )->execute([
                    'otp_id' => $verification['otp_id']
                ]);

                $pdo->commit();

                $error = 'Your verification code has expired. Please request another code.';

            } elseif (!password_verify($otp, $verification['otp_hash'])) {

                $newAttempts = (int) $verification['attempts'] + 1;

                $update = $pdo->prepare(
                    "UPDATE otp_verifications
                     SET attempts = :attempts,
                         used_at = CASE
                             WHEN :attempts_check >= 5 THEN NOW()
                             ELSE used_at
                         END
                     WHERE otp_id = :otp_id"
                );

                $update->execute([
                    'attempts' => $newAttempts,
                    'attempts_check' => $newAttempts,
                    'otp_id' => $verification['otp_id']
                ]);

                $pdo->commit();

                $remaining = 5 - $newAttempts;

                $error = $remaining > 0
                    ? "Incorrect code. You have {$remaining} attempts remaining."
                    : 'Too many incorrect attempts. Please request another code.';

            } else {

                // Confirm that the student account still exists.
                $userStmt = $pdo->prepare(
                    "SELECT user_id
                     FROM users
                     WHERE user_id = :user_id
                       AND email = :email
                       AND role = 'student'
                     LIMIT 1"
                );

                $userStmt->execute([
                    'user_id' => $userId,
                    'email' => $email
                ]);

                if (!$userStmt->fetch()) {

                    $pdo->rollBack();

                    $error = 'Student account not found.';

                } else {

                    // Mark this OTP as used.
                    $markUsed = $pdo->prepare(
                        "UPDATE otp_verifications
                         SET used_at = NOW()
                         WHERE otp_id = :otp_id"
                    );

                    $markUsed->execute([
                        'otp_id' => $verification['otp_id']
                    ]);

                    $pdo->commit();

                    // Allow password creation only after verification.
                    $_SESSION['reset_otp_verified'] = true;

                    header("Location: reset_password.php");
                    exit;
                }
            }

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Reset OTP verification error: ' . $e->getMessage());

            $error = 'Unable to verify your code. Please try again.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Verify Reset Code | Campus Hostel</title>

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

        .email {
            color: #1769aa;
            font-weight: 700;
            overflow-wrap: anywhere;
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
            padding: 16px;
            border: 1px solid #d6e4f0;
            border-radius: 10px;
            background: #f5faff;
            color: #19334d;
            outline: none;
            font-size: 23px;
            font-weight: 700;
            letter-spacing: 9px;
            text-align: center;
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

    <h1>Verify Reset Code</h1>

    <p class="subtitle">

        Enter the six-digit password-reset code sent to

        <span class="email">
            <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>
        </span>.

    </p>

    <?php if ($error): ?>

        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php endif; ?>

    <form method="POST" action="verify_reset_otp.php">

        <label for="otp">Six-Digit Verification Code</label>

        <input
            type="text"
            id="otp"
            name="otp"
            placeholder="000000"
            maxlength="6"
            minlength="6"
            inputmode="numeric"
            pattern="[0-9]{6}"
            autocomplete="one-time-code"
            required
            autofocus
        >

        <button type="submit">
            Verify Code
        </button>

    </form>

    <div class="footer">

        <a href="forgot_password.php">Request another code</a>

    </div>

</div>

</body>
</html>
