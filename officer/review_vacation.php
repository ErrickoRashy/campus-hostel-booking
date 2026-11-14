<?php
require_once __DIR__ . '/../config/auth_check.php';
requireRole('officer');

require_once __DIR__ . '/../config/database.php';

$vacationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$vacationId) {
    header("Location: vacation_requests.php");
    exit;
}

$error = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function statusClass($status): string
{
    return match (strtolower((string) $status)) {
        'pending' => 'pending',
        'approved' => 'approved',
        'rejected' => 'rejected',
        default => 'neutral'
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = "Your session token is invalid. Please refresh the page.";
    } elseif (!in_array($action, ['approve', 'reject'], true)) {
        $error = "Invalid review action.";
    } else {

        try {
            $pdo->beginTransaction();

            $query = $pdo->prepare(
                "SELECT * FROM vacation_requests
                 WHERE vacation_id = :id
                 FOR UPDATE"
            );

            $query->execute(['id' => $vacationId]);
            $vacation = $query->fetch(PDO::FETCH_ASSOC);

            if (!$vacation) {
                throw new Exception("Vacation request not found.");
            }

            if ($vacation['status'] !== 'pending') {
                throw new Exception("This request has already been reviewed.");
            }

            
            $allocationQuery = $pdo->prepare(
                "SELECT allocation_id
                 FROM allocations
                 WHERE allocation_id = :allocation_id
                 AND student_id = :student_id
                 FOR UPDATE"
            );

            $allocationQuery->execute([
                'allocation_id' => $vacation['allocation_id'],
                'student_id' => $vacation['student_id']
            ]);

            if (!$allocationQuery->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception(
                    "The related allocation could not be found."
                );
            }

        
            $update = $pdo->prepare(
                "UPDATE vacation_requests
                 SET status = :status,
                     reviewed_at = CURRENT_TIMESTAMP,
                     reviewed_by = :officer_id
                 WHERE vacation_id = :id"
            );

            $update->execute([
                'status' => $action === 'approve' ? 'approved' : 'rejected',
                'officer_id' => $_SESSION['user_id'],
                'id' => $vacationId
            ]);

            $pdo->commit();

            header("Location: vacation_requests.php");
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();
        }
    }
}


$query = $pdo->prepare(
    "SELECT vr.*, u.full_name, u.email,
            h.hall_name, r.room_number
     FROM vacation_requests vr
     JOIN users u ON vr.student_id = u.user_id
     JOIN allocations a ON vr.allocation_id = a.allocation_id
     JOIN rooms r ON a.room_id = r.room_id
     JOIN halls h ON r.hall_id = h.hall_id
     WHERE vr.vacation_id = :id"
);

