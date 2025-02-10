<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('error_log', 'error.log');

require_once 'ResponWebhookFormatter.php';
require_once 'config.php';

define('OLLAMA_API', 'http://<ip-server-ollama>:11434/api/generate');
define('MAX_REQUESTS_PER_MINUTE', 10);
define('CACHE_TIMEOUT', 60);

// Fungsi untuk menyimpan log ke database
function saveWebhookLog($data) {
    global $db;
    try {
        $stmt = $db->prepare("INSERT INTO whatsapp_logs (request_data, ip_address) VALUES (?, ?)");
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt->execute([
            json_encode($data),
            $ip_address
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        return false;
    }
}

// Fungsi untuk membersihkan log lama
function cleanupOldLogs($days = 30) {
    global $db;
    try {
        $stmt = $db->prepare("DELETE FROM whatsapp_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$days]);
        return true;
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        return false;
    }
}

function isUserActive($user_id) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT status FROM whatsapp_users WHERE whatsapp_number = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['status'] === 'active';
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        return false;
    }
}

function updateUserStatus($user_id, $status) {
    global $db;
    try {
        $stmt = $db->prepare("INSERT INTO whatsapp_users (whatsapp_number, status) 
                             VALUES (?, ?) 
                             ON DUPLICATE KEY UPDATE status = ?");
        $stmt->execute([$user_id, $status, $status]);
        return true;
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        return false;
    }
}

function isSpam($user_id) {
    $cache_key = "user_requests_{$user_id}";
    $current_time = time();
    
    if (!isset($_SESSION[$cache_key])) {
        $_SESSION[$cache_key] = [
            'count' => 1,
            'timestamp' => $current_time
        ];
        return false;
    }

    $user_data = $_SESSION[$cache_key];
    if (($current_time - $user_data['timestamp']) > CACHE_TIMEOUT) {
        $_SESSION[$cache_key] = [
            'count' => 1,
            'timestamp' => $current_time
        ];
        return false;
    }

    $user_data['count']++;
    $_SESSION[$cache_key] = $user_data;

    return $user_data['count'] > MAX_REQUESTS_PER_MINUTE;
}

function getQwenResponse($prompt) {
    $data = [
        'model' => 'qwen',
        'prompt' => $prompt,
        'stream' => false,
        'options' => [
            'num_predict' => 500,
            'temperature' => 0.7,
            'top_k' => 40,
            'top_p' => 0.95
        ]
    ];

    $ch = curl_init(OLLAMA_API);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log("Curl Error: " . curl_error($ch));
        return "Maaf, terjadi kesalahan dalam memproses permintaan Anda.";
    }
    
    curl_close($ch);
    $result = json_decode($response, true);
    return $result['response'] ?? 'Error: No response';
}

function saveChat($user_id, $message, $response) {
    global $db;
    try {
        $stmt = $db->prepare("INSERT INTO whatsapp_chat_history (whatsapp_number, role, content) VALUES (?, 'user', ?)");
        $stmt->execute([$user_id, $message]);
        
        $stmt = $db->prepare("INSERT INTO whatsapp_chat_history (whatsapp_number, role, content) VALUES (?, 'assistant', ?)");
        $stmt->execute([$user_id, $response]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        return false;
    }
}

session_start();

header('content-type: application/json; charset=utf-8');
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) die('this url is for webhook.');

// Simpan log ke database
saveWebhookLog($data);

$message = strtolower($data['message']); 
$from = strtolower($data['from']); 
$bufferimage = isset($data['bufferImage']) ? $data['bufferImage'] : null;

$responFormatter = new ResponWebhookFormater();

// Handle commands
if ($message == '/start') {
    updateUserStatus($from, 'active');
    $welcome_message = "👋 Halo! Saya adalah bot AI yang didukung oleh Qwen AI.\n\n"
                    . "Anda dapat menanyakan apa saja kepada saya dan saya akan mencoba membantu Anda.\n\n"
                    . "Beberapa contoh pertanyaan yang bisa Anda ajukan:\n"
                    . "- Jelaskan tentang artificial intelligence\n"
                    . "- Bagaimana cara membuat kue brownies?\n"
                    . "- Apa itu energi terbarukan?\n\n"
                    . "Untuk menghentikan bot, ketik /stop\n\n"
                    . "Silakan mulai bertanya! 😊";
    
    echo $responFormatter->quoted()->line($welcome_message)->responAsText();
    exit;
}

if ($message == '/stop') {
    updateUserStatus($from, 'inactive');
    echo $responFormatter->quoted()->line("Bot telah dinonaktifkan. Untuk mengaktifkan kembali, silakan ketik /start")->responAsText();
    exit;
}

// Jika user belum aktif dan bukan perintah /start, tidak perlu memberikan respons
if (!isUserActive($from)) {
    exit;
}

// Cek spam
if (isSpam($from)) {
    echo $responFormatter->quoted()->line('Mohon tunggu beberapa saat sebelum mengirim pesan lagi.')->responAsText();
    exit;
}

// Cek panjang pesan
if (strlen($message) > 500) {
    echo $responFormatter->quoted()->line('Pesan terlalu panjang. Maksimal 500 karakter.')->responAsText();
    exit;
}

// Get AI Response
$ai_response = getQwenResponse($message);

// Save chat history
saveChat($from, $message, $ai_response);

// Format and send response
echo $responFormatter->quoted()->line($ai_response)->responAsText();
