<?php
require_once __DIR__ . '/../config/auth_check.php';
requireRole('student');

require_once __DIR__ . '/../config/database.php';

$studentId = $_SESSION['user_id'];

$query = $pdo->prepare(
    "SELECT
        al.allocation_id,
        al.allocation_date,
        al.start_date,
        al.end_date,
        al.status,
        r.room_number,
        r.room_type,
        r.capacity,
        r.floor_number,
        h.hall_name
     FROM allocations al
     INNER JOIN rooms r ON al.room_id = r.room_id
     INNER JOIN halls h ON r.hall_id = h.hall_id
     WHERE al.student_id = :student_id
     AND al.status = 'active'
     ORDER BY al.allocation_date DESC
     LIMIT 1"
);

$query->execute(['student_id' => $studentId]);
$allocation = $query->fetch();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Allocation | Campus Hostel</title>

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

        
        header {
            min-height: 85px;
            padding: 15px 7%;
            background: #111827;
            border-bottom: 1px solid #263449;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            color: #ffffff;
        }

       
        .container {
            width: 88%;
            max-width: 1000px;
            margin: 50px auto;
        }

        .page-heading {
            margin-bottom: 32px;
        }

        .eyebrow {
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
        }

        .page-heading p {
            color: #aab6c8;
            line-height: 1.7;
            font-size: 15px;
        }

        
        .room-highlight {
            background: linear-gradient(120deg, #172b45, #111827);
            border: 1px solid #31527c;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .room-highlight small {
            display: block;
            color: #93c5fd;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-size: 12px;
            margin-bottom: 12px;
        }

        .room-highlight h2 {
            font-size: 31px;
            margin-bottom: 9px;
        }

        .room-highlight p {
            color: #b8c9dc;
            font-size: 14px;
        }

        .room-icon {
            width: 75px;
            height: 75px;
            flex-shrink: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #1e3a5f;
            border-radius: 17px;
            color: #93c5fd;
            font-size: 35px;
        }

        /* DETAILS CARD */

        .allocation-card {
            background: #111827;
            border: 1px solid #263449;
            border-radius: 14px;
            padding: 30px;
        }

        .card-heading {
            padding-bottom: 20px;
            margin-bottom: 25px;
            border-bottom: 1px solid #263449;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .card-heading h3 {
            font-size: 19px;
        }

        .status {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 30px;
            background: #123d2b;
            color: #4ade80;
            font-size: 12px;
            font-weight: bold;
            text-transform: capitalize;
        }

        /* DETAILS GRID */

        .details {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 28px 22px;
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
            background: #111827;
            border: 1px solid #263449;
            border-radius: 14px;
            padding: 60px 25px;
            text-align: center;
        }

        .empty-icon {
            width: 78px;
            height: 78px;
            margin: 0 auto 22px;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #1e3a5f;
            border-radius: 50%;
            color: #93c5fd;
            font-size: 35px;
        }

        .empty h3 {
            font-size: 21px;
            margin-bottom: 12px;
        }

        .empty p {
            color: #aab6c8;
            font-size: 14px;
            line-height: 1.8;
            max-width: 520px;
            margin: auto;
        }

        .button {
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

        .button:hover {
            background: #1d4ed8;
        }

        /* FOOTER */

        footer {
            margin-top: 60px;
            padding: 25px 15px;
            border-top: 1px solid #263449;
            color: #77869a;
            text-align: center;
            font-size: 13px;
        }

        /* RESPONSIVE DESIGN */

        @media (max-width: 700px) {

            header {
                padding: 14px 5%;
            }

            .brand {
                gap: 8px;
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

            .room-highlight {
                padding: 22px;
            }

            .room-highlight h2 {
                font-size: 24px;
            }

            .room-icon {
                width: 60px;
                height: 60px;
                font-size: 28px;
            }

            .allocation-card {
                padding: 22px;
            }

            .details {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 24px 15px;
            }
        }

        @media (max-width: 420px) {

            .brand h2 {
                font-size: 14px;
            }

            .back-link {
                font-size: 11px;
                padding: 9px;
            }

            .room-highlight {
                padding: 18px;
            }

            .room-highlight h2 {
                font-size: 21px;
            }

            .room-icon {
                width: 48px;
                height: 48px;
                font-size: 23px;
            }

            .allocation-card {
                padding: 17px;
            }

            .details {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 22px 12px;
            }

            .detail-value {
                font-size: 13px;
            }

            .card-heading h3 {
                font-size: 16px;
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

        <h1>My Room Allocation</h1>

        <p>
            View your assigned hostel, room information
            and accommodation period.
        </p>

    </div>

    <?php if (!$allocation): ?>

        <div class="empty">

            <div class="empty-icon">
                &#127968;
            </div>

            <h3>No Active Room Allocation</h3>

            <p>
                You currently do not have an active hostel room allocation.
                Submit an accommodation application or contact the accommodation officer
                for assistance.
            </p>

            <a href="application.php" class="button">
                View My Applications
            </a>

        </div>

    <?php else: ?>

        <div class="room-highlight">

            <div>

                <small>Your Assigned Room</small>

                <h2>
                    <?= htmlspecialchars($allocation['room_number']) ?>
                </h2>

                <p>
                    <?= htmlspecialchars($allocation['hall_name']) ?>
                </p>

            </div>

            <div class="room-icon">
                &#127968;
            </div>

        </div>

        <section class="allocation-card">

            <div class="card-heading">

                <h3>Accommodation Details</h3>

                <span class="status">
                    <?= htmlspecialchars($allocation['status']) ?>
                </span>

            </div>

            <div class="details">

                <div class="detail">

                    <span class="detail-label">Hostel</span>

                    <span class="detail-value">
                        <?= htmlspecialchars($allocation['hall_name']) ?>
                    </span>

                </div>

                <div class="detail">

                    <span class="detail-label">Room Number</span>

                    <span class="detail-value">
                        <?= htmlspecialchars($allocation['room_number']) ?>
                    </span>

                </div>

                <div class="detail">

                    <span class="detail-label">Room Type</span>

                    <span class="detail-value">
                        <?= htmlspecialchars($allocation['room_type']) ?>
                    </span>

                </div>

                <div class="detail">

                    <span class="detail-label">Room Capacity</span>

                    <span class="detail-value">
                        <?= (int)$allocation['capacity'] ?> Students
                    </span>

                </div>

                <div class="detail">

                    <span class="detail-label">Floor Number</span>

                    <span class="detail-value">
                        <?= (int)$allocation['floor_number'] ?>
                    </span>

                </div>

                <div class="detail">

                    <span class="detail-label">Allocation Date</span>

                    <span class="detail-value">
                        <?= htmlspecialchars(date(
                            'd M Y',
                            strtotime($allocation['allocation_date'])
                        )) ?>
                    </span>

                </div>

                <div class="detail">

                    <span class="detail-label">Start Date</span>

                    <span class="detail-value">
                        <?= $allocation['start_date']
                            ? htmlspecialchars(date('d M Y', strtotime($allocation['start_date'])))
                            : 'Not specified' ?>
                    </span>

                </div>

                <div class="detail">

                    <span class="detail-label">End Date</span>

                    <span class="detail-value">
                        <?= $allocation['end_date']
                            ? htmlspecialchars(date('d M Y', strtotime($allocation['end_date'])))
                            : 'Not specified' ?>
                    </span>

                </div>

            </div>

        </section>

    <?php endif; ?>

</main>

<footer>
    &copy; <?= date('Y') ?> Campus Hostel Booking System.
    All rights reserved.
</footer>

</body>
</html>
