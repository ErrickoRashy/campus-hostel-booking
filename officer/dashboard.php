<?php
require_once __DIR__ . '/../config/auth_check.php';
requireRole('officer');

require_once __DIR__ . '/../config/database.php';

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Dashboard statistics
$totalStudents = $pdo->query(
    "SELECT COUNT(*) FROM users WHERE role = 'student'"
)->fetchColumn();

$totalApplications = $pdo->query(
    "SELECT COUNT(*) FROM applications"
)->fetchColumn();

$pendingApplications = $pdo->query(
    "SELECT COUNT(*) FROM applications WHERE status = 'pending'"
)->fetchColumn();

$approvedApplications = $pdo->query(
    "SELECT COUNT(*) FROM applications WHERE status = 'approved'"
)->fetchColumn();

$totalRooms = $pdo->query(
    "SELECT COUNT(*) FROM rooms"
)->fetchColumn();

// Count rooms that have available capacity.
$availableRooms = $pdo->query(
    "SELECT COUNT(*) FROM rooms r
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

$pendingTransfers = $pdo->query(
    "SELECT COUNT(*) FROM transfer_requests WHERE status = 'pending'"
)->fetchColumn();

$pendingVacations = $pdo->query(
    "SELECT COUNT(*) FROM vacation_requests WHERE status = 'pending'"
)->fetchColumn();

// Recent applications
$recentQuery = $pdo->query(
    "SELECT a.application_id, u.full_name,
            a.academic_year, a.semester,
            a.status, a.application_date
     FROM applications a
     JOIN users u ON a.student_id = u.user_id
     ORDER BY a.application_date DESC
     LIMIT 5"
);

$recentApplications = $recentQuery->fetchAll();

function statusColor(string $status): string
{
    return match ($status) {
        'pending' => 'pending',
        'approved' => 'approved',
        'allocated' => 'allocated',
        'rejected' => 'rejected',
        default => 'other'
    };
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Officer Dashboard | Campus Hostel</title>

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
            margin: 0;
            font-size: 19px;
            color: #ffffff;
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

        .welcome {
            margin-bottom: 28px;
        }

        .welcome h1 {
            font-size: 27px;
            margin: 0 0 10px;
        }

        .welcome p {
            color: #9ca3af;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        .welcome strong {
            color: #60a5fa;
        }

        .section-title {
            font-size: 19px;
            color: #60a5fa;
            margin: 0 0 20px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 17px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #111827;
            padding: 21px;
            border-radius: 11px;
            border: 1px solid #263449;
            transition: 0.2s;
        }

        .stat-card:hover {
            border-color: #3b82f6;
            transform: translateY(-2px);
        }

        .stat-card h3 {
            color: #9ca3af;
            font-size: 13px;
            font-weight: normal;
            margin: 0 0 13px;
        }

        .stat-card p {
            color: #60a5fa;
            font-size: 29px;
            font-weight: bold;
            margin: 0;
        }

        .section {
            background: #111827;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid #263449;
        }

        .menu {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
        }

        .menu a {
            display: block;
            background: #0b1220;
            border: 1px solid #263449;
            color: #d1d5db;
            padding: 19px;
            border-radius: 9px;
            text-decoration: none;
            font-size: 13px;
            line-height: 1.6;
            transition: 0.2s;
            min-width: 0;
        }

        .menu a:hover {
            background: #172033;
            border-color: #3b82f6;
            transform: translateY(-2px);
        }

        .menu a span {
            display: block;
            color: #60a5fa;
            font-size: 15px;
            font-weight: bold;
            margin-bottom: 8px;
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

        th, td {
            text-align: left;
            padding: 14px;
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

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        .pending {
            background: #422006;
            color: #fcd34d;
        }

        .approved {
            background: #164e63;
            color: #bae6fd;
        }

        .allocated {
            background: #1e3a8a;
            color: #bfdbfe;
        }

        .rejected {
            background: #450a0a;
            color: #fecaca;
        }

        .other {
            background: #374151;
            color: #e5e7eb;
        }

        .empty {
            color: #9ca3af;
            padding: 15px 0;
            font-size: 14px;
        }

        @media (max-width: 850px) {
            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .menu {
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

            .welcome h1 {
                font-size: 23px;
            }

            .stats {
                gap: 10px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-card h3 {
                font-size: 12px;
            }

            .stat-card p {
                font-size: 24px;
            }

            .section {
                padding: 17px;
            }

            .menu {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .menu a {
                padding: 14px;
                font-size: 12px;
            }

            .menu a span {
                font-size: 13px;
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

    <div class="welcome">
        <h1>
            Welcome, <?= e($_SESSION['full_name'] ?? 'Officer') ?>!
        </h1>

        <p>
            Manage hostel applications, students, rooms and accommodation requests.
        </p>
    </div>

    <section class="stats">

        <div class="stat-card">
            <h3>Total Students</h3>
            <p><?= (int)$totalStudents ?></p>
        </div>

        <div class="stat-card">
            <h3>Total Applications</h3>
            <p><?= (int)$totalApplications ?></p>
        </div>

        <div class="stat-card">
            <h3>Pending Applications</h3>
            <p><?= (int)$pendingApplications ?></p>
        </div>

        <div class="stat-card">
            <h3>Approved Applications</h3>
            <p><?= (int)$approvedApplications ?></p>
        </div>

        <div class="stat-card">
            <h3>Total Rooms</h3>
            <p><?= (int)$totalRooms ?></p>
        </div>

        <div class="stat-card">
            <h3>Rooms With Available Spaces</h3>
            <p><?= (int)$availableRooms ?></p>
        </div>

        <div class="stat-card">
            <h3>Occupied Rooms</h3>
            <p><?= (int)$occupiedRooms ?></p>
        </div>

        <div class="stat-card">
            <h3>Pending Transfers</h3>
            <p><?= (int)$pendingTransfers ?></p>
        </div>

        <div class="stat-card">
            <h3>Pending Vacation Requests</h3>
            <p><?= (int)$pendingVacations ?></p>
        </div>

    </section>

    <section class="section">

        <h2 class="section-title">Management Menu</h2>

        <div class="menu">

            <a href="applications.php">
                <span>Student Applications</span>
                Review, approve or reject student accommodation applications.
            </a>

            <a href="allocation_requests.php">
                <span>Allocation Requests</span>
                Review approved applications and assign available rooms.
            </a>

            <a href="allocation_requests.php">
                <span>Allocate Room</span>
                Select an approved student and assign a suitable hostel room.
            </a>

            <a href="rooms.php">
                <span>Rooms and Halls</span>
                View and manage hostel rooms and halls.
            </a>

            <a href="students.php">
                <span>Students</span>
                View registered students and their details.
            </a>

            <a href="reports.php">
                <span>Reports</span>
                View hostel accommodation and allocation reports.
            </a>

            <a href="transfer_requests.php">
                <span>Transfer Requests</span>
                Review and manage student hostel transfer requests.
            </a>

            <a href="vacation_requests.php">
                <span>Vacation Requests</span>
                Review student vacation requests.
            </a>

        </div>

    </section>

    <section class="section">

        <h2 class="section-title">Recent Applications</h2>

        <?php if ($recentApplications): ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Academic Year</th>
                            <th>Semester</th>
                            <th>Date Submitted</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($recentApplications as $application): ?>

                            <tr>

                                <td><?= e($application['full_name']) ?></td>

                                <td><?= e($application['academic_year']) ?></td>

                                <td><?= e($application['semester']) ?></td>

                                <td><?= e($application['application_date']) ?></td>

                                <td>
                                    <span class="status <?= e(statusColor($application['status'])) ?>">
                                        <?= e(ucfirst($application['status'])) ?>
                                    </span>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p class="empty">
                No applications have been submitted yet.
            </p>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
