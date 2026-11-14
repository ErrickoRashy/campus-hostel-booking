<?php
require_once __DIR__ . '/../config/auth_check.php';
requireRole('student');

require_once __DIR__ . '/../config/database.php';

$studentName = $_SESSION['full_name'] ?? 'Student';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Help & Support | Campus Hostel</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: #07131f;
            color: white;
            min-height: 100vh;
        }

        header {
            background: #102638;
            padding: 20px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        header h2 {
            color: #70e000;
        }

        header a {
            color: #70e000;
            text-decoration: none;
        }

        .container {
            width: 90%;
            max-width: 950px;
            margin: 45px auto;
        }

        h1 {
            color: #70e000;
            margin-bottom: 12px;
        }

        .intro {
            color: #b9c6d1;
            line-height: 1.7;
            margin-bottom: 30px;
        }

        .section {
            background: #102638;
            border: 1px solid #203b50;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 22px;
        }

        .section h2 {
            color: #70e000;
            margin-bottom: 18px;
        }

        .faq {
            padding: 17px 0;
            border-bottom: 1px solid #294258;
        }

        .faq:last-child {
            border-bottom: none;
        }

        .faq h3 {
            color: white;
            margin-bottom: 9px;
            font-size: 17px;
        }

        .faq p, .section p, .section li {
            color: #b9c6d1;
            line-height: 1.7;
        }

        .section ul {
            padding-left: 22px;
        }

        .section li {
            margin-bottom: 8px;
        }

        .help-link {
            display: inline-block;
            margin-top: 15px;
            padding: 12px 18px;
            background: #70e000;
            color: #07131f;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
        }

        @media (max-width: 600px) {
            header {
                padding: 18px 5%;
            }

            .container {
                margin: 30px auto;
            }

            .section {
                padding: 19px;
            }
        }
    </style>
</head>

<body>

<header>
    <h2>CampusHostel</h2>
    <a href="dashboard.php">Back to Dashboard</a>
</header>

<div class="container">

    <h1>Help & Support</h1>

    <p class="intro">
        Hello <?= htmlspecialchars($studentName) ?>!
        Welcome to the Campus Hostel support centre.
        Find answers to common accommodation questions below.
    </p>

    <div class="section">

        <h2>Frequently Asked Questions</h2>

        <div class="faq">
            <h3>1. How do I apply for hostel accommodation?</h3>
            <p>
                Open your student dashboard, select Apply for Accommodation,
                enter your academic year and semester, select your preferred
                hostel, and submit the application.
            </p>
        </div>

        <div class="faq">
            <h3>2. How can I check my application status?</h3>
            <p>
                Open My Applications from your dashboard. You can see whether
                your application is pending, approved, rejected, or allocated.
            </p>
        </div>

        <div class="faq">
            <h3>3. How do I know which room I have been allocated?</h3>
            <p>
                Select My Allocation from your dashboard to view your hostel,
                room number, room type, floor, and allocation dates.
            </p>
        </div>

        <div class="faq">
            <h3>4. Can I request a room transfer?</h3>
            <p>
                Yes. Open Transfer Request, select another hostel, explain
                your reason, and submit the request for officer review.
            </p>
        </div>

        <div class="faq">
            <h3>5. How do I request permission to go on vacation?</h3>
            <p>
                Open Vacation Request, enter your departure date and expected
                return date, provide a reason, and submit your request.
            </p>
        </div>

        <div class="faq">
            <h3>6. What happens after submitting a request?</h3>
            <p>
                Your request is recorded in the system and forwarded to the
                accommodation officer for review. You can check its status
                through the relevant student page.
            </p>
        </div>

        <div class="faq">
            <h3>7. Can I change my account information?</h3>
            <p>
                Yes. Open My Profile to update your full name, email address,
                and phone number.
            </p>
        </div>

    </div>

    <div class="section">

        <h2>Important Student Guidelines</h2>

        <ul>
            <li>Submit accommodation applications before the official deadline.</li>
            <li>Provide accurate personal and academic information.</li>
            <li>Do not submit duplicate applications for the same semester.</li>
            <li>Wait for officer approval before assuming a transfer is confirmed.</li>
            <li>Keep your login credentials private.</li>
            <li>Report room maintenance problems to the caretaker.</li>
        </ul>

    </div>

    <div class="section">

        <h2>Need More Assistance?</h2>

        <p>
            If your question is not answered here, contact the accommodation
            officer through the university's official accommodation support
            channels.
        </p>

        <a href="chatbot.php" class="help-link">
            Open Campus Hostel Assistant
        </a>

    </div>

</div>

</body>
</html>
