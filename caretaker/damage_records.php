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

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid request. Please refresh the page.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'report') {
            $roomId = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
            $description = trim($_POST['description'] ?? '');
            $severity = trim($_POST['severity'] ?? '');

            if (!$roomId || $description === '' || strlen($description) > 2000) {
                $error = 'Please provide a valid room and damage description.';
            } elseif (!in_array($severity, ['Low', 'Medium', 'High'], true)) {
                $error = 'Please select a valid damage severity.';
            } else {
                $check = $pdo->prepare(
                    "SELECT room_id FROM rooms WHERE room_id = :room_id"
                );
                $check->execute(['room_id' => $roomId]);

                if (!$check->fetch()) {
                    $error = 'The selected room does not exist.';
                } else {
                    $insert = $pdo->prepare(
                        "INSERT INTO damage_records
                        (room_id, caretaker_id, description, severity)
                        VALUES (:room_id, :caretaker_id, :description, :severity)"
                    );

                    $insert->execute([
                        'room_id' => $roomId,
                        'caretaker_id' => $caretakerId,
                        'description' => $description,
                        'severity' => $severity
                    ]);

                    $message = 'Damage report submitted successfully.';
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }
            }
        } elseif ($action === 'update_status') {
            $damageId = filter_input(INPUT_POST, 'damage_id', FILTER_VALIDATE_INT);
            $newStatus = trim($_POST['status'] ?? '');

            if (!$damageId || !in_array($newStatus, ['Pending', 'In Progress', 'Resolved'], true)) {
                $error = 'Invalid damage record or status.';
            } else {
                $update = $pdo->prepare(
                    "UPDATE damage_records
                     SET status = :status,
                         resolved_at = CASE
                             WHEN :resolved_status = 'Resolved'
                             THEN CURRENT_TIMESTAMP
                             ELSE NULL
                         END
                     WHERE damage_id = :damage_id"
                );

                $update->execute([
                    'status' => $newStatus,
                    'resolved_status' => $newStatus,
                    'damage_id' => $damageId
                ]);

                if ($update->rowCount() >= 0) {
                    $message = 'Damage record status updated successfully.';
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }
            }
        } else {
            $error = 'Unknown request.';
        }
    }
}

$roomsQuery = $pdo->query(
    "SELECT r.room_id, r.room_number, h.hall_name
     FROM rooms r
     JOIN halls h ON h.hall_id = r.hall_id
     ORDER BY h.hall_name, r.room_number"
);
$rooms = $roomsQuery->fetchAll();

$recordsQuery = $pdo->query(
    "SELECT d.damage_id, d.description, d.severity,
            d.status, d.reported_at, d.resolved_at,
            r.room_number, h.hall_name, u.full_name AS caretaker_name
     FROM damage_records d
     JOIN rooms r ON r.room_id = d.room_id
     JOIN halls h ON h.hall_id = r.hall_id
     JOIN users u ON u.user_id = d.caretaker_id
     ORDER BY
        CASE d.status
            WHEN 'Pending' THEN 1
            WHEN 'In Progress' THEN 2
            ELSE 3
        END,
        d.reported_at DESC"
);
$records = $recordsQuery->fetchAll();

$totalRecords = count($records);
$pendingCount = 0;
$progressCount = 0;
$resolvedCount = 0;

