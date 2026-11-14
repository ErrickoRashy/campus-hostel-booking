<?php


require_once __DIR__ . '/../config/auth_check.php';
requireRole('caretaker');

require_once __DIR__ . '/../config/database.php';

$caretakerId = (int) $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } else {
        $roomId = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
        $condition = trim($_POST['condition_status'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        $allowedConditions = ['Good', 'Needs Repair', 'Damaged'];

        if (!$roomId || !in_array($condition, $allowedConditions, true)) {
            $error = 'Please select a valid room and condition.';
        } elseif (strlen($notes) > 2000) {
            $error = 'Inspection notes must not exceed 2,000 characters.';
        } else {
            $checkRoom = $pdo->prepare(
                "SELECT room_id FROM rooms WHERE room_id = :room_id"
            );
            $checkRoom->execute(['room_id' => $roomId]);

            if (!$checkRoom->fetch()) {
                $error = 'The selected room does not exist.';
            } else {
                $insert = $pdo->prepare(
                    "INSERT INTO room_inspections
                    (room_id, caretaker_id, condition_status, notes)
                    VALUES (:room_id, :caretaker_id, :condition_status, :notes)"
                );

                $insert->execute([
                    'room_id' => $roomId,
                    'caretaker_id' => $caretakerId,
                    'condition_status' => $condition,
                    'notes' => $notes !== '' ? $notes : null
                ]);

                $message = 'Room inspection recorded successfully.';
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
        }
    }
}

$roomsQuery = $pdo->query(
    "SELECT r.room_id, r.room_number, r.room_type,
            h.hall_name
     FROM rooms r
     JOIN halls h ON h.hall_id = r.hall_id
     ORDER BY h.hall_name, r.room_number"
);
$rooms = $roomsQuery->fetchAll();

$inspectionsQuery = $pdo->query(
    "SELECT i.inspection_id, i.condition_status, i.notes,
            i.inspection_date, r.room_number, h.hall_name,
            u.full_name AS caretaker_name
     FROM room_inspections i
     JOIN rooms r ON r.room_id = i.room_id
     JOIN halls h ON h.hall_id = r.hall_id
     JOIN users u ON u.user_id = i.caretaker_id
     ORDER BY i.inspection_date DESC
     LIMIT 100"
);
$inspections = $inspectionsQuery->fetchAll();

$totalInspections = count($inspections);
$goodCount = 0;
$repairCount = 0;
$damagedCount = 0;

foreach ($inspections as $inspection) {
    if ($inspection['condition_status'] === 'Good') {
        $goodCount++;
    } elseif ($inspection['condition_status'] === 'Needs Repair') {
        $repairCount++;
    } elseif ($inspection['condition_status'] === 'Damaged') {
        $damagedCount++;
    }
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Room Conditions | Campus Hostel</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: #000000;
            color: #ffffff;
            min-height: 100vh;
        }

        header {
            background: #111827;
            border-bottom: 1px solid #263449;
            padding: 15px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand img {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }

        .brand h2 {
            font-size: 19px;
            color: #ffffff;
        }

        .back-link {
            color: #60a5fa;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
        }

        .back-link:hover {
            color: #93c5fd;
        }

        .container {
            width: 92%;
            max-width: 1250px;
            margin: 35px auto;
        }

        .intro {
            margin-bottom: 28px;
        }

        .intro h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .intro p {
            color: #9ca3af;
            line-height: 1.6;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 17px;
            margin-bottom: 28px;
        }

        .stat {
            background: #111827;
            padding: 23px;
            border-radius: 12px;
            border: 1px solid #263449;
            transition: 0.2s;
        }

        .stat:hover {
            border-color: #2563eb;
            transform: translateY(-3px);
        }

        .stat h3 {
            font-size: 29px;
            margin-bottom: 9px;
            color: #60a5fa;
        }

        .stat p {
            color: #9ca3af;
            font-size: 13px;
        }

        .panel {
            background: #111827;
            padding: 27px;
            border-radius: 12px;
            border: 1px solid #263449;
            margin-bottom: 25px;
        }

        .panel-heading {
            margin-bottom: 22px;
        }

        .panel-heading h2 {
            font-size: 20px;
            margin-bottom: 8px;
        }

        .panel-heading p {
            color: #9ca3af;
            font-size: 14px;
        }

        form {
            display: grid;
            gap: 17px;
            max-width: 700px;
        }

        .form-group label {
            font-weight: bold;
            margin-bottom: 9px;
            display: block;
            color: #e5e7eb;
            font-size: 14px;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 14px;
            border-radius: 7px;
            border: 1px solid #374151;
            background: #080d16;
            color: #ffffff;
            font-size: 14px;
        }

        select option {
            background: #111827;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .submit-button {
            background: #2563eb;
            color: #ffffff;
            padding: 14px 22px;
            border: none;
            border-radius: 7px;
            font-weight: bold;
            cursor: pointer;
            font-size: 15px;
            transition: 0.2s;
        }

        .submit-button:hover {
            background: #1d4ed8;
        }

        .alert {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            line-height: 1.5;
            font-size: 14px;
        }

        .success {
            background: #123524;
            color: #bbf7d0;
            border: 1px solid #166534;
        }

        .error {
            background: #451a1a;
            color: #fecaca;
            border: 1px solid #991b1b;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        th,
        td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #263449;
            vertical-align: top;
        }

        th {
            color: #60a5fa;
            background: #0b1220;
            font-size: 13px;
        }

        td {
            color: #e5e7eb;
            font-size: 14px;
        }

        tbody tr:hover {
            background: #172033;
        }

        .status {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .good {
            background: #123524;
            color: #bbf7d0;
        }

        .repair {
            background: #493b16;
            color: #fde68a;
        }

        .damaged {
            background: #451a1a;
            color: #fecaca;
        }

        .empty {
            color: #9ca3af;
            padding: 18px 0;
            line-height: 1.6;
        }

        footer {
            text-align: center;
            color: #6b7280;
            padding: 25px;
            font-size: 13px;
            border-top: 1px solid #1f2937;
        }

        @media (max-width: 850px) {
            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 550px) {
            header {
                padding: 14px 5%;
            }

            .brand img {
                width: 40px;
                height: 40px;
            }

            .brand h2 {
                font-size: 15px;
            }

            .back-link {
                font-size: 13px;
            }

            .container {
                margin: 25px auto;
            }

            .intro h1 {
                font-size: 23px;
            }

            .stats {
                gap: 11px;
            }

            .stat {
                padding: 17px 13px;
            }

            .stat h3 {
                font-size: 25px;
            }

            .stat p {
                font-size: 12px;
            }

            .panel {
                padding: 20px 15px;
            }

            .panel-heading h2 {
                font-size: 18px;
            }

            .submit-button {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<header>

    <div class="brand">
        <img src="../campus-hostel-logo.png" alt="Campus Hostel Logo">
        <h2>Campus Hostel | Caretaker</h2>
    </div>

    <a href="dashboard.php" class="back-link">
        ← Back to Dashboard
    </a>

</header>

<main class="container">

    <section class="intro">

        <h1>Room Conditions</h1>

        <p>
            Inspect hostel rooms, record their current conditions
            and monitor maintenance requirements.
        </p>

    </section>

    <section class="stats">

        <div class="stat">
            <h3><?= (int) $totalInspections ?></h3>
            <p>Recent Inspections</p>
        </div>

        <div class="stat">
            <h3><?= (int) $goodCount ?></h3>
            <p>Good Condition</p>
        </div>

        <div class="stat">
            <h3><?= (int) $repairCount ?></h3>
            <p>Needs Repair</p>
        </div>

        <div class="stat">
            <h3><?= (int) $damagedCount ?></h3>
            <p>Damaged Rooms</p>
        </div>

    </section>

    <section class="panel">

        <div class="panel-heading">
            <h2>Record Room Inspection</h2>
            <p>Enter the room details and its current condition.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert success">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($rooms)): ?>

            <p class="empty">
                No rooms are available for inspection yet.
            </p>

        <?php else: ?>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <div class="form-group">

                    <label for="room_id">Select Hostel Room</label>

                    <select name="room_id" id="room_id" required>

                        <option value="">Choose a room</option>

                        <?php foreach ($rooms as $room): ?>

                            <option value="<?= (int) $room['room_id'] ?>">
                                <?= e($room['hall_name']) ?>
                                - Room <?= e($room['room_number']) ?>
                                (<?= e($room['room_type']) ?>)
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label for="condition_status">Room Condition</label>

                    <select
                        name="condition_status"
                        id="condition_status"
                        required
                    >

                        <option value="">Select condition</option>
                        <option value="Good">Good</option>
                        <option value="Needs Repair">Needs Repair</option>
                        <option value="Damaged">Damaged</option>

                    </select>

                </div>

                <div class="form-group">

                    <label for="notes">Inspection Notes</label>

                    <textarea
                        name="notes"
                        id="notes"
                        maxlength="2000"
                        placeholder="Describe the room condition or any problems found..."
                    ></textarea>

                </div>

                <button type="submit" class="submit-button">
                    Save Room Inspection
                </button>

            </form>

        <?php endif; ?>

    </section>

    <section class="panel">

        <div class="panel-heading">
            <h2>Inspection History</h2>
            <p>Review the latest 100 recorded room inspections.</p>
        </div>

        <?php if (empty($inspections)): ?>

            <p class="empty">
                No room inspections have been recorded yet.
            </p>

        <?php else: ?>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>Hall</th>
                            <th>Room</th>
                            <th>Condition</th>
                            <th>Notes</th>
                            <th>Inspected By</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($inspections as $inspection): ?>

                            <?php
                            $statusClass = match ($inspection['condition_status']) {
                                'Good' => 'good',
                                'Needs Repair' => 'repair',
                                'Damaged' => 'damaged',
                                default => ''
                            };
                            ?>

                            <tr>

                                <td>
                                    <?= e($inspection['hall_name']) ?>
                                </td>

                                <td>
                                    <?= e($inspection['room_number']) ?>
                                </td>

                                <td>
                                    <span class="status <?= e($statusClass) ?>">
                                        <?= e($inspection['condition_status']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e($inspection['notes'] ?: 'No notes') ?>
                                </td>

                                <td>
                                    <?= e($inspection['caretaker_name']) ?>
                                </td>

                                <td>
                                    <?= e(date(
                                        'd M Y, H:i',
                                        strtotime($inspection['inspection_date'])
                                    )) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

</main>

<footer>
    © <?= date('Y') ?> Campus Hostel Booking System. All rights reserved.
</footer>

</body>
</html>
