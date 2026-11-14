<?php
require_once __DIR__ . '/../config/auth_check.php';
requireRole('student');

require_once __DIR__ . '/../config/database.php';

$studentId = $_SESSION['user_id'];
$errors = [];
$success = '';

$academicYear = '';
$semester = '';
$preferredHall = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}



$hallQuery = $pdo->query(
    "SELECT h.hall_id, h.hall_name,
            COUNT(r.room_id) AS total_rooms,
            COUNT(r.room_id) FILTER (
                WHERE r.status = 'available'
            ) AS available_rooms
     FROM halls h
     LEFT JOIN rooms r ON h.hall_id = r.hall_id
     GROUP BY h.hall_id, h.hall_name
     ORDER BY h.hall_name"
);

$halls = $hallQuery->fetchAll(PDO::FETCH_ASSOC);




$roomQuery = $pdo->query(
    "SELECT
        r.room_id,
        r.room_number,
        r.room_type,
        r.capacity,
        r.floor_number,
        r.status,
        h.hall_id,
        h.hall_name
     FROM rooms r
     INNER JOIN halls h ON r.hall_id = h.hall_id
     WHERE r.status = 'available'
     ORDER BY h.hall_name, r.room_number"
);

$rooms = $roomQuery->fetchAll(PDO::FETCH_ASSOC);




if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $academicYear = trim($_POST['academic_year'] ?? '');
    $semester = trim($_POST['semester'] ?? '');
    $preferredHall = $_POST['preferred_hall_id'] ?? '';

    $token = $_POST['csrf_token'] ?? '';

    if (
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $errors[] = "Invalid request. Please refresh the page.";
    }

    if ($academicYear === '') {
        $errors[] = "Academic year is required.";
    }

    if (
        !in_array(
            $semester,
            ['Semester 1', 'Semester 2', 'Semester 3'],
            true
        )
    ) {
        $errors[] = "Please select a valid semester.";
    }

    $hallId = null;

    if ($preferredHall !== '') {

        if (filter_var($preferredHall, FILTER_VALIDATE_INT) === false) {

            $errors[] = "Invalid hostel selection.";

        } else {

            $hallId = (int)$preferredHall;

            $hallCheck = $pdo->prepare(
                "SELECT hall_id FROM halls WHERE hall_id = :id"
            );

            $hallCheck->execute(['id' => $hallId]);

            if (!$hallCheck->fetch()) {
                $errors[] = "The selected hostel does not exist.";
            }
        }
    }

    if (empty($errors)) {

        try {

            $duplicate = $pdo->prepare(
                "SELECT application_id
                 FROM applications
                 WHERE student_id = :student_id
                 AND academic_year = :academic_year
                 AND semester = :semester
                 LIMIT 1"
            );

            $duplicate->execute([
                'student_id' => $studentId,
                'academic_year' => $academicYear,
                'semester' => $semester
            ]);

            if ($duplicate->fetch()) {

                $errors[] = "You have already applied for this academic year and semester.";

            } else {

                $insert = $pdo->prepare(
                    "INSERT INTO applications
                    (
                        student_id,
                        academic_year,
                        semester,
                        preferred_hall_id
                    )
                    VALUES
                    (
                        :student_id,
                        :academic_year,
                        :semester,
                        :hall_id
                    )"
                );

                $insert->execute([
                    'student_id' => $studentId,
                    'academic_year' => $academicYear,
                    'semester' => $semester,
                    'hall_id' => $hallId
                ]);

                $success = "Your accommodation application was submitted successfully.";

                $academicYear = '';
                $semester = '';
                $preferredHall = '';

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }

        } catch (PDOException $e) {

            error_log("Application submission error: " . $e->getMessage());

            $errors[] = "Unable to submit your application. Please try again.";
        }
    }
}




$totalAvailableRooms = count($rooms);
$totalHalls = count($halls);

$singleRooms = 0;
$doubleRooms = 0;
$tripleRooms = 0;

