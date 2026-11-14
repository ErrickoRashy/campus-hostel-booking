<?php
require_once __DIR__ . '/../config/auth_check.php';
requireRole('caretaker');

require_once __DIR__ . '/../config/database.php';


$totalRooms = $pdo->query(
    "SELECT COUNT(*) FROM rooms"
)->fetchColumn();


$availableRooms = $pdo->query(
    "SELECT COUNT(*)
     FROM rooms r
     WHERE r.status = 'available'
     AND (
         SELECT COUNT(*)
         FROM allocations a
         WHERE a.room_id = r.room_id
         AND a.status = 'active'
     ) < r.capacity"
)->fetchColumn();


$occupiedRooms = $pdo->query(
    "SELECT COUNT(DISTINCT room_id)
     FROM allocations
     WHERE status = 'active'"
)->fetchColumn();


$maintenanceRooms = $pdo->query(
    "SELECT COUNT(*)
     FROM rooms
     WHERE status = 'maintenance'"
)->fetchColumn();


$activeAllocations = $pdo->query(
    "SELECT COUNT(*)
     FROM allocations
     WHERE status = 'active'"
)->fetchColumn();


$recentAllocations = $pdo->query(
    "SELECT u.full_name, h.hall_name,
            r.room_number, r.room_type,
            a.allocation_date
     FROM allocations a
     JOIN users u ON a.student_id = u.user_id
     JOIN rooms r ON a.room_id = r.room_id
     JOIN halls h ON r.hall_id = h.hall_id
     WHERE a.status = 'active'
     ORDER BY a.allocation_date DESC
     LIMIT 8"
)->fetchAll();


