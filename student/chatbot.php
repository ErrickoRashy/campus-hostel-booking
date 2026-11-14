<?php
require_once __DIR__ . '/../config/auth_check.php';
requireRole('student');

require_once __DIR__ . '/../config/database.php';

$studentId = $_SESSION['user_id'];
$studentName = $_SESSION['full_name'] ?? 'Student';

$reply = '';
$question = '';

function getBotResponse(string $question, PDO $pdo, int $studentId): string
{
    $message = strtolower(trim($question));

    if (
        str_contains($message, 'my room') ||
        str_contains($message, 'allocation') ||
        str_contains($message, 'hostel')
    ) {
        $query = $pdo->prepare(
            "SELECT r.room_number, r.room_type, h.hall_name,
                    al.start_date, al.end_date
             FROM allocations al
             JOIN rooms r ON al.room_id = r.room_id
             JOIN halls h ON r.hall_id = h.hall_id
             WHERE al.student_id = :student_id
             AND al.status = 'active'
             LIMIT 1"
        );

        $query->execute(['student_id' => $studentId]);
        $allocation = $query->fetch();

        if ($allocation) {
            return "Your current hostel is " . $allocation['hall_name'] .
                ". Your room number is " . $allocation['room_number'] .
                " (" . $allocation['room_type'] . ").";
        }

        return "You do not currently have an active room allocation. Please submit an accommodation application.";
    }

    if (
        str_contains($message, 'application') ||
        str_contains($message, 'apply')
    ) {
        $query = $pdo->prepare(
            "SELECT status, academic_year, semester
             FROM applications
             WHERE student_id = :student_id
             ORDER BY application_date DESC
             LIMIT 1"
        );

        $query->execute(['student_id' => $studentId]);
        $application = $query->fetch();

        if ($application) {
            return "Your latest application for " .
                $application['academic_year'] . ", " .
                $application['semester'] . " has status: " .
                ucfirst($application['status']) .
                ". Visit My Applications for more details.";
        }

        return "You have not submitted an accommodation application yet. Visit Apply for Accommodation on your dashboard.";
    }

    if (str_contains($message, 'transfer')) {
        return "To request a hostel transfer, open Transfer Request on your dashboard, select another hostel, explain your reason, and submit it for officer review.";
    }

    if (
        str_contains($message, 'vacation') ||
        str_contains($message, 'holiday')
    ) {
        return "To request vacation permission, open Vacation Request on your dashboard, enter your departure and return dates, provide a reason, and submit the request.";
    }

    if (
        str_contains($message, 'profile') ||
        str_contains($message, 'email') ||
        str_contains($message, 'phone')
    ) {
        return "You can update your personal details through My Profile on your student dashboard.";
    }

    if (
        str_contains($message, 'help') ||
        str_contains($message, 'support')
    ) {
        return "You can find assistance on the Help & Support page. For room maintenance problems, contact your hostel caretaker.";
    }

    if (
        str_contains($message, 'hello') ||
        str_contains($message, 'hi') ||
        str_contains($message, 'hey')
    ) {
        return "Hello! Welcome to the Campus Hostel Assistant. How can I help you today?";
    }

    return "I'm the Campus Hostel Assistant. You can ask me about applications, room allocation, hostel transfers, vacation requests, or your student profile.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question = trim($_POST['message'] ?? '');

    if ($question !== '' && strlen($question) <= 500) {
        $reply = getBotResponse($question, $pdo, (int)$studentId);
    } else {
        $reply = 'Please enter a question of up to 500 characters.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Campus Hostel Assistant</title>

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

        .chat-container {
            width: 90%;
            max-width: 750px;
            margin: 45px auto;
            background: #102638;
            border: 1px solid #203b50;
            border-radius: 12px;
            overflow: hidden;
        }

        .chat-heading {
            padding: 25px;
            background: #18364d;
            border-bottom: 1px solid #294258;
        }

        .chat-heading h1 {
            color: #70e000;
            margin-bottom: 10px;
        }

        .chat-heading p {
            color: #b9c6d1;
            line-height: 1.6;
        }

        .chat-body {
            padding: 25px;
            min-height: 250px;
        }

        .message {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 18px;
            line-height: 1.7;
        }

        .bot {
            background: #071f2e;
            border-left: 4px solid #70e000;
        }

        .user {
            background: #183d2a;
            border-left: 4px solid #70e000;
        }

        .message strong {
            display: block;
            margin-bottom: 6px;
            color: #70e000;
        }

        .chat-form {
            padding: 20px 25px;
            border-top: 1px solid #294258;
        }

        textarea {
            width: 100%;
            min-height: 100px;
            padding: 14px;
            border: 1px solid #38536a;
            border-radius: 7px;
            background: #07131f;
            color: white;
            resize: vertical;
        }

        button {
            width: 100%;
            padding: 14px;
            margin-top: 14px;
            border: none;
            border-radius: 6px;
            background: #70e000;
            color: #07131f;
            font-weight: bold;
            cursor: pointer;
        }

        .suggestions {
            padding: 0 25px 25px;
        }

        .suggestions p {
            color: #b9c6d1;
            margin-bottom: 12px;
        }

        .suggestions span {
            display: inline-block;
            background: #203b50;
            color: #dce8f0;
            padding: 8px 12px;
            margin: 4px;
            border-radius: 20px;
            font-size: 13px;
        }

        @media (max-width: 600px) {
            header {
                padding: 18px 5%;
            }

            .chat-container {
                margin: 25px auto;
            }

            .chat-body {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<header>
    <h2>CampusHostel</h2>
    <a href="dashboard.php">Back to Dashboard</a>
</header>

<div class="chat-container">

    <div class="chat-heading">
        <h1>Campus Hostel Assistant</h1>

        <p>
            Hello <?= htmlspecialchars($studentName) ?>!
            I'm your virtual accommodation assistant.
            Ask me anything about your hostel services.
        </p>
    </div>

    <div class="chat-body">

        <div class="message bot">
            <strong>Campus Assistant</strong>
            Hello! How can I help you with your accommodation today?
        </div>

        <?php if ($question !== ''): ?>

            <div class="message user">
                <strong>You</strong>
                <?= htmlspecialchars($question) ?>
            </div>

            <div class="message bot">
                <strong>Campus Assistant</strong>
                <?= htmlspecialchars($reply) ?>
            </div>

        <?php endif; ?>

    </div>

    <form method="POST" class="chat-form">

        <textarea
            name="message"
            maxlength="500"
            placeholder="Type your question here..."
            required
        ></textarea>

        <button type="submit">Send Message</button>

    </form>

    <div class="suggestions">
        <p>Try asking:</p>

        <span>How do I apply?</span>
        <span>Check my application</span>
        <span>Where is my room?</span>
        <span>How do I transfer?</span>
        <span>Vacation request</span>
    </div>

</div>

</body>
</html>