foreach ($rooms as $room) {

    if ($room['room_type'] === 'Single') {
        $singleRooms++;
    } elseif ($room['room_type'] === 'Double') {
        $doubleRooms++;
    } elseif ($room['room_type'] === 'Triple') {
        $tripleRooms++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Accommodation Application | Campus Hostel</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: "Segoe UI", Arial, sans-serif;
}

:root {
    --background: #07131f;
    --panel: #102638;
    --panel-light: #16344a;
    --green: #70e000;
    --text: #ffffff;
    --muted: #aab9c6;
    --border: #29445a;
}

body {
    background: var(--background);
    color: var(--text);
    min-height: 100vh;
}



header {
    background: #0c2030;
    border-bottom: 1px solid var(--border);
    padding: 17px 7%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.logo {
    font-size: 23px;
    font-weight: 800;
    color: var(--green);
}

.header-link {
    text-decoration: none;
    color: white;
    padding: 10px 17px;
    border: 1px solid var(--border);
    border-radius: 8px;
    transition: .3s;
}

.header-link:hover {
    background: var(--green);
    color: var(--background);
}

/* MAIN */

.main {
    width: 90%;
    max-width: 1200px;
    margin: 40px auto 70px;
}

.hero {
    padding: 35px;
    background: linear-gradient(120deg, #102638, #173b4e);
    border: 1px solid var(--border);
    border-radius: 18px;
    margin-bottom: 30px;
}

.hero-tag {
    display: inline-block;
    background: rgba(112, 224, 0, .12);
    color: var(--green);
    padding: 7px 13px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 15px;
}

.hero h1 {
    font-size: clamp(27px, 4vw, 38px);
    margin-bottom: 12px;
}

.hero h1 span {
    color: var(--green);
}

.hero p {
    color: var(--muted);
    line-height: 1.8;
    max-width: 700px;
}



.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 35px;
}

.stat-card {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 13px;
    padding: 22px;
}

.stat-card h2 {
    color: var(--green);
    font-size: 29px;
    margin-bottom: 7px;
}

.stat-card p {
    color: var(--muted);
    font-size: 13px;
}

/* SECTION */

.section-heading {
    margin-bottom: 20px;
}

.section-heading h2 {
    font-size: 25px;
    margin-bottom: 8px;
}

.section-heading p {
    color: var(--muted);
    line-height: 1.6;
}



.hall-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 45px;
}

.hall-card {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 22px;
    transition: .3s;
}

.hall-card:hover {
    border-color: var(--green);
    transform: translateY(-3px);
}

.hall-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 17px;
}

.hall-header h3 {
    font-size: 19px;
}

.available-badge {
    color: var(--green);
    background: rgba(112, 224, 0, .12);
    border-radius: 20px;
    padding: 6px 10px;
    font-size: 11px;
    white-space: nowrap;
}

