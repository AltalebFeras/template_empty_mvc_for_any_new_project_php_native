<?php

namespace src\Services;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/../../vendor/autoload.php';

final class Mail
{
    private $mail;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);
        $this->configureSMTP();
    }
    private function configureSMTP()
    {
        // SMTP configuration
        $this->mail->isSMTP();
        $this->mail->Host = HOST;
        $this->mail->SMTPAuth = true;
        $this->mail->Port = PORT;
        $this->mail->Username = USERNAME;
        $this->mail->Password = PASSWORD;

        // For email on server side only, it uses the SSL protocol
        if (IS_PROD === true) {
            $this->mail->SMTPSecure = 'ssl';
        }
    }
    private function generateTemplate($HOME_URL, $DOMAIN, $logoPath, $subject, $body)
    {
        return "
        <!DOCTYPE html>
        <html>
            <head>
                <meta charset='utf-8'>
                <meta http-equiv='x-ua-compatible' content='ie=edge'>
                <title>{$subject}</title>
                <meta name='viewport' content='width=device-width, initial-scale=1'>
                <style>
                    /* Global Styles */
                    body {
                        font-family: Arial, sans-serif;
                        line-height: 1.6;
                        color: #333;
                        background-color: #f4f4f9;
                        margin: 20px;
                        padding: 0px ;
                    }
                    .container {
                        max-width: 600px;
                        margin: 20px auto;
                        background: #fff;
                        border-radius: 8px;
                        overflow: hidden;
                        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                    }
                    .email-header {
                        background:rgb(119, 233, 102);
                        padding: 20px;
                        text-align: center;
                    }
                    .email-header .logo {
                        max-width: 120px;
                    }
                    .email-content {
                        border-left: 1px solid black; 
                        border-right: 1px solid black;
                        padding: 20px;
                    }
                    .email-content h3 {
                        color: #004f9e;
                        margin-bottom: 10px;
                    }
                    h3 {
                        font-size: 18px;
                    }
                    .email-content p {
                        font-size: 16px;
                        margin-bottom: 15px;
                    }
                    .footer-text {
                        background: #2A6174;
                        text-align: center;
                        padding: 15px;
                        font-size: 14px;
                        color: white;
                    }
                    .footer-text p {
                        margin: 0;
                    }
                </style>
            </head>

            <body>
                <div class='container'>
                    <!-- Email Header -->
                    <div class='email-header'>
                    <a href='{$DOMAIN}{$HOME_URL}'><img src='{$logoPath}' alt='Logo de site tirage au sort' class='logo'></a>
                    </div>

                    <!-- Email Content -->
                    <div class='email-content'>
                        <h3>{$subject}</h3>
                        <p>{$body}</p>
                    </div>

                    <!-- Email Footer -->
                    <div class='footer-text'>
                        <p>&copy; " . date('Y') . " TIRSO. Tous droits réservés.</p>
                    </div>
                </div>
            </body>
        </html>";
    }

    public function addAttachment($filePath)
    {
        try {
            $this->mail->addAttachment($filePath);
        } catch (Exception $e) {
            throw new \Exception("Failed to add attachment: " . $e->getMessage());
        }
    }

    /**
     * Sends an HTML email using the configured PHPMailer instance.
     *
     * This method sets the character encoding to UTF-8 to support multilingual content,
     * generates a styled HTML email using a template, sets sender and recipient information,
     * and optionally applies custom headers before sending the email.
     *
     * @param string $from       The sender's email address.
     * @param string $fromName   The sender's display name.
     * @param string $to         The recipient's email address.
     * @param string $toName     The recipient's display name.
     * @param string $subject    The subject of the email.
     * @param string $body       The main body content of the email (before templating).
     * @param array  $headers    Optional associative array of additional headers (e.g., ['X-Priority' => '1']).
     *
     * @throws \Exception        If email sending fails or PHPMailer throws an error.
     *
     * @return void
     */

    public function sendEmail($from, $fromName, $to, $toName, $subject, $body, $headers = [])
    {
        try {
            // Set the charset to UTF-8 for French and English compatibility
            $this->mail->CharSet = 'UTF-8';

            // Generate the template with the subject and body
            $HOME_URL = HOME_URL;
            $DOMAIN = DOMAIN;
            $logoPath = DOMAIN . HOME_URL . 'assets/images/logo.jpg';
            $emailContent = $this->generateTemplate($HOME_URL, $DOMAIN, $logoPath, $subject, $body);

            // Recipients
            $this->mail->setFrom($from, $fromName);
            $this->mail->addAddress($to, $toName);

            // Content
            $this->mail->isHTML(true);
            $this->mail->Subject = mb_encode_mimeheader($subject, 'UTF-8');
            $this->mail->Body = $emailContent;

            // Apply custom headers
            foreach ($headers as $key => $value) {
                $this->mail->addCustomHeader($key, $value);
            }

            // Send the email
            $this->mail->send();
        } catch (Exception $e) {
            error_log("Mailer Error: {$this->mail->ErrorInfo}");
            throw new \Exception("Failed to send email: " . $e->getMessage());
        }
    }
}
