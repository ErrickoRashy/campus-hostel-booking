<?php
require_once __DIR__ . '/../config/auth_check.php';
requireRole('officer');

require_once __DIR__ . '/../config/database.php';

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$message = '';
$messageType = '';


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? '';

    $applicationId = filter_input(
        INPUT_POST,
        'application_id',
        FILTER_VALIDATE_INT
    );

    $action = $_POST['action'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        $message = 'Invalid or expired form submission. Please refresh the page.';
        $messageType = 'error';

    } elseif (
        $applicationId &&
        in_array($action, ['approved', 'rejected'], true)
    ) {

        $update = $pdo->prepare(
            "UPDATE applications
             SET status = :status
             WHERE application_id = :application_id
             AND status = 'pending'"
        );

        $update->execute([
            'status' => $action,
            'application_id' => $applicationId
        ]);

        if ($update->rowCount() > 0) {
            $message = "Application successfully " . $action . ".";
            $messageType = 'success';

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } else {
            $message = 'Application was not found or has already been reviewed.';
            $messageType = 'error';
        }

    } else {
        $message = 'Invalid application action.';
        $messageType = 'error';
    }
}


$applications = $pdo->query(
    "SELECT a.application_id, a.academic_year, a.semester,
            a.status, a.application_date,
            u.full_name, u.email, u.phone,
            h.hall_name
     FROM applications a
     JOIN users u ON a.student_id = u.user_id
     LEFT JOIN halls h ON a.preferred_hall_id = h.hall_id
     ORDER BY
        CASE WHEN a.status = 'pending' THEN 0 ELSE 1 END,
        a.application_date DESC"
)->fetchAll();


$totalApplications = count($applications);
$pendingCount = 0;
$approvedCount = 0;
$allocatedCount = 0;
$rejectedCount = 0;

foreach ($applications as $application) {
    switch ($application['status']) {
        case 'pending':
            $pendingCount++;
            break;
        case 'approved':
            $approvedCount++;
            break;
        case 'allocated':
            $allocatedCount++;
            break;
        case 'rejected':
            $rejectedCount++;
            break;
    }
}

function applicationStatusColor(string $status): string
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

    <title>Student Applications | Campus Hostel</title>

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
        }

        .brand p {
            margin: 5px 0 0;
            font-size: 12px;
            color: #9ca3af;
        }

        .logout {
            color: #ffffff;
            background: #1f2937;
            border: 1px solid #374151;
            padding: 10px 16px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 13px;
        }

        .logout:hover {
            background: #374151;
        }

        .container {
            width: 94%;
            max-width: 1400px;
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
            font-size: 27px;
            margin: 0 0 10px;
        }

        .page-heading p {
            color: #9ca3af;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: #111827;
            border: 1px solid #263449;
            padding: 20px;
            border-radius: 11px;
        }

        .stat-card span {
            display: block;
            color: #9ca3af;
            font-size: 13px;
            margin-bottom: 12px;
        }

        .stat-card h2 {
            color: #60a5fa;
            font-size: 27px;
            margin: 0;
        }

        .section {
            background: #111827;
            padding: 24px;
            border: 1px solid #263449;
            border-radius: 13px;
        }

        .section h2 {
            font-size: 19px;
            margin: 0 0 20px;
            color: #60a5fa;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .success {
            background: #052e16;
            border: 1px solid #166534;
            color: #bbf7d0;
        }

        .error {
            background: #451a1a;
            border: 1px solid #7f1d1d;
            color: #fecaca;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border-radius: 8px;
        }

        table {
            width: 100%;
            min-width: 1100px;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px 13px;
            text-align: left;
            border-bottom: 1px solid #263449;
            font-size: 13px;
        }

        th {
            color: #93c5fd;
            background: #0b1220;
            font-size: 12px;
            white-space: nowrap;
        }

        td {
            color: #d1d5db;
        }

        tbody tr:hover {
            background: #172033;
        }

        .status {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
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

        .actions {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .actions form {
            margin: 0;
        }

        button {
            border: none;
            padding: 9px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 12px;
            transition: 0.2s;
        }

        .approve {
            background: #2563eb;
            color: #ffffff;
        }

        .approve:hover {
            background: #1d4ed8;
        }

        .reject {
            background: #991b1b;
            color: #ffffff;
        }

        .reject:hover {
            background: #7f1d1d;
        }

        .reviewed {
            color: #9ca3af;
            font-size: 12px;
        }

        .empty {
            padding: 25px;
            text-align: center;
            color: #9ca3af;
            background: #0b1220;
            border: 1px dashed #374151;
            border-radius: 8px;
        }

        @media (max-width: 850px) {
            .stats {
                grid-template-columns: repeat(2, 1fr);
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
                gap: 10px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-card h2 {
                font-size: 23px;
            }

            .section {
                padding: 15px;
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
        <h1>Student Applications</h1>

        <p>
            Review, approve or reject student accommodation applications.
        </p>
    </div>

    <div class="stats">

        <div class="stat-card">
            <span>Total Applications</span>
            <h2><?= $totalApplications ?></h2>
        </div>

        <div class="stat-card">
            <span>Pending Applications</span>
            <h2><?= $pendingCount ?></h2>
        </div>

        <div class="stat-card">
            <span>Approved Applications</span>
            <h2><?= $approvedCount ?></h2>
        </div>

        <div class="stat-card">
            <span>Allocated Applications</span>
            <h2><?= $allocatedCount ?></h2>
        </div>

    </div>

    <section class="section">

        <h2>All Student Applications</h2>

        <?php if ($message !== ''): ?>
            <div class="alert <?= e($messageType) ?>">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($applications): ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Academic Year</th>
                            <th>Semester</th>
                            <th>Preferred Hall</th>
                            <th>Application Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($applications as $application): ?>

                            <tr>

                                <td><?= e($application['full_name']) ?></td>

                                <td><?= e($application['email']) ?></td>

                                <td><?= e($application['phone'] ?? 'Not provided') ?></td>

                                <td><?= e($application['academic_year']) ?></td>

                                <td><?= e($application['semester']) ?></td>

                                <td><?= e($application['hall_name'] ?? 'No preference') ?></td>

                                <td><?= e($application['application_date']) ?></td>

                                <td>
                                    <span class="status <?= e(applicationStatusColor($application['status'])) ?>">
                                        <?= e(ucfirst($application['status'])) ?>
                                    </span>
                                </td>

                                <td>

                                    <?php if ($application['status'] === 'pending'): ?>

                                        <div class="actions">

                                            <form method="POST">
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e($_SESSION['csrf_token']) ?>">

                                                <input
                                                    type="hidden"
                                                    name="application_id"
                                                    value="<?= (int)$application['application_id'] ?>">

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="approved">

                                                <button class="approve" type="submit">
                                                    Approve
                                                </button>
                                            </form>

                                            <form method="POST">
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e($_SESSION['csrf_token']) ?>">

                                                <input
                                                    type="hidden"
                                                    name="application_id"
                                                    value="<?= (int)$application['application_id'] ?>">

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="rejected">

                                                <button class="reject" type="submit">
                                                    Reject
                                                </button>
                                            </form>

                                        </div>

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
                No student applications found.
            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
