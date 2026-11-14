<?php
require_once __DIR__ . '/../config/auth_check.php';
requireRole('officer');

require_once __DIR__ . '/../config/database.php';

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}


$query = $pdo->query(
    "SELECT a.application_id, a.academic_year, a.semester,
            a.application_date, u.user_id, u.full_name, u.email,
            h.hall_name
     FROM applications a
     JOIN users u ON a.student_id = u.user_id
     LEFT JOIN halls h ON a.preferred_hall_id = h.hall_id
     WHERE a.status = 'approved'
     AND NOT EXISTS (
         SELECT 1
         FROM allocations al
         WHERE al.student_id = a.student_id
         AND al.status = 'active'
     )
     ORDER BY a.application_date ASC"
);

$requests = $query->fetchAll();


$availableRooms = $pdo->query(
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
)->fetchAll();

$totalRequests = count($requests);
$totalRooms = count($availableRooms);

$totalSpaces = array_sum(array_map(
    fn($room) => (int)$room['spaces_left'],
    $availableRooms
));

$allocated = isset($_GET['allocated']) && $_GET['allocated'] === '1';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Allocation Requests | Campus Hostel</title>

    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            margin: 0;
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
            gap: 15px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand img {
            width: 52px;
            height: 52px;
            object-fit: contain;
        }

        .brand h2 {
            font-size: 19px;
            margin: 0;
            color: #ffffff;
        }

        .brand p {
            margin: 5px 0 0;
            font-size: 12px;
            color: #9ca3af;
        }

        .logout {
            background: #1f2937;
            border: 1px solid #374151;
            color: #ffffff;
            padding: 10px 17px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 13px;
            transition: 0.2s;
        }

        .logout:hover {
            background: #374151;
        }

        .container {
            width: 92%;
            max-width: 1200px;
            margin: 35px auto;
        }

        .back {
            display: inline-block;
            margin-bottom: 22px;
            color: #60a5fa;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        }

        .back:hover {
            color: #93c5fd;
        }

        .page-heading {
            margin-bottom: 25px;
        }

        .page-heading h1 {
            margin: 0 0 10px;
            font-size: 27px;
        }

        .page-heading p {
            color: #9ca3af;
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 17px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: #111827;
            border: 1px solid #263449;
            padding: 20px;
            border-radius: 12px;
        }

        .stat-card span {
            display: block;
            color: #9ca3af;
            font-size: 13px;
            margin-bottom: 12px;
        }

        .stat-card h2 {
            margin: 0;
            color: #60a5fa;
            font-size: 28px;
        }

        .section {
            background: #111827;
            padding: 25px;
            border-radius: 13px;
            border: 1px solid #263449;
            margin-bottom: 25px;
        }

        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 20px;
        }

        .section-heading h2 {
            color: #60a5fa;
            font-size: 19px;
            margin: 0;
        }

        .count {
            background: #1e3a5f;
            color: #bfdbfe;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 12px;
        }

        .request-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 17px;
        }

        .request {
            background: #0b1220;
            border: 1px solid #263449;
            padding: 20px;
            border-radius: 10px;
            min-width: 0;
            transition: 0.2s;
        }

        .request:hover {
            border-color: #3b82f6;
        }

        .request h3 {
            margin: 0 0 17px;
            font-size: 17px;
            color: #ffffff;
            overflow-wrap: anywhere;
        }

        .request p {
            margin: 11px 0;
            color: #d1d5db;
            font-size: 13px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .request strong {
            color: #60a5fa;
        }

        .allocate-btn {
            display: inline-block;
            margin-top: 12px;
            padding: 11px 16px;
            background: #2563eb;
            color: #ffffff;
            text-decoration: none;
            border-radius: 7px;
            font-size: 13px;
            font-weight: bold;
            transition: 0.2s;
        }

        .allocate-btn:hover {
            background: #1d4ed8;
        }

        .room-list {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .room {
            background: #0b1220;
            border: 1px solid #263449;
            padding: 19px;
            border-radius: 10px;
            min-width: 0;
        }

        .room h3 {
            color: #60a5fa;
            margin: 0 0 15px;
            font-size: 16px;
            overflow-wrap: anywhere;
        }

        .room p {
            margin: 10px 0;
            color: #d1d5db;
            font-size: 13px;
        }

        .spaces {
            display: inline-block;
            margin-top: 8px;
            padding: 7px 10px;
            border-radius: 6px;
            background: #164e63;
            color: #bae6fd;
            font-size: 12px;
            font-weight: bold;
        }

        .empty {
            padding: 25px;
            text-align: center;
            color: #9ca3af;
            background: #0b1220;
            border: 1px dashed #374151;
            border-radius: 9px;
            font-size: 14px;
        }

        .success {
            background: #052e16;
            border: 1px solid #166534;
            color: #bbf7d0;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 22px;
            font-size: 14px;
        }

        @media (max-width: 800px) {
            .room-list {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            header {
                padding: 12px 4%;
            }

            .brand img {
                width: 43px;
                height: 43px;
            }

            .brand h2 {
                font-size: 15px;
            }

            .container {
                width: 94%;
                margin: 22px auto;
            }

            .page-heading h1 {
                font-size: 23px;
            }

            .stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-card h2 {
                font-size: 23px;
            }

            .section {
                padding: 17px;
            }

            .request-list,
            .room-list {
                grid-template-columns: 1fr;
            }

            .section-heading h2 {
                font-size: 17px;
            }
        }
    </style>
</head>

<body>

<header>

    <div class="brand">
        <img src="../campus-hostel-logo.png" alt="Campus Hostel Logo">

        <div>
            <h2>Campus Hostel</h2>
            <p>Accommodation Officer Portal</p>
        </div>
    </div>

    <a href="../auth/logout.php" class="logout">Logout</a>

</header>

<main class="container">

    <a href="dashboard.php" class="back">
        &larr; Back to Dashboard
    </a>

    <div class="page-heading">
        <h1>Allocation Requests</h1>

        <p>
            Review approved student applications and assign available hostel rooms.
        </p>
    </div>

    <?php if ($allocated): ?>
        <div class="success">
            Room allocation completed successfully.
        </div>
    <?php endif; ?>

    <div class="stats">

        <div class="stat-card">
            <span>Awaiting Allocation</span>
            <h2><?= $totalRequests ?></h2>
        </div>

        <div class="stat-card">
            <span>Rooms With Available Spaces</span>
            <h2><?= $totalRooms ?></h2>
        </div>

        <div class="stat-card">
            <span>Total Available Spaces</span>
            <h2><?= $totalSpaces ?></h2>
        </div>

    </div>

    <section class="section">

        <div class="section-heading">
            <h2>Students Awaiting Allocation</h2>
            <span class="count"><?= $totalRequests ?> Requests</span>
        </div>

        <?php if ($requests): ?>

            <div class="request-list">

                <?php foreach ($requests as $request): ?>

                    <div class="request">

                        <h3><?= e($request['full_name']) ?></h3>

                        <p>
                            <strong>Email:</strong>
                            <?= e($request['email']) ?>
                        </p>

                        <p>
                            <strong>Academic Year:</strong>
                            <?= e($request['academic_year']) ?>
                        </p>

                        <p>
                            <strong>Semester:</strong>
                            <?= e($request['semester']) ?>
                        </p>

                        <p>
                            <strong>Preferred Hall:</strong>
                            <?= e($request['hall_name'] ?? 'No preference') ?>
                        </p>

                        <p>
                            <strong>Application Date:</strong>
                            <?= e($request['application_date']) ?>
                        </p>

                        <a
                            class="allocate-btn"
                            href="allocate_room.php?student_id=<?= (int)$request['user_id'] ?>&application_id=<?= (int)$request['application_id'] ?>">

                            Allocate Room &rarr;

                        </a>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty">
                No approved applications are currently awaiting allocation.
            </div>

        <?php endif; ?>

    </section>

    <section class="section">

        <div class="section-heading">
            <h2>Available Hostel Rooms</h2>
            <span class="count"><?= $totalRooms ?> Rooms</span>
        </div>

        <?php if ($availableRooms): ?>

            <div class="room-list">

                <?php foreach ($availableRooms as $room): ?>

                    <div class="room">

                        <h3>
                            <?= e($room['hall_name']) ?>
                            - Room <?= e($room['room_number']) ?>
                        </h3>

                        <p>
                            <strong>Room Type:</strong>
                            <?= e($room['room_type']) ?>
                        </p>

                        <p>
                            <strong>Capacity:</strong>
                            <?= (int)$room['capacity'] ?> students
                        </p>

                        <p>
                            <strong>Floor:</strong>
                            <?= (int)$room['floor_number'] ?>
                        </p>

                        <span class="spaces">
                            <?= (int)$room['spaces_left'] ?> Spaces Available
                        </span>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty">
                There are currently no rooms with available spaces.
            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
