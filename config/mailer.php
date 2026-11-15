<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function createMailer(): PHPMailer
{
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = getenv('SMTP_HOST') ?: 'smtp-relay.brevo.com';
    $mail->SMTPAuth = true;
    $mail->Username = getenv('SMTP_USERNAME');
    $mail->Password = getenv('SMTP_PASSWORD');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int) (getenv('SMTP_PORT') ?: 587);

    $mail->CharSet = 'UTF-8';

    $mail->setFrom(
        getenv('MAIL_FROM_EMAIL'),
        getenv('MAIL_FROM_NAME') ?: 'Campus Hostel Booking System'
    );

    $mail->isHTML(true);

    return $mail;
}

function sendOtpEmail(string $recipient, string $otp, string $purpose): bool
{
    try {
        $mail = createMailer();

        $mail->addAddress($recipient);
        $mail->Subject = 'Your Campus Hostel Verification Code';

        $message = match ($purpose) {
            'registration' => 'verify your email address',
            'login' => 'complete your login',
            'password_reset' => 'reset your password',
            default => 'verify your account'
        };

        $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:500px;margin:auto;padding:25px;border:1px solid #ddd;border-radius:10px;'>
                <h2 style='color:#07131f;'>Campus Hostel Booking System</h2>
                <p>Your verification code will help you {$message}.</p>
                <div style='font-size:32px;font-weight:bold;letter-spacing:8px;color:#70e000;padding:15px 0;'>
                    {$otp}
                </div>
                <p>This code expires in <strong>5 minutes</strong>.</p>
                <p>If you did not request this code, please ignore this email.</p>
            </div>
        ";

        $mail->AltBody = "Your Campus Hostel verification code is {$otp}. It expires in 5 minutes.";

        $mail->send();

        return true;

    } catch (Exception $e) {
        error_log('OTP email delivery failed: ' . $e->getMessage());
        return false;
    }
}
