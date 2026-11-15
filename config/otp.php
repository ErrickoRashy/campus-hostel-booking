
<?php

require_once __DIR__ . '/mailer.php';

/**
 * Generate, store and email a six-digit OTP.
 */
function createAndSendOtp(
    PDO $pdo,
    string $email,
    string $purpose,
    ?int $userId = null,
    ?int $pendingRegistrationId = null
): array {

    $allowedPurposes = ['registration', 'login', 'password_reset'];

    if (!in_array($purpose, $allowedPurposes, true)) {
        return ['success' => false, 'message' => 'Invalid OTP request.'];
    }

    try {
        // Check whether a code was sent within the last 60 seconds.
        $recent = $pdo->prepare(
            "SELECT last_sent_at
             FROM otp_verifications
             WHERE email = :email
               AND purpose = :purpose
               AND used_at IS NULL
             ORDER BY created_at DESC
             LIMIT 1"
        );

        $recent->execute([
            'email' => $email,
            'purpose' => $purpose
        ]);

        $previous = $recent->fetch(PDO::FETCH_ASSOC);

        if ($previous) {
            $lastSent = strtotime($previous['last_sent_at']);

            if ($lastSent !== false && (time() - $lastSent) < 60) {
                return [
                    'success' => false,
                    'message' => 'Please wait 60 seconds before requesting another code.'
                ];
            }
        }

        // Generate a secure six-digit code.
        $otp = (string) random_int(100000, 999999);
        $otpHash = password_hash($otp, PASSWORD_DEFAULT);

        $pdo->beginTransaction();

        // Invalidate previous unused codes for this purpose.
        $invalidate = $pdo->prepare(
            "UPDATE otp_verifications
             SET used_at = NOW()
             WHERE email = :email
               AND purpose = :purpose
               AND used_at IS NULL"
        );

        $invalidate->execute([
            'email' => $email,
            'purpose' => $purpose
        ]);

        // Store the new hashed OTP. It expires after five minutes.
        $insert = $pdo->prepare(
            "INSERT INTO otp_verifications
             (
                 user_id,
                 pending_registration_id,
                 email,
                 purpose,
                 otp_hash,
                 expires_at,
                 attempts,
                 last_sent_at
             )
             VALUES
             (
                 :user_id,
                 :pending_registration_id,
                 :email,
                 :purpose,
                 :otp_hash,
                 NOW() + INTERVAL '5 minutes',
                 0,
                 NOW()
             )
             RETURNING otp_id"
        );

        $insert->execute([
            'user_id' => $userId,
            'pending_registration_id' => $pendingRegistrationId,
            'email' => $email,
            'purpose' => $purpose,
            'otp_hash' => $otpHash
        ]);

        $otpId = $insert->fetchColumn();

        $pdo->commit();

        // Send the code through Brevo.
        if (!sendOtpEmail($email, $otp, $purpose)) {

            $delete = $pdo->prepare(
                "DELETE FROM otp_verifications
                 WHERE otp_id = :otp_id"
            );

            $delete->execute(['otp_id' => $otpId]);

            return [
                'success' => false,
                'message' => 'We could not send your verification email. Please try again later.'
            ];
        }

        return [
            'success' => true,
            'message' => 'A verification code has been sent to your email.'
        ];

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('OTP creation error: ' . $e->getMessage());

        return [
            'success' => false,
            'message' => 'Unable to process your verification request.'
        ];
    }
}
