<?php
require_once __DIR__ . '/../config/auth_check.php';
requireRole('student');

require_once __DIR__ . '/../config/database.php';

$studentId = $_SESSION['user_id'];
$errors = [];
$success = '';

$query = $pdo->prepare(
    "SELECT user_id, full_name, email, phone, profile_photo
     FROM users
     WHERE user_id = :id"
);
$query->execute(['id' => $studentId]);
$student = $query->fetch();

if (!$student) {
    http_response_code(404);
    exit('Student account not found.');
}

$formFullName = $student['full_name'];
$formEmail = $student['email'];
$formPhone = $student['phone'] ?? '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $token = $_POST['csrf_token'] ?? '';

    $formFullName = $fullName;
    $formEmail = $email;
    $formPhone = $phone;

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $errors[] = 'Invalid request. Please refresh the page.';
    }

    if ($fullName === '' || strlen($fullName) > 120) {
        $errors[] = 'Enter a valid full name (maximum 120 characters).';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $errors[] = 'Enter a valid email address.';
    }

    if (strlen($phone) > 20) {
        $errors[] = 'Phone number must not exceed 20 characters.';
    }

    if (empty($errors)) {
        try {
            $check = $pdo->prepare(
                "SELECT user_id FROM users
                 WHERE email = :email AND user_id <> :id"
            );

            $check->execute([
                'email' => $email,
                'id' => $studentId
            ]);

            if ($check->fetch()) {
                $errors[] = 'That email address is already in use.';
            } else {

                $newPhotoName = $student['profile_photo'] ?? null;
                $newPhotoPath = null;

                // Handle optional profile photo upload.
                if (
                    isset($_FILES['profile_photo']) &&
                    $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE
                ) {
                    $file = $_FILES['profile_photo'];

                    if ($file['error'] !== UPLOAD_ERR_OK) {
                        $errors[] = 'There was a problem uploading your photo.';
                    } elseif ($file['size'] > 2 * 1024 * 1024) {
                        $errors[] = 'Your photo must not exceed 2 MB.';
                    } else {
                        $imageInfo = @getimagesize($file['tmp_name']);
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mimeType = finfo_file($finfo, $file['tmp_name']);
                        finfo_close($finfo);

                        $allowedTypes = [
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/webp' => 'webp'
                        ];

                        if (
                            !$imageInfo ||
                            !isset($allowedTypes[$mimeType])
                        ) {
                            $errors[] = 'Upload a valid JPG, PNG or WEBP image.';
                        } elseif (
                            $imageInfo[0] > 5000 ||
                            $imageInfo[1] > 5000
                        ) {
                            $errors[] = 'Your image dimensions are too large.';
                        } else {
                            $uploadDirectory = __DIR__ .
                                '/../uploads/profile_photos/';

                            if (
                                !is_dir($uploadDirectory) &&
                                !mkdir($uploadDirectory, 0755, true)
                            ) {
                                $errors[] = 'Unable to prepare the photo upload folder.';
                            } else {
                                $extension = $allowedTypes[$mimeType];

                                $newPhotoName = 'student_' .
                                    $studentId . '_' .
                                    bin2hex(random_bytes(16)) .
                                    '.' . $extension;

                                $newPhotoPath = $uploadDirectory . $newPhotoName;

                                if (!move_uploaded_file(
                                    $file['tmp_name'],
                                    $newPhotoPath
                                )) {
                                    $errors[] = 'Unable to save your uploaded photo.';
                                    $newPhotoPath = null;
                                    $newPhotoName = $student['profile_photo'] ?? null;
                                }
                            }
                        }
                    }
                }

                if (empty($errors)) {

                    $update = $pdo->prepare(
                        "UPDATE users
                         SET full_name = :name,
                             email = :email,
                             phone = :phone,
                             profile_photo = :photo
                         WHERE user_id = :id"
                    );

                    $update->execute([
                        'name' => $fullName,
                        'email' => $email,
                        'phone' => $phone !== '' ? $phone : null,
                        'photo' => $newPhotoName,
                        'id' => $studentId
                    ]);

                    // Delete the previous photo after a successful update.
                    $oldPhoto = $student['profile_photo'] ?? '';

                    if (
                        $newPhotoPath !== null &&
                        $oldPhoto !== '' &&
                        basename($oldPhoto) === $oldPhoto
                    ) {
                        $oldPhotoPath = __DIR__ .
                            '/../uploads/profile_photos/' . $oldPhoto;

                        if (is_file($oldPhotoPath)) {
                            unlink($oldPhotoPath);
                        }
                    }

                    $student['full_name'] = $fullName;
                    $student['email'] = $email;
                    $student['phone'] = $phone;
                    $student['profile_photo'] = $newPhotoName;

                    $_SESSION['full_name'] = $fullName;
                    $_SESSION['email'] = $email;

                    $formFullName = $fullName;
                    $formEmail = $email;
                    $formPhone = $phone;

                    $success = 'Your profile was updated successfully.';

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }
            }

        } catch (PDOException $e) {
            if (
                isset($newPhotoPath) &&
                $newPhotoPath !== null &&
                is_file($newPhotoPath)
            ) {
                unlink($newPhotoPath);
            }

            error_log('Profile update error: ' . $e->getMessage());
            $errors[] = 'Unable to update your profile. Please try again.';
        }
    }
}

