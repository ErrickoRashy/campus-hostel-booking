<?php
session_start();

require_once __DIR__ . '/../config/database.php';

$errors = [];
$full_name = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($full_name === '') {
        $errors[] = "Full name is required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Enter a valid email address.";
    }

    if ($phone === '') {
        $errors[] = "Phone number is required.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must contain at least 8 characters.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        try {
            $check = $pdo->prepare(
                "SELECT user_id FROM users WHERE email = :email"
            );
            $check->execute(['email' => $email]);

            if ($check->fetch()) {
                $errors[] = "This email is already registered.";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $insert = $pdo->prepare(
                    "INSERT INTO users
                    (full_name, email, password_hash, role, phone)
                    VALUES
                    (:full_name, :email, :password_hash, 'student', :phone)"
                );

                $insert->execute([
                    'full_name' => $full_name,
                    'email' => $email,
                    'password_hash' => $password_hash,
                    'phone' => $phone
                ]);

                header("Location: login.php?registered=1");
                exit;
            }
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $errors[] = "Registration failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Account | Campus Hostel Booking System</title>

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

        .register-container {
            width: 100%;
            max-width: 490px;
            padding: 38px;
            background: #ffffff;
            border-radius: 22px;
            border: 1px solid #e1eaf2;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.45);
        }

        .brand {
            text-align: center;
            margin-bottom: 25px;
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
            box-shadow: 0 5px 18px rgba(35, 91, 130, 0.10);
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
            margin-bottom: 9px;
            font-size: 28px;
        }

        .subtitle {
            text-align: center;
            color: #75889b;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 17px;
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
            padding: 5px;
            margin: 0;
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

        .register-button {
            width: 100%;
            padding: 15px;
            margin-top: 10px;
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

        .register-button:hover {
            background: linear-gradient(135deg, #12578f, #1978bd);
            transform: translateY(-2px);
            box-shadow: 0 10px 22px rgba(30, 112, 175, 0.28);
        }

        .errors {
            background: #fff0f0;
            border: 1px solid #f5c6cb;
            padding: 14px;
            border-radius: 9px;
            margin-bottom: 20px;
            color: #b42332;
            font-size: 13px;
        }

        .errors p {
            margin: 5px 0;
            line-height: 1.5;
        }

        .footer {
            text-align: center;
            margin-top: 25px;
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
            margin-top: 22px;
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
            margin-top: 20px;
        }

        @media (max-width: 500px) {
            .register-container {
                padding: 28px 21px;
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

<div class="register-container">

    <div class="brand">

        <img src="../campus-hostel-logo.png"
             alt="Campus Hostel Logo">

        <h2>Campus Hostel</h2>

        <p>Student Accommodation Management System</p>

    </div>

    <h1>Create Account</h1>

    <p class="subtitle">
        Register as a Campus Hostel student
    </p>

    <?php if (!empty($errors)): ?>

        <div class="errors">

            <?php foreach ($errors as $error): ?>

                <p>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </p>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <form method="POST" action="register.php">

        <div class="form-group">

            <label for="full_name">Full Name</label>

            <input
                type="text"
                id="full_name"
                name="full_name"
                placeholder="Enter your full name"
                autocomplete="name"
                value="<?= htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') ?>"
                required
            >

        </div>

        <div class="form-group">

            <label for="email">Email Address</label>

            <input
                type="email"
                id="email"
                name="email"
                placeholder="Enter your email address"
                autocomplete="email"
                value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                required
            >

        </div>

        <div class="form-group">

            <label for="phone">Phone Number</label>

            <input
                type="tel"
                id="phone"
                name="phone"
                placeholder="e.g. 07XXXXXXXX"
                autocomplete="tel"
                value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>"
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
                    placeholder="Create a password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

                <button
                    type="button"
                    class="toggle-password"
                    data-target="password"
                    aria-pressed="false"
                >
                    Show
                </button>

            </div>

        </div>

        <div class="form-group">

            <label for="confirm_password">Confirm Password</label>

            <div class="password-wrapper">

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Confirm your password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

                <button
                    type="button"
                    class="toggle-password"
                    data-target="confirm_password"
                    aria-pressed="false"
                >
                    Show
                </button>

            </div>

        </div>

        <button type="submit" class="register-button">
            Create Student Account
        </button>

    </form>

    <div class="footer">

        Already have an account?

        <a href="login.php">Login here</a>

    </div>

    <a class="home-link" href="../index.php">
        &#8592; Back to Homepage
    </a>

    <p class="security-note">
        Your account details are securely protected.
    </p>

</div>

<script>
    const passwordButtons = document.querySelectorAll('.toggle-password');

    passwordButtons.forEach(function(button) {

        button.addEventListener('click', function() {

            const targetId = button.getAttribute('data-target');
            const passwordInput = document.getElementById(targetId);

            const isHidden = passwordInput.type === 'password';

            passwordInput.type = isHidden ? 'text' : 'password';

            button.textContent = isHidden ? 'Hide' : 'Show';

            button.setAttribute('aria-pressed', isHidden);

        });

    });
</script>

</body>
</html>
