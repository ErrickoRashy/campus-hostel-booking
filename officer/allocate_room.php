<?php
require_once __DIR__ . '/../config/auth_check.php';
requireRole('officer');

require_once __DIR__ . '/../config/database.php';

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
$applicationId = filter_input(INPUT_GET, 'application_id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $applicationId = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
}

if (!$studentId || !$applicationId) {
    header('Location: allocation_requests.php');
    exit;
}

$message = '';
$roomId = null;
$startDate = date('Y-m-d');
$endDate = '';

$studentQuery = $pdo->prepare(
    "SELECT u.user_id, u.full_name, u.email,
            a.application_id, a.academic_year,
            a.semester, a.status, h.hall_name
     FROM applications a
     JOIN users u ON a.student_id = u.user_id
     LEFT JOIN halls h ON a.preferred_hall_id = h.hall_id
     WHERE a.application_id = :application_id
     AND a.student_id = :student_id"
);

$studentQuery->execute([
    'application_id' => $applicationId,
    'student_id' => $studentId
]);

$student = $studentQuery->fetch();

if (!$student) {
    http_response_code(404);
    exit('Student application not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? '';

    $roomId = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate = trim($_POST['end_date'] ?? '');

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        $message = 'Invalid or expired form submission. Please refresh the page.';
    } elseif (!$roomId || $startDate === '') {
        $message = 'Please select a room and allocation start date.';
    } else {

        $startObject = DateTime::createFromFormat('!Y-m-d', $startDate);
        $endObject = $endDate !== ''
            ? DateTime::createFromFormat('!Y-m-d', $endDate)
            : null;

        $validStart = $startObject &&
            $startObject->format('Y-m-d') === $startDate;

        $validEnd = $endDate === '' || (
            $endObject &&
            $endObject->format('Y-m-d') === $endDate
        );

        if (!$validStart || !$validEnd) {
            $message = 'Please enter valid allocation dates.';
        } elseif ($endDate !== '' && $endDate < $startDate) {
            $message = 'The end date cannot be earlier than the start date.';
        } else {

            try {
                $pdo->beginTransaction();

                
                $studentLock = $pdo->prepare(
                    "SELECT user_id FROM users
                     WHERE user_id = :student_id
                     FOR UPDATE"
                );

                $studentLock->execute([
                    'student_id' => $studentId
                ]);

                if (!$studentLock->fetch()) {
                    throw new RuntimeException('Student account not found.');
                }

                // Lock the application.
                $applicationLock = $pdo->prepare(
                    "SELECT status FROM applications
                     WHERE application_id = :application_id
                     AND student_id = :student_id
                     FOR UPDATE"
                );

                $applicationLock->execute([
                    'application_id' => $applicationId,
                    'student_id' => $studentId
                ]);

                $applicationStatus = $applicationLock->fetchColumn();

                if ($applicationStatus !== 'approved') {
                    throw new RuntimeException(
                        'This application is no longer awaiting allocation.'
                    );
                }

                
                $roomLock = $pdo->prepare(
                    "SELECT room_id, capacity, status
                     FROM rooms
                     WHERE room_id = :room_id
                     FOR UPDATE"
                );

                $roomLock->execute([
                    'room_id' => $roomId
                ]);

                $room = $roomLock->fetch();

                if (!$room || $room['status'] !== 'available') {
                    throw new RuntimeException(
                        'The selected room is unavailable.'
                    );
                }

                
                $activeCheck = $pdo->prepare(
                    "SELECT COUNT(*) FROM allocations
                     WHERE student_id = :student_id
                     AND status = 'active'"
                );

                $activeCheck->execute([
                    'student_id' => $studentId
                ]);

                if ((int)$activeCheck->fetchColumn() > 0) {
                    throw new RuntimeException(
                        'This student already has an active room allocation.'
                    );
                }

                // Check room occupancy.
                $occupancyCheck = $pdo->prepare(
                    "SELECT COUNT(*) FROM allocations
                     WHERE room_id = :room_id
                     AND status = 'active'"
                );

                $occupancyCheck->execute([
                    'room_id' => $roomId
                ]);

                $occupiedSpaces = (int)$occupancyCheck->fetchColumn();

                if ($occupiedSpaces >= (int)$room['capacity']) {
                    throw new RuntimeException(
                        'The selected room has no remaining spaces.'
                    );
                }

                // Create the allocation.
                $insert = $pdo->prepare(
                    "INSERT INTO allocations
                        (student_id, room_id, application_id,
                         start_date, end_date, status)
                     VALUES
                        (:student_id, :room_id, :application_id,
                         :start_date, :end_date, 'active')"
                );

                $insert->execute([
                    'student_id' => $studentId,
                    'room_id' => $roomId,
                    'application_id' => $applicationId,
                    'start_date' => $startDate,
                    'end_date' => $endDate !== '' ? $endDate : null
                ]);

                // Update application status.
                $update = $pdo->prepare(
                    "UPDATE applications
                     SET status = 'allocated'
                     WHERE application_id = :application_id"
                );

                $update->execute([
                    'application_id' => $applicationId
                ]);

                $pdo->commit();

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header('Location: allocation_requests.php?allocated=1');
                exit;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $message = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Allocation could not be completed. Please try again.';
            }
        }
    }
}