$photoUrl = '';

if (!empty($student['profile_photo'])) {
    $photoName = basename($student['profile_photo']);
    $photoPath = __DIR__ . '/../uploads/profile_photos/' . $photoName;

    if (is_file($photoPath)) {
        $photoUrl = '../uploads/profile_photos/' . rawurlencode($photoName);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile | Campus Hostel</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            background: #000000;
            color: #ffffff;
        }

        header {
            background: #111827;
            border-bottom: 1px solid #263449;
            padding: 17px 7%;
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
        }

        .brand h2 {
            font-size: 19px;
            color: #ffffff;
        }

        header a {
            color: #60a5fa;
            text-decoration: none;
            font-weight: bold;
        }

        header a:hover {
            color: #93c5fd;
        }

        .container {
            width: 92%;
            max-width: 720px;
            margin: 40px auto;
            background: #111827;
            border: 1px solid #263449;
            border-radius: 16px;
            padding: 32px;
        }

        h1 {
            color: #ffffff;
            margin-bottom: 10px;
            font-size: 27px;
        }

        .description {
            color: #aab7c8;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .photo-section {
            text-align: center;
            padding: 22px;
            margin-bottom: 25px;
            background: #0b1220;
            border: 1px solid #263449;
            border-radius: 12px;
        }

        .profile-photo {
            width: 125px;
            height: 125px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #2563eb;
            margin-bottom: 13px;
        }

        .photo-placeholder {
            width: 125px;
            height: 125px;
            border-radius: 50%;
            margin: 0 auto 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #1e3a8a;
            color: #ffffff;
            font-size: 45px;
            font-weight: bold;
            border: 4px solid #2563eb;
        }

        .photo-section p {
            color: #aab7c8;
            font-size: 13px;
            margin-top: 8px;
        }

        .photo-label {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 10px 18px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: bold;
        }

        .photo-label:hover {
            background: #1d4ed8;
        }

        #profile_photo {
            display: none;
        }

        label.field-label {
            display: block;
            margin: 20px 0 9px;
            color: #e5e7eb;
            font-weight: bold;
        }

        input[type="text"],
        input[type="email"] {
            width: 100%;
            padding: 14px;
            border: 1px solid #374151;
            border-radius: 7px;
            background: #080d16;
            color: white;
            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .update-button {
            width: 100%;
            padding: 15px;
            margin-top: 28px;
            border: none;
            border-radius: 7px;
            background: #2563eb;
            color: white;
            font-weight: bold;
            font-size: 15px;
            cursor: pointer;
        }

        .update-button:hover {
            background: #1d4ed8;
        }

        .success,
        .errors {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .success {
            background: #123524;
            color: #bbf7d0;
            border: 1px solid #166534;
        }

        .errors {
            background: #451a1a;
            color: #fecaca;
            border: 1px solid #991b1b;
        }

        footer {
            text-align: center;
            color: #778397;
            padding: 15px;
            font-size: 13px;
        }

        @media (max-width: 600px) {
            header {
                padding: 14px 5%;
            }

            .brand h2 {
                font-size: 15px;
            }

            .brand img {
                width: 40px;
                height: 40px;
            }

            header a {
                font-size: 13px;
            }

            .container {
                padding: 22px 18px;
                margin: 25px auto;
            }

            h1 {
                font-size: 23px;
            }
        }
    </style>
</head>

<body>

<header>
    <div class="brand">
        <img src="../campus-hostel-logo.png" alt="Campus Hostel Logo">
        <h2>Campus Hostel</h2>
    </div>

    <a href="dashboard.php">← Back to Dashboard</a>
</header>

<main class="container">

    <h1>My Profile</h1>

    <p class="description">
        Manage your personal information and upload a profile photo.
    </p>

    <?php if ($success): ?>
        <div class="success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="errors">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
        >

        <div class="photo-section">

            <?php if ($photoUrl): ?>

                <img
                    src="<?= htmlspecialchars($photoUrl) ?>"
                    alt="Student profile photo"
                    class="profile-photo"
                    id="photoPreview"
                >

            <?php else: ?>

                <div class="photo-placeholder" id="photoPlaceholder">
                    <?= htmlspecialchars(strtoupper(substr($student['full_name'], 0, 1))) ?>
                </div>

                <img
                    src=""
                    alt="Photo preview"
                    class="profile-photo"
                    id="photoPreview"
                    style="display:none;"
                >

            <?php endif; ?>

            <br>

            <label for="profile_photo" class="photo-label">
                Choose Profile Photo
            </label>

            <input
                type="file"
                id="profile_photo"
                name="profile_photo"
                accept="image/jpeg,image/png,image/webp"
            >

            <p>Accepted formats: JPG, PNG and WEBP. Maximum size: 2 MB.</p>

        </div>

        <label class="field-label" for="full_name">Full Name</label>

        <input
            type="text"
            id="full_name"
            name="full_name"
            maxlength="120"
            value="<?= htmlspecialchars($formFullName) ?>"
            required
        >

        <label class="field-label" for="email">Email Address</label>

        <input
            type="email"
            id="email"
            name="email"
            maxlength="150"
            value="<?= htmlspecialchars($formEmail) ?>"
            required
        >

        <label class="field-label" for="phone">Phone Number</label>

        <input
            type="text"
            id="phone"
            name="phone"
            maxlength="20"
            value="<?= htmlspecialchars($formPhone) ?>"
        >

        <button type="submit" class="update-button">
            Save Profile Changes
        </button>

    </form>

</main>

<footer>
    © <?= date('Y') ?> Campus Hostel Booking System
</footer>

<script>
    const photoInput = document.getElementById('profile_photo');
    const photoPreview = document.getElementById('photoPreview');
    const photoPlaceholder = document.getElementById('photoPlaceholder');

    photoInput.addEventListener('change', function () {
        const file = this.files[0];

        if (file) {
            const allowedTypes = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];

            if (!allowedTypes.includes(file.type)) {
                alert('Please select a JPG, PNG or WEBP image.');
                this.value = '';
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                alert('The photo must not exceed 2 MB.');
                this.value = '';
                return;
            }

            photoPreview.src = URL.createObjectURL(file);
            photoPreview.style.display = 'inline-block';

            if (photoPlaceholder) {
                photoPlaceholder.style.display = 'none';
            }
        }
    });
</script>

</body>
</html>
