<?php
class MoltMailer
{
    private $host;
    private $port;
    private $encryption;
    private $username;
    private $password;
    private $fromEmail;
    private $fromName;
    private $socket;
    private $log = [];

    public function __construct($config = null)
    {
        if (!$config) {
            $config = require realpath(__DIR__ . '/../../config/smtp.php');
        }
        $this->host = $config['host'];
        $this->port = $config['port'] ?? 465;
        $this->encryption = $config['encryption'] ?? 'ssl';
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->fromEmail = $config['from_email'];
        $this->fromName = $config['from_name'] ?? 'MoltDeals';
    }

    public function send($to, $subject, $htmlBody)
    {
        try {
            // Try SSL first (port 465), fallback to TLS (port 587)
            $prefix = ($this->encryption === 'ssl') ? 'ssl://' : 'tls://';
            
            $ctx = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ]
            ]);
            
            $this->socket = stream_socket_client(
                $prefix . $this->host . ':' . $this->port,
                $errno, $errstr, 15,
                STREAM_CLIENT_CONNECT,
                $ctx
            );
            
            if (!$this->socket) {
                throw new Exception("Connection failed: $errstr ($errno)");
            }
            
            stream_set_timeout($this->socket, 10);
            
            $this->getResponse(); // greeting
            $this->sendCommand("EHLO moltdeals.net");
            
            // If using TLS on 587, need STARTTLS
            if ($this->port == 587) {
                $this->sendCommand("STARTTLS");
                stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
                $this->sendCommand("EHLO moltdeals.net");
            }
            
            $this->sendCommand("AUTH LOGIN");
            $this->sendCommand(base64_encode($this->username));
            $this->sendCommand(base64_encode($this->password));
            $this->sendCommand("MAIL FROM:<{$this->fromEmail}>");
            $this->sendCommand("RCPT TO:<$to>");
            $this->sendCommand("DATA");

            $boundary = md5(uniqid(time()));
            $headers = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
            $headers .= "To: $to\r\n";
            $headers .= "Subject: $subject\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "Message-ID: <" . md5(uniqid()) . "@moltdeals.net>\r\n";
            $headers .= "\r\n";

            // Escape any leading dots in body
            $body = str_replace("\r\n.", "\r\n..", $htmlBody);
            
            fwrite($this->socket, $headers . $body . "\r\n.\r\n");
            $this->getResponse();
            $this->sendCommand("QUIT");
            fclose($this->socket);

            return ['ok' => true];
        } catch (Exception $e) {
            if ($this->socket) @fclose($this->socket);
            return ['ok' => false, 'error' => $e->getMessage(), 'log' => $this->log];
        }
    }

    private function sendCommand($cmd)
    {
        fwrite($this->socket, $cmd . "\r\n");
        $logCmd = (strpos($cmd, 'AUTH') !== false || strlen($cmd) < 50) ? $cmd : substr($cmd, 0, 50) . '...';
        $this->log[] = ">>> $logCmd";
        return $this->getResponse();
    }

    private function getResponse()
    {
        $response = '';
        $deadline = time() + 10;
        while (time() < $deadline) {
            $line = fgets($this->socket, 515);
            if ($line === false) break;
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $this->log[] = "<<< " . trim(substr($response, 0, 200));
        $code = (int)substr($response, 0, 3);
        if ($code >= 400) throw new Exception("SMTP Error ($code): " . trim($response));
        return $response;
    }

    public function getLog() { return $this->log; }
}