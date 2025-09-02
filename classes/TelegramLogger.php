<?php

class TelegramLogger {
    private $botToken;
    private $chatId;
    private $enabled;
    
    public function __construct($botToken = null, $chatId = null, $enabled = true) {
        $this->botToken = $botToken;
        $this->chatId = $chatId;
        $this->enabled = $enabled;
    }
    
    public function log($level, $message, $context = []) {
        if (!$this->enabled || !$this->botToken || !$this->chatId) {
            return false;
        }
        
        $timestamp = date('d/m/Y H:i:s');
        $emoji = $this->getEmojiForLevel($level);
        
        $text = "$emoji *[$level]* - $timestamp\n";
        $text .= "📄 *Arquivo:* payment.php\n";
        $text .= "💬 *Mensagem:* $message\n";
        
        if (!empty($context)) {
            $text .= "📋 *Contexto:*\n```\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n```";
        }
        
        return $this->sendToTelegram($text);
    }
    
    private function getEmojiForLevel($level) {
        $emojis = [
            'ERROR' => '🚨',
            'WARNING' => '⚠️',
            'INFO' => 'ℹ️',
            'SUCCESS' => '✅',
            'DEBUG' => '🔍'
        ];
        
        return $emojis[$level] ?? '📝';
    }
    
    private function sendToTelegram($message) {
        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        
        $data = [
            'chat_id' => $this->chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    }
    
    public function error($message, $context = []) {
        return $this->log('ERROR', $message, $context);
    }
    
    public function warning($message, $context = []) {
        return $this->log('WARNING', $message, $context);
    }
    
    public function info($message, $context = []) {
        return $this->log('INFO', $message, $context);
    }
    
    public function success($message, $context = []) {
        return $this->log('SUCCESS', $message, $context);
    }
    
    public function debug($message, $context = []) {
        return $this->log('DEBUG', $message, $context);
    }
}