<?php
require_once __DIR__ . '/../config/auth_check.php';
requireRole('student');

require_once __DIR__ . '/../config/database.php';

$studentId = $_SESSION['user_id'];

$query = $pdo->prepare(
    "SELECT
        a.application_id,
        a.academic_year,
        a.semester,
        a.status,
        a.application_date,
        h.hall_name
     FROM applications a
     LEFT JOIN halls h ON a.preferred_hall_id = h.hall_id
     WHERE a.student_id = :student_id
     ORDER BY a.application_date DESC"
);

$query->execute(['student_id' => $studentId]);
$applications = $query->fetchAll();

function statusColor(string $status): string
{
    return match (strtolower($status)) {
        'pending' => '#f59e0b',
        'approved' => '#16a34a',
        'rejected' => '#ef4444',
        'allocated' => '#2563eb',
        default => '#64748b'
    };
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Applications | Campus Hostel</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            min-height: 100vh;
            background: #000000;
            color: #ffffff;
        }

        /* HEADER */

        header {
            width: 100%;
            min-height: 85px;
            padding: 15px 7%;
            background: #111827;
            border-bottom: 1px solid #263449;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .brand img {
            width: 53px;
            height: 53px;
            object-fit: contain;
            border-radius: 10px;
        }

        .brand h2 {
            font-size: 21px;
            color: #ffffff;
        }

        .brand span {
            color: #60a5fa;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 17px;
            border: 1px solid #3b82f6;
            border-radius: 8px;
            color: #93c5fd;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            transition: 0.3s;
        }

        .back-link:hover {
            background: #2563eb;
            color: white;
        }

        /* MAIN CONTAINER */

        .container {
            width: 88%;
            max-width: 1150px;
            margin: 55px auto;
        }

        .page-heading {
            margin-bottom: 35px;
        }

        .page-heading .eyebrow {
            display: inline-block;
            color: #60a5fa;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .page-heading h1 {
            font-size: 34px;
            margin-bottom: 12px;
            color: #ffffff;
        }

        .page-heading p {
            color: #aab6c8;
            font-size: 15px;
            line-height: 1.7;
        }

        /* APPLICATION SUMMARY */

        .summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 20px 24px;
            margin-bottom: 25px;
            background: #111827;
            border: 1px solid #263449;
            border-radius: 12px;
        }

        .summary-label {
            color: #aab6c8;
            font-size: 14px;
        }

        .summary-number {
            color: #60a5fa;
            font-size: 27px;
            font-weight: bold;
            margin-top: 5px;
        }

        .summary-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #1e3a5f;
            border-radius: 12px;
            color: #93c5fd;
            font-size: 23px;
        }

        /* APPLICATION CARD */

        .application-card {
            background: #111827;
            border: 1px solid #263449;
            border-radius: 14px;
            padding: 27px;
            margin-bottom: 22px;
            transition: 0.3s;
        }

        .application-card:hover {
            border-color: #3b82f6;
            transform: translateY(-3px);
        }

        .card-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 18px;
            border-bottom: 1px solid #263449;
        }

        .card-heading h3 {
            color: #ffffff;
            font-size: 19px;
        }

        .application-number {
            color: #60a5fa;
        }

        .status {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: bold;
            text-transform: capitalize;
        }

        /* DETAILS GRID */

        .details {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 25px;
        }

        .detail {
            min-width: 0;
        }

        .detail-label {
            display: block;
            color: #8fa0b5;
            font-size: 12px;
            margin-bottom: 9px;
            text-transform: uppercase;
            letter-spacing: 0.7px;
        }

        .detail-value {
            display: block;
            color: #ffffff;
            font-size: 15px;
            font-weight: bold;
            overflow-wrap: anywhere;
        }

        /* EMPTY STATE */

        .empty {
            text-align: center;
            padding: 65px 25px;
            background: #111827;
            border: 1px solid #263449;
            border-radius: 14px;
        }

        .empty-icon {
            width: 75px;
            height: 75px;
            margin: 0 auto 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #1e3a5f;
            border-radius: 50%;
            color: #93c5fd;
            font-size: 32px;
        }

        .empty h3 {
            color: #ffffff;
            font-size: 21px;
            margin-bottom: 12px;
        }

        .empty p {
            color: #aab6c8;
            line-height: 1.7;
            font-size: 14px;
        }

        .dashboard-button {
            display: inline-block;
            margin-top: 23px;
            padding: 13px 23px;
            background: #2563eb;
            color: #ffffff;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            transition: 0.3s;
        }

        .dashboard-button:hover {
            background: #1d4ed8;
        }

        /* FOOTER */

        footer {
            padding: 25px 15px;
            margin-top: 60px;
            border-top: 1px solid #263449;
            text-align: center;
            color: #77869a;
            font-size: 13px;
        }

        /* MOBILE RESPONSIVENESS */

        @media (max-width: 700px) {

            header {
                padding: 14px 5%;
            }

            .brand img {
                width: 43px;
                height: 43px;
            }

            .brand h2 {
                font-size: 17px;
            }

            .back-link {
                padding: 10px 11px;
                font-size: 12px;
            }

            .container {
                width: 90%;
                margin: 35px auto;
            }

            .page-heading h1 {
                font-size: 27px;
            }

            .details {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 23px 15px;
            }

            .application-card {
                padding: 20px;
            }

            .card-heading h3 {
                font-size: 16px;
            }
        }

        @media (max-width: 420px) {

            .brand h2 {
                font-size: 14px;
            }

            .brand {
                gap: 8px;
            }

            .back-link {
                font-size: 11px;
                padding: 9px;
            }

            .details {
                grid-template-columns: 1fr 1fr;
            }

            .detail-value {
                font-size: 13px;
            }
        }
    </style>
