<?php
session_start();
require_once 'config.php';
require_once 'db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'dashboard';
$page = $_GET['page'] ?? 'dashboard';

// Handle API calls from APK
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    handleApiRequest();
    exit;
}

// Handle admin login
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username === ADMIN_USER && password_verify($password, ADMIN_PASS_HASH)) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit;
    }
    $error = 'Credenciais inválidas';
}

// Handle admin logout
if ($action === 'logout') {
    session_destroy();
    header('Location: index.php?action=login');
    exit;
}

// Require admin login for admin pages
$adminPages = ['dashboard', 'devices', 'playlists', 'settings'];
if (in_array($page, $adminPages) && !($_SESSION['admin_logged_in'] ?? false)) {
    $page = 'login';
}

function handleApiRequest() {
    global $pdo;
    $endpoint = $_GET['api'];
    
    switch ($endpoint) {
        case 'fetch_dns':
            // App calls this to get playlist for a device
            $mac = $_GET['mac'] ?? '';
            $device_key = $_GET['device_key'] ?? '';
            
            if (!$mac || !$device_key) {
                echo json_encode(['error' => 'MAC e device_key obrigatórios']);
                return;
            }
            
            $stmt = $pdo->prepare("SELECT * FROM devices WHERE mac_address = ? AND device_key = ? AND status = 'active'");
            $stmt->execute([$mac, $device_key]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$device) {
                echo json_encode(['error' => 'Dispositivo não encontrado ou inativo']);
                return;
            }
            
            // Get playlist for this device
            $stmt = $pdo->prepare("SELECT * FROM playlists WHERE device_id = ? AND status = 'active'");
            $stmt->execute([$device['id']]);
            $playlist = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$playlist) {
                echo json_encode(['error' => 'Nenhuma playlist configurada para este dispositivo']);
                return;
            }
            
            // Return Xtream Codes format
            echo json_encode([
                'success' => true,
                'server_url' => $playlist['server_url'],
                'username' => $playlist['username'],
                'password' => $playlist['password'],
                'playlist_type' => $playlist['type'] ?? 'xtream_codes'
            ]);
            break;
            
        case 'device_info':
            // Get device info for display
            $mac = $_GET['mac'] ?? '';
            $device_key = $_GET['device_key'] ?? '';
            
            $stmt = $pdo->prepare("SELECT * FROM devices WHERE mac_address = ? AND device_key = ?");
            $stmt->execute([$mac, $device_key]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($device) {
                echo json_encode(['success' => true, 'device' => $device]);
            } else {
                echo json_encode(['error' => 'Dispositivo não encontrado']);
            }
            break;
            
        case 'register_device':
            // Register new device (optional - for automatic registration)
            $mac = $_POST['mac'] ?? '';
            $device_key = $_POST['device_key'] ?? '';
            $name = $_POST['name'] ?? 'Dispositivo ' . substr($mac, -6);
            
            if (!$mac || !$device_key) {
                echo json_encode(['error' => 'MAC e device_key obrigatórios']);
                return;
            }
            
            try {
                $stmt = $pdo->prepare("INSERT INTO devices (mac_address, device_key, name, status) VALUES (?, ?, ?, 'pending') ON DUPLICATE KEY UPDATE name = VALUES(name)");
                $stmt->execute([$mac, $device_key, $name]);
                echo json_encode(['success' => true, 'message' => 'Dispositivo registrado, aguardando aprovação']);
            } catch (PDOException $e) {
                echo json_encode(['error' => 'Erro ao registrar dispositivo']);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Endpoint não encontrado']);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LKPlay - Painel de Gerenciamento</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0d1117; color: #e6edf3; min-height: 100vh; }
        
        /* Login Page */
        .login-container { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        .login-box { background: #161b22; border: 1px solid #30363d; border-radius: 12px; padding: 40px; width: 100%; max-width: 400px; }
        .login-header { text-align: center; margin-bottom: 32px; }
        .login-header h1 { font-size: 28px; font-weight: 600; color: #58a6ff; margin-bottom: 8px; }
        .login-header p { color: #8b949e; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: #e6edf3; }
        .form-group input { width: 100%; padding: 12px 16px; background: #0d1117; border: 1px solid #30363d; border-radius: 6px; color: #e6edf3; font-size: 14px; transition: border-color 0.2s; }
        .form-group input:focus { outline: none; border-color: #58a6ff; }
        .btn { width: 100%; padding: 12px; background: #238636; border: none; border-radius: 6px; color: white; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn:hover { background: #2ea043; }
        .btn-secondary { background: #30363d; color: #e6edf3; }
        .btn-secondary:hover { background: #484f58; }
        .btn-danger { background: #da3633; }
        .btn-danger:hover { background: #f85149; }
        .error-msg { background: #da3633; color: white; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; text-align: center; }
        .info-box { background: #1f6feb; color: white; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; line-height: 1.5; }
        
        /* Dashboard Layout */
        .sidebar { width: 260px; background: #161b22; border-right: 1px solid #30363d; height: 100vh; position: fixed; left: 0; top: 0; display: flex; flex-direction: column; z-index: 100; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid #30363d; }
        .sidebar-header h2 { font-size: 18px; font-weight: 600; color: #58a6ff; }
        .nav-list { list-style: none; flex: 1; padding: 16px 12px; overflow-y: auto; }
        .nav-item { margin-bottom: 4px; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 6px; color: #8b949e; text-decoration: none; font-size: 14px; font-weight: 500; transition: all 0.2s; }
        .nav-link:hover, .nav-link.active { background: #238636; color: white; }
        .nav-link svg { width: 20px; height: 20px; flex-shrink: 0; }
        .sidebar-footer { padding: 16px; border-top: 1px solid #30363d; }
        .user-info { display: flex; align-items: center; gap: 12px; padding: 8px; }
        .user-avatar { width: 32px; height: 32px; border-radius: 50%; background: #238636; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; }
        .user-details { flex: 1; min-width: 0; }
        .user-name { font-size: 13px; font-weight: 600; color: #e6edf3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-role { font-size: 11px; color: #8b949e; }
        
        .main-content { margin-left: 260px; padding: 32px; min-height: 100vh; }
        .page-header { margin-bottom: 32px; }
        .page-header h1 { font-size: 28px; font-weight: 600; color: #e6edf3; margin-bottom: 8px; }
        .page-header p { color: #8b949e; font-size: 14px; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 32px; }
        .stat-card { background: #161b22; border: 1px solid #30363d; border-radius: 12px; padding: 24px; }
        .stat-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; font-size: 24px; }
        .stat-icon.devices { background: rgba(88, 166, 255, 0.15); color: #58a6ff; }
        .stat-icon.playlists { background: rgba(63, 185, 80, 0.15); color: #3fb950; }
        .stat-icon.pending { background: rgba(218, 54, 51, 0.15); color: #da3633; }
        .stat-value { font-size: 32px; font-weight: 700; color: #e6edf3; margin-bottom: 4px; }
        .stat-label { font-size: 13px; color: #8b949e; }
        
        /* Tables */
        .table-container { background: #161b22; border: 1px solid #30363d; border-radius: 12px; overflow: hidden; }
        .table-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid #30363d; }
        .table-header h3 { font-size: 16px; font-weight: 600; color: #e6edf3; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 16px 24px; text-align: left; border-bottom: 1px solid #21262d; }
        th { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #8b949e; background: #161b22; }
        td { font-size: 14px; color: #e6edf3; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #1c2128; }
        .badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge.active { background: rgba(63, 185, 80, 0.15); color: #3fb950; }
        .badge.pending { background: rgba(218, 54, 51, 0.15); color: #da3633; }
        .badge.inactive { background: rgba(139, 148, 158, 0.15); color: #8b949e; }
        .mac-address { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 13px; }
        .device-key { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 12px; color: #8b949e; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .action-btns { display: flex; gap: 8px; }
        
        /* Forms */
        .form-section { background: #161b22; border: 1px solid #30363d; border-radius: 12px; padding: 24px; margin-bottom: 24px; }
        .form-section h3 { font-size: 16px; font-weight: 600; margin-bottom: 20px; color: #e6edf3; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 16px; }
        .form-actions { display: flex; gap: 12px; margin-top: 24px; padding-top: 24px; border-top: 1px solid #30363d; }
        
        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
        .modal-overlay.active { display: flex; }
        .modal { background: #161b22; border: 1px solid #30363d; border-radius: 12px; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid #30363d; }
        .modal-header h3 { font-size: 16px; font-weight: 600; }
        .modal-close { background: none; border: none; color: #8b949e; font-size: 20px; cursor: pointer; padding: 4px; }
        .modal-body { padding: 24px; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 12px; padding: 20px 24px; border-top: 1px solid #30363d; }
        
        /* Device Form */
        .device-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 600px) { .device-form-grid { grid-template-columns: 1fr; } }
        
        /* Empty State */
        .empty-state { text-align: center; padding: 60px 20px; color: #8b949e; }
        .empty-state svg { width: 64px; height: 64px; margin-bottom: 16px; opacity: 0.5; }
        .empty-state h3 { font-size: 18px; color: #e6edf3; margin-bottom: 8px; }
        
        /* Toast */
        .toast { position: fixed; bottom: 24px; right: 24px; padding: 16px 24px; border-radius: 8px; font-size: 14px; font-weight: 500; z-index: 2000; transform: translateY(100px); opacity: 0; transition: all 0.3s; }
        .toast.show { transform: translateY(0); opacity: 1; }
        .toast.success { background: #238636; color: white; }
        .toast.error { background: #da3633; color: white; }
        .toast.info { background: #1f6feb; color: white; }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-menu-btn { display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: #161b22; border: 1px solid #30363d; border-radius: 6px; color: #e6edf3; }
        }
        .mobile-menu-btn { display: none; }
    </style>
</head>
<body>
    <!-- Toast Container -->
    <div id="toastContainer"></div>

    <?php if ($page === 'login'): ?>
    <!-- Login Page -->
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>LKPlay Panel</h1>
                <p>Painel de Gerenciamento de Playlists IPTV</p>
            </div>
            
            <?php if (isset($error)): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="info-box">
                <strong>API Endpoints para o APK:</strong><br>
                <code>?api=fetch_dns&mac=XX:XX:XX:XX:XX:XX&device_key=KEY</code><br>
                <code>?api=device_info&mac=XX:XX:XX:XX:XX:XX&device_key=KEY</code>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="username">Usuário</label>
                    <input type="text" id="username" name="username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn">Entrar</button>
            </form>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Dashboard Layout -->
    <button class="mobile-menu-btn" id="menuBtn" onclick="toggleSidebar()">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
    </button>
    
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>LKPlay Panel</h2>
        </div>
        <nav>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="index.php?page=dashboard" class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect></svg>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="index.php?page=devices" class="nav-link <?= $page === 'devices' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"></rect><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4" y1="22" x2="20" y2="22"></line></svg>
                        Dispositivos
                    </a>
                </li>
                <li class="nav-item">
                    <a href="index.php?page=playlists" class="nav-link <?= $page === 'playlists' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"></path><polyline points="19 9 19 15 15 15"></polyline><path d="M5 9h14"></path></svg>
                        Playlists
                    </a>
                </li>
                <li class="nav-item">
                    <a href="index.php?page=settings" class="nav-link <?= $page === 'settings' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                        Configurações
                    </a>
                </li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">A</div>
                <div class="user-details">
                    <div class="user-name">Administrador</div>
                    <div class="user-role">Admin</div>
                </div>
                <a href="index.php?action=logout" class="btn btn-secondary btn-sm" style="width: auto; margin-top: 12px;">Sair</a>
            </div>
        </div>
    </aside>
    
    <main class="main-content">
        <?php if ($page === 'dashboard'): ?>
        <!-- Dashboard -->
        <div class="page-header">
            <h1>Dashboard</h1>
            <p>Visão geral do painel de gerenciamento</p>
        </div>
        
        <?php
        $stats = [];
        $stats['devices'] = $pdo->query("SELECT COUNT(*) FROM devices")->fetchColumn();
        $stats['active_devices'] = $pdo->query("SELECT COUNT(*) FROM devices WHERE status = 'active'")->fetchColumn();
        $stats['playlists'] = $pdo->query("SELECT COUNT(*) FROM playlists")->fetchColumn();
        $stats['pending'] = $pdo->query("SELECT COUNT(*) FROM devices WHERE status = 'pending'")->fetchColumn();
        ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon devices"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"></rect><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4" y1="22" x2="20" y2="22"></line></svg></div>
                <div class="stat-value"><?= $stats['devices'] ?></div>
                <div class="stat-label">Total de Dispositivos</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon devices"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg></div>
                <div class="stat-value"><?= $stats['active_devices'] ?></div>
                <div class="stat-label">Dispositivos Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon playlists"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"></path><polyline points="19 9 19 15 15 15"></polyline><path d="M5 9h14"></path></svg></div>
                <div class="stat-value"><?= $stats['playlists'] ?></div>
                <div class="stat-label">Playlists Configuradas</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon pending"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg></div>
                <div class="stat-value"><?= $stats['pending'] ?></div>
                <div class="stat-label">Pendentes de Aprovação</div>
            </div>
        </div>
        
        <div class="table-container">
            <div class="table-header">
                <h3>Dispositivos Recentes</h3>
                <a href="index.php?page=devices" class="btn btn-secondary btn-sm">Ver Todos</a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>MAC Address</th>
                        <th>Device Key</th>
                        <th>Nome</th>
                        <th>Status</th>
                        <th>Última Conexão</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM devices ORDER BY created_at DESC LIMIT 10");
                    while ($device = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr>
                        <td><span class="mac-address"><?= htmlspecialchars($device['mac_address']) ?></span></td>
                        <td><span class="device-key"><?= htmlspecialchars(substr($device['device_key'], 0, 12)) ?>...</span></td>
                        <td><?= htmlspecialchars($device['name']) ?></td>
                        <td><span class="badge <?= $device['status'] ?>"><?= ucfirst($device['status']) ?></span></td>
                        <td><?= $device['last_seen'] ? date('d/m/Y H:i', strtotime($device['last_seen'])) : 'Nunca' ?></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn btn-secondary btn-sm" onclick="editDevice(<?= json_encode($device) ?>)">Editar</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteDevice(<?= $device['id'] ?>)">Excluir</button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($stats['devices'] === 0): ?>
                    <tr><td colspan="6"><div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="2" width="16" height="20" rx="2"></rect><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4" y1="22" x2="20" y2="22"></line></svg><h3>Nenhum dispositivo</h3><p>Dispositivos aparecerão aqui após o primeiro acesso</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php elseif ($page === 'devices'): ?>
        <!-- Devices Page -->
        <div class="page-header">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1>Dispositivos</h1>
                    <p>Gerencie os dispositivos autorizados a acessar playlists</p>
                </div>
                <button class="btn" onclick="openDeviceModal()">+ Novo Dispositivo</button>
            </div>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>MAC Address</th>
                        <th>Device Key</th>
                        <th>Nome</th>
                        <th>Status</th>
                        <th>Criado em</th>
                        <th>Última Conexão</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM devices ORDER BY created_at DESC");
                    while ($device = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr>
                        <td><span class="mac-address"><?= htmlspecialchars($device['mac_address']) ?></span></td>
                        <td><span class="device-key"><?= htmlspecialchars($device['device_key']) ?></span></td>
                        <td><?= htmlspecialchars($device['name']) ?></td>
                        <td><span class="badge <?= $device['status'] ?>"><?= ucfirst($device['status']) ?></span></td>
                        <td><?= date('d/m/Y H:i', strtotime($device['created_at'])) ?></td>
                        <td><?= $device['last_seen'] ? date('d/m/Y H:i', strtotime($device['last_seen'])) : 'Nunca' ?></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn btn-secondary btn-sm" onclick="editDevice(<?= json_encode($device) ?>)">Editar</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteDevice(<?= $device['id'] ?>)">Excluir</button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($stats['devices'] === 0): ?>
                    <tr><td colspan="7"><div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="2" width="16" height="20" rx="2"></rect><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4" y1="22" x2="20" y2="22"></line></svg><h3>Nenhum dispositivo cadastrado</h3><p>Clique em "Novo Dispositivo" para adicionar</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php elseif ($page === 'playlists'): ?>
        <!-- Playlists Page -->
        <div class="page-header">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1>Playlists</h1>
                    <p>Configure playlists Xtream Codes para os dispositivos</p>
                </div>
                <button class="btn" onclick="openPlaylistModal()">+ Nova Playlist</button>
            </div>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Dispositivo</th>
                        <th>Tipo</th>
                        <th>URL do Servidor</th>
                        <th>Usuário</th>
                        <th>Status</th>
                        <th>Criado em</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT p.*, d.mac_address, d.name as device_name FROM playlists p LEFT JOIN devices d ON p.device_id = d.id ORDER BY p.created_at DESC");
                    while ($playlist = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr>
                        <td>
                            <div class="mac-address"><?= htmlspecialchars($playlist['device_name'] ?? 'N/A') ?></div>
                            <div class="device-key"><?= htmlspecialchars($playlist['mac_address'] ?? 'N/A') ?></div>
                        </td>
                        <td><?= htmlspecialchars($playlist['type'] ?? 'xtream_codes') ?></td>
                        <td><span class="device-key"><?= htmlspecialchars($playlist['server_url']) ?></span></td>
                        <td><?= htmlspecialchars($playlist['username']) ?></td>
                        <td><span class="badge <?= $playlist['status'] ?>"><?= ucfirst($playlist['status']) ?></span></td>
                        <td><?= date('d/m/Y H:i', strtotime($playlist['created_at'])) ?></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn btn-secondary btn-sm" onclick="editPlaylist(<?= json_encode($playlist) ?>)">Editar</button>
                                <button class="btn btn-danger btn-sm" onclick="deletePlaylist(<?= $playlist['id'] ?>)">Excluir</button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($stats['playlists'] === 0): ?>
                    <tr><td colspan="7"><div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"></path><polyline points="19 9 19 15 15 15"></polyline><path d="M5 9h14"></path></svg><h3>Nenhuma playlist configurada</h3><p>Adicione playlists Xtream Codes para os dispositivos</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php elseif ($page === 'settings'): ?>
        <!-- Settings Page -->
        <div class="page-header">
            <h1>Configurações</h1>
            <p>Configurações do painel e credenciais de acesso</p>
        </div>
        
        <div class="form-section">
            <h3>Credenciais de Administrador</h3>
            <form method="POST" action="index.php?page=settings&action=change_password">
                <input type="hidden" name="action" value="change_password">
                <div class="form-row">
                    <div class="form-group">
                        <label>Senha Atual</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>Nova Senha</label>
                        <input type="password" name="new_password" required minlength="8">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Confirmar Nova Senha</label>
                        <input type="password" name="confirm_password" required minlength="8">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn">Alterar Senha</button>
                </div>
            </form>
        </div>
        
        <div class="form-section">
            <h3>Informações da API</h3>
            <div class="info-box">
                <strong>Endpoints disponíveis para o APK modificado:</strong><br><br>
                <code>GET /index.php?api=fetch_dns&mac=XX:XX:XX:XX:XX:XX&device_key=KEY</code><br>
                Retorna a playlist configurada para o dispositivo no formato Xtream Codes.<br><br>
                <code>GET /index.php?api=device_info&mac=XX:XX:XX:XX:XX:XX&device_key=KEY</code><br>
                Retorna informações do dispositivo.<br><br>
                <strong>URL base para configurar no APK:</strong> <code><?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) ?></code>
            </div>
        </div>
        
        <div class="form-section">
            <h3>Banco de Dados</h3>
            <div class="form-actions">
                <button class="btn btn-secondary" onclick="backupDatabase()">Fazer Backup</button>
                <button class="btn btn-danger" onclick="if(confirm('Tem certeza? Isso apagará todos os dados!')) clearDatabase()">Limpar Tudo</button>
            </div>
        </div>
        
        <?php endif; ?>
    </main>
    
    <!-- Device Modal -->
    <div class="modal-overlay" id="deviceModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="deviceModalTitle">Novo Dispositivo</h3>
                <button class="modal-close" onclick="closeModal('deviceModal')">&times;</button>
            </div>
            <form id="deviceForm" onsubmit="saveDevice(event)">
                <div class="modal-body">
                    <input type="hidden" name="id" id="deviceId">
                    <div class="form-row device-form-grid">
                        <div class="form-group">
                            <label>MAC Address *</label>
                            <input type="text" name="mac_address" id="deviceMac" required placeholder="AA:BB:CC:DD:EE:FF" style="text-transform: uppercase;">
                        </div>
                        <div class="form-group">
                            <label>Device Key *</label>
                            <input type="text" name="device_key" id="deviceKey" required placeholder="Chave do dispositivo">
                        </div>
                        <div class="form-group">
                            <label>Nome</label>
                            <input type="text" name="name" id="deviceName" placeholder="Nome do dispositivo">
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" id="deviceStatus">
                                <option value="pending">Pendente</option>
                                <option value="active">Ativo</option>
                                <option value="inactive">Inativo</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deviceModal')">Cancelar</button>
                    <button type="submit" class="btn">Salvar</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Playlist Modal -->
    <div class="modal-overlay" id="playlistModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="playlistModalTitle">Nova Playlist</h3>
                <button class="modal-close" onclick="closeModal('playlistModal')">&times;</button>
            </div>
            <form id="playlistForm" onsubmit="savePlaylist(event)">
                <div class="modal-body">
                    <input type="hidden" name="id" id="playlistId">
                    <div class="form-group">
                        <label>Dispositivo *</label>
                        <select name="device_id" id="playlistDevice" required>
                            <option value="">Selecione um dispositivo</option>
                            <?php
                            $stmt = $pdo->query("SELECT id, mac_address, name FROM devices WHERE status = 'active' ORDER BY name");
                            while ($d = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name'] . ' (' . $d['mac_address'] . ')') ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tipo</label>
                            <select name="type" id="playlistType">
                                <option value="xtream_codes">Xtream Codes</option>
                                <option value="m3u">M3U</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>URL do Servidor *</label>
                        <input type="url" name="server_url" id="playlistServerUrl" required placeholder="http://servidor:porta">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Usuário *</label>
                            <input type="text" name="username" id="playlistUsername" required>
                        </div>
                        <div class="form-group">
                            <label>Senha *</label>
                            <input type="password" name="password" id="playlistPassword" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="playlistStatus">
                            <option value="active">Ativo</option>
                            <option value="inactive">Inativo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('playlistModal')">Cancelar</button>
                    <button type="submit" class="btn">Salvar</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Mobile sidebar toggle
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.getElementById('menuBtn');
            if (window.innerWidth <= 1024 && sidebar.classList.contains('open') && !sidebar.contains(e.target) && !menuBtn.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
        
        // Toast notifications
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }
        
        // Device Modal
        function openDeviceModal(device = null) {
            const modal = document.getElementById('deviceModal');
            const form = document.getElementById('deviceForm');
            const title = document.getElementById('deviceModalTitle');
            form.reset();
            
            if (device) {
                title.textContent = 'Editar Dispositivo';
                document.getElementById('deviceId').value = device.id;
                document.getElementById('deviceMac').value = device.mac_address;
                document.getElementById('deviceKey').value = device.device_key;
                document.getElementById('deviceName').value = device.name;
                document.getElementById('deviceStatus').value = device.status;
                document.getElementById('deviceMac').readOnly = true;
                document.getElementById('deviceKey').readOnly = true;
            } else {
                title.textContent = 'Novo Dispositivo';
                document.getElementById('deviceId').value = '';
                document.getElementById('deviceMac').readOnly = false;
                document.getElementById('deviceKey').readOnly = false;
            }
            modal.classList.add('active');
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        
        async function saveDevice(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const isEdit = formData.get('id');
            
            try {
                const res = await fetch('index.php?page=devices&action=' + (isEdit ? 'edit_device' : 'add_device'), {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal('deviceModal');
                    setTimeout(() => location.reload(), 500);
                } else {
                    showToast(data.error || 'Erro ao salvar', 'error');
                }
            } catch (err) {
                showToast('Erro de conexão', 'error');
            }
        }
        
        async function deleteDevice(id) {
            if (!confirm('Excluir este dispositivo?')) return;
            try {
                const res = await fetch('index.php?page=devices&action=delete_device', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + id
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 500);
                } else {
                    showToast(data.error || 'Erro ao excluir', 'error');
                }
            } catch (err) {
                showToast('Erro de conexão', 'error');
            }
        }
        
        // Playlist Modal
        function openPlaylistModal(playlist = null) {
            const modal = document.getElementById('playlistModal');
            const form = document.getElementById('playlistForm');
            const title = document.getElementById('playlistModalTitle');
            form.reset();
            
            if (playlist) {
                title.textContent = 'Editar Playlist';
                document.getElementById('playlistId').value = playlist.id;
                document.getElementById('playlistDevice').value = playlist.device_id;
                document.getElementById('playlistType').value = playlist.type || 'xtream_codes';
                document.getElementById('playlistServerUrl').value = playlist.server_url;
                document.getElementById('playlistUsername').value = playlist.username;
                document.getElementById('playlistPassword').value = playlist.password;
                document.getElementById('playlistStatus').value = playlist.status;
            } else {
                title.textContent = 'Nova Playlist';
                document.getElementById('playlistId').value = '';
            }
            modal.classList.add('active');
        }
        
        async function savePlaylist(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const isEdit = formData.get('id');
            
            try {
                const res = await fetch('index.php?page=playlists&action=' + (isEdit ? 'edit_playlist' : 'add_playlist'), {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal('playlistModal');
                    setTimeout(() => location.reload(), 500);
                } else {
                    showToast(data.error || 'Erro ao salvar', 'error');
                }
            } catch (err) {
                showToast('Erro de conexão', 'error');
            }
        }
        
        async function deletePlaylist(id) {
            if (!confirm('Excluir esta playlist?')) return;
            try {
                const res = await fetch('index.php?page=playlists&action=delete_playlist', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + id
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 500);
                } else {
                    showToast(data.error || 'Erro ao excluir', 'error');
                }
            } catch (err) {
                showToast('Erro de conexão', 'error');
            }
        }
        
        // Close modals on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) closeModal(overlay.id);
            });
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
            }
        });
    </script>
</body>
</html>