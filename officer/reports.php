<?php
require_once __DIR__ . '/../config/auth_check.php';
requireRole('officer');

require_once __DIR__ . '/../config/database.php';

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Application statistics
$applicationStats = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        COUNT(*) FILTER (WHERE status = 'pending') AS pending,
        COUNT(*) FILTER (WHERE status = 'approved') AS approved,
        COUNT(*) FILTER (WHERE status = 'allocated') AS allocated,
        COUNT(*) FILTER (WHERE status = 'rejected') AS rejected
     FROM applications"
)->fetch();

// Room statistics
$roomStats = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        COUNT(*) FILTER (WHERE status = 'maintenance') AS maintenance,
        COUNT(*) FILTER (
            WHERE status = 'available'
            AND capacity > (
                SELECT COUNT(*)
                FROM allocations a
                WHERE a.room_id = rooms.room_id
                AND a.status = 'active'
            )
        ) AS available
     FROM rooms"
)->fetch();

// Active allocations and allocated students
$allocationStats = $pdo->query(
    "SELECT
        COUNT(*) AS active_allocations,
        COUNT(DISTINCT student_id) AS allocated_students
     FROM allocations
     WHERE status = 'active'"
)->fetch();

$totalStudents = $pdo->query(
    "SELECT COUNT(*) FROM users WHERE role = 'student'"
)->fetchColumn();

// Calculate available capacity and occupied spaces
$capacityStats = $pdo->query(
    "SELECT
        COALESCE(SUM(r.capacity), 0) AS total_capacity,
        COALESCE(SUM(
            LEAST(
                r.capacity,
                (
                    SELECT COUNT(*)
                    FROM allocations a
                    WHERE a.room_id = r.room_id
                    AND a.status = 'active'
                )
            )
        ), 0) AS occupied_spaces
     FROM rooms r
     WHERE r.status = 'available'"
)->fetch();

$totalCapacity = (int)$capacityStats['total_capacity'];
$occupiedSpaces = (int)$capacityStats['occupied_spaces'];

$occupancyPercentage = $totalCapacity > 0
    ? round(($occupiedSpaces / $totalCapacity) * 100, 1)
    : 0;


$transferStats = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        COUNT(*) FILTER (WHERE status = 'pending') AS pending,
        COUNT(*) FILTER (WHERE status = 'approved') AS approved,
        COUNT(*) FILTER (WHERE status = 'rejected') AS rejected
     FROM transfer_requests"
)->fetch();


$vacationStats = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        COUNT(*) FILTER (WHERE status = 'pending') AS pending,
        COUNT(*) FILTER (WHERE status = 'approved') AS approved,
        COUNT(*) FILTER (WHERE status = 'rejected') AS rejected
     FROM vacation_requests"
)->fetch();

$hallReport = $pdo->query(
    "SELECT
        h.hall_name,
        COALESCE(room_data.total_rooms, 0) AS total_rooms,
        COALESCE(room_data.total_capacity, 0) AS total_capacity,
        COALESCE(room_data.occupied_spaces, 0) AS occupied_spaces
     FROM halls h
     LEFT JOIN (
         SELECT
             r.hall_id,
             COUNT(DISTINCT r.room_id) AS total_rooms,
             SUM(r.capacity) AS total_capacity,
             COUNT(a.allocation_id) FILTER (
                 WHERE a.status = 'active'
             ) AS occupied_spaces
         FROM rooms r
         LEFT JOIN allocations a ON r.room_id = a.room_id
         GROUP BY r.hall_id
     ) room_data ON h.hall_id = room_data.hall_id
     ORDER BY h.hall_name"
)->fetchAll();


