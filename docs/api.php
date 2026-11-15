<?php
/**
 * LKPlay Panel - API Backend
 * Endpoints para o APK modificado e painel admin
 */

require_once 'config.php';
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ========== AUTH HELPERS ==========
function verifyAdminToken() {
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $token);
    if (!$token) {
        // Check session fallback
        session_start();
        return $_SESSION['admin_logged_in'] ?? false;
    }
    // Simple token validation (in production, use JWT)
    return $token === (ADMIN_USER . ':' . ADMIN_PASS_HASH);
}

function requireAdmin() {
    if (!verifyAdminToken()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autorizado']);
        exit;
    }
}

function generateAdminToken() {
    return base64_encode(ADMIN_USER . ':' . ADMIN_PASS_HASH . ':' . time());
}

// ========== ROUTING ==========
$api = $_GET['api'] ?? $_POST['api'] ?? '';

try {
    switch ($api) {
        // --- PUBLIC ENDPOINTS (APK) ---
        case 'fetch_dns':
            handleFetchDns();
            break;
            
        case 'device_info':
            handleDeviceInfo();
            break;
            
        case 'register_device':
            handleRegisterDevice();
            break;
            
        // --- ADMIN ENDPOINTS ---
        case 'login':
            handleAdminLogin();
            break;
            
        case 'list_devices':
            requireAdmin();
            handleListDevices();
            break;
            
        case 'add_device':
            requireAdmin();
            handleAddDevice();
            break;
            
        case 'edit_device':
            requireAdmin();
            handleEditDevice();
            break;
            
        case 'delete_device':
            requireAdmin();
            handleDeleteDevice();
            break;
            
        case 'list_playlists':
            requireAdmin();
            handleListPlaylists();
            break;
            
        case 'add_playlist':
            requireAdmin();
            handleAddPlaylist();
            break;
            
        case 'edit_playlist':
            requireAdmin();
            handleEditPlaylist();
            break;
            
        case 'delete_playlist':
            requireAdmin();
            handleDeletePlaylist();
            break;
            
        case 'change_password':
            requireAdmin();
            handleChangePassword();
            break;
            
        case 'backup':
            requireAdmin();
            handleBackup();
            break;
            
        case 'clear_database':
            requireAdmin();
            handleClearDatabase();
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint não encontrado']);
    }
} catch (Throwable $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno do servidor']);
}

// ========== HANDLERS ==========