// Load rooms with available spaces.
$roomsQuery = $pdo->query(
    "SELECT r.room_id, r.room_number, r.room_type,
            r.capacity, r.floor_number, h.hall_name,
            r.capacity - (
                SELECT COUNT(*)
                FROM allocations al
                WHERE al.room_id = r.room_id
                AND al.status = 'active'
            ) AS spaces_left
     FROM rooms r
     JOIN halls h ON r.hall_id = h.hall_id
     WHERE r.status = 'available'
     AND (
         SELECT COUNT(*)
         FROM allocations al
         WHERE al.room_id = r.room_id
         AND al.status = 'active'
     ) < r.capacity
     ORDER BY h.hall_name, r.room_number"
);

$rooms = $roomsQuery->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Allocate Room | Campus Hostel</title>

    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            margin: 0;
            padding: 25px 15px;
            background: #000000;
            color: #ffffff;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 780px;
            margin: 25px auto;
            padding: 30px;
            background: #111827;
            border: 1px solid #263449;
            border-radius: 16px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.35);
        }

        .header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .header img {
            width: 65px;
            height: 65px;
            object-fit: contain;
        }

        .header h1 {
            margin: 0 0 7px;
            font-size: 25px;
            color: #ffffff;
        }

        .header p {
            margin: 0;
            color: #9ca3af;
            font-size: 13px;
        }

        .back {
            display: inline-block;
            margin-bottom: 25px;
            color: #60a5fa;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        }

        .back:hover {
            color: #93c5fd;
        }

        .student-details {
            padding: 22px;
            margin-bottom: 25px;
            background: #0b1220;
            border: 1px solid #263449;
            border-radius: 12px;
        }

        .student-details h2 {
            margin: 0 0 18px;
            color: #60a5fa;
            font-size: 18px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 17px;
        }

        .detail {
            min-width: 0;
        }

        .detail span {
            display: block;
            margin-bottom: 6px;
            color: #9ca3af;
            font-size: 12px;
        }

        .detail strong {
            display: block;
            color: #ffffff;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .status {
            display: inline-block;
            padding: 6px 11px;
            background: #164e63;
            color: #bae6fd;
            border-radius: 20px;
            font-size: 12px;
        }

        .form-section h2 {
            margin-bottom: 8px;
            font-size: 19px;
        }

        .form-section > p {
            color: #9ca3af;
            font-size: 13px;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin: 19px 0 9px;
            color: #e5e7eb;
            font-size: 14px;
            font-weight: bold;
        }

        select,
        input {
            width: 100%;
            padding: 14px;
            background: #0b1220;
            color: #ffffff;
            border: 1px solid #374151;
            border-radius: 8px;
            outline: none;
            font-size: 14px;
        }

        select:focus,
        input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        select option {
            background: #111827;
            color: #ffffff;
        }

        input[type="date"] {
            color-scheme: dark;
        }

        .submit-btn {
            width: 100%;
            padding: 15px;
            margin-top: 28px;
            border: none;
            border-radius: 8px;
            background: #2563eb;
            color: #ffffff;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .submit-btn:hover {
            background: #1d4ed8;
        }

        .alert {
            padding: 15px;
            margin-bottom: 22px;
            border-radius: 8px;
            font-size: 14px;
        }

        .error {
            background: #451a1a;
            border: 1px solid #7f1d1d;
            color: #fecaca;
        }

        .empty {
            padding: 20px;
            background: #0b1220;
            border: 1px solid #263449;
            border-radius: 8px;
            color: #9ca3af;
            text-align: center;
        }

        .required-note {
            color: #9ca3af;
            font-size: 12px;
            margin-top: 12px;
        }

        @media (max-width: 550px) {
            body {
                padding: 12px;
            }

            .container {
                margin: 10px auto;
                padding: 20px 16px;
            }

            .header img {
                width: 50px;
                height: 50px;
            }

            .header h1 {
                font-size: 20px;
            }

            .details-grid {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .student-details {
                padding: 17px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <a href="allocation_requests.php" class="back">
        &larr; Back to Allocation Requests
    </a>

    <div class="header">
        <img src="../campus-hostel-logo.png" alt="Campus Hostel Logo">

        <div>
            <h1>Allocate Hostel Room</h1>
            <p>Accommodation Officer Portal</p>
        </div>
    </div>

    <div class="student-details">

        <h2>Student Application Details</h2>

        <div class="details-grid">

            <div class="detail">
                <span>Student Name</span>
                <strong><?= e($student['full_name']) ?></strong>
            </div>

            <div class="detail">
                <span>Email Address</span>
                <strong><?= e($student['email']) ?></strong>
            </div>

            <div class="detail">
                <span>Academic Year</span>
                <strong><?= e($student['academic_year']) ?></strong>
            </div>

            <div class="detail">
                <span>Semester</span>
                <strong><?= e($student['semester']) ?></strong>
            </div>

            <div class="detail">
                <span>Preferred Hall</span>
                <strong><?= e($student['hall_name'] ?? 'No preference') ?></strong>
            </div>

            <div class="detail">
                <span>Application Status</span>
                <strong>
                    <span class="status">
                        <?= e(ucfirst($student['status'])) ?>
                    </span>
                </strong>
            </div>

        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert error">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($student['status'] === 'approved'): ?>

        <?php if ($rooms): ?>

            <div class="form-section">

                <h2>Room Allocation</h2>

                <p>Select a room with available spaces and set the allocation period.</p>

                <form method="POST">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= e($_SESSION['csrf_token']) ?>">

                    <input type="hidden"
                           name="student_id"
                           value="<?= (int)$studentId ?>">

                    <input type="hidden"
                           name="application_id"
                           value="<?= (int)$applicationId ?>">

                    <label for="room_id">Select Available Room</label>

                    <select name="room_id" id="room_id" required>

                        <option value="">-- Choose a Room --</option>

                        <?php foreach ($rooms as $room): ?>

                            <option
                                value="<?= (int)$room['room_id'] ?>"
                                <?= ($roomId == $room['room_id']) ? 'selected' : '' ?>>

                                <?= e(
                                    $room['hall_name'] .
                                    ' - Room ' . $room['room_number'] .
                                    ' (' . $room['room_type'] .
                                    ', ' . $room['spaces_left'] .
                                    ' spaces left)'
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <label for="start_date">Allocation Start Date</label>

                    <input
                        type="date"
                        name="start_date"
                        id="start_date"
                        value="<?= e($startDate) ?>"
                        required>

                    <label for="end_date">Allocation End Date (Optional)</label>

                    <input
                        type="date"
                        name="end_date"
                        id="end_date"
                        value="<?= e($endDate) ?>">

                    <p class="required-note">
                        The end date is optional. Leave it blank if no end date has been specified.
                    </p>

                    <button type="submit" class="submit-btn">
                        Confirm Room Allocation
                    </button>

                </form>

            </div>

        <?php else: ?>

            <div class="empty">
                No rooms currently have available spaces.
            </div>

        <?php endif; ?>

    <?php else: ?>

        <div class="empty">
            This application is not approved for allocation.
        </div>

    <?php endif; ?>

</div>

</body>
</html>