$pendingVacations = $pdo->query(
    "SELECT COUNT(*)
     FROM vacation_requests
     WHERE status = 'pending'"
)->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Caretaker Dashboard | Campus Hostel</title>

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

        .header-right {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
            color: #d1d5db;
            font-size: 14px;
        }

        a {
            text-decoration: none;
        }

        .logout {
            color: #60a5fa;
            font-weight: bold;
        }

        .logout:hover {
            color: #93c5fd;
        }

        .container {
            width: 92%;
            max-width: 1250px;
            margin: 35px auto;
        }

        .welcome {
            margin-bottom: 30px;
        }

        .welcome h1 {
            font-size: 29px;
            margin-bottom: 10px;
        }

        .welcome p {
            color: #9ca3af;
            line-height: 1.6;
        }

        .welcome span {
            color: #60a5fa;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }

        .stat {
            background: #111827;
            padding: 23px;
            border: 1px solid #263449;
            border-radius: 12px;
            transition: 0.2s;
        }

        .stat:hover {
            border-color: #2563eb;
            transform: translateY(-3px);
        }

        .stat-label {
            color: #9ca3af;
            font-size: 14px;
            margin-bottom: 13px;
        }

        .stat-value {
            color: #60a5fa;
            font-size: 32px;
            font-weight: bold;
        }

        .section {
            background: #111827;
            padding: 26px;
            border: 1px solid #263449;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .section-heading {
            margin-bottom: 22px;
        }

        .section-heading h2 {
            font-size: 20px;
            margin-bottom: 7px;
        }

        .section-heading p {
            color: #9ca3af;
            font-size: 14px;
        }

        .links {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .link-card {
            display: block;
            background: #0b1220;
            padding: 24px;
            border-radius: 10px;
            color: white;
            border: 1px solid #263449;
            transition: 0.2s;
        }

        .link-card:hover {
            border-color: #3b82f6;
            background: #172554;
            transform: translateY(-3px);
        }

        .card-icon {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #1e3a8a;
            color: #bfdbfe;
            border-radius: 10px;
            font-size: 22px;
            margin-bottom: 17px;
        }

        .link-card h3 {
            color: #60a5fa;
            margin-bottom: 10px;
            font-size: 17px;
        }

        .link-card p {
            color: #9ca3af;
            font-size: 14px;
            line-height: 1.6;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 650px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #263449;
        }

        th {
            color: #60a5fa;
            font-size: 13px;
            background: #0b1220;
        }

        td {
            color: #e5e7eb;
            font-size: 14px;
        }

        tbody tr:hover {
            background: #172033;
        }

        .room-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            background: #1e3a8a;
            color: #bfdbfe;
            font-size: 12px;
        }

        .empty {
            color: #9ca3af;
            padding: 15px 0;
        }

        footer {
            text-align: center;
            padding: 25px;
            color: #6b7280;
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

            .header-right {
                width: 100%;
                justify-content: space-between;
                font-size: 13px;
            }

            .container {
                margin: 25px auto;
            }

            .welcome h1 {
                font-size: 23px;
            }

            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 11px;
            }

            .stat {
                padding: 17px 13px;
            }

            .stat-value {
                font-size: 26px;
            }

            .stat-label {
                font-size: 12px;
            }

            .section {
                padding: 19px 15px;
            }

            .links {
                grid-template-columns: 1fr;
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

    <div class="header-right">

        <span>
            Welcome,
            <?= htmlspecialchars($_SESSION['full_name'] ?? 'Caretaker') ?>
        </span>

        <a href="../auth/logout.php" class="logout">Logout ↗</a>

    </div>

</header>

<main class="container">

    <section class="welcome">

        <h1>Caretaker Dashboard</h1>

        <p>
            Welcome to your hostel management workspace.
            Monitor room occupancy, inspect accommodation conditions
            and manage maintenance activities.
        </p>

    </section>

    <section class="stats">

        <div class="stat">
            <div class="stat-label">Total Rooms</div>
            <div class="stat-value"><?= (int)$totalRooms ?></div>
        </div>

        <div class="stat">
            <div class="stat-label">Available Room Spaces</div>
            <div class="stat-value"><?= (int)$availableRooms ?></div>
        </div>

        <div class="stat">
            <div class="stat-label">Occupied Rooms</div>
            <div class="stat-value"><?= (int)$occupiedRooms ?></div>
        </div>

        <div class="stat">
            <div class="stat-label">Maintenance Rooms</div>
            <div class="stat-value"><?= (int)$maintenanceRooms ?></div>
        </div>

        <div class="stat">
            <div class="stat-label">Active Allocations</div>
            <div class="stat-value"><?= (int)$activeAllocations ?></div>
        </div>

        <div class="stat">
            <div class="stat-label">Pending Vacation Requests</div>
            <div class="stat-value"><?= (int)$pendingVacations ?></div>
        </div>

    </section>

    <section class="section">

        <div class="section-heading">
            <h2>Hostel Management</h2>
            <p>Access your caretaker responsibilities.</p>
        </div>

        <div class="links">

            <a href="room_conditions.php" class="link-card">

                <div class="card-icon">⌂</div>

                <h3>Room Conditions</h3>

                <p>
                    Inspect hostel rooms, record room conditions
                    and keep accommodation facilities in good order.
                </p>

            </a>

            <a href="damage_records.php" class="link-card">

                <div class="card-icon">⚒</div>

                <h3>Damage Records</h3>

                <p>
                    View reported hostel damages and manage
                    maintenance and repair records.
                </p>

            </a>

        </div>

    </section>

    <section class="section">

        <div class="section-heading">
            <h2>Recent Active Allocations</h2>
            <p>Recently allocated hostel rooms and their students.</p>
        </div>

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

                                <td>
                                    <?= htmlspecialchars($allocation['full_name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($allocation['hall_name']) ?>
                                </td>

                                <td>
                                    <span class="room-badge">
                                        <?= htmlspecialchars($allocation['room_number']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars($allocation['room_type']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($allocation['allocation_date']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p class="empty">
                No active room allocations are currently available.
            </p>

        <?php endif; ?>

    </section>

</main>

<footer>
    © <?= date('Y') ?> Campus Hostel Booking System. All rights reserved.
</footer>

</body>
</html>
