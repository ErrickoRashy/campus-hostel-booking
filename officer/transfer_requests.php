<?php
require_once __DIR__ . '/../config/auth_check.php';
requireRole('officer');

require_once __DIR__ . '/../config/database.php';

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$statusFilter = $_GET['status'] ?? 'all';

$allowedStatuses = ['all', 'pending', 'approved', 'rejected'];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

$sql = "
    SELECT
        tr.transfer_id,
        tr.reason,
        tr.status,
        tr.request_date,
        u.full_name,
        u.email,
        old_h.hall_name AS current_hall,
        old_r.room_number AS current_room,
        new_h.hall_name AS requested_hall
    FROM transfer_requests tr
    JOIN users u ON tr.student_id = u.user_id
    JOIN allocations a ON tr.current_allocation_id = a.allocation_id
    JOIN rooms old_r ON a.room_id = old_r.room_id
    JOIN halls old_h ON old_r.hall_id = old_h.hall_id
    JOIN halls new_h ON tr.requested_hall_id = new_h.hall_id
";

$params = [];

if ($statusFilter !== 'all') {
    $sql .= " WHERE tr.status = :status";
    $params['status'] = $statusFilter;
}

$sql .= " ORDER BY tr.request_date DESC";

$query = $pdo->prepare($sql);
$query->execute($params);
$requests = $query->fetchAll(PDO::FETCH_ASSOC);

$counts = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        COUNT(*) FILTER (WHERE status = 'pending') AS pending,
        COUNT(*) FILTER (WHERE status = 'approved') AS approved,
        COUNT(*) FILTER (WHERE status = 'rejected') AS rejected
     FROM transfer_requests"
)->fetch(PDO::FETCH_ASSOC);

function statusClass($status): string
{
    return match ($status) {
        'pending' => 'pending',
        'approved' => 'approved',
        'rejected' => 'rejected',
        default => 'neutral'
    };
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Transfer Requests | Campus Hostel</title>

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
            max-width: 1300px;
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
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat {
            background: #111827;
            padding: 22px;
            border: 1px solid #263449;
            border-radius: 12px;
        }

        .stat h3 {
            color: #9ca3af;
            font-size: 13px;
            font-weight: normal;
            margin: 0 0 13px;
        }

        .stat p {
            color: #60a5fa;
            font-size: 28px;
            font-weight: bold;
            margin: 0;
        }

        .section {
            background: #111827;
            padding: 25px;
            border: 1px solid #263449;
            border-radius: 12px;
        }

        .section h2 {
            margin: 0 0 22px;
            font-size: 20px;
        }

        .filter {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 22px;
        }

        .filter label {
            color: #d1d5db;
            font-size: 14px;
        }

        select {
            min-width: 170px;
            padding: 11px 13px;
            background: #030712;
            color: #ffffff;
            border: 1px solid #374151;
            border-radius: 7px;
            outline: none;
        }

        select:focus {
            border-color: #3b82f6;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #263449;
            border-radius: 9px;
        }

        table {
            width: 100%;
            min-width: 1150px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 15px 14px;
            text-align: left;
            border-bottom: 1px solid #263449;
            font-size: 13px;
            vertical-align: middle;
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

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: capitalize;
        }

        .pending {
            color: #fcd34d;
            background: rgba(245, 158, 11, 0.12);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .approved {
            color: #86efac;
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .rejected {
            color: #fca5a5;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .neutral {
            color: #d1d5db;
            background: #374151;
        }

        .review {
            display: inline-block;
            padding: 9px 14px;
            background: #2563eb;
            color: #ffffff;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .review:hover {
            background: #1d4ed8;
            color: #ffffff;
        }

        .reviewed {
            color: #9ca3af;
            font-size: 12px;
        }

        .empty {
            color: #9ca3af;
            background: #0b1220;
            border: 1px dashed #374151;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
        }

        .record-count {
            color: #9ca3af;
            font-size: 13px;
            margin: 0 0 14px;
        }

        @media (max-width: 850px) {
            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
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
                font-size: 25px;
            }

            .section {
                padding: 16px;
            }

            .filter {
                align-items: stretch;
                flex-direction: column;
            }

            select {
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
            .filter,
            .review {
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

            .intro,
            .stat h3,
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

    <h1>Student Transfer Requests</h1>

    <p class="intro">
        View and review requests from students who want to change hostel halls.
    </p>

    <section class="stats">

        <div class="stat">
            <h3>Total Requests</h3>
            <p><?= (int)$counts['total'] ?></p>
        </div>

        <div class="stat">
            <h3>Pending Requests</h3>
            <p><?= (int)$counts['pending'] ?></p>
        </div>

        <div class="stat">
            <h3>Approved Requests</h3>
            <p><?= (int)$counts['approved'] ?></p>
        </div>

        <div class="stat">
            <h3>Rejected Requests</h3>
            <p><?= (int)$counts['rejected'] ?></p>
        </div>

    </section>

    <section class="section">

        <h2>Transfer Request Records</h2>

        <form method="GET" class="filter">

            <label for="status">Filter requests by status:</label>

            <select name="status" id="status" onchange="this.form.submit()">

                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>
                    All Requests
                </option>

                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>
                    Pending
                </option>

                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>
                    Approved
                </option>

                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>
                    Rejected
                </option>

            </select>

        </form>

        <p class="record-count">
            <?= count($requests) ?> request(s) displayed
        </p>

        <?php if (!empty($requests)): ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Email</th>
                            <th>Current Hall</th>
                            <th>Current Room</th>
                            <th>Requested Hall</th>
                            <th>Reason</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($requests as $request): ?>

                            <tr>

                                <td><?= (int)$request['transfer_id'] ?></td>

                                <td><?= e($request['full_name']) ?></td>

                                <td><?= e($request['email']) ?></td>

                                <td><?= e($request['current_hall']) ?></td>

                                <td><?= e($request['current_room']) ?></td>

                                <td><?= e($request['requested_hall']) ?></td>

                                <td><?= e($request['reason']) ?></td>

                                <td>
                                    <?= e(
                                        $request['request_date']
                                            ? date('d M Y', strtotime($request['request_date']))
                                            : 'N/A'
                                    ) ?>
                                </td>

                                <td>
                                    <span class="status <?= e(statusClass($request['status'])) ?>">
                                        <?= e($request['status']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($request['status'] === 'pending'): ?>

                                        <a
                                            class="review"
                                            href="review_transfer.php?id=<?= (int)$request['transfer_id'] ?>"
                                        >
                                            Review Request
                                        </a>

                                    <?php else: ?>

                                        <span class="reviewed">Reviewed</span>

                                    <?php endif; ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty">
                <?php if ($statusFilter !== 'all'): ?>
                    No <?= e($statusFilter) ?> transfer requests found.
                <?php else: ?>
                    No transfer requests have been submitted yet.
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