.room-list {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.room-item {
    background: #0a1b29;
    border: 1px solid #253f52;
    border-radius: 9px;
    padding: 13px;
}

.room-number {
    color: var(--green);
    font-size: 17px;
    font-weight: bold;
    margin-bottom: 7px;
}

.room-type {
    font-size: 13px;
    margin-bottom: 7px;
}

.room-detail {
    font-size: 11px;
    color: var(--muted);
    line-height: 1.7;
}

.room-status {
    color: var(--green);
    font-size: 11px;
    margin-top: 7px;
    font-weight: bold;
}



.form-container {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 30px;
    max-width: 750px;
    margin: auto;
}

.form-container h2 {
    font-size: 25px;
    margin-bottom: 10px;
}

.form-description {
    color: var(--muted);
    line-height: 1.7;
    margin-bottom: 20px;
}

label {
    display: block;
    margin: 20px 0 9px;
    font-weight: 600;
    font-size: 14px;
}

input, select {
    width: 100%;
    padding: 14px;
    background: #07131f;
    border: 1px solid var(--border);
    border-radius: 8px;
    color: white;
    font-size: 14px;
}

input:focus, select:focus {
    outline: none;
    border-color: var(--green);
}

select option {
    background: #102638;
    color: white;
}

.submit-btn {
    width: 100%;
    padding: 15px;
    background: var(--green);
    color: #07131f;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    font-size: 15px;
    margin-top: 25px;
    cursor: pointer;
    transition: .3s;
}

.submit-btn:hover {
    background: #8aff19;
    transform: translateY(-2px);
}



.success, .errors {
    padding: 15px;
    border-radius: 9px;
    margin-bottom: 20px;
    line-height: 1.7;
}

.success {
    background: #153b29;
    color: #b7f5c7;
    border: 1px solid #287347;
}

.errors {
    background: #51232b;
    color: #ffd6d6;
    border: 1px solid #843c48;
}



.empty {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 25px;
    color: var(--muted);
    text-align: center;
    grid-column: 1 / -1;
}



footer {
    text-align: center;
    color: var(--muted);
    padding: 25px;
    border-top: 1px solid var(--border);
    font-size: 13px;
}

/* RESPONSIVE */

@media (max-width: 800px) {

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .hall-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 500px) {

    header {
        padding: 15px 5%;
    }

    .logo {
        font-size: 18px;
    }

    .header-link {
        font-size: 12px;
        padding: 9px;
    }

    .main {
        width: 92%;
        margin-top: 25px;
    }

    .hero {
        padding: 24px;
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

    .form-container {
        padding: 20px;
    }

    .room-list {
        grid-template-columns: 1fr 1fr;
    }

}

</style>

</head>

<body>

<header>

    <div class="logo">
        CampusHostel
    </div>

    <a href="dashboard.php" class="header-link">
        ← Back to Dashboard
    </a>

</header>

<main class="main">

    <section class="hero">

        <span class="hero-tag">
            STUDENT ACCOMMODATION
        </span>

        <h1>
            Find Your <span>Campus Home</span>
        </h1>

        <p>
            Explore the available accommodation across our halls.
            Check room types, capacities and availability before
            submitting your accommodation application.
        </p>

    </section>




    <section class="stats">

        <div class="stat-card">
            <h2><?= $totalHalls ?></h2>
            <p>Available Halls Listed</p>
        </div>

        <div class="stat-card">
            <h2><?= $totalAvailableRooms ?></h2>
            <p>Available Rooms</p>
        </div>

        <div class="stat-card">
            <h2><?= $singleRooms ?></h2>
            <p>Single Rooms</p>
        </div>

        <div class="stat-card">
            <h2><?= $doubleRooms + $tripleRooms ?></h2>
            <p>Double & Triple Rooms</p>
        </div>

    </section>




    <section>

        <div class="section-heading">

            <h2>Explore Our Hostels</h2>

            <p>
                View available rooms and their accommodation
                capacities in each hall.
            </p>

        </div>

        <div class="hall-grid">

            <?php foreach ($halls as $hall): ?>

                <div class="hall-card">

                    <div class="hall-header">

                        <h3>
                            <?= htmlspecialchars($hall['hall_name']) ?>
                        </h3>

                        <span class="available-badge">
                            <?= (int)$hall['available_rooms'] ?> Available
                        </span>

                    </div>

                    <div class="room-list">

                        <?php
                        $hallRooms = array_filter(
                            $rooms,
                            function ($room) use ($hall) {
                                return (int)$room['hall_id'] ===
                                       (int)$hall['hall_id'];
                            }
                        );
                        ?>

                        <?php if (!empty($hallRooms)): ?>

                            <?php foreach ($hallRooms as $room): ?>

                                <div class="room-item">

                                    <div class="room-number">
                                        <?= htmlspecialchars($room['room_number']) ?>
                                    </div>

                                    <div class="room-type">
                                        <?= htmlspecialchars($room['room_type']) ?> Room
                                    </div>

                                    <div class="room-detail">

                                        Capacity:
                                        <?= (int)$room['capacity'] ?>
                                        student(s)

                                        <br>

                                        Floor:
                                        <?= (int)$room['floor_number'] ?>

                                    </div>

                                    <div class="room-status">
                                        ● Available
                                    </div>

                                </div>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <div class="empty">
                                No available rooms in this hall currently.
                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </section>




    <section class="form-container">

        <h2>Apply for Accommodation</h2>

        <p class="form-description">
            Complete the form below. Your application will be
            forwarded to the accommodation officer for review.
        </p>


        <?php if ($success): ?>

            <div class="success">
                <?= htmlspecialchars($success) ?>
            </div>

        <?php endif; ?>


        <?php if (!empty($errors)): ?>

            <div class="errors">

                <?php foreach ($errors as $error): ?>

                    <p>
                        <?= htmlspecialchars($error) ?>
                    </p>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>


        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
            >


            <label for="academic_year">
                Academic Year
            </label>

            <input
                type="text"
                id="academic_year"
                name="academic_year"
                placeholder="Example: 2026/2027"
                value="<?= htmlspecialchars($academicYear) ?>"
                required
            >


            <label for="semester">
                Semester
            </label>

            <select id="semester" name="semester" required>

                <option value="">
                    Select Semester
                </option>

                <?php foreach (
                    ['Semester 1', 'Semester 2', 'Semester 3']
                    as $option
                ): ?>

                    <option
                        value="<?= htmlspecialchars($option) ?>"
                        <?= $semester === $option ? 'selected' : '' ?>
                    >

                        <?= htmlspecialchars($option) ?>

                    </option>

                <?php endforeach; ?>

            </select>


            <label for="preferred_hall_id">
                Preferred Hostel
            </label>

            <select
                id="preferred_hall_id"
                name="preferred_hall_id"
            >

                <option value="">
                    No preference
                </option>

                <?php foreach ($halls as $hall): ?>

                    <option
                        value="<?= (int)$hall['hall_id'] ?>"
                        <?= (string)$preferredHall ===
                            (string)$hall['hall_id'] ? 'selected' : '' ?>
                    >

                        <?= htmlspecialchars($hall['hall_name']) ?>
                        (<?= (int)$hall['available_rooms'] ?> rooms available)

                    </option>

                <?php endforeach; ?>

            </select>


            <button type="submit" class="submit-btn">
                Submit Accommodation Application →
            </button>

        </form>

    </section>

</main>


<footer>

    Campus Hostel Booking System

    <br><br>

    Making student accommodation easier and more accessible.

</footer>

</body>
</html>
