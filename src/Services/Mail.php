<?php

namespace App\Services;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Mail service — wraps PHPMailer with a table-based HTML email template.
 *
 * Why table-based layout?
 * Email clients (Outlook, Gmail, Yahoo, Apple Mail) strip <style> blocks,
 * ignore CSS classes, and do not support flex/grid/border-radius/box-shadow.
 * The only reliable approach is a nested <table> structure with 100% inline
 * styles and HTML presentation attributes (bgcolor, width, cellpadding…).
 *
 * Usage:
 *   $mailer = new Mail();
 *   $mailer->sendEmail(Config::get('MAIL_FROM_ADDRESS'), 'Site', $to, $name, 'Welcome', 'Your account is active.');
 */
final class Mail
{
    private PHPMailer $mail;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);
        $this->configureSMTP();
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function configureSMTP(): void
    {
        $this->mail->isSMTP();
        $this->mail->Host     = Config::get('MAIL_HOST', '');
        $this->mail->SMTPAuth = true;
        $this->mail->Port     = Config::getInt('MAIL_PORT', 587);
        $this->mail->Username = Config::get('MAIL_USERNAME', '');
        $this->mail->Password = Config::get('MAIL_PASSWORD', '');
        $this->mail->CharSet  = 'UTF-8';

        // Encryption: port 465 = SMTPS/SSL, port 587 = STARTTLS
        $encryption = strtolower((string) Config::get('MAIL_ENCRYPTION', 'tls'));
        if ($encryption === 'ssl' || $encryption === 'smtps' || $this->mail->Port === 465) {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $this->mail->SMTPAutoTLS = true;
        } elseif ($encryption === 'tls' || $encryption === 'starttls' || $this->mail->Port === 587) {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->SMTPAutoTLS = true;
        } else {
            $this->mail->SMTPSecure = '';
            $this->mail->SMTPAutoTLS = false;
        }
    }

    /**
     * Builds a table-based HTML email that renders consistently across all
     * major email clients, including Outlook (Word rendering engine).
     */
    private function generateTemplate(
        string $siteUrl,
        string $logoPath,
        string $subject,
        string $body
    ): string {
        $year = date('Y');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$subject}</title>
        </head>
        <!--[if mso]>
        <xml>
            <o:OfficeDocumentSettings>
                <o:AllowPNG/>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
        <![endif]-->
        <body style="margin:0;padding:0;background-color:#f4f4f9;font-family:Arial,Helvetica,sans-serif;">

            <!-- Outer wrapper table -->
            <table role="presentation" border="0" cellpadding="0" cellspacing="0"
                   width="100%" style="background-color:#f4f4f9;">
                <tr>
                    <td align="center" style="padding:30px 10px;">

                        <!-- Email card — 600px max, 100% on mobile -->
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0"
                               width="600" style="max-width:600px;width:100%;background-color:#ffffff;">

                            <!-- ===== HEADER ===== -->
                            <tr>
                                <td align="center" bgcolor="#2A6174"
                                    style="background-color:#2A6174;padding:24px 20px;">
                                    <a href="{$siteUrl}" target="_blank"
                                       style="display:inline-block;text-decoration:none;">
                                        <img src="{$logoPath}" alt="Logo"
                                             width="120" height="auto"
                                             style="display:block;border:0;max-width:120px;">
                                    </a>
                                </td>
                            </tr>

                            <!-- ===== SUBJECT BANNER ===== -->
                            <tr>
                                <td bgcolor="#f0f7ff"
                                    style="background-color:#f0f7ff;
                                           padding:20px 30px 10px 30px;
                                           border-left:4px solid #2A6174;">
                                    <h1 style="margin:0;
                                               font-family:Arial,Helvetica,sans-serif;
                                               font-size:20px;
                                               font-weight:bold;
                                               color:#004f9e;
                                               line-height:1.3;">
                                        {$subject}
                                    </h1>
                                </td>
                            </tr>

                            <!-- ===== BODY ===== -->
                            <tr>
                                <td bgcolor="#ffffff"
                                    style="background-color:#ffffff;
                                           padding:24px 30px 30px 30px;
                                           border-left:4px solid #2A6174;
                                           border-right:4px solid #2A6174;">
                                    <p style="margin:0 0 16px 0;
                                              font-family:Arial,Helvetica,sans-serif;
                                              font-size:15px;
                                              line-height:1.7;
                                              color:#333333;">
                                        {$body}
                                    </p>
                                </td>
                            </tr>

                            <!-- ===== DIVIDER ===== -->
                            <tr>
                                <td bgcolor="#2A6174"
                                    style="background-color:#2A6174;
                                           height:4px;
                                           font-size:0;
                                           line-height:0;">
                                    &nbsp;
                                </td>
                            </tr>

                            <!-- ===== FOOTER ===== -->
                            <tr>
                                <td align="center" bgcolor="#1e4a5c"
                                    style="background-color:#1e4a5c;padding:18px 20px;">
                                    <p style="margin:0;
                                              font-family:Arial,Helvetica,sans-serif;
                                              font-size:13px;
                                              color:#ccddee;
                                              line-height:1.5;">
                                        &copy; {$year} — All rights reserved.
                                    </p>
                                    <p style="margin:6px 0 0 0;
                                              font-family:Arial,Helvetica,sans-serif;
                                              font-size:11px;
                                              color:#8aaabb;">
                                        You are receiving this email because you are registered on our site.
                                    </p>
                                </td>
                            </tr>

                        </table>
                        <!-- /Email card -->

                    </td>
                </tr>
            </table>
            <!-- /Outer wrapper table -->

        </body>
        </html>
        HTML;
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Attaches a file to the next email sent.
     *
     * @param string $filePath Absolute path to the file.
     * @throws \RuntimeException
     */
    public function addAttachment(string $filePath): void
    {
        try {
            $this->mail->addAttachment($filePath);
        } catch (Exception $e) {
            throw new \RuntimeException('Failed to add attachment: ' . $e->getMessage());
        }
    }

    /**
     * Sends an HTML email using the table-based template.
     *
     * @param string               $from      Sender email address.
     * @param string               $fromName  Sender display name.
     * @param string               $to        Recipient email address.
     * @param string               $toName    Recipient display name.
     * @param string               $subject   Email subject.
     * @param string               $body      Main body text (plain text or simple HTML paragraph).
     * @param array<string,string> $headers   Optional extra headers, e.g. ['X-Priority' => '1'].
     *
     * @throws \RuntimeException If sending fails.
     */
    public function sendEmail(
        string $from,
        string $fromName,
        string $to,
        string $toName,
        string $subject,
        string $body,
        array  $headers = []
    ): void {
        try {
            $siteUrl  = Config::baseUrl() . '/';
            $logoPath = $siteUrl . 'assets/imgs/logo.svg';

            $this->mail->setFrom($from, $fromName);
            $this->mail->addAddress($to, $toName);

            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $this->generateTemplate($siteUrl, $logoPath, $subject, $body);
            // Plain-text fallback for clients that don't render HTML.
            $this->mail->AltBody = strip_tags(str_replace(['<br>', '<br/>'], "\n", $body));

            foreach ($headers as $key => $value) {
                $this->mail->addCustomHeader($key, $value);
            }

            $this->mail->send();
        } catch (Exception $e) {
            Logger::channel('mail')->error('Mail send failed', [
                'to'    => $to,
                'error' => $this->mail->ErrorInfo,
            ]);
            throw new \RuntimeException('Failed to send email: ' . $e->getMessage());
        }
    }
}
