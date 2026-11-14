<?php
require_once __DIR__ . '/../config/auth_check.php';
requireRole('officer');

require_once __DIR__ . '/../config/database.php';

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        u.user_id,
        u.full_name,
        u.email,
        u.phone,
        u.created_at,
        r.room_number,
        r.room_type,
        h.hall_name,
        al.status AS allocation_status
    FROM users u
    LEFT JOIN allocations al
        ON al.student_id = u.user_id
        AND al.status = 'active'
    LEFT JOIN rooms r ON al.room_id = r.room_id
    LEFT JOIN halls h ON r.hall_id = h.hall_id
    WHERE u.role = 'student'
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            u.full_name ILIKE :search
            OR u.email ILIKE :search
            OR u.phone ILIKE :search
        )
    ";

    $params['search'] = '%' . $search . '%';
}

$sql .= " ORDER BY u.full_name ASC";

$query = $pdo->prepare($sql);
$query->execute($params);
$students = $query->fetchAll(PDO::FETCH_ASSOC);

$totalStudents = (int)$pdo->query(
    "SELECT COUNT(*) FROM users WHERE role = 'student'"
)->fetchColumn();

$allocatedStudents = (int)$pdo->query(
    "SELECT COUNT(DISTINCT al.student_id)
     FROM allocations al
     INNER JOIN users u ON u.user_id = al.student_id
     WHERE al.status = 'active'
       AND u.role = 'student'"
)->fetchColumn();