</head>

<body>

<header>

    <div class="brand">
        <img src="../campus-hostel-logo.png" alt="Campus Hostel Logo">

        <h2>Campus <span>Hostel</span></h2>
    </div>

    <a href="dashboard.php" class="back-link">
        &#8592; Dashboard
    </a>

</header>

<main class="container">

    <div class="page-heading">

        <span class="eyebrow">Student Portal</span>

        <h1>My Applications</h1>

        <p>
            Keep track of your accommodation applications,
            view their details and monitor their progress.
        </p>

    </div>

    <div class="summary">

        <div>
            <div class="summary-label">Total Applications Submitted</div>

            <div class="summary-number">
                <?= count($applications) ?>
            </div>
        </div>

        <div class="summary-icon">
            &#9776;
        </div>

    </div>

    <?php if (empty($applications)): ?>

        <div class="empty">

            <div class="empty-icon">
                &#128203;
            </div>

            <h3>No Applications Found</h3>

            <p>
                You have not submitted any accommodation applications yet.
                Visit your student dashboard to get started.
            </p>

            <a href="dashboard.php" class="dashboard-button">
                Return to Dashboard
            </a>

        </div>

    <?php else: ?>

        <?php foreach ($applications as $application): ?>

            <section class="application-card">

                <div class="card-heading">

                    <h3>
                        Application
                        <span class="application-number">
                            #<?= (int)$application['application_id'] ?>
                        </span>
                    </h3>

                    <span
                        class="status"
                        style="
                            color: <?= statusColor($application['status']) ?>;
                            background: <?= statusColor($application['status']) ?>20;
                        "
                    >
                        <?= htmlspecialchars($application['status']) ?>
                    </span>

                </div>

                <div class="details">

                    <div class="detail">

                        <span class="detail-label">
                            Academic Year
                        </span>

                        <span class="detail-value">
                            <?= htmlspecialchars($application['academic_year']) ?>
                        </span>

                    </div>

                    <div class="detail">

                        <span class="detail-label">
                            Semester
                        </span>

                        <span class="detail-value">
                            <?= htmlspecialchars($application['semester']) ?>
                        </span>

                    </div>

                    <div class="detail">

                        <span class="detail-label">
                            Preferred Hostel
                        </span>

                        <span class="detail-value">
                            <?= htmlspecialchars($application['hall_name'] ?? 'No preference') ?>
                        </span>

                    </div>

                    <div class="detail">

                        <span class="detail-label">
                            Submission Date
                        </span>

                        <span class="detail-value">
                            <?= htmlspecialchars(date(
                                'd M Y',
                                strtotime($application['application_date'])
                            )) ?>
                        </span>

                    </div>

                    <div class="detail">

                        <span class="detail-label">
                            Application Status
                        </span>

                        <span class="detail-value">
                            <?= htmlspecialchars(ucfirst($application['status'])) ?>
                        </span>

                    </div>

                </div>

            </section>

        <?php endforeach; ?>

    <?php endif; ?>

</main>

<footer>
    &copy; <?= date('Y') ?> Campus Hostel Booking System.
    All rights reserved.
</footer>

</body>
</html>