foreach ($records as $record) {
    if ($record['status'] === 'Pending') {
        $pendingCount++;
    } elseif ($record['status'] === 'In Progress') {
        $progressCount++;
    } elseif ($record['status'] === 'Resolved') {
        $resolvedCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Damage Records | Campus Hostel</title>

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
            max-width: 1300px;
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
            color: #60a5fa;
            font-size: 29px;
            margin-bottom: 9px;
        }

        .stat p {
            color: #9ca3af;
            font-size: 13px;
        }

        .panel {
            background: #111827;
            padding: 27px;
            border: 1px solid #263449;
            border-radius: 12px;
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
        }

        .report-form {
            max-width: 700px;
        }

        .form-group label {
            display: block;
            margin-bottom: 9px;
            font-weight: bold;
            color: #e5e7eb;
            font-size: 14px;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 14px;
            border: 1px solid #374151;
            border-radius: 7px;
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

        button {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 13px 18px;
            border-radius: 7px;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
            transition: 0.2s;
        }

        button:hover {
            background: #1d4ed8;
        }

        .submit-button {
            padding: 14px 22px;
            font-size: 15px;
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
            min-width: 1000px;
            border-collapse: collapse;
        }

        th,
        td {
            text-align: left;
            padding: 14px;
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
            font-size: 13px;
        }

        tbody tr:hover {
            background: #172033;
        }

        .status,
        .severity {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .pending {
            background: #493b16;
            color: #fde68a;
        }

        .progress {
            background: #163b57;
            color: #9bd5ff;
        }

        .resolved {
            background: #123524;
            color: #bbf7d0;
        }

        .low {
            background: #123524;
            color: #bbf7d0;
        }

        .medium {
            background: #493b16;
            color: #fde68a;
        }

        .high {
            background: #451a1a;
            color: #fecaca;
        }

        .status-form {
            min-width: 140px;
            gap: 8px;
        }

        .status-form select {
            padding: 9px;
        }

        .status-form button {
            width: 100%;
            padding: 9px;
        }

        .empty {
            color: #9ca3af;
            padding: 18px 0;
            line-height: 1.6;
        }

        footer {
            text-align: center;
            padding: 25px;
            color: #6b7280;
            font-size: 13px;
            border-top: 1px solid #1f2937;
        }

        @media (max-width: 900px) {
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

        <h1>Damage Records</h1>

        <p>
            Report room damage, monitor repairs and update
            maintenance progress.
        </p>

    </section>

    <section class="stats">

        <div class="stat">
            <h3><?= (int) $totalRecords ?></h3>
            <p>Total Damage Reports</p>
        </div>

        <div class="stat">
            <h3><?= (int) $pendingCount ?></h3>
            <p>Pending Reports</p>
        </div>

        <div class="stat">
            <h3><?= (int) $progressCount ?></h3>
            <p>Repairs In Progress</p>
        </div>

        <div class="stat">
            <h3><?= (int) $resolvedCount ?></h3>
            <p>Resolved Reports</p>
        </div>

    </section>

    <section class="panel">

        <div class="panel-heading">
            <h2>Report Room Damage</h2>
            <p>Provide the room details and describe the damage observed.</p>
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

            <p class="empty">No rooms are available yet.</p>

        <?php else: ?>

            <form method="POST" class="report-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <input type="hidden" name="action" value="report">

                <div class="form-group">

                    <label for="room_id">Select Hostel Room</label>

                    <select name="room_id" id="room_id" required>

                        <option value="">Choose a room</option>

                        <?php foreach ($rooms as $room): ?>

                            <option value="<?= (int) $room['room_id'] ?>">
                                <?= e($room['hall_name']) ?> -
                                Room <?= e($room['room_number']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label for="severity">Damage Severity</label>

                    <select name="severity" id="severity" required>

                        <option value="">Select severity</option>
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>

                    </select>

                </div>

                <div class="form-group">

                    <label for="description">Damage Description</label>

                    <textarea
                        name="description"
                        id="description"
                        maxlength="2000"
                        required
                        placeholder="Describe the damage observed..."
                    ></textarea>

                </div>

                <button type="submit" class="submit-button">
                    Submit Damage Report
                </button>

            </form>

        <?php endif; ?>

    </section>

    <section class="panel">

        <div class="panel-heading">
            <h2>Damage Report History</h2>
            <p>Review reported damage and manage maintenance progress.</p>
        </div>

        <?php if (empty($records)): ?>

            <p class="empty">
                No damage reports have been recorded yet.
            </p>

        <?php else: ?>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>Hall</th>
                            <th>Room</th>
                            <th>Description</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>Reported By</th>
                            <th>Date Reported</th>
                            <th>Update Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($records as $record): ?>

                            <?php
                            $statusClass = match ($record['status']) {
                                'Pending' => 'pending',
                                'In Progress' => 'progress',
                                'Resolved' => 'resolved',
                                default => ''
                            };

                            $severityClass = strtolower($record['severity']);
                            ?>

                            <tr>

                                <td><?= e($record['hall_name']) ?></td>

                                <td><?= e($record['room_number']) ?></td>

                                <td><?= e($record['description']) ?></td>

                                <td>
                                    <span class="severity <?= e($severityClass) ?>">
                                        <?= e($record['severity']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="status <?= e($statusClass) ?>">
                                        <?= e($record['status']) ?>
                                    </span>
                                </td>

                                <td><?= e($record['caretaker_name']) ?></td>

                                <td>
                                    <?= e(date(
                                        'd M Y, H:i',
                                        strtotime($record['reported_at'])
                                    )) ?>
                                </td>

                                <td>

                                    <form method="POST" class="status-form">

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($_SESSION['csrf_token']) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="update_status"
                                        >

                                        <input
                                            type="hidden"
                                            name="damage_id"
                                            value="<?= (int) $record['damage_id'] ?>"
                                        >

                                        <select name="status" required>

                                            <option value="Pending"
                                                <?= $record['status'] === 'Pending' ? 'selected' : '' ?>>
                                                Pending
                                            </option>

                                            <option value="In Progress"
                                                <?= $record['status'] === 'In Progress' ? 'selected' : '' ?>>
                                                In Progress
                                            </option>

                                            <option value="Resolved"
                                                <?= $record['status'] === 'Resolved' ? 'selected' : '' ?>>
                                                Resolved
                                            </option>

                                        </select>

                                        <button type="submit">Update Status</button>

                                    </form>

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