$unallocatedStudents = max(0, $totalStudents - $allocatedStudents);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Students | Campus Hostel</title>

    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: #000000;
            color: #ffffff;
        }

        header {
            background: #111827;
            border-bottom: 1px solid #263449;
            padding: 14px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand img {
            width: 48px;
            height: 48px;
            object-fit: contain;
            border-radius: 9px;
        }

        .brand h2 {
            margin: 0;
            font-size: 19px;
            color: #ffffff;
        }

        .brand span {
            display: block;
            margin-top: 4px;
            color: #9ca3af;
            font-size: 12px;
        }

        a {
            color: #60a5fa;
            text-decoration: none;
        }

        a:hover {
            color: #93c5fd;
        }

        .logout {
            background: #1f2937;
            color: #ffffff;
            border: 1px solid #374151;
            padding: 10px 16px;
            border-radius: 7px;
            white-space: nowrap;
        }

        .logout:hover {
            background: #374151;
            color: #ffffff;
        }

        .container {
            width: 92%;
            max-width: 1250px;
            margin: 35px auto;
        }

        .back {
            display: inline-block;
            margin-bottom: 22px;
            font-size: 14px;
        }

        h1 {
            margin: 0 0 10px;
            font-size: 30px;
        }

        .intro {
            color: #9ca3af;
            margin: 0 0 28px;
            line-height: 1.6;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat {
            background: #111827;
            border: 1px solid #263449;
            border-radius: 12px;
            padding: 22px;
        }

        .stat h3 {
            color: #9ca3af;
            font-size: 14px;
            font-weight: normal;
            margin: 0 0 13px;
        }

        .stat p {
            color: #60a5fa;
            font-size: 30px;
            font-weight: bold;
            margin: 0;
        }

        .section {
            background: #111827;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #263449;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .section-heading h2 {
            margin: 0;
            font-size: 20px;
        }

        .section-heading span {
            color: #9ca3af;
            font-size: 13px;
        }

        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 22px;
        }

        .search-form input {
            flex: 1;
            min-width: 0;
            padding: 13px 15px;
            background: #030712;
            border: 1px solid #374151;
            color: #ffffff;
            border-radius: 7px;
            outline: none;
            font-size: 14px;
        }

        .search-form input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        button {
            padding: 12px 20px;
            background: #2563eb;
            color: #ffffff;
            border: none;
            border-radius: 7px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .clear-search {
            display: inline-flex;
            align-items: center;
            padding: 0 12px;
            color: #9ca3af;
            font-size: 13px;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #263449;
            border-radius: 9px;
        }

        table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 15px 14px;
            text-align: left;
            border-bottom: 1px solid #263449;
            white-space: nowrap;
            font-size: 13px;
        }

        th {
            background: #0b1220;
            color: #93c5fd;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        td {
            color: #e5e7eb;
        }

        tbody tr:hover {
            background: #172033;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .allocated,
        .not-allocated {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        .allocated {
            color: #86efac;
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .not-allocated {
            color: #fcd34d;
            background: rgba(245, 158, 11, 0.12);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .empty {
            color: #9ca3af;
            background: #0b1220;
            border: 1px dashed #374151;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
        }

        .result-count {
            color: #9ca3af;
            font-size: 13px;
            margin: 0 0 14px;
        }

        @media (max-width: 700px) {
            header {
                padding: 12px 4%;
            }

            .brand img {
                width: 42px;
                height: 42px;
            }

            .brand h2 {
                font-size: 15px;
            }

            .container {
                width: 94%;
                margin: 25px auto;
            }

            h1 {
                font-size: 25px;
            }

            .stats {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .stat {
                padding: 18px;
            }

            .stat p {
                font-size: 26px;
            }

            .section {
                padding: 16px;
            }

            .section-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .search-form {
                flex-direction: column;
            }

            .search-form button {
                width: 100%;
            }

            .clear-search {
                justify-content: center;
                padding: 8px;
            }
        }

        @media print {
            body {
                background: #ffffff;
                color: #000000;
            }

            header,
            .back,
            .search-form {
                display: none;
            }

            .container {
                width: 100%;
                margin: 0;
            }

            .section,
            .stat {
                background: #ffffff;
                color: #000000;
                border: 1px solid #cccccc;
            }

            .stat h3,
            .intro,
            td {
                color: #000000;
            }

            .table-wrapper {
                overflow: visible;
            }

            table {
                min-width: 0;
            }

            th,
            td {
                white-space: normal;
                font-size: 9px;
                padding: 7px;
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
            <span>Accommodation Officer</span>
        </div>
    </div>

    <a href="../auth/logout.php" class="logout">Logout</a>
</header>

<main class="container">

    <a href="dashboard.php" class="back">← Back to Dashboard</a>

    <h1>Registered Students</h1>

    <p class="intro">
        View student registration records and their current accommodation details.
    </p>

    <section class="stats">

        <div class="stat">
            <h3>Total Registered Students</h3>
            <p><?= $totalStudents ?></p>
        </div>

        <div class="stat">
            <h3>Allocated Students</h3>
            <p><?= $allocatedStudents ?></p>
        </div>

        <div class="stat">
            <h3>Students Without Allocation</h3>
            <p><?= $unallocatedStudents ?></p>
        </div>

    </section>

    <section class="section">

        <div class="section-heading">
            <h2>Student Records</h2>
            <span><?= count($students) ?> record(s) displayed</span>
        </div>

        <form method="GET" class="search-form">

            <input
                type="search"
                name="search"
                placeholder="Search by name, email or phone..."
                value="<?= e($search) ?>"
            >

            <button type="submit">Search Students</button>

            <?php if ($search !== ''): ?>
                <a href="students.php" class="clear-search">Clear search</a>
            <?php endif; ?>

        </form>

        <?php if (!empty($students)): ?>

            <div class="table-wrapper">

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Hall</th>
                            <th>Room</th>
                            <th>Room Type</th>
                            <th>Accommodation</th>
                            <th>Registered</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($students as $student): ?>

                            <tr>
                                <td><?= (int)$student['user_id'] ?></td>

                                <td><?= e($student['full_name']) ?></td>

                                <td><?= e($student['email']) ?></td>

                                <td><?= e($student['phone'] ?: 'Not provided') ?></td>

                                <td><?= e($student['hall_name'] ?: 'Not allocated') ?></td>

                                <td><?= e($student['room_number'] ?: 'N/A') ?></td>

                                <td><?= e($student['room_type'] ?: 'N/A') ?></td>

                                <td>
                                    <?php if ($student['allocation_status'] === 'active'): ?>
                                        <span class="allocated">Allocated</span>
                                    <?php else: ?>
                                        <span class="not-allocated">Not Allocated</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= e(
                                        $student['created_at']
                                            ? date('d M Y', strtotime($student['created_at']))
                                            : 'N/A'
                                    ) ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>
                </table>

            </div>

        <?php else: ?>

            <div class="empty">
                <?php if ($search !== ''): ?>
                    No students matched your search. Try another name, email or phone number.
                <?php else: ?>
                    No registered students found.
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