$recentAllocations = $pdo->query(
    "SELECT
        u.full_name,
        h.hall_name,
        r.room_number,
        r.room_type,
        a.allocation_date
     FROM allocations a
     JOIN users u ON a.student_id = u.user_id
     JOIN rooms r ON a.room_id = r.room_id
     JOIN halls h ON r.hall_id = h.hall_id
     WHERE a.status = 'active'
     ORDER BY a.allocation_date DESC
     LIMIT 10"
)->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Hostel Reports | Campus Hostel</title>

    <style>

        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            margin: 0;
            background: #000000;
            color: #ffffff;
        }

        header {
            background: #111827;
            padding: 15px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #263449;
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
            color: #ffffff;
            font-size: 19px;
            margin: 0;
        }

        .brand p {
            color: #9ca3af;
            font-size: 12px;
            margin: 5px 0 0;
        }

        .logout {
            color: #ffffff;
            background: #1f2937;
            border: 1px solid #374151;
            padding: 10px 16px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 13px;
            transition: 0.2s;
        }

        .logout:hover {
            background: #374151;
        }

        .container {
            width: 94%;
            max-width: 1250px;
            margin: 35px auto;
        }

        .back {
            display: inline-block;
            color: #60a5fa;
            text-decoration: none;
            margin-bottom: 22px;
            font-size: 14px;
        }

        .back:hover {
            color: #93c5fd;
        }

        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .page-heading h1 {
            margin: 0 0 9px;
            font-size: 27px;
        }

        .intro {
            color: #9ca3af;
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
        }

        .print-button {
            padding: 12px 20px;
            background: #2563eb;
            color: #ffffff;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
            transition: 0.2s;
        }

        .print-button:hover {
            background: #1d4ed8;
        }

        .section {
            background: #111827;
            padding: 25px;
            border: 1px solid #263449;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .section h2 {
            color: #60a5fa;
            font-size: 19px;
            margin: 0 0 20px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
        }

        .stat {
            background: #0b1220;
            padding: 20px;
            border-radius: 9px;
            border: 1px solid #263449;
            transition: 0.2s;
            min-width: 0;
        }

        .stat:hover {
            border-color: #3b82f6;
        }

        .stat h3 {
            color: #9ca3af;
            font-size: 13px;
            font-weight: normal;
            margin: 0 0 13px;
        }

        .stat p {
            color: #60a5fa;
            font-size: 27px;
            font-weight: bold;
            margin: 0;
        }

        .progress-heading {
            margin-top: 27px;
            margin-bottom: 12px;
            font-size: 14px;
            color: #d1d5db;
        }

        .progress {
            background: #030712;
            height: 13px;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 13px;
            border: 1px solid #263449;
        }

        .progress-bar {
            height: 100%;
            background: #3b82f6;
            border-radius: 10px;
            transition: width 0.3s;
        }

        .percentage {
            color: #60a5fa;
            font-size: 26px;
            font-weight: bold;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th,
        td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #263449;
            font-size: 13px;
        }

        th {
            color: #93c5fd;
            background: #0b1220;
        }

        td {
            color: #d1d5db;
        }

        tbody tr:hover {
            background: #172033;
        }

        .empty {
            color: #9ca3af;
            font-size: 14px;
        }

        @media (max-width: 900px) {

            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

        }

        @media (max-width: 550px) {

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
                margin: 23px auto;
            }

            .page-heading h1 {
                font-size: 23px;
            }

            .section {
                padding: 17px;
            }

            .stats {
                gap: 10px;
            }

            .stat {
                padding: 14px;
            }

            .stat h3 {
                font-size: 12px;
            }

            .stat p {
                font-size: 23px;
            }

            .print-button {
                width: 100%;
            }

        }

        @media print {

            body {
                background: #ffffff;
                color: #000000;
            }

            header,
            .back,
            .print-button {
                display: none !important;
            }

            .container {
                width: 100%;
                max-width: none;
                margin: 0;
            }

            .section,
            .stat {
                background: #ffffff;
                color: #000000;
                border: 1px solid #cccccc;
                break-inside: avoid;
            }

            .section h2,
            .stat p,
            th,
            .percentage {
                color: #222222;
            }

            .stat h3,
            td,
            .intro {
                color: #333333;
            }

            .progress {
                border: 1px solid #aaaaaa;
            }

            .progress-bar {
                background: #555555;
            }

            th {
                background: #eeeeee;
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
        ← Back to Dashboard
    </a>

    <div class="page-heading">

        <div>

            <h1>Hostel Management Reports</h1>

            <p class="intro">
                Review accommodation applications, room occupancy and student requests.
            </p>

        </div>

        <button class="print-button" onclick="window.print()">
            Print / Save Report
        </button>

    </div>

    <!-- Student Overview -->
    <section class="section">

        <h2>Student Overview</h2>

        <div class="stats">

            <div class="stat">
                <h3>Total Registered Students</h3>
                <p><?= (int)$totalStudents ?></p>
            </div>

            <div class="stat">
                <h3>Allocated Students</h3>
                <p><?= (int)$allocationStats['allocated_students'] ?></p>
            </div>

            <div class="stat">
                <h3>Students Without Allocation</h3>
                <p>
                    <?= max(
                        0,
                        (int)$totalStudents - (int)$allocationStats['allocated_students']
                    ) ?>
                </p>
            </div>

        </div>

    </section>

    <!-- Application Report -->
    <section class="section">

        <h2>Application Report</h2>

        <div class="stats">

            <div class="stat">
                <h3>Total Applications</h3>
                <p><?= (int)$applicationStats['total'] ?></p>
            </div>

            <div class="stat">
                <h3>Pending Applications</h3>
                <p><?= (int)$applicationStats['pending'] ?></p>
            </div>

            <div class="stat">
                <h3>Approved Applications</h3>
                <p><?= (int)$applicationStats['approved'] ?></p>
            </div>

            <div class="stat">
                <h3>Allocated Applications</h3>
                <p><?= (int)$applicationStats['allocated'] ?></p>
            </div>

            <div class="stat">
                <h3>Rejected Applications</h3>
                <p><?= (int)$applicationStats['rejected'] ?></p>
            </div>

        </div>

    </section>

    
    <section class="section">

        <h2>Room Occupancy Report</h2>

        <div class="stats">

            <div class="stat">
                <h3>Total Rooms</h3>
                <p><?= (int)$roomStats['total'] ?></p>
            </div>

            <div class="stat">
                <h3>Rooms With Available Spaces</h3>
                <p><?= (int)$roomStats['available'] ?></p>
            </div>

            <div class="stat">
                <h3>Maintenance Rooms</h3>
                <p><?= (int)$roomStats['maintenance'] ?></p>
            </div>

            <div class="stat">
                <h3>Active Allocations</h3>
                <p><?= (int)$allocationStats['active_allocations'] ?></p>
            </div>

        </div>

        <h3 class="progress-heading">
            Occupancy Percentage
        </h3>

        <div class="percentage">
            <?= e($occupancyPercentage) ?>%
        </div>

        <div class="progress">

            <div class="progress-bar"
                 style="width: <?= min(100, $occupancyPercentage) ?>%;">
            </div>

        </div>

    </section>

    
    <section class="section">

        <h2>Transfer Requests Report</h2>

        <div class="stats">

            <div class="stat">
                <h3>Total Requests</h3>
                <p><?= (int)$transferStats['total'] ?></p>
            </div>

            <div class="stat">
                <h3>Pending</h3>
                <p><?= (int)$transferStats['pending'] ?></p>
            </div>

            <div class="stat">
                <h3>Approved</h3>
                <p><?= (int)$transferStats['approved'] ?></p>
            </div>

            <div class="stat">
                <h3>Rejected</h3>
                <p><?= (int)$transferStats['rejected'] ?></p>
            </div>

        </div>

    </section>

    
    <section class="section">

        <h2>Vacation Requests Report</h2>

        <div class="stats">

            <div class="stat">
                <h3>Total Requests</h3>
                <p><?= (int)$vacationStats['total'] ?></p>
            </div>

            <div class="stat">
                <h3>Pending</h3>
                <p><?= (int)$vacationStats['pending'] ?></p>
            </div>

            <div class="stat">
                <h3>Approved</h3>
                <p><?= (int)$vacationStats['approved'] ?></p>
            </div>

            <div class="stat">
                <h3>Rejected</h3>
                <p><?= (int)$vacationStats['rejected'] ?></p>
            </div>

        </div>

    </section>

    
    <section class="section">

        <h2>Hall Occupancy Summary</h2>

        <?php if ($hallReport): ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Hall</th>
                            <th>Total Rooms</th>
                            <th>Total Capacity</th>
                            <th>Occupied Spaces</th>
                            <th>Remaining Spaces</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($hallReport as $hall): ?>

                            <tr>

                                <td><?= e($hall['hall_name']) ?></td>

                                <td><?= (int)$hall['total_rooms'] ?></td>

                                <td><?= (int)$hall['total_capacity'] ?></td>

                                <td><?= (int)$hall['occupied_spaces'] ?></td>

                                <td>
                                    <?= max(
                                        0,
                                        (int)$hall['total_capacity'] -
                                        (int)$hall['occupied_spaces']
                                    ) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p class="empty">No hall records available.</p>

        <?php endif; ?>

    </section>

    
    <section class="section">

        <h2>Recent Active Allocations</h2>

        <?php if ($recentAllocations): ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Hall</th>
                            <th>Room</th>
                            <th>Room Type</th>
                            <th>Allocation Date</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($recentAllocations as $allocation): ?>

                            <tr>

                                <td><?= e($allocation['full_name']) ?></td>

                                <td><?= e($allocation['hall_name']) ?></td>

                                <td><?= e($allocation['room_number']) ?></td>

                                <td><?= e($allocation['room_type']) ?></td>

                                <td><?= e($allocation['allocation_date']) ?></td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p class="empty">No active allocations found.</p>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
