<?php
require_once __DIR__ . '/../config/auth_check.php';
requireRole('officer');

require_once __DIR__ . '/../config/database.php';

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$applicationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$applicationId || $applicationId < 1) {
    header('Location: applications.php');
    exit;
}


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = 'error';

// Retrieve application details
$query = $pdo->prepare(
    "SELECT a.*, u.full_name, u.email, u.phone,
            h.hall_name
     FROM applications a
     JOIN users u ON a.student_id = u.user_id
     LEFT JOIN halls h ON a.preferred_hall_id = h.hall_id
     WHERE a.application_id = :id"
);

$query->execute(['id' => $applicationId]);
$application = $query->fetch();

if (!$application) {
    http_response_code(404);
    exit('Application not found.');
}

// Process review action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfToken = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION['csrf_token'], $csrfToken)
    ) {
        $message = 'Security verification failed. Please refresh and try again.';

    } elseif (!in_array($action, ['approved', 'rejected'], true)) {

        $message = 'Invalid application review action.';

    } elseif ($application['status'] !== 'pending') {

        $message = 'This application has already been reviewed.';

    } else {

        $update = $pdo->prepare(
            "UPDATE applications
             SET status = :status
             WHERE application_id = :id
             AND status = 'pending'"
        );

        $update->execute([
            'status' => $action,
            'id' => $applicationId
        ]);

        if ($update->rowCount() > 0) {

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: applications.php');
            exit;

        } else {

            $message = 'This application has already been reviewed.';
        }
    }

    
    $query->execute(['id' => $applicationId]);
    $application = $query->fetch();
}

function statusClass(string $status): string
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

    <title>Review Application | Campus Hostel</title>

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
        }

        .logout:hover {
            background: #374151;
        }

        .container {
            width: 94%;
            max-width: 800px;
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
            margin-bottom: 25px;
        }

        .page-heading h1 {
            font-size: 27px;
            margin: 0 0 9px;
        }

        .page-heading p {
            color: #9ca3af;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        .card {
            background: #111827;
            padding: 28px;
            border-radius: 12px;
            border: 1px solid #263449;
        }

        .card h2 {
            color: #60a5fa;
            font-size: 18px;
            margin: 0 0 20px;
        }

        .detail {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            padding: 16px 0;
            border-bottom: 1px solid #263449;
        }

        .detail:last-of-type {
            border-bottom: none;
        }

        .detail strong {
            color: #9ca3af;
            font-size: 13px;
            min-width: 150px;
        }

        .detail span {
            color: #ffffff;
            font-size: 14px;
            text-align: right;
            overflow-wrap: anywhere;
        }

        .status {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        .pending {
            background: #422006;
            color: #fcd34d !important;
        }

        .approved {
            background: #164e63;
            color: #bae6fd !important;
        }

        .allocated {
            background: #1e3a8a;
            color: #bfdbfe !important;
        }

        .rejected {
            background: #450a0a;
            color: #fecaca !important;
        }

        .other {
            background: #374151;
            color: #e5e7eb !important;
        }

        .message {
            padding: 13px 15px;
            margin-bottom: 20px;
            border-radius: 7px;
            background: #450a0a;
            border: 1px solid #7f1d1d;
            color: #fecaca;
            font-size: 13px;
            line-height: 1.5;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        button {
            padding: 13px 20px;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
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
            margin-top: 25px;
            padding: 14px;
            background: #0b1220;
            border: 1px solid #263449;
            border-radius: 7px;
            color: #9ca3af;
            font-size: 13px;
            line-height: 1.5;
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

            .card {
                padding: 18px;
            }

            .detail {
                flex-direction: column;
                gap: 7px;
            }

            .detail strong {
                min-width: auto;
            }

            .detail span {
                text-align: left;
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

        <div>
            <h2>Campus Hostel</h2>
            <p>Accommodation Officer Portal</p>
        </div>

    </div>

    <a href="../auth/logout.php" class="logout">Logout</a>

</header>

<main class="container">

    <a href="applications.php" class="back">
        ← Back to Applications
    </a>

    <div class="page-heading">

        <h1>Review Student Application</h1>

        <p>
            Review the student's information and make an accommodation decision.
        </p>

    </div>

    <section class="card">

        <h2>Application Details</h2>

        <?php if ($message !== ''): ?>

            <div class="message">
                <?= e($message) ?>
            </div>

        <?php endif; ?>

        <div class="detail">

            <strong>Student Name</strong>

            <span><?= e($application['full_name']) ?></span>

        </div>

        <div class="detail">

            <strong>Email Address</strong>

            <span><?= e($application['email']) ?></span>

        </div>

        <div class="detail">

            <strong>Phone Number</strong>

            <span><?= e($application['phone'] ?? 'Not provided') ?></span>

        </div>

        <div class="detail">

            <strong>Academic Year</strong>

            <span><?= e($application['academic_year']) ?></span>

        </div>

        <div class="detail">

            <strong>Semester</strong>

            <span><?= e($application['semester']) ?></span>

        </div>

        <div class="detail">

            <strong>Preferred Hall</strong>

            <span><?= e($application['hall_name'] ?? 'No preference') ?></span>

        </div>

        <div class="detail">

            <strong>Application Date</strong>

            <span><?= e($application['application_date']) ?></span>

        </div>

        <div class="detail">

            <strong>Current Status</strong>

            <span>
                <span class="status <?= e(statusClass($application['status'])) ?>">
                    <?= e(ucfirst($application['status'])) ?>
                </span>
            </span>

        </div>

        <?php if ($application['status'] === 'pending'): ?>

            <form method="POST" class="actions">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <button
                    type="submit"
                    name="action"
                    value="approved"
                    class="approve"
                    onclick="return confirm('Are you sure you want to approve this application?');"
                >
                    Approve Application
                </button>

                <button
                    type="submit"
                    name="action"
                    value="rejected"
                    class="reject"
                    onclick="return confirm('Are you sure you want to reject this application?');"
                >
                    Reject Application
                </button>

            </form>

        <?php else: ?>

            <div class="reviewed">
                This application has already been reviewed. No further action is available.
            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
