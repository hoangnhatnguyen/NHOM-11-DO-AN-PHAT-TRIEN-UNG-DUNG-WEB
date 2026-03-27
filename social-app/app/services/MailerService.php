<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

class MailerService {
    public function sendPasswordReset(string $toEmail, string $toName, string $resetUrl): bool {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = (string) env('MAIL_HOST', 'smtp.gmail.com');
            $mail->Port = (int) env('MAIL_PORT', 587);
            $mail->SMTPAuth = true;
            $mail->Username = (string) env('MAIL_USERNAME', '');
            $mail->Password = (string) env('MAIL_PASSWORD', '');

            $encryption = strtolower((string) env('MAIL_ENCRYPTION', 'tls'));
            if ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->setFrom((string) env('MAIL_FROM_ADDRESS', 'no-reply@example.com'), (string) env('MAIL_FROM_NAME', APP_NAME));
            $mail->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);

            $mail->isHTML(true);
            $mail->Subject = 'Yeu cau dat lai mat khau - ' . APP_NAME;
            $mail->Body = $this->buildResetEmailHtml($toName, $resetUrl);
            $mail->AltBody = "Ban vua yeu cau dat lai mat khau. Mo link sau de dat lai mat khau: {$resetUrl}";

            return $mail->send();
        } catch (Exception $e) {
            Logger::error('Mailer error', ['message' => $e->getMessage()]);
            return false;
        }
    }

    private function buildResetEmailHtml(string $name, string $resetUrl): string {
        $safeName = htmlspecialchars($name !== '' ? $name : 'ban', ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

        return "
            <div style='font-family:Arial,sans-serif;line-height:1.6;color:#1f2937'>
                <h2 style='margin-bottom:8px;'>Dat lai mat khau</h2>
                <p>Chao {$safeName},</p>
                <p>Ban vua yeu cau dat lai mat khau cho tai khoan " . APP_NAME . ".</p>
                <p>
                    <a href='{$safeUrl}' style='display:inline-block;background:#1A6291;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;'>Dat lai mat khau</a>
                </p>
                <p>Link co hieu luc trong 30 phut.</p>
                <p>Neu ban khong yeu cau, hay bo qua email nay.</p>
            </div>
        ";
    }
}
