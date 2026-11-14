<?php
require_once __DIR__ . '/../config/auth_check.php';
requireRole('officer');

require_once __DIR__ . '/../config/database.php';

$transferId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$transferId) {
    header("Location: transfer_requests.php");
    exit;
}

$success = '';
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
                "SELECT * FROM transfer_requests
                 WHERE transfer_id = :id
                 FOR UPDATE"
            );

            $query->execute(['id' => $transferId]);
            $transfer = $query->fetch(PDO::FETCH_ASSOC);

            if (!$transfer) {
                throw new Exception("Transfer request not found.");
            }

            if ($transfer['status'] !== 'pending') {
                throw new Exception("This request has already been reviewed.");
            }

            if ($action === 'approve') {
                
                $allocationQuery = $pdo->prepare(
                    "SELECT * FROM allocations
                     WHERE allocation_id = :allocation_id
                     AND student_id = :student_id
                     AND status = 'active'
                     FOR UPDATE"
                );

                $allocationQuery->execute([
                    'allocation_id' => $transfer['current_allocation_id'],
                    'student_id' => $transfer['student_id']
                ]);

                $allocation = $allocationQuery->fetch(PDO::FETCH_ASSOC);

                if (!$allocation) {
                    throw new Exception(
                        "The student's current active allocation could not be found."
                    );
                }

                
                $roomsQuery = $pdo->prepare(
                    "SELECT r.room_id, r.capacity,
                            COUNT(a.allocation_id) AS occupied_spaces
                     FROM rooms r
                     LEFT JOIN allocations a
                        ON r.room_id = a.room_id
                        AND a.status = 'active'
                     WHERE r.hall_id = :hall_id
                     AND r.status = 'available'
                     GROUP BY r.room_id, r.capacity
                     HAVING COUNT(a.allocation_id) < r.capacity
                     ORDER BY r.room_number
                     FOR UPDATE OF r"
                );

                $roomsQuery->execute([
                    'hall_id' => $transfer['requested_hall_id']
                ]);

                $availableRoom = $roomsQuery->fetch(PDO::FETCH_ASSOC);

                if (!$availableRoom) {
                    throw new Exception(
                        "The requested hall has no available room spaces."
                    );
                }

                
                $endOldAllocation = $pdo->prepare(
                    "UPDATE allocations
                     SET status = 'completed',
                         end_date = CURRENT_DATE
                     WHERE allocation_id = :id"
                );

                $endOldAllocation->execute([
                    'id' => $allocation['allocation_id']
                ]);

            
                $newAllocation = $pdo->prepare(
                    "INSERT INTO allocations
                     (student_id, room_id, allocation_date, start_date, status)
                     VALUES
                     (:student_id, :room_id, CURRENT_DATE, CURRENT_DATE, 'active')"
                );

                $newAllocation->execute([
                    'student_id' => $transfer['student_id'],
                    'room_id' => $availableRoom['room_id']
                ]);
            }

            
            $updateRequest = $pdo->prepare(
                "UPDATE transfer_requests
                 SET status = :status,
                     reviewed_at = CURRENT_TIMESTAMP,
                     reviewed_by = :officer_id
                 WHERE transfer_id = :id"
            );

            $updateRequest->execute([
                'status' => $action === 'approve' ? 'approved' : 'rejected',
                'officer_id' => $_SESSION['user_id'],
                'id' => $transferId
            ]);

            $pdo->commit();

            header("Location: transfer_requests.php");
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
    "SELECT tr.*, u.full_name, u.email,
            h.hall_name AS requested_hall,
            old_h.hall_name AS current_hall,
            old_r.room_number AS current_room
     FROM transfer_requests tr
     JOIN users u ON tr.student_id = u.user_id
     JOIN halls h ON tr.requested_hall_id = h.hall_id
     JOIN allocations a ON tr.current_allocation_id = a.allocation_id
     JOIN rooms old_r ON a.room_id = old_r.room_id
     JOIN halls old_h ON old_r.hall_id = old_h.hall_id
     WHERE tr.transfer_id = :id"
);

$query->execute(['id' => $transferId]);
$request = $query->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    header("Location: transfer_requests.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Review Transfer | Campus Hostel</title>

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
            color: #fde68a;
        }

        .approved {
            background: #064e3b;
            color: #a7f3d0;
        }

        .rejected {
            background: #7f1d1d;
            color: #fecaca;
        }

        .neutral {
            background: #334155;
            color: #e2e8f0;
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

    <a href="transfer_requests.php" class="back">
        &larr; Back to Transfer Requests
    </a>

    <section class="card">

        <h1>Review Transfer Request</h1>

        <p class="subtitle">
            Review the student's current accommodation and requested hall
            before making your decision.
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
            <strong>Current Hall</strong>
            <span><?= e($request['current_hall']) ?></span>
        </div>

        <div class="detail">
            <strong>Current Room</strong>
            <span><?= e($request['current_room']) ?></span>
        </div>

        <div class="detail">
            <strong>Requested Hall</strong>
            <span><?= e($request['requested_hall']) ?></span>
        </div>

        <h3 class="section-title">Request Information</h3>

        <div class="detail">
            <strong>Reason for Transfer</strong>
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
                  onsubmit="return confirm('Are you sure you want to submit this transfer decision?');">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= e($_SESSION['csrf_token']) ?>">

                <div class="actions">

                    <button
                        type="submit"
                        name="action"
                        value="approve"
                        class="approve">
                        Approve Transfer
                    </button>

                    <button
                        type="submit"
                        name="action"
                        value="reject"
                        class="reject">
                        Reject Transfer
                    </button>

                </div>

            </form>

        <?php else: ?>

            <div class="reviewed">
                This transfer request has already been reviewed.
            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
