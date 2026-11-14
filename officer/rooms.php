<?php
require_once __DIR__ . '/../config/auth_check.php';
requireRole('officer');

require_once __DIR__ . '/../config/database.php';

$message = '';
$messageType = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $message = 'Your session token is invalid. Please refresh the page.';
        $messageType = 'error';
    } else {

        $action = $_POST['action'] ?? '';

        if ($action === 'add_room') {

            $hallId = filter_input(INPUT_POST, 'hall_id', FILTER_VALIDATE_INT);
            $roomNumber = trim($_POST['room_number'] ?? '');
            $roomType = $_POST['room_type'] ?? '';
            $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);
            $floor = filter_input(INPUT_POST, 'floor_number', FILTER_VALIDATE_INT);

            $expectedCapacity = [
                'Single' => 1,
                'Double' => 2,
                'Triple' => 3
            ];

            if (
                !$hallId ||
                $roomNumber === '' ||
                strlen($roomNumber) > 20 ||
                !isset($expectedCapacity[$roomType]) ||
                $capacity === false ||
                $capacity === null ||
                $capacity !== $expectedCapacity[$roomType] ||
                $floor === false ||
                $floor === null ||
                $floor < 0
            ) {
                $message = 'Please provide valid room details. Capacity must match the selected room type.';
                $messageType = 'error';

            } else {

                try {
                    $insert = $pdo->prepare(
                        "INSERT INTO rooms
                            (hall_id, room_number, room_type, capacity, floor_number, status)
                         VALUES
                            (:hall_id, :room_number, :room_type, :capacity, :floor_number, 'available')"
                    );

                    $insert->execute([
                        'hall_id' => $hallId,
                        'room_number' => $roomNumber,
                        'room_type' => $roomType,
                        'capacity' => $capacity,
                        'floor_number' => $floor
                    ]);

                    $message = 'Room added successfully.';
                    $messageType = 'success';

                } catch (PDOException $e) {
                    $message = 'Could not add the room. Check that the hall exists and the room number is not already in use.';
                    $messageType = 'error';
                }
            }

        } elseif ($action === 'update_status') {

            $roomId = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
            $newStatus = $_POST['status'] ?? '';

            if (
                !$roomId ||
                !in_array($newStatus, ['available', 'maintenance'], true)
            ) {
                $message = 'Invalid room status request.';
                $messageType = 'error';

            } else {

                try {
                    $update = $pdo->prepare(
                        "UPDATE rooms
                         SET status = :status
                         WHERE room_id = :room_id
                         AND NOT EXISTS (
                             SELECT 1
                             FROM allocations
                             WHERE allocations.room_id = rooms.room_id
                             AND allocations.status = 'active'
                         )"
                    );

                    $update->execute([
                        'status' => $newStatus,
                        'room_id' => $roomId
                    ]);

                    if ($update->rowCount() > 0) {
                        $message = 'Room status updated successfully.';
                        $messageType = 'success';
                    } else {
                        $message = 'Room status was not changed. The room may be occupied, unavailable for updating, or already have that status.';
                        $messageType = 'error';
                    }

                } catch (PDOException $e) {
                    $message = 'Unable to update the room status. Please try again.';
                    $messageType = 'error';
                }
            }

        } else {
            $message = 'Invalid action.';
            $messageType = 'error';
        }
    }
}


$halls = $pdo->query(
    "SELECT hall_id, hall_name
     FROM halls
     ORDER BY hall_name"
)->fetchAll(PDO::FETCH_ASSOC);


$rooms = $pdo->query(
    "SELECT r.room_id, r.room_number, r.room_type,
            r.capacity, r.floor_number, r.status,
            h.hall_name,
            COUNT(a.allocation_id) FILTER (
                WHERE a.status = 'active'
            ) AS occupied_spaces
     FROM rooms r
     JOIN halls h ON r.hall_id = h.hall_id
     LEFT JOIN allocations a ON a.room_id = r.room_id
     GROUP BY r.room_id, h.hall_name
     ORDER BY h.hall_name, r.room_number"
)->fetchAll(PDO::FETCH_ASSOC);


$totalRooms = count($rooms);
$availableRooms = 0;
$occupiedRooms = 0;
$maintenanceRooms = 0;

