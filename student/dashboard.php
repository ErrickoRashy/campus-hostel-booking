<?php
require_once __DIR__ . '/../config/auth_check.php';
requireRole('student');

require_once __DIR__ . '/../config/database.php';

$studentId = $_SESSION['user_id'];
$studentName = $_SESSION['full_name'] ?? 'Student';


$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM applications WHERE student_id = :id"
);
$stmt->execute(['id' => $studentId]);
$totalApplications = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM applications
     WHERE student_id = :id AND status = 'pending'"
);
$stmt->execute(['id' => $studentId]);
$pendingApplications = (int) $stmt->fetchColumn();


$stmt = $pdo->prepare(
    "SELECT status, application_date
     FROM applications
     WHERE student_id = :id
     ORDER BY application_date DESC, application_id DESC
     LIMIT 1"
);
$stmt->execute(['id' => $studentId]);
$latestApplication = $stmt->fetch(PDO::FETCH_ASSOC);


$stmt = $pdo->prepare(
    "SELECT r.room_number, r.room_type, r.floor_number,
            h.hall_name, a.start_date, a.end_date
     FROM allocations a
     JOIN rooms r ON a.room_id = r.room_id
     JOIN halls h ON r.hall_id = h.hall_id
     WHERE a.student_id = :id AND a.status = 'active'
     LIMIT 1"
);
$stmt->execute(['id' => $studentId]);
$allocation = $stmt->fetch(PDO::FETCH_ASSOC);

$firstName = explode(' ', trim($studentName))[0];

