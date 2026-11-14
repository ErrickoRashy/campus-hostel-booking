<?php
require_once __DIR__ . '/../config/auth_check.php';
requireRole('student');

require_once __DIR__ . '/../config/database.php';

$studentId = $_SESSION['user_id'];
$errors = [];
$success = '';

$currentQuery = $pdo->prepare(
    "SELECT
        al.allocation_id,
        r.room_number,
        r.room_type,
        h.hall_name
     FROM allocations al
     INNER JOIN rooms r ON al.room_id = r.room_id
     INNER JOIN halls h ON r.hall_id = h.hall_id
     WHERE al.student_id = :student_id
     AND al.status = 'active'
     LIMIT 1"
);

$currentQuery->execute(['student_id' => $studentId]);
$currentAllocation = $currentQuery->fetch();

$hallQuery = $pdo->prepare(
    "SELECT hall_id, hall_name
     FROM halls
     WHERE hall_id <> (
         SELECT r.hall_id
         FROM allocations al
         INNER JOIN rooms r ON al.room_id = r.room_id
         WHERE al.student_id = :student_id
         AND al.status = 'active'
         LIMIT 1
     )
     ORDER BY hall_name"
);

$hallQuery->execute(['student_id' => $studentId]);
$halls = $hallQuery->fetchAll();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $requestedHall = $_POST['requested_hall_id'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $errors[] = 'Invalid request. Please refresh the page.';
    }

    if (!$currentAllocation) {
        $errors[] = 'You need an active room allocation before requesting a transfer.';
    }

    if (filter_var($requestedHall, FILTER_VALIDATE_INT) === false) {
        $errors[] = 'Please select a valid hostel.';
    }

    if (strlen($reason) < 10 || strlen($reason) > 1000) {
        $errors[] = 'Your reason must be between 10 and 1000 characters.';
    }

    if (empty($errors)) {
        try {
            $hallCheck = $pdo->prepare(
                "SELECT hall_id FROM halls
                 WHERE hall_id = :hall_id
                 AND hall_id <> (
                     SELECT r.hall_id
                     FROM allocations al
                     INNER JOIN rooms r ON al.room_id = r.room_id
                     WHERE al.student_id = :student_id
                     AND al.status = 'active'
                     LIMIT 1
                 )"
            );

            $hallCheck->execute([
                'hall_id' => (int)$requestedHall,
                'student_id' => $studentId
            ]);

            if (!$hallCheck->fetch()) {
                $errors[] = 'The selected hostel is invalid or is your current hostel.';
            } else {
                $pendingCheck = $pdo->prepare(
                    "SELECT transfer_id
                     FROM transfer_requests
                     WHERE student_id = :student_id
                     AND status = 'pending'
                     LIMIT 1"
                );

                $pendingCheck->execute(['student_id' => $studentId]);

                if ($pendingCheck->fetch()) {
                    $errors[] = 'You already have a pending transfer request.';
                } else {
                    $insert = $pdo->prepare(
                        "INSERT INTO transfer_requests
                        (student_id, current_allocation_id, requested_hall_id, reason)
                        VALUES (:student_id, :allocation_id, :hall_id, :reason)"
                    );

                    $insert->execute([
                        'student_id' => $studentId,
                        'allocation_id' => $currentAllocation['allocation_id'],
                        'hall_id' => (int)$requestedHall,
                        'reason' => $reason
                    ]);

                    $success = 'Your transfer request was submitted successfully.';
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }
            }

        } catch (PDOException $e) {
            error_log('Transfer request error: ' . $e->getMessage());
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

    <title>Hostel Transfer Request | Campus Hostel</title>

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

        /* HEADER */

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

        /* MAIN CONTAINER */

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

        /* FORM CARD */

        .form-card {
            background: #111827;
            border: 1px solid #263449;
            border-radius: 15px;
            padding: 30px;
        }

        .section-heading {
            font-size: 19px;
            margin-bottom: 10px;
        }

        .section-description {
            color: #8fa0b5;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 23px;
        }

        /* CURRENT ACCOMMODATION */

        .current-room {
            background: #172b45;
            border: 1px solid #31527c;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 28px;
        }

        .current-room h3 {
            color: #93c5fd;
            font-size: 16px;
            margin-bottom: 18px;
        }

        .room-details {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
        }

        .room-detail span {
            display: block;
            color: #aabbd0;
            font-size: 12px;
            margin-bottom: 8px;
        }

        .room-detail strong {
            color: #ffffff;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        /* FORM INPUTS */

        label {
            display: block;
            color: #e5e7eb;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .form-group {
            margin-bottom: 23px;
        }

        select,
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

        select:focus,
        textarea:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        select option {
            background: #111827;
            color: #ffffff;
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

        /* SUBMIT BUTTON */

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

        /* MESSAGES */

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

        /* EMPTY STATE */

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

        /* RESPONSIVE */

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

            .room-details {
                grid-template-columns: repeat(2, minmax(0, 1fr));
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

            .room-details {
                gap: 17px 12px;
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

        <h1>Hostel Transfer Request</h1>

        <p class="description">
            Request to move from your current hostel to another hostel.
            Your request will be reviewed by the accommodation officer.
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

        <?php if (!$currentAllocation): ?>

            <div class="empty">

                <h3>No Active Room Allocation</h3>

                You do not currently have an active room allocation.
                You must have an allocated room before requesting a transfer.

            </div>

        <?php else: ?>

            <h2 class="section-heading">
                Your Current Accommodation
            </h2>

            <p class="section-description">
                Review your current room details before requesting a transfer.
            </p>

            <div class="current-room">

                <h3>Current Room Information</h3>

                <div class="room-details">

                    <div class="room-detail">

                        <span>Current Hostel</span>

                        <strong>
                            <?= htmlspecialchars($currentAllocation['hall_name']) ?>
                        </strong>

                    </div>

                    <div class="room-detail">

                        <span>Room Number</span>

                        <strong>
                            <?= htmlspecialchars($currentAllocation['room_number']) ?>
                        </strong>

                    </div>

                    <div class="room-detail">

                        <span>Room Type</span>

                        <strong>
                            <?= htmlspecialchars($currentAllocation['room_type']) ?>
                        </strong>

                    </div>

                </div>

            </div>

            <?php if (empty($halls)): ?>

                <div class="empty">

                    There are no other hostels available for selection.

                </div>

            <?php else: ?>

                <h2 class="section-heading">
                    Transfer Details
                </h2>

                <p class="section-description">
                    Select your preferred destination hostel and explain
                    why you need to transfer.
                </p>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                    >

                    <div class="form-group">

                        <label for="requested_hall_id">
                            Preferred New Hostel
                        </label>

                        <select
                            id="requested_hall_id"
                            name="requested_hall_id"
                            required
                        >

                            <option value="">Select a hostel</option>

                            <?php foreach ($halls as $hall): ?>

                                <option
                                    value="<?= (int)$hall['hall_id'] ?>"
                                    <?= (string)($_POST['requested_hall_id'] ?? '') === (string)$hall['hall_id'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($hall['hall_name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="form-group">

                        <label for="reason">
                            Reason for Transfer
                        </label>

                        <textarea
                            id="reason"
                            name="reason"
                            minlength="10"
                            maxlength="1000"
                            placeholder="Explain why you need to transfer to another hostel..."
                            required
                        ><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>

                        <span class="hint">
                            Enter between 10 and 1000 characters.
                        </span>

                    </div>

                    <button type="submit">
                        Submit Transfer Request
                    </button>

                </form>

            <?php endif; ?>

        <?php endif; ?>

    </section>

</main>

<footer>
    &copy; <?= date('Y') ?> Campus Hostel Booking System.
    All rights reserved.
</footer>

</body>
</html>