foreach ($rooms as $room) {

    if ($room['status'] === 'maintenance') {
        $maintenanceRooms++;

    } elseif ((int) $room['occupied_spaces'] >= (int) $room['capacity']) {
        $occupiedRooms++;

    } else {
        $availableRooms++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Rooms and Halls | Campus Hostel</title>

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
            max-width: 1200px;
            margin: 35px auto;
        }

        .back {
            display: inline-block;
            margin-bottom: 20px;
            font-weight: bold;
        }

        h1 {
            margin: 0 0 10px;
            font-size: 28px;
        }

        .intro {
            color: #94a3b8;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 25px;
        }

        .stat {
            background: #111827;
            border: 1px solid #263449;
            padding: 22px;
            border-radius: 11px;
        }

        .stat h3 {
            color: #94a3b8;
            font-size: 14px;
            margin: 0 0 12px;
        }

        .stat p {
            color: #60a5fa;
            font-size: 29px;
            font-weight: bold;
            margin: 0;
        }

        .section {
            background: #111827;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #263449;
            margin-bottom: 25px;
        }

        .section h2 {
            color: #60a5fa;
            margin: 0 0 20px;
            font-size: 21px;
        }

        .alert {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .success {
            background: #064e3b;
            border: 1px solid #047857;
            color: #a7f3d0;
        }

        .error {
            background: #451a1a;
            border: 1px solid #991b1b;
            color: #fecaca;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 17px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #cbd5e1;
            font-size: 14px;
        }

        input,
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #334155;
            border-radius: 7px;
            background: #0b1220;
            color: #ffffff;
            outline: none;
        }

        input:focus,
        select:focus {
            border-color: #3b82f6;
        }

        button {
            padding: 11px 16px;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
        }

        button:focus-visible,
        a:focus-visible {
            outline: 2px solid #60a5fa;
            outline-offset: 3px;
        }

        .add-button {
            background: #2563eb;
            color: #ffffff;
            margin-top: 20px;
            padding: 13px 22px;
        }

        .add-button:hover {
            background: #1d4ed8;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th,
        td {
            padding: 15px 13px;
            text-align: left;
            border-bottom: 1px solid #263449;
            font-size: 14px;
        }

        th {
            color: #60a5fa;
            font-size: 13px;
            white-space: nowrap;
        }

        td {
            color: #e2e8f0;
        }

        tbody tr:hover {
            background: #172235;
        }

        .status-form {
            display: flex;
            gap: 7px;
            align-items: center;
        }

        .status-form select {
            min-width: 120px;
            padding: 9px;
        }

        .status-form button {
            background: #2563eb;
            color: #ffffff;
        }

        .status-form button:hover {
            background: #1d4ed8;
        }

        .status-label {
            font-weight: bold;
        }

        .available-label {
            color: #60a5fa;
        }

        .occupied-label {
            color: #fbbf24;
        }

        .maintenance-label {
            color: #f87171;
        }

        .empty {
            color: #94a3b8;
            padding: 15px 0;
        }

        .occupied-note {
            color: #94a3b8;
            font-size: 12px;
        }

        @media (max-width: 600px) {
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

            h1 {
                font-size: 23px;
            }

            .section {
                padding: 17px;
            }

            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .stat {
                padding: 16px;
            }

            .stat p {
                font-size: 25px;
            }

            .stat h3 {
                font-size: 12px;
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

    <a href="dashboard.php" class="back">
        &larr; Back to Dashboard
    </a>

    <h1>Rooms and Halls Management</h1>

    <p class="intro">
        Add hostel rooms, monitor occupancy and manage room availability.
    </p>

    <?php if ($message !== ''): ?>
        <div class="alert <?= e($messageType) ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <section class="stats">

        <div class="stat">
            <h3>Total Rooms</h3>
            <p><?= $totalRooms ?></p>
        </div>

        <div class="stat">
            <h3>Available Rooms</h3>
            <p><?= $availableRooms ?></p>
        </div>

        <div class="stat">
            <h3>Fully Occupied Rooms</h3>
            <p><?= $occupiedRooms ?></p>
        </div>

        <div class="stat">
            <h3>Maintenance Rooms</h3>
            <p><?= $maintenanceRooms ?></p>
        </div>

    </section>

    <section class="section">

        <h2>Add New Room</h2>

        <?php if ($halls): ?>

            <form method="POST">

                <input type="hidden" name="csrf_token"
                       value="<?= e($_SESSION['csrf_token']) ?>">

                <input type="hidden" name="action" value="add_room">

                <div class="form-grid">

                    <div>
                        <label for="hall_id">Select Hall</label>

                        <select name="hall_id" id="hall_id" required>
                            <option value="">Choose Hall</option>

                            <?php foreach ($halls as $hall): ?>
                                <option value="<?= (int) $hall['hall_id'] ?>">
                                    <?= e($hall['hall_name']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div>
                        <label for="room_number">Room Number</label>

                        <input
                            type="text"
                            name="room_number"
                            id="room_number"
                            maxlength="20"
                            placeholder="e.g. A101"
                            required
                        >
                    </div>

                    <div>
                        <label for="room_type">Room Type</label>

                        <select name="room_type" id="room_type" required>
                            <option value="">Choose Type</option>
                            <option value="Single">Single</option>
                            <option value="Double">Double</option>
                            <option value="Triple">Triple</option>
                        </select>
                    </div>

                    <div>
                        <label for="capacity">Capacity</label>

                        <select name="capacity" id="capacity" required>
                            <option value="">Choose Capacity</option>
                            <option value="1">1 Person</option>
                            <option value="2">2 People</option>
                            <option value="3">3 People</option>
                        </select>
                    </div>

                    <div>
                        <label for="floor_number">Floor Number</label>

                        <input
                            type="number"
                            name="floor_number"
                            id="floor_number"
                            min="0"
                            value="0"
                            required
                        >
                    </div>

                </div>

                <button type="submit" class="add-button">
                    + Add Room
                </button>

            </form>

        <?php else: ?>

            <p class="empty">
                No halls exist yet. Add a hall before creating rooms.
            </p>

        <?php endif; ?>

    </section>

    <section class="section">

        <h2>All Hostel Rooms</h2>

        <?php if ($rooms): ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Hall</th>
                            <th>Room</th>
                            <th>Type</th>
                            <th>Capacity</th>
                            <th>Occupied</th>
                            <th>Floor</th>
                            <th>Status</th>
                            <th>Manage</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($rooms as $room): ?>

                            <?php
                            $occupied = (int) $room['occupied_spaces'];
                            $capacityValue = (int) $room['capacity'];

                            if ($room['status'] === 'maintenance') {
                                $displayStatus = 'Maintenance';
                                $statusClass = 'maintenance-label';

                            } elseif ($occupied >= $capacityValue) {
                                $displayStatus = 'Occupied';
                                $statusClass = 'occupied-label';

                            } else {
                                $displayStatus = 'Available';
                                $statusClass = 'available-label';
                            }
                            ?>

                            <tr>

                                <td><?= e($room['hall_name']) ?></td>

                                <td><?= e($room['room_number']) ?></td>

                                <td><?= e($room['room_type']) ?></td>

                                <td><?= $capacityValue ?></td>

                                <td><?= $occupied ?></td>

                                <td><?= (int) $room['floor_number'] ?></td>

                                <td class="status-label <?= $statusClass ?>">
                                    <?= e($displayStatus) ?>
                                </td>

                                <td>

                                    <?php if ($occupied === 0): ?>

                                        <form method="POST" class="status-form">

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION['csrf_token']) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="update_status"
                                            >

                                            <input
                                                type="hidden"
                                                name="room_id"
                                                value="<?= (int) $room['room_id'] ?>"
                                            >

                                            <select name="status" required>
                                                <option
                                                    value="available"
                                                    <?= $room['status'] === 'available' ? 'selected' : '' ?>
                                                >
                                                    Available
                                                </option>

                                                <option
                                                    value="maintenance"
                                                    <?= $room['status'] === 'maintenance' ? 'selected' : '' ?>
                                                >
                                                    Maintenance
                                                </option>
                                            </select>

                                            <button type="submit">Update</button>

                                        </form>

                                    <?php else: ?>

                                        <span class="occupied-note">
                                            Currently occupied
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p class="empty">
                No rooms have been registered yet.
            </p>

        <?php endif; ?>

    </section>

</main>

<script>
    const roomType = document.getElementById('room_type');
    const capacity = document.getElementById('capacity');

    if (roomType && capacity) {
        roomType.addEventListener('change', function () {

            const values = {
                Single: '1',
                Double: '2',
                Triple: '3'
            };

            capacity.value = values[this.value] || '';
        });
    }
</script>

</body>
</html>
