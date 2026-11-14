<?php
require_once __DIR__ . '/../config/auth_check.php';
requireRole('student');

require_once __DIR__ . '/../config/database.php';

$studentId = $_SESSION['user_id'];
$errors = [];
$success = '';

$allocationQuery = $pdo->prepare(
    "SELECT allocation_id
     FROM allocations
     WHERE student_id = :student_id
     AND status = 'active'
     LIMIT 1"
);

$allocationQuery->execute(['student_id' => $studentId]);
$allocation = $allocationQuery->fetch();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $startDate = $_POST['start_date'] ?? '';
    $returnDate = $_POST['return_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $errors[] = 'Invalid request. Please refresh the page.';
    }

    if (!$allocation) {
        $errors[] = 'You need an active room allocation to request vacation.';
    }

    $validStart = DateTime::createFromFormat('!Y-m-d', $startDate);
    $validReturn = DateTime::createFromFormat('!Y-m-d', $returnDate);

    if (!$validStart || $validStart->format('Y-m-d') !== $startDate) {
        $errors[] = 'Please enter a valid vacation start date.';
    }

    if (!$validReturn || $validReturn->format('Y-m-d') !== $returnDate) {
        $errors[] = 'Please enter a valid return date.';
    }

    if (
        $validStart && $validReturn &&
        $validReturn <= $validStart
    ) {
        $errors[] = 'Return date must be after the start date.';
    }

    if (strlen($reason) < 10 || strlen($reason) > 1000) {
        $errors[] = 'Reason must be between 10 and 1000 characters.';
    }

    if (empty($errors)) {
        try {
            $pendingCheck = $pdo->prepare(
                "SELECT vacation_id
                 FROM vacation_requests
                 WHERE student_id = :student_id
                 AND status = 'pending'
                 LIMIT 1"
            );

            $pendingCheck->execute(['student_id' => $studentId]);

            if ($pendingCheck->fetch()) {
                $errors[] = 'You already have a pending vacation request.';
            } else {
                $insert = $pdo->prepare(
                    "INSERT INTO vacation_requests
                    (student_id, allocation_id, start_date, return_date, reason)
                    VALUES (:student_id, :allocation_id, :start_date, :return_date, :reason)"
                );

                $insert->execute([
                    'student_id' => $studentId,
                    'allocation_id' => $allocation['allocation_id'],
                    'start_date' => $startDate,
                    'return_date' => $returnDate,
                    'reason' => $reason
                ]);

                $success = 'Your vacation request was submitted successfully.';
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
        } catch (PDOException $e) {
            error_log('Vacation request error: ' . $e->getMessage());
            $errors[] = 'Unable to submit your request. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Vacation Request | Campus Hostel</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            min-height: 100vh;
            background: #000000;
            color: #ffffff;
        }


        header {
            min-height: 85px;
            padding: 15px 7%;
            background: #111827;
            border-bottom: 1px solid #263449;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .brand img {
            width: 53px;
            height: 53px;
            object-fit: contain;
            border-radius: 10px;
        }

        .brand h2 {
            color: #ffffff;
            font-size: 21px;
        }

        .brand span {
            color: #60a5fa;
        }

        .back-link {
            padding: 11px 17px;
            border: 1px solid #3b82f6;
            border-radius: 8px;
            color: #93c5fd;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            transition: 0.3s;
        }

        .back-link:hover {
            background: #2563eb;
            color: #ffffff;
        }


        .container {
            width: 90%;
            max-width: 750px;
            margin: 45px auto;
        }

        .page-heading {
            margin-bottom: 28px;
        }

        .eyebrow {
            display: inline-block;
            color: #60a5fa;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .page-heading h1 {
            font-size: 32px;
            margin-bottom: 12px;
        }

        .description {
            color: #aab6c8;
            font-size: 15px;
            line-height: 1.7;
        }


        .form-card {
            background: #111827;
            border: 1px solid #263449;
            border-radius: 15px;
            padding: 30px;
        }

        .section-heading {
            font-size: 20px;
            margin-bottom: 10px;
        }

        .section-description {
            color: #8fa0b5;
            font-size: 13px;
            line-height: 1.7;
            margin-bottom: 25px;
        }


        .info-panel {
            background: #172b45;
            border: 1px solid #31527c;
            border-radius: 10px;
            padding: 17px;
            margin-bottom: 25px;
            color: #cbd5e1;
            font-size: 13px;
            line-height: 1.7;
        }

        .info-panel strong {
            color: #93c5fd;
        }


        .form-group {
            margin-bottom: 23px;
        }

        label {
            display: block;
            color: #e5e7eb;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        input,
        textarea {
            width: 100%;
            padding: 14px 15px;
            background: #080f1c;
            border: 1px solid #35465e;
            border-radius: 8px;
            color: #ffffff;
            font-size: 14px;
            outline: none;
            transition: 0.3s;
        }

        input:focus,
        textarea:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        input::-webkit-calendar-picker-indicator {
            cursor: pointer;
            filter: invert(1);
        }

        textarea {
            min-height: 145px;
            resize: vertical;
            line-height: 1.6;
        }

        .hint {
            display: block;
            color: #8493a8;
            font-size: 12px;
            margin-top: 8px;
        }


        .date-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }


        button {
            width: 100%;
            padding: 15px;
            margin-top: 5px;
            border: none;
            border-radius: 8px;
            background: #2563eb;
            color: #ffffff;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover {
            background: #1d4ed8;
        }


        .success,
        .errors {
            padding: 16px 18px;
            border-radius: 9px;
            margin-bottom: 22px;
            font-size: 14px;
            line-height: 1.7;
        }

        .success {
            background: #123d2b;
            border: 1px solid #166534;
            color: #bbf7d0;
        }

        .errors {
            background: #451d27;
            border: 1px solid #991b1b;
            color: #fecaca;
        }

        .errors p + p {
            margin-top: 5px;
        }


        .empty {
            background: #172b45;
            border: 1px solid #31527c;
            border-radius: 12px;
            padding: 25px;
            color: #cbd5e1;
            font-size: 14px;
            line-height: 1.8;
        }

        .empty h3 {
            color: #93c5fd;
            font-size: 18px;
            margin-bottom: 12px;
        }

        /* FOOTER */

        footer {
            margin-top: 60px;
            padding: 25px 15px;
            border-top: 1px solid #263449;
            text-align: center;
            color: #77869a;
            font-size: 13px;
        }


        @media (max-width: 650px) {

            header {
                padding: 14px 5%;
            }

            .brand {
                gap: 8px;
            }

            .brand img {
                width: 43px;
                height: 43px;
            }

            .brand h2 {
                font-size: 17px;
            }

            .back-link {
                padding: 10px 11px;
                font-size: 12px;
            }

            .container {
                margin: 32px auto;
            }

            .page-heading h1 {
                font-size: 26px;
            }

            .form-card {
                padding: 21px;
            }
        }

        @media (max-width: 420px) {

            .brand h2 {
                font-size: 14px;
            }

            .back-link {
                font-size: 11px;
                padding: 9px;
            }

            .form-card {
                padding: 17px;
            }

            .date-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>

<body>

<header>

    <div class="brand">

        <img src="../campus-hostel-logo.png" alt="Campus Hostel Logo">

        <h2>Campus <span>Hostel</span></h2>

    </div>

    <a href="dashboard.php" class="back-link">
        &#8592; Dashboard
    </a>

</header>

<main class="container">

    <div class="page-heading">

        <span class="eyebrow">Student Portal</span>

        <h1>Vacation Request</h1>

        <p class="description">
            Submit a request to temporarily leave your allocated hostel room
            during a planned vacation period.
        </p>

    </div>

    <section class="form-card">

        <?php if ($success): ?>

            <div class="success">
                &#10003; <?= htmlspecialchars($success) ?>
            </div>

        <?php endif; ?>

        <?php if (!empty($errors)): ?>

            <div class="errors">

                <?php foreach ($errors as $error): ?>

                    <p>
                        &#9888; <?= htmlspecialchars($error) ?>
                    </p>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

        <?php if (!$allocation): ?>

            <div class="empty">

                <h3>No Active Room Allocation</h3>

                You need an active room allocation before submitting
                a vacation request. Please contact the accommodation officer
                if you need assistance.

            </div>

        <?php else: ?>

            <h2 class="section-heading">
                Vacation Details
            </h2>

            <p class="section-description">
                Provide your intended departure date, expected return date
                and reason for requesting vacation.
            </p>

            <div class="info-panel">

                <strong>Important information:</strong>

                Your vacation request will be sent to the accommodation
                officer for review. Submitting a request does not automatically
                approve your vacation.

            </div>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                >

                <div class="date-grid">

                    <div class="form-group">

                        <label for="start_date">
                            Vacation Start Date
                        </label>

                        <input
                            type="date"
                            id="start_date"
                            name="start_date"
                            min="<?= date('Y-m-d') ?>"
                            value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="return_date">
                            Expected Return Date
                        </label>

                        <input
                            type="date"
                            id="return_date"
                            name="return_date"
                            min="<?= date('Y-m-d') ?>"
                            value="<?= htmlspecialchars($_POST['return_date'] ?? '') ?>"
                            required
                        >

                    </div>

                </div>

                <div class="form-group">

                    <label for="reason">
                        Reason for Vacation
                    </label>

                    <textarea
                        id="reason"
                        name="reason"
                        minlength="10"
                        maxlength="1000"
                        placeholder="Explain why you need to leave during this period..."
                        required
                    ><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>

                    <span class="hint">
                        Enter between 10 and 1000 characters.
                    </span>

                </div>

                <button type="submit">
                    Submit Vacation Request
                </button>

            </form>

        <?php endif; ?>

    </section>

</main>

<footer>
    &copy; <?= date('Y') ?> Campus Hostel Booking System.
    All rights reserved.
</footer>

</body>
</html>
