<?php

require_once __DIR__ . '/../libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailHelper {
    
    // ==========================================
    // GMAIL CONFIGURATION
    // ==========================================
    const SMTP_HOST = 'smtp.gmail.com';
    const SMTP_PORT = 465; // or 587 for TLS
    const SMTP_USER = 'crimsoncolleges@gmail.com'; // <--- REPLACE THIS
    const SMTP_PASS = 'fiqfzycsqbqnbjoo';   // <--- REPLACE THIS
    const SMTP_FROM_NAME = 'Disaster Relief Tracker';
    // ==========================================

    /**
     * Sends an approval email to the volunteer with their temporary password using PHPMailer.
     * 
     * @param string $email
     * @param string $name
     * @param string $tempPassword
     * @return boolean
     */
    public static function sendApprovalEmail($email, $name, $tempPassword) {
        $subject = "Volunteer Application Approved - Disaster Relief Tracker";
        
        $loginUrl = "http://localhost/DSRT/login.php"; // Update this when deploying

        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin: 0; padding: 0; background-color: #f8fafc; font-family: Arial, sans-serif; color: #334155;'>
            <div style='max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05);'>
                
                <!-- Header -->
                <div style='background-color: #3b82f6; padding: 30px 20px; text-align: center;'>
                    <h1 style='color: #ffffff; margin: 0; font-size: 24px; font-weight: bold;'>Disaster Relief Tracker</h1>
                </div>

                <!-- Body -->
                <div style='padding: 40px 30px;'>
                    <h2 style='color: #0f172a; margin-top: 0; font-size: 20px;'>Welcome to the Team, $name!</h2>
                    
                    <p style='font-size: 15px; line-height: 1.6; color: #475569;'>
                        We are thrilled to let you know that your volunteer application has been <strong>approved</strong>. Thank you for stepping up to help the community when it matters most!
                    </p>

                    <p style='font-size: 15px; line-height: 1.6; color: #475569;'>
                        To get started, we have generated a secure temporary password for your account. Please use this to log in to the system.
                    </p>

                    <!-- Password Box -->
                    <div style='background-color: #f1f5f9; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 20px; text-align: center; margin: 30px 0;'>
                        <p style='margin: 0; font-size: 13px; color: #64748b; text-transform: uppercase; letter-spacing: 1px;'>Temporary Password</p>
                        <p style='margin: 10px 0 0 0; font-size: 24px; font-weight: bold; color: #0f172a; letter-spacing: 2px;'>$tempPassword</p>
                    </div>

                    <!-- CTA Button -->
                    <div style='text-align: center; margin: 35px 0;'>
                        <a href='$loginUrl' style='background-color: #3b82f6; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: bold; font-size: 15px; display: inline-block;'>Login to Your Account</a>
                    </div>

                    <div style='background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 15px; margin-top: 30px;'>
                        <p style='margin: 0; font-size: 14px; color: #991b1b;'>
                            <strong>Important:</strong> For your security, please navigate to <em>Settings > My Profile</em> and change this temporary password immediately after your first login.
                        </p>
                    </div>
                </div>

                <!-- Footer -->
                <div style='background-color: #f1f5f9; padding: 20px; text-align: center; font-size: 13px; color: #64748b;'>
                    <p style='margin: 0;'>This is an automated message from the Disaster Relief Tracker.</p>
                    <p style='margin: 5px 0 0 0;'>Please do not reply directly to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        // Still log it locally just in case SMTP fails during testing
        $logFile = __DIR__ . '/../scratch/emails.log';
        $logEntry = "=========================================================\nDate: " . date('Y-m-d H:i:s') . "\nTo: $email\nSubject: $subject\nPassword: $tempPassword\n=========================================================\n\n";
        if (!is_dir(__DIR__ . '/../scratch')) mkdir(__DIR__ . '/../scratch', 0777, true);
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = self::SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = self::SMTP_USER;
            $mail->Password   = self::SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Enable implicit TLS encryption
            $mail->Port       = self::SMTP_PORT;

            // Recipients
            $mail->setFrom(self::SMTP_USER, self::SMTP_FROM_NAME);
            $mail->addAddress($email, $name);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->AltBody = strip_tags(str_replace('<br>', "\n", $message));

            $mail->send();
            return true;
        } catch (Exception $e) {
            // Log the error
            error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * Checks if the given email has a valid and working domain (MX/A records).
     * 
     * @param string $email
     * @return boolean
     */
    public static function isValidEmailDomain($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        $domain = substr(strrchr($email, "@"), 1);
        if (empty($domain)) {
            return false;
        }
        
        // checkdnsrr works on Windows starting PHP 5.3+
        return checkdnsrr($domain, "MX") || checkdnsrr($domain, "A");
    }

    /**
     * Sends a welcome email to a newly created staff member with their temp password.
     */
    public static function sendStaffWelcomeEmail($email, $name, $tempPassword) {
        $subject = "Your Staff Account - Disaster Relief Tracker";
        $loginUrl = "http://localhost/DSRT/login.php";

        $message = "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'></head>
        <body style='margin: 0; padding: 0; background-color: #f8fafc; font-family: Arial, sans-serif; color: #334155;'>
            <div style='max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05);'>
                <div style='background-color: #0f172a; padding: 30px 20px; text-align: center;'>
                    <h1 style='color: #ffffff; margin: 0; font-size: 24px; font-weight: bold;'>Disaster Relief Tracker</h1>
                    <p style='color: #94a3b8; margin: 8px 0 0 0; font-size: 14px;'>Staff Account Created</p>
                </div>
                <div style='padding: 40px 30px;'>
                    <h2 style='color: #0f172a; margin-top: 0; font-size: 20px;'>Welcome, $name!</h2>
                    <p style='font-size: 15px; line-height: 1.6; color: #475569;'>
                        An administrator has created a staff account for you on the Disaster Relief Tracker system.
                        You can now log in using the credentials below.
                    </p>
                    <div style='background-color: #f1f5f9; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 20px; text-align: center; margin: 30px 0;'>
                        <p style='margin: 0; font-size: 13px; color: #64748b; text-transform: uppercase; letter-spacing: 1px;'>Your Email</p>
                        <p style='margin: 6px 0 16px 0; font-size: 16px; font-weight: bold; color: #0f172a;'>$email</p>
                        <p style='margin: 0; font-size: 13px; color: #64748b; text-transform: uppercase; letter-spacing: 1px;'>Temporary Password</p>
                        <p style='margin: 6px 0 0 0; font-size: 24px; font-weight: bold; color: #0f172a; letter-spacing: 2px;'>$tempPassword</p>
                    </div>
                    <div style='text-align: center; margin: 35px 0;'>
                        <a href='$loginUrl' style='background-color: #0f172a; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: bold; font-size: 15px; display: inline-block;'>Login to Your Account</a>
                    </div>
                    <div style='background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 15px;'>
                        <p style='margin: 0; font-size: 14px; color: #991b1b;'>
                            <strong>Important:</strong> Please navigate to <em>Settings &gt; My Profile</em> and change this temporary password immediately after your first login.
                        </p>
                    </div>
                </div>
                <div style='background-color: #f1f5f9; padding: 20px; text-align: center; font-size: 13px; color: #64748b;'>
                    <p style='margin: 0;'>This is an automated message from the Disaster Relief Tracker.</p>
                    <p style='margin: 5px 0 0 0;'>Please do not reply directly to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        // Log locally
        $logFile = __DIR__ . '/../scratch/emails.log';
        $logEntry = "=========================================================\nDate: " . date('Y-m-d H:i:s') . "\nTo: $email\nSubject: $subject\nPassword: $tempPassword\n=========================================================\n\n";
        if (!is_dir(__DIR__ . '/../scratch')) mkdir(__DIR__ . '/../scratch', 0777, true);
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = self::SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = self::SMTP_USER;
            $mail->Password   = self::SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = self::SMTP_PORT;
            $mail->setFrom(self::SMTP_USER, self::SMTP_FROM_NAME);
            $mail->addAddress($email, $name);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->AltBody = strip_tags(str_replace('<br>', "\n", $message));
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Staff welcome email failed: {$mail->ErrorInfo}");
            return false;
        }
    }
}
?>