$query->execute(['id' => $vacationId]);
$request = $query->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    header("Location: vacation_requests.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Review Vacation | Campus Hostel</title>

    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
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
            width: 48px;
            height: 48px;
            object-fit: contain;
            border-radius: 10px;
        }

        .brand h2 {
            font-size: 19px;
            margin: 0;
            color: #ffffff;
        }

        a {
            color: #60a5fa;
            text-decoration: none;
        }

        a:hover {
            color: #93c5fd;
        }

        .logout {
            background: #1e293b;
            border: 1px solid #334155;
            padding: 10px 16px;
            border-radius: 7px;
            color: #ffffff;
        }

        .container {
            width: 92%;
            max-width: 800px;
            margin: 35px auto;
        }

        .back {
            display: inline-block;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .card {
            background: #111827;
            padding: 30px;
            border: 1px solid #263449;
            border-radius: 13px;
        }

        h1 {
            margin-top: 0;
            margin-bottom: 8px;
            font-size: 27px;
        }

        .subtitle {
            color: #94a3b8;
            margin-top: 0;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .section-title {
            font-size: 16px;
            color: #60a5fa;
            margin: 25px 0 10px;
        }

        .detail {
            padding: 15px 0;
            border-bottom: 1px solid #263449;
        }

        .detail strong {
            display: block;
            color: #94a3b8;
            font-size: 13px;
            margin-bottom: 7px;
        }

        .detail span {
            color: #ffffff;
            font-size: 15px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .status {
            display: inline-block;
            padding: 7px 13px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .pending {
            background: #78350f;
            color: #fde68a !important;
        }

        .approved {
            background: #064e3b;
            color: #a7f3d0 !important;
        }

        .rejected {
            background: #7f1d1d;
            color: #fecaca !important;
        }

        .neutral {
            background: #334155;
            color: #e2e8f0 !important;
        }

        .notice {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .error {
            background: #451a1a;
            border: 1px solid #991b1b;
            color: #fecaca;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 28px;
            flex-wrap: wrap;
        }

        button {
            border: none;
            padding: 13px 22px;
            border-radius: 7px;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
        }

        button:focus-visible,
        a:focus-visible {
            outline: 2px solid #60a5fa;
            outline-offset: 3px;
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
            background: #1e293b;
            border: 1px solid #334155;
            color: #cbd5e1;
            padding: 15px;
            border-radius: 8px;
            margin-top: 25px;
        }

        @media (max-width: 550px) {
            header {
                padding: 12px 4%;
            }

            .brand img {
                width: 40px;
                height: 40px;
            }

            .brand h2 {
                font-size: 15px;
            }

            .container {
                width: 94%;
                margin: 22px auto;
            }

            .card {
                padding: 20px;
            }

            h1 {
                font-size: 23px;
            }

            .actions {
                flex-direction: column;
            }

            .actions button {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<header>

    <div class="brand">
        <img src="../campus-hostel-logo.png" alt="Campus Hostel Logo">
        <h2>Campus Hostel | Officer</h2>
    </div>

    <a href="../auth/logout.php" class="logout">Logout</a>

</header>

<main class="container">

    <a href="vacation_requests.php" class="back">
        &larr; Back to Vacation Requests
    </a>

    <section class="card">

        <h1>Review Vacation Request</h1>

        <p class="subtitle">
            Review the student's vacation details before making your decision.
        </p>

        <?php if ($error): ?>
            <div class="notice error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <h3 class="section-title">Student Information</h3>

        <div class="detail">
            <strong>Student Name</strong>
            <span><?= e($request['full_name']) ?></span>
        </div>

        <div class="detail">
            <strong>Email Address</strong>
            <span><?= e($request['email']) ?></span>
        </div>

        <h3 class="section-title">Accommodation Details</h3>

        <div class="detail">
            <strong>Allocated Hall</strong>
            <span><?= e($request['hall_name']) ?></span>
        </div>

        <div class="detail">
            <strong>Room Number</strong>
            <span><?= e($request['room_number']) ?></span>
        </div>

        <h3 class="section-title">Vacation Information</h3>

        <div class="detail">
            <strong>Departure Date</strong>
            <span><?= e($request['start_date']) ?></span>
        </div>

        <div class="detail">
            <strong>Expected Return Date</strong>
            <span><?= e($request['return_date']) ?></span>
        </div>

        <div class="detail">
            <strong>Reason for Vacation</strong>
            <span><?= nl2br(e($request['reason'])) ?></span>
        </div>

        <div class="detail">
            <strong>Request Date</strong>
            <span><?= e($request['request_date']) ?></span>
        </div>

        <div class="detail">
            <strong>Current Status</strong>
            <span>
                <span class="status <?= e(statusClass($request['status'])) ?>">
                    <?= e(ucfirst($request['status'])) ?>
                </span>
            </span>
        </div>

        <?php if ($request['status'] === 'pending'): ?>

            <form method="POST"
                  onsubmit="return confirm('Are you sure you want to submit this vacation decision?');">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <div class="actions">

                    <button
                        type="submit"
                        name="action"
                        value="approve"
                        class="approve">
                        Approve Vacation
                    </button>

                    <button
                        type="submit"
                        name="action"
                        value="reject"
                        class="reject">
                        Reject Vacation
                    </button>

                </div>

            </form>

        <?php else: ?>

            <div class="reviewed">
                This vacation request has already been reviewed.
            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
