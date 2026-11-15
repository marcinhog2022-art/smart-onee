<?php
require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // If database doesn't exist, try to create it
    if ($e->getCode() === '42000' || strpos($e->getMessage(), 'Unknown database') !== false) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE " . DB_NAME);
            
            // Create tables
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS devices (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    mac_address VARCHAR(17) NOT NULL UNIQUE,
                    device_key VARCHAR(255) NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    status ENUM('pending', 'active', 'inactive') DEFAULT 'pending',
                    last_seen TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_mac (mac_address),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS playlists (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    device_id INT NOT NULL,
                    type VARCHAR(50) DEFAULT 'xtream_codes',
                    server_url VARCHAR(500) NOT NULL,
                    username VARCHAR(255) NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    status ENUM('active', 'inactive') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
                    INDEX idx_device (device_id),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // Reconnect to the new database
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e2) {
            die("Erro ao criar banco de dados: " . $e2->getMessage());
        }
    } else {
        die("Erro de conexão com banco de dados: " . $e->getMessage());
    }
}

// Handle admin actions
if (isset($_GET['action']) && ($_SESSION['admin_logged_in'] ?? false)) {
    $action = $_GET['action'];
    
    if ($action === 'add_device' || $action === 'edit_device') {
        $mac = strtoupper(trim($_POST['mac_address'] ?? ''));
        $key = trim($_POST['device_key'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $status = $_POST['status'] ?? 'pending';
        $id = $_POST['id'] ?? null;
        
        if (!$mac || !$key) {
            echo json_encode(['success' => false, 'error' => 'MAC e Device Key são obrigatórios']);
            exit;
        }
        
        // Validate MAC format
        if (!preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/i', $mac)) {
            echo json_encode(['success' => false, 'error' => 'Formato de MAC inválido (use AA:BB:CC:DD:EE:FF)']);
            exit;
        }
        
        try {
            if ($action === 'add_device') {
                $stmt = $pdo->prepare("INSERT INTO devices (mac_address, device_key, name, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$mac, $key, $name, $status]);
                echo json_encode(['success' => true, 'message' => 'Dispositivo adicionado com sucesso']);
            } else {
                $stmt = $pdo->prepare("UPDATE devices SET name = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$name, $status, $id]);
                echo json_encode(['success' => true, 'message' => 'Dispositivo atualizado com sucesso']);
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                echo json_encode(['success' => false, 'error' => 'MAC Address já cadastrado']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erro ao salvar: ' . $e->getMessage()]);
            }
        }
        exit;
    }
    
    if ($action === 'delete_device') {
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM devices WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Dispositivo excluído']);
        exit;
    }
    
    if ($action === 'add_playlist' || $action === 'edit_playlist') {
        $device_id = $_POST['device_id'] ?? 0;
        $type = $_POST['type'] ?? 'xtream_codes';
        $server_url = trim($_POST['server_url'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $id = $_POST['id'] ?? null;
        
        if (!$device_id || !$server_url || !$username || !$password) {
            echo json_encode(['success' => false, 'error' => 'Todos os campos são obrigatórios']);
            exit;
        }
        
        try {
            if ($action === 'add_playlist') {
                $stmt = $pdo->prepare("INSERT INTO playlists (device_id, type, server_url, username, password, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$device_id, $type, $server_url, $username, $password, $status]);
                echo json_encode(['success' => true, 'message' => 'Playlist adicionada com sucesso']);
            } else {
                $stmt = $pdo->prepare("UPDATE playlists SET type = ?, server_url = ?, username = ?, password = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$type, $server_url, $username, $password, $status, $id]);
                echo json_encode(['success' => true, 'message' => 'Playlist atualizada com sucesso']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Erro ao salvar: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'delete_playlist') {
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM playlists WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Playlist excluída']);
        exit;
    }
    
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if (!password_verify($current, ADMIN_PASS_HASH)) {
            echo json_encode(['success' => false, 'error' => 'Senha atual incorreta']);
            exit;
        }
        if ($new !== $confirm) {
            echo json_encode(['success' => false, 'error' => 'Senhas não conferem']);
            exit;
        }
        if (strlen($new) < 8) {
            echo json_encode(['success' => false, 'error' => 'Senha deve ter pelo menos 8 caracteres']);
            exit;
        }
        
        // Note: In production, you'd update the config file or use a database setting
        // For now, just show success (manual config update needed)
        echo json_encode(['success' => true, 'message' => 'Senha alterada! Atualize ADMIN_PASS_HASH no config.php com: ' . password_hash($new, PASSWORD_DEFAULT)]);
        exit;
    }
    
    if ($action === 'backup_database') {
        // Simple SQL dump
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="lkplay_backup_' . date('Y-m-d_H-i-s') . '.sql"');
        
        $tables = ['devices', 'playlists'];
        foreach ($tables as $table) {
            $result = $pdo->query("SELECT * FROM $table");
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $cols = array_keys($row);
                $vals = array_map(function($v) use ($pdo) { return $pdo->quote($v); }, $row);
                echo "INSERT INTO `$table` (" . implode(',', array_map(function($c){return "`$c`";}, $cols)) . ") VALUES (" . implode(',', $vals) . ");\n";
            }
        }
        exit;
    }
    
    if ($action === 'clear_database') {
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $pdo->exec("TRUNCATE TABLE playlists");
        $pdo->exec("TRUNCATE TABLE devices");
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        echo json_encode(['success' => true, 'message' => 'Banco de dados limpo']);
        exit;
    }
}