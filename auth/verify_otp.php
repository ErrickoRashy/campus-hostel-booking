<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$error = '';

$purpose = $_GET['purpose'] ?? 'registration';

if (!in_array($purpose, ['registration', 'login'], true)) {
    $purpose = 'registration';
}

// Confirm the correct temporary session exists.
if ($purpose === 'registration') {

    $email = $_SESSION['pending_registration_email'] ?? '';
    $pendingId = $_SESSION['pending_registration_id'] ?? null;

    if (empty($email) || empty($pendingId)) {
        header("Location: register.php");
        exit;
    }

} else {

    $email = $_SESSION['pending_login_email'] ?? '';
    $userId = $_SESSION['pending_login_user_id'] ?? null;

    if (empty($email) || empty($userId)) {
        header("Location: login.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $otp = trim($_POST['otp'] ?? '');

    if (!preg_match('/^[0-9]{6}$/', $otp)) {

        $error = "Enter a valid six-digit verification code.";

    } else {

        try {

            $pdo->beginTransaction();

            // Find and lock the latest unused OTP.
            $stmt = $pdo->prepare(
                "SELECT otp_id, otp_hash, attempts, expires_at
                 FROM otp_verifications
                 WHERE email = :email
                   AND purpose = :purpose
                   AND used_at IS NULL
                   AND (
                       (:registration_id IS NOT NULL
                        AND pending_registration_id = :registration_id)
                       OR
                       (:login_id IS NOT NULL
                        AND user_id = :login_id)
                   )
                 ORDER BY created_at DESC
                 LIMIT 1
                 FOR UPDATE"
            );

            $stmt->execute([
                'email' => $email,
                'purpose' => $purpose,
                'registration_id' => $purpose === 'registration' ? $pendingId : null,
                'login_id' => $purpose === 'login' ? $userId : null
            ]);

            $verification = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$verification) {

                $pdo->rollBack();

                $error = "No active verification code was found. Please start again.";

            } elseif ((int) $verification['attempts'] >= 5) {

                $pdo->prepare(
                    "UPDATE otp_verifications
                     SET used_at = NOW()
                     WHERE otp_id = :otp_id"
                )->execute([
                    'otp_id' => $verification['otp_id']
                ]);

                $pdo->commit();

                $error = "Too many incorrect attempts. Please request a new code.";

            } elseif (strtotime($verification['expires_at']) <= time()) {

                $pdo->prepare(
                    "UPDATE otp_verifications
                     SET used_at = NOW()
                     WHERE otp_id = :otp_id"
                )->execute([
                    'otp_id' => $verification['otp_id']
                ]);

                $pdo->commit();

                $error = "Your verification code has expired. Please request a new one.";

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
                    : "Too many incorrect attempts. Please request a new code.";

            } else {

                if ($purpose === 'registration') {

                    // Retrieve the pending registration.
                    $pendingStmt = $pdo->prepare(
                        "SELECT pending_id, full_name, email,
                                phone, password_hash
                         FROM pending_registrations
                         WHERE pending_id = :pending_id
                           AND email = :email
                           AND expires_at > NOW()
                         FOR UPDATE"
                    );

                    $pendingStmt->execute([
                        'pending_id' => $pendingId,
                        'email' => $email
                    ]);

                    $pending = $pendingStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$pending) {

                        $pdo->rollBack();

                        $error = "Your registration has expired. Please register again.";

                    } else {

                        // Create the verified student account.
                        $createUser = $pdo->prepare(
                            "INSERT INTO users
                             (full_name, email, password_hash, role, phone)
                             VALUES
                             (:full_name, :email, :password_hash, 'student', :phone)"
                        );

                        $createUser->execute([
                            'full_name' => $pending['full_name'],
                            'email' => $pending['email'],
                            'password_hash' => $pending['password_hash'],
                            'phone' => $pending['phone']
                        ]);

                        // Mark the OTP as used.
                        $markUsed = $pdo->prepare(
                            "UPDATE otp_verifications
                             SET used_at = NOW()
                             WHERE otp_id = :otp_id"
                        );

                        $markUsed->execute([
                            'otp_id' => $verification['otp_id']
                        ]);

                        // Remove the temporary registration.
                        $deletePending = $pdo->prepare(
                            "DELETE FROM pending_registrations
                             WHERE pending_id = :pending_id"
                        );

                        $deletePending->execute([
                            'pending_id' => $pendingId
                        ]);

                        $pdo->commit();

                        unset(
                            $_SESSION['pending_registration_id'],
                            $_SESSION['pending_registration_email']
                        );

                        header("Location: login.php?registered=1");
                        exit;
                    }

                } else {

                    // Retrieve the user whose login is being verified.
                    $userStmt = $pdo->prepare(
                        "SELECT user_id, full_name, email, role
                         FROM users
                         WHERE user_id = :user_id
                           AND email = :email
                         LIMIT 1"
                    );

                    $userStmt->execute([
                        'user_id' => $userId,
                        'email' => $email
                    ]);

                    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$user) {

                        $pdo->rollBack();

                        $error = "Account not found. Please log in again.";

                    } elseif (!in_array(
                        $user['role'],
                        ['student', 'officer', 'caretaker'],
                        true
                    )) {

                        $pdo->rollBack();

                        $error = "This account has an invalid role.";

                    } else {

                        // Mark the OTP as used before granting access.
                        $markUsed = $pdo->prepare(
                            "UPDATE otp_verifications
                             SET used_at = NOW()
                             WHERE otp_id = :otp_id"
                        );

                        $markUsed->execute([
                            'otp_id' => $verification['otp_id']
                        ]);

                        $pdo->commit();

                        // Only now create the authenticated session.
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['otp_verified'] = true;

                        unset(
                            $_SESSION['pending_login_user_id'],
                            $_SESSION['pending_login_email']
                        );

                        // Redirect according to account role.
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
                        }

                        exit;
                    }
                }
            }

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log("OTP verification error: " . $e->getMessage());

            $error = "Verification failed. Please try again.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Email Verification | Campus Hostel</title>

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

        .verify-container {
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

        .verify-button {
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

        .verify-button:hover {
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

        .security-note {
            text-align: center;
            color: #91a2b1;
            font-size: 11px;
            margin-top: 22px;
        }

        @media (max-width: 500px) {
            .verify-container {
                padding: 30px 22px;
            }
        }

    </style>

</head>

<body>

<div class="verify-container">

    <div class="brand">

        <img src="../campus-hostel-logo.png"
             alt="Campus Hostel Logo">

        <h2>Campus Hostel</h2>

        <p>Student Accommodation Management System</p>

    </div>

    <h1>Verify Your Email</h1>

    <p class="subtitle">

        <?php if ($purpose === 'registration'): ?>

            Enter the six-digit code sent to

        <?php else: ?>

            Enter the six-digit login code sent to

        <?php endif; ?>

        <span class="email">
            <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>
        </span>.

    </p>

    <?php if ($error): ?>

        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php endif; ?>

    <form method="POST"
          action="verify_otp.php?purpose=<?= htmlspecialchars($purpose, ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-group">

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

        </div>

        <button type="submit" class="verify-button">
            Verify Code
        </button>

    </form>

    <div class="footer">

        <?php if ($purpose === 'registration'): ?>

            <a href="register.php">Back to Registration</a>

        <?php else: ?>

            <a href="login.php">Back to Login</a>

        <?php endif; ?>

    </div>

    <p class="security-note">
        Your verification code expires after five minutes.
    </p>

</div>

</body>
</html>