function escapeStudent($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatStudentDate($date): string {
    if (empty($date)) {
        return 'Not set';
    }

    $timestamp = strtotime($date);

    return $timestamp ? date('d M Y', $timestamp) : 'Not set';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Dashboard | Campus Hostel</title>

    <style>
        :root {
            --navy: #071525;
            --navy-light: #102638;
            --navy-soft: #19364d;
            --green: #70e000;
            --green-dark: #4c9c00;
            --white: #ffffff;
            --background: #f3f6f9;
            --text: #1c2938;
            --muted: #718096;
            --border: #e3eaf0;
            --success-bg: #eaf8df;
            --success-text: #397d00;
            --warning-bg: #fff4d9;
            --warning-text: #946200;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Segoe UI", Arial, sans-serif;
        }

        body {
            background: var(--background);
            color: var(--text);
            min-height: 100vh;
        }

        a {
            text-decoration: none;
        }

        button {
            font: inherit;
        }

        /* MAIN LAYOUT */

        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */

        .sidebar {
            width: 255px;
            background: var(--navy);
            color: white;
            padding: 25px 16px;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            min-height: 100vh;
            position: sticky;
            top: 0;
            height: 100vh;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 0 10px 30px;
            border-bottom: 1px solid #294052;
        }

        .brand-logo {
            width: 43px;
            height: 43px;
            flex-shrink: 0;
        }

        .brand-text strong {
            display: block;
            font-size: 17px;
            color: white;
        }

        .brand-text span {
            color: var(--green);
            font-size: 10px;
            letter-spacing: 1.2px;
        }

        .sidebar-label {
            color: #8fa5b6;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.3px;
            text-transform: uppercase;
            padding: 25px 12px 12px;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 13px 14px;
            color: #c8d4df;
            font-size: 14px;
            border-left: 3px solid transparent;
            transition: .2s;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: #19364d;
            color: var(--green);
            border-left-color: var(--green);
        }

        .nav-icon {
            width: 22px;
            text-align: center;
            font-size: 18px;
        }

        .sidebar-bottom {
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid #294052;
        }

        .sidebar-bottom a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px;
            color: #e2e8f0;
            font-size: 14px;
        }

        .sidebar-bottom a:hover {
            color: var(--green);
        }

        .sidebar-bottom .logout-link {
            color: #ffb5b5;
        }

        /* MAIN CONTENT */

        .main-content {
            flex: 1;
            min-width: 0;
        }

        /* TOP BAR */

        .topbar {
            height: 75px;
            padding: 0 35px;
            background: white;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .topbar h2 {
            font-size: 18px;
            color: var(--navy);
        }

        .topbar p {
            color: var(--muted);
            font-size: 12px;
            margin-top: 3px;
        }

        .profile-info {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .avatar {
            width: 42px;
            height: 42px;
            background: var(--navy);
            color: var(--green);
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: 800;
            font-size: 17px;
        }

        .profile-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--navy);
        }

        .profile-role {
            display: block;
            font-size: 11px;
            color: var(--muted);
            margin-top: 3px;
        }

        .mobile-menu {
            display: none;
            background: var(--navy);
            color: white;
            border: none;
            padding: 9px 12px;
            cursor: pointer;
        }

        /* PAGE BODY */

        .page-content {
            padding: 35px;
            max-width: 1500px;
            margin: auto;
        }

        

        .welcome-banner {
            background:
                linear-gradient(110deg, #071525 0%, #173b53 100%);
            color: white;
            padding: 35px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::after {
            content: "";
            position: absolute;
            width: 230px;
            height: 230px;
            border: 35px solid rgba(112,224,0,.10);
            border-radius: 50%;
            right: -65px;
            top: -90px;
        }

        .welcome-text {
            position: relative;
            z-index: 1;
        }

        .welcome-label {
            color: var(--green);
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 2px;
            font-weight: 700;
            margin-bottom: 12px;
            display: block;
        }

        .welcome-banner h1 {
            font-size: clamp(25px, 3vw, 34px);
            margin-bottom: 10px;
        }

        .welcome-banner p {
            color: #cbd9e5;
            line-height: 1.6;
            font-size: 14px;
        }

        .welcome-action {
            position: relative;
            z-index: 1;
            flex-shrink: 0;
        }

        .welcome-action a {
            display: inline-block;
            background: var(--green);
            color: var(--navy);
            padding: 13px 20px;
            font-weight: 800;
            font-size: 13px;
            transition: .2s;
        }

        .welcome-action a:hover {
            background: white;
        }

        

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 17px;
        }

        .section-heading h2 {
            color: var(--navy);
            font-size: 19px;
        }

        .section-heading p {
            color: var(--muted);
            font-size: 12px;
            margin-top: 4px;
        }


        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border: 1px solid var(--border);
            padding: 23px;
            display: flex;
            align-items: center;
            gap: 17px;
            min-width: 0;
            transition: .2s;
        }

        .stat-card:hover {
            border-color: #a7d77f;
            transform: translateY(-3px);
        }

        .stat-icon {
            width: 53px;
            height: 53px;
            flex-shrink: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 23px;
            background: #eef8e8;
            color: var(--green-dark);
        }

        .stat-card:nth-child(2) .stat-icon {
            background: #fff4d9;
            color: #a66d00;
        }

        .stat-card:nth-child(3) .stat-icon {
            background: #e8f1fa;
            color: #24659a;
        }

        .stat-info p {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 7px;
        }

        .stat-info h3 {
            font-size: 26px;
            color: var(--navy);
            line-height: 1;
        }

        

        .content-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(250px, .85fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .panel {
            background: white;
            border: 1px solid var(--border);
            padding: 24px;
            min-width: 0;
        }

        .panel-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 23px;
        }

        .panel-heading h2 {
            color: var(--navy);
            font-size: 17px;
        }

        .panel-heading a {
            color: var(--green-dark);
            font-size: 12px;
            font-weight: 700;
        }

        /* ROOM ALLOCATION */

        .room-card {
            background: #f6f9fb;
            border: 1px solid var(--border);
            padding: 22px;
        }

        .room-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .room-top h3 {
            color: var(--navy);
            font-size: 23px;
        }

        .status-badge {
            display: inline-block;
            padding: 7px 11px;
            font-size: 11px;
            font-weight: 800;
            background: var(--success-bg);
            color: var(--success-text);
        }

        .room-details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px 15px;
        }

        .detail-item span {
            display: block;
            color: var(--muted);
            font-size: 11px;
            margin-bottom: 6px;
        }

        .detail-item strong {
            color: var(--navy);
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .empty-allocation {
            text-align: center;
            padding: 28px 15px;
            background: #f6f9fb;
            border: 1px dashed #cbd8e2;
        }

        .empty-icon {
            font-size: 36px;
            margin-bottom: 12px;
        }

        .empty-allocation h3 {
            color: var(--navy);
            margin-bottom: 8px;
            font-size: 16px;
        }

        .empty-allocation p {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 17px;
        }

        .small-button {
            display: inline-block;
            background: var(--green);
            color: var(--navy);
            padding: 11px 16px;
            font-size: 12px;
            font-weight: 800;
        }

        

        .application-status {
            display: flex;
            flex-direction: column;
            gap: 17px;
        }

        .status-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
        }

        .status-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .status-row span {
            color: var(--muted);
            font-size: 12px;
        }

        .status-row strong {
            color: var(--navy);
            font-size: 12px;
            text-align: right;
        }

        .status-pill {
            padding: 6px 9px;
            font-size: 10px;
            text-transform: capitalize;
            font-weight: 800;
            display: inline-block;
        }

        .status-pending {
            background: var(--warning-bg);
            color: var(--warning-text);
        }

        .status-approved,
        .status-allocated {
            background: var(--success-bg);
            color: var(--success-text);
        }

        .status-rejected {
            background: #fee9e9;
            color: #b42318;
        }

        .status-none {
            background: #edf1f5;
            color: #536476;
        }

        .application-message {
            background: #f4f8fb;
            padding: 14px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        

        .services-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
        }

        .service-card {
            background: white;
            border: 1px solid var(--border);
            padding: 21px 17px;
            display: flex;
            flex-direction: column;
            min-height: 160px;
            transition: .2s;
        }

        .service-card:hover {
            border-color: var(--green);
            transform: translateY(-4px);
            box-shadow: 0 7px 18px rgba(7,21,37,.07);
        }

        .service-icon {
            width: 43px;
            height: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eef8e8;
            color: var(--green-dark);
            font-size: 21px;
            margin-bottom: 15px;
        }

        .service-card h3 {
            color: var(--navy);
            font-size: 14px;
            margin-bottom: 7px;
        }

        .service-card p {
            color: var(--muted);
            font-size: 11px;
            line-height: 1.5;
            flex: 1;
        }

        .service-arrow {
            color: var(--green-dark);
            font-size: 12px;
            font-weight: 800;
            margin-top: 13px;
        }

      

        .dashboard-footer {
            padding: 22px 35px;
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: 11px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .dashboard-footer strong {
            color: var(--navy);
        }

       

        @media (max-width: 1100px) {
            .services-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 800px) {
            .sidebar {
                position: fixed;
                left: -270px;
                top: 0;
                z-index: 1000;
                width: 255px;
                transition: left .25s ease;
            }

            .sidebar.open {
                left: 0;
            }

            .mobile-menu {
                display: inline-block;
            }

            .topbar {
                padding: 0 20px;
            }

            .page-content {
                padding: 25px 20px;
            }

            .dashboard-footer {
                padding: 20px;
            }
        }

        @media (max-width: 550px) {
            .topbar {
                height: 68px;
            }

            .topbar h2 {
                font-size: 15px;
            }

            .topbar p {
                font-size: 10px;
            }

            .profile-name,
            .profile-role {
                display: none;
            }

            .page-content {
                padding: 18px 14px;
            }

            .welcome-banner {
                padding: 25px 20px;
                align-items: flex-start;
                flex-direction: column;
            }

            .welcome-banner h1 {
                font-size: 25px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .stat-card {
                padding: 18px;
            }

            .panel {
                padding: 17px;
            }

            .room-details {
                grid-template-columns: 1fr 1fr;
                gap: 17px 10px;
            }

            .services-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .service-card {
                padding: 16px 12px;
                min-height: 165px;
            }

            .section-heading h2 {
                font-size: 17px;
            }

            .dashboard-footer {
                padding: 18px 14px;
            }
        }

        @media (max-width: 350px) {
            .services-grid {
                grid-template-columns: 1fr;
            }

            .room-details {
                grid-template-columns: 1fr;
            }
        }

        /* ACCESSIBILITY */

        a:focus-visible,
        button:focus-visible {
            outline: 3px solid #70e000;
            outline-offset: 3px;
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
                animation-duration: .01ms !important;
            }
        }
    </style>
</head>

<body>

<div class="dashboard-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">

        <a href="dashboard.php" class="brand">

            <svg class="brand-logo" viewBox="0 0 100 100"
                 xmlns="http://www.w3.org/2000/svg"
                 role="img" aria-label="Campus Hostel logo">

                <path d="M8 43 L50 8 L92 43"
                      fill="none" stroke="#70e000"
                      stroke-width="8"/>

                <path d="M19 42 V86 H81 V42"
                      fill="none" stroke="#ffffff"
                      stroke-width="7"/>

                <rect x="39" y="54" width="22" height="32"
                      fill="#70e000"/>

                <rect x="26" y="49" width="8" height="8"
                      fill="#ffffff"/>

                <rect x="66" y="49" width="8" height="8"
                      fill="#ffffff"/>

                <path d="M5 91 H95"
                      stroke="#70e000" stroke-width="5"/>

            </svg>

            <div class="brand-text">
                <strong>Campus Hostel</strong>
                <span>STUDENT PORTAL</span>
            </div>

        </a>

        <div class="sidebar-label">Main Menu</div>

        <nav class="sidebar-nav">

            <a href="dashboard.php" class="active">
                <span class="nav-icon">⌂</span>
                Dashboard
            </a>

            <a href="application.php">
                <span class="nav-icon">✎</span>
                Apply for Accommodation
            </a>

            <a href="my_application.php">
                <span class="nav-icon">▤</span>
                My Applications
            </a>

            <a href="my_allocation.php">
                <span class="nav-icon">⌂</span>
                My Allocation
            </a>

            <a href="transfer_request.php">
                <span class="nav-icon">⇄</span>
                Room Transfer
            </a>

            <a href="vacation_request.php">
                <span class="nav-icon">◷</span>
                Vacation Request
            </a>

        </nav>

        <div class="sidebar-label">Account & Support</div>

        <nav class="sidebar-nav">

            <a href="profile.php">
                <span class="nav-icon">♙</span>
                My Profile
            </a>

            <a href="help.php">
                <span class="nav-icon">?</span>
                Help & Support
            </a>

            <a href="chatbot.php">
                <span class="nav-icon">☏</span>
                Hostel Assistant
            </a>

        </nav>

        <div class="sidebar-bottom">

            <a href="../index.php">
                <span class="nav-icon">←</span>
                Back to Homepage
            </a>

            <a href="../auth/logout.php" class="logout-link">
                <span class="nav-icon">⇥</span>
                Logout
            </a>

        </div>

    </aside>


    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- TOP BAR -->
        <header class="topbar">

            <div class="topbar-left">

                <button class="mobile-menu"
                        id="menuButton"
                        aria-label="Open navigation menu"
                        aria-controls="sidebar"
                        aria-expanded="false">
                    ☰
                </button>

                <div>
                    <h2>Student Dashboard</h2>
                    <p>Campus Hostel Management Portal</p>
                </div>

            </div>

            <div class="profile-info">

                <div class="avatar">
                    <?= escapeStudent(strtoupper(substr($firstName, 0, 1))) ?>
                </div>

                <div>
                    <div class="profile-name">
                        <?= escapeStudent($studentName) ?>
                    </div>

                    <span class="profile-role">Student Account</span>
                </div>

            </div>

        </header>


       
        <main class="page-content">

          
            <section class="welcome-banner">

                <div class="welcome-text">

                    <span class="welcome-label">
                        Your Student Portal
                    </span>

                    <h1>
                        Welcome back, <?= escapeStudent($firstName) ?>!
                    </h1>

                    <p>
                        Manage your accommodation, check your application
                        progress and access your student services from here.
                    </p>

                </div>

                <div class="welcome-action">
                    <a href="application.php">
                        + New Application
                    </a>
                </div>

            </section>


            <section>

                <div class="section-heading">
                    <div>
                        <h2>Your Overview</h2>
                        <p>A quick summary of your accommodation activities.</p>
                    </div>
                </div>

                <div class="stats-grid">

                    <div class="stat-card">

                        <div class="stat-icon">▤</div>

                        <div class="stat-info">
                            <p>Total Applications</p>
                            <h3><?= $totalApplications ?></h3>
                        </div>

                    </div>

                    <div class="stat-card">

                        <div class="stat-icon">◷</div>

                        <div class="stat-info">
                            <p>Pending Applications</p>
                            <h3><?= $pendingApplications ?></h3>
                        </div>

                    </div>

                    <div class="stat-card">

                        <div class="stat-icon">⌂</div>

                        <div class="stat-info">
                            <p>Room Allocation</p>
                            <h3 style="font-size:20px;">
                                <?= $allocation ? 'Active' : 'None' ?>
                            </h3>
                        </div>

                    </div>

                </div>

            </section>


       
            <section class="content-grid">

                <!-- ROOM ALLOCATION -->
                <div class="panel">

                    <div class="panel-heading">

                        <h2>My Room Allocation</h2>

                        <a href="my_allocation.php">
                            View Details →
                        </a>

                    </div>

                    <?php if ($allocation): ?>

                        <div class="room-card">

                            <div class="room-top">

                                <div>
                                    <span style="font-size:11px;color:#718096;">
                                        YOUR ALLOCATED ROOM
                                    </span>

                                    <h3>
                                        <?= escapeStudent($allocation['room_number']) ?>
                                    </h3>
                                </div>

                                <span class="status-badge">
                                    ● Active
                                </span>

                            </div>

                            <div class="room-details">

                                <div class="detail-item">
                                    <span>Hostel / Hall</span>
                                    <strong>
                                        <?= escapeStudent($allocation['hall_name']) ?>
                                    </strong>
                                </div>

                                <div class="detail-item">
                                    <span>Room Type</span>
                                    <strong>
                                        <?= escapeStudent($allocation['room_type']) ?>
                                    </strong>
                                </div>

                                <div class="detail-item">
                                    <span>Floor Number</span>
                                    <strong>
                                        <?= escapeStudent($allocation['floor_number']) ?>
                                    </strong>
                                </div>

                                <div class="detail-item">
                                    <span>Start Date</span>
                                    <strong>
                                        <?= escapeStudent(formatStudentDate($allocation['start_date'])) ?>
                                    </strong>
                                </div>

                                <div class="detail-item">
                                    <span>End Date</span>
                                    <strong>
                                        <?= escapeStudent(formatStudentDate($allocation['end_date'])) ?>
                                    </strong>
                                </div>

                            </div>

                        </div>

                    <?php else: ?>

                        <div class="empty-allocation">

                            <div class="empty-icon">⌂</div>

                            <h3>No Active Room Allocation</h3>

                            <p>
                                You haven't been allocated a room yet.
                                Submit an accommodation application
                                and follow its progress here.
                            </p>

                            <a href="application.php" class="small-button">
                                Apply for Accommodation →
                            </a>

                        </div>

                    <?php endif; ?>

                </div>


              
                <div class="panel">

                    <div class="panel-heading">
                        <h2>Application Status</h2>

                        <a href="my_application.php">
                            View All →
                        </a>
                    </div>

                    <div class="application-status">

                        <div class="status-row">
                            <span>Latest Application</span>

                            <strong>
                                <?php if ($latestApplication): ?>

                                    <span class="status-pill status-<?= escapeStudent(strtolower($latestApplication['status'])) ?>">
                                        <?= escapeStudent(ucfirst($latestApplication['status'])) ?>
                                    </span>

                                <?php else: ?>

                                    <span class="status-pill status-none">
                                        No Application
                                    </span>

                                <?php endif; ?>
                            </strong>
                        </div>

                        <div class="status-row">
                            <span>Total Submitted</span>
                            <strong><?= $totalApplications ?></strong>
                        </div>

                        <div class="status-row">
                            <span>Awaiting Review</span>
                            <strong><?= $pendingApplications ?></strong>
                        </div>

                        <div class="status-row">
                            <span>Room Allocation</span>

                            <strong>
                                <?= $allocation ? 'Allocated' : 'Not Allocated' ?>
                            </strong>
                        </div>

                        <?php if ($latestApplication): ?>

                            <div class="status-row">
                                <span>Last Application Date</span>

                                <strong>
                                    <?= escapeStudent(formatStudentDate($latestApplication['application_date'])) ?>
                                </strong>
                            </div>

                        <?php endif; ?>

                    </div>

                    <div class="application-message" style="margin-top:20px;">

                        <?php if ($allocation): ?>

                            Your accommodation currently has an active room allocation.

                        <?php elseif ($pendingApplications > 0): ?>

                            Your application is awaiting review by the accommodation officer.

                        <?php elseif ($totalApplications > 0): ?>

                            Check your application history for the latest decision and next steps.

                        <?php else: ?>

                            You have not submitted an accommodation application yet.
                            Start by applying for a hostel.

                        <?php endif; ?>

                    </div>

                </div>

            </section>


           
            <section>

                <div class="section-heading">

                    <div>
                        <h2>Student Services</h2>
                        <p>Quick access to your accommodation services.</p>
                    </div>

                </div>

                <div class="services-grid">

                    <a href="application.php" class="service-card">

                        <div class="service-icon">✎</div>

                        <h3>Apply for Accommodation</h3>

                        <p>Submit a new hostel application.</p>

                        <span class="service-arrow">Get Started →</span>

                    </a>


                    <a href="my_application.php" class="service-card">

                        <div class="service-icon">▤</div>

                        <h3>My Applications</h3>

                        <p>Review your submitted applications and their status.</p>

                        <span class="service-arrow">View Applications →</span>

                    </a>


                    <a href="my_allocation.php" class="service-card">

                        <div class="service-icon">⌂</div>

                        <h3>My Allocation</h3>

                        <p>View your allocated hostel and room details.</p>

                        <span class="service-arrow">View Allocation →</span>

                    </a>


                    <a href="transfer_request.php" class="service-card">

                        <div class="service-icon">⇄</div>

                        <h3>Room Transfer</h3>

                        <p>Submit a request to transfer to another room.</p>

                        <span class="service-arrow">Request Transfer →</span>

                    </a>


                    <a href="vacation_request.php" class="service-card">

                        <div class="service-icon">◷</div>

                        <h3>Vacation Request</h3>

                        <p>Submit and manage your vacation requests.</p>

                        <span class="service-arrow">Submit Request →</span>

                    </a>


                    <a href="profile.php" class="service-card">

                        <div class="service-icon">♙</div>

                        <h3>My Profile</h3>

                        <p>View and update your personal information.</p>

                        <span class="service-arrow">Manage Profile →</span>

                    </a>


                    <a href="help.php" class="service-card">

                        <div class="service-icon">?</div>

                        <h3>Help & Support</h3>

                        <p>Find answers to common accommodation questions.</p>

                        <span class="service-arrow">Get Help →</span>

                    </a>


                    <a href="chatbot.php" class="service-card">

                        <div class="service-icon">☏</div>

                        <h3>Hostel Assistant</h3>

                        <p>Chat with the Campus Hostel Assistant.</p>

                        <span class="service-arrow">Start Chatting →</span>

                    </a>

                </div>

            </section>

        </main>


       
        <footer class="dashboard-footer">

            <span>
                © <?= date('Y') ?> <strong>Campus Hostel Booking System</strong>
            </span>

            <span>
                Stay Smart. Live Better.
            </span>

        </footer>

    </div>

</div>


<script>
    const menuButton = document.getElementById('menuButton');
    const sidebar = document.getElementById('sidebar');

    menuButton.addEventListener('click', function () {
        const isOpen = sidebar.classList.toggle('open');

        menuButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

  
    document.querySelectorAll('.sidebar-nav a, .sidebar-bottom a')
        .forEach(function (link) {
            link.addEventListener('click', function () {
                sidebar.classList.remove('open');
                menuButton.setAttribute('aria-expanded', 'false');
            });
        });

   
    document.addEventListener('click', function (event) {
        if (
            window.innerWidth <= 800 &&
            !sidebar.contains(event.target) &&
            !menuButton.contains(event.target)
        ) {
            sidebar.classList.remove('open');
            menuButton.setAttribute('aria-expanded', 'false');
        }
    });
</script>

</body>
</html>
