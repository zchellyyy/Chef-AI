<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';
require __DIR__ . '/phpmailer/src/Exception.php';
/**
 * Send OTP email using PHPMailer
 *
 * @param string $toEmail
 * @param string $toName
 * @param string $otp  6-digit code
 * @return bool true on success, false on failure
 */
function sendOtpEmail(string $toEmail, string $toName, string $otp): bool
{
    $mail = new PHPMailer(true);

    try {
        // SERVER SETTINGS
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';   // Gmail SMTP
        $mail->SMTPAuth   = true;
        $mail->Username   = 'chefaicatering09@gmail.com';          // <-- change this
        $mail->Password   = 'bbvhgiswabekrwfa';       // <-- app password, not your real login
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // or 'tls'
        $mail->Port       = 587;

        // FROM / TO
        $mail->setFrom('chefaicatering09@gmail.com', 'Chef-AI');   // sender
        $mail->addAddress($toEmail, $toName);

        // CONTENT
        $mail->isHTML(true);
        $mail->Subject = 'Your Chef-AI Email Verification Code';
        $mail->Body    = "
            <p>Hi <strong>" . htmlspecialchars($toName) . "</strong>,</p>
            <p>Thank you for signing up to <strong>Chef-AI</strong>! </p>
            <p>Your 6-digit verification code is:</p>
            <h2 style='letter-spacing:4px;'>" . htmlspecialchars($otp) . "</h2>
            <p>This code will expire in <strong>60 seconds</strong>.</p>
            <p>If you did not request this, you can ignore this email.</p>
        ";
        $mail->AltBody = "Your Chef-AI verification code is: $otp";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // You can log errors with $mail->ErrorInfo
        return false;
    }
}