function handleFetchDns() {
    global $pdo;
    $mac = strtoupper(trim($_GET['mac'] ?? ''));
    $device_key = trim($_GET['device_key'] ?? '');
    
    if (!$mac || !$device_key) {
        echo json_encode(['success' => false, 'error' => 'MAC e device_key obrigatórios']);
        return;
    }
    
    if (!preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/i', $mac)) {
        echo json_encode(['success' => false, 'error' => 'Formato de MAC inválido']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE mac_address = ? AND device_key = ? AND status = 'active'");
    $stmt->execute([$mac, $device_key]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$device) {
        echo json_encode(['success' => false, 'error' => 'Dispositivo não encontrado ou inativo']);
        return;
    }
    
    // Update last_seen
    $pdo->prepare("UPDATE devices SET last_seen = NOW() WHERE id = ?")->execute([$device['id']]);
    
    // Get playlist
    $stmt = $pdo->prepare("SELECT * FROM playlists WHERE device_id = ? AND status = 'active'");
    $stmt->execute([$device['id']]);
    $playlist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$playlist) {
        echo json_encode(['success' => false, 'error' => 'Nenhuma playlist configurada para este dispositivo']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'server_url' => $playlist['server_url'],
        'username' => $playlist['username'],
        'password' => $playlist['password'],
        'playlist_type' => $playlist['type'] ?? 'xtream_codes'
    ]);
}

function handleDeviceInfo() {
    global $pdo;
    $mac = strtoupper(trim($_GET['mac'] ?? ''));
    $device_key = trim($_GET['device_key'] ?? '');
    
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE mac_address = ? AND device_key = ?");
    $stmt->execute([$mac, $device_key]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($device) {
        unset($device['device_key']); // Don't expose key in info
        echo json_encode(['success' => true, 'device' => $device]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Dispositivo não encontrado']);
    }
}

function handleRegisterDevice() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $mac = strtoupper(trim($input['mac'] ?? ''));
    $device_key = trim($input['device_key'] ?? '');
    $name = trim($input['name'] ?? 'Dispositivo ' . substr($mac, -6));
    
    if (!$mac || !$device_key) {
        echo json_encode(['success' => false, 'error' => 'MAC e device_key obrigatórios']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO devices (mac_address, device_key, name, status) VALUES (?, ?, ?, 'pending') ON DUPLICATE KEY UPDATE name = VALUES(name)");
        $stmt->execute([$mac, $device_key, $name]);
        echo json_encode(['success' => true, 'message' => 'Dispositivo registrado, aguardando aprovação']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Erro ao registrar dispositivo']);
    }
}

function handleAdminLogin() {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    
    if ($username === ADMIN_USER && password_verify($password, ADMIN_PASS_HASH)) {
        session_start();
        $_SESSION['admin_logged_in'] = true;
        $token = generateAdminToken();
        echo json_encode(['success' => true, 'token' => $token, 'message' => 'Login efetuado com Sucesso']);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Credenciais inválidas']);
    }
}

function handleListDevices() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM devices ORDER BY created_at DESC");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function handleAddDevice() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $mac = strtoupper(trim($input['mac_address'] ?? ''));
    $key = trim($input['device_key'] ?? '');
    $name = trim($input['name'] ?? '');
    $status = $input['status'] ?? 'pending';
    
    if (!$mac || !$key) {
        echo json_encode(['success' => false, 'error' => 'MAC e Device Key são obrigatórios']);
        return;
    }
    
    if (!preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/i', $mac)) {
        echo json_encode(['success' => false, 'error' => 'Formato de MAC inválido (use AA:BB:CC:DD:EE:FF)']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO devices (mac_address, device_key, name, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$mac, $key, $name, $status]);
        echo json_encode(['success' => true, 'message' => 'Dispositivo adicionado com sucesso']);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            echo json_encode(['success' => false, 'error' => 'MAC Address já cadastrado']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erro ao salvar: ' . $e->getMessage()]);
        }
    }
}

function handleEditDevice() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;
    $name = trim($input['name'] ?? '');
    $status = $input['status'] ?? 'pending';
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID obrigatório']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE devices SET name = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$name, $status, $id]);
        echo json_encode(['success' => true, 'message' => 'Dispositivo atualizado com sucesso']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Erro ao atualizar: ' . $e->getMessage()]);
    }
}

function handleDeleteDevice() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;
    
    $stmt = $pdo->prepare("DELETE FROM devices WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Dispositivo excluído']);
}

function handleListPlaylists() {
    global $pdo;
    $stmt = $pdo->query("SELECT p.*, d.mac_address, d.name as device_name FROM playlists p LEFT JOIN devices d ON p.device_id = d.id ORDER BY p.created_at DESC");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function handleAddPlaylist() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $device_id = (int)($input['device_id'] ?? 0);
    $type = $input['type'] ?? 'xtream_codes';
    $server_url = trim($input['server_url'] ?? '');
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');
    $status = $input['status'] ?? 'active';
    
    if (!$device_id || !$server_url || !$username || !$password) {
        echo json_encode(['success' => false, 'error' => 'Todos os campos são obrigatórios']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO playlists (device_id, type, server_url, username, password, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$device_id, $type, $server_url, $username, $password, $status]);
        echo json_encode(['success' => true, 'message' => 'Playlist adicionada com sucesso']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Erro ao salvar: ' . $e->getMessage()]);
    }
}

function handleEditPlaylist() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;
    $type = $input['type'] ?? 'xtream_codes';
    $server_url = trim($input['server_url'] ?? '');
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');
    $status = $input['status'] ?? 'active';
    
    if (!$id || !$server_url || !$username || !$password) {
        echo json_encode(['success' => false, 'error' => 'Todos os campos são obrigatórios']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE playlists SET type = ?, server_url = ?, username = ?, password = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$type, $server_url, $username, $password, $status, $id]);
        echo json_encode(['success' => true, 'message' => 'Playlist atualizada com sucesso']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Erro ao atualizar: ' . $e->getMessage()]);
    }
}

function handleDeletePlaylist() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;
    
    $stmt = $pdo->prepare("DELETE FROM playlists WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Playlist excluída']);
}

function handleChangePassword() {
    $input = json_decode(file_get_contents('php://input'), true);
    $current = $input['current_password'] ?? '';
    $new = $input['new_password'] ?? '';
    $confirm = $input['confirm_password'] ?? '';
    
    if (!password_verify($current, ADMIN_PASS_HASH)) {
        echo json_encode(['success' => false, 'error' => 'Senha atual incorreta']);
        return;
    }
    if ($new !== $confirm) {
        echo json_encode(['success' => false, 'error' => 'Senhas não conferem']);
        return;
    }
    if (strlen($new) < 8) {
        echo json_encode(['success' => false, 'error' => 'Senha deve ter pelo menos 8 caracteres']);
        return;
    }
    
    $newHash = password_hash($new, PASSWORD_DEFAULT);
    echo json_encode([
        'success' => true, 
        'message' => 'Senha alterada! Atualize ADMIN_PASS_HASH no config.php com: ' . $newHash
    ]);
}

function handleBackup() {
    global $pdo;
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="lkplay_backup_' . date('Y-m-d_H-i-s') . '.sql"');
    
    $tables = ['devices', 'playlists'];
    foreach ($tables as $table) {
        $result = $pdo->query("SELECT * FROM $table");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $cols = array_keys($row);
            $vals = array_map(function($v) use ($pdo) { return $pdo->quote($v); }, $row);
            echo "INSERT INTO `$table` (" . implode(',', array_map(fn($c)=>"`$c`", $cols)) . ") VALUES (" . implode(',', $vals) . ");\n";
        }
    }
    exit;
}

function handleClearDatabase() {
    global $pdo;
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    $pdo->exec("TRUNCATE TABLE playlists");
    $pdo->exec("TRUNCATE TABLE devices");
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    echo json_encode(['success' => true, 'message' => 'Banco de dados limpo']);
}