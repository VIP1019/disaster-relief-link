<?php
/**
 * Custom Mailtrap SMTP Integration API
 * Native PHP implementation (Dependency-Free)
 */
class Mailer {
    private static $host = "sandbox.smtp.mailtrap.io";
    private static $port = 2525;
    
    // REPLACE THESE WITH YOUR MAILTRAP CREDENTIALS
    private static $username = "11f6e0e09802f3"; 
    private static $password = "1e8c72db1edb09"; 

    public static function sendEmail($toEmail, $toName, $subject, $htmlMessage) {
        if (self::$username === 'PASTE_YOUR_USERNAME_HERE') {
            error_log("Mailtrap API credentials missing. Email skipped.");
            return false;
        }

        // Native PHP Socket Connection (Extremely fast, zero dependencies)
        $socket = fsockopen(self::$host, self::$port, $errno, $errstr, 10);
        if (!$socket) {
            error_log("Failed to connect to Mailtrap API: $errstr ($errno)");
            return false;
        }

        $res = function($socket) {
            $data = "";
            while($str = fgets($socket, 515)) {
                $data .= $str;
                if(substr($str,3,1) == " ") break;
            }
            return $data;
        };

        $res($socket); // read greeting
        
        fputs($socket, "EHLO localhost\r\n");
        $res($socket);
        
        fputs($socket, "AUTH LOGIN\r\n");
        $res($socket);
        fputs($socket, base64_encode(self::$username) . "\r\n");
        $res($socket);
        fputs($socket, base64_encode(self::$password) . "\r\n");
        $res($socket);
        
        fputs($socket, "MAIL FROM: <no-reply@relieflink.gov.ph>\r\n");
        $res($socket);
        
        fputs($socket, "RCPT TO: <$toEmail>\r\n");
        $res($socket);
        
        fputs($socket, "DATA\r\n");
        $res($socket);
        
        $headers = "From: ReliefLink Alert <no-reply@relieflink.gov.ph>\r\n";
        $headers .= "To: $toName <$toEmail>\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        $body = $headers . "\r\n" . $htmlMessage . "\r\n.\r\n";
        fputs($socket, $body);
        $res($socket);
        
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        return true;
    }
}
?>
