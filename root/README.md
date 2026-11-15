# LKPlay Panel - Painel de Gerenciamento IPTV

Painel de gerenciamento para o APK LKPlay modificado. Substitui o painel original (djbxp.top/ibo/lbstream).

## 📋 Funcionalidades

- **Autenticação Admin** - Login seguro com hash de senha
- **Gerenciamento de Dispositivos** - CRUD completo (MAC Address + Device Key)
- **Gerenciamento de Playlists** - Playlists Xtream Codes por dispositivo
- **API para APK** - Endpoints compatíveis com o app modificado
- **Dashboard** - Estatísticas e visão geral
- **Interface Moderna** - Dark theme, responsivo, mobile-friendly

## 🚀 Deploy Rápido

### 1. Requisitos do Servidor
- PHP 8.0+
- MySQL/MariaDB 5.7+
- Apache/Nginx com mod_rewrite
- **HTTPS obrigatório** (Let's Encrypt recomendado)

### 2. Configuração do Banco de Dados

```sql
CREATE DATABASE lkplay_panel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'lkplay_user'@'localhost' IDENTIFIED BY 'SUA_SENHA_FORTE_AQUI';
GRANT ALL PRIVILEGES ON lkplay_panel.* TO 'lkplay_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Configurar `config.php`

```php
// Edite config.php com suas credenciais:
define('DB_HOST', 'localhost');
define('DB_NAME', 'lkplay_panel');
define('DB_USER', 'lkplay_user');
define('DB_PASS', 'SUA_SENHA_FORTE_AQUI');

// IMPORTANTE: Gere novo hash de senha!
// php -r "echo password_hash('sua_nova_senha', PASSWORD_DEFAULT);"
// Cole o resultado em ADMIN_PASS_HASH
define('ADMIN_USER', 'admin');
define('ADMIN_PASS_HASH', '$2y$10$...'); // seu hash aqui
```

### 4. Upload e Permissões

```bash
# Upload para seu servidor (ex: /var/www/lkplay-panel)
# Ajuste permissões:
chown -R www-data:www-data /var/www/lkplay-panel
chmod 755 /var/www/lkplay-panel
chmod 644 /var/www/lkplay-panel/*.php
```

### 5. Configurar Virtual Host (Apache)

```apache
<VirtualHost *:443>
    ServerName painel.seudominio.com
    DocumentRoot /var/www/lkplay-panel
    
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/painel.seudominio.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/painel.seudominio.com/privkey.pem
    
    <Directory /var/www/lkplay-panel>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

<VirtualHost *:80>
    ServerName painel.seudominio.com
    Redirect permanent / https://painel.seudominio.com/
</VirtualHost>
```

### 6. Acessar e Testar

1. Acesse `https://painel.seudominio.com`
2. Login: `admin` / `admin123` (ALTERE IMEDIATAMENTE em Configurações)
3. Vá em **Dispositivos** → **Novo Dispositivo**
4. Cadastre o MAC Address e Device Key que aparecem no app
5. Vá em **Playlists** → **Nova Playlist**
6. Selecione o dispositivo e configure a URL Xtream Codes

## 🔌 API para o APK

### Buscar Playlist (fetch_dns)
```
GET https://painel.seudominio.com/index.php?api=fetch_dns&mac=AA:BB:CC:DD:EE:FF&device_key=CHAVE
```

**Resposta de sucesso:**
```json
{
  "success": true,
  "server_url": "http://servidor:porta",
  "username": "usuario",
  "password": "senha",
  "playlist_type": "xtream_codes"
}
```

**Resposta de erro:**
```json
{
  "error": "Dispositivo não encontrado ou inativo"
}
```

### Info do Dispositivo
```
GET https://painel.seudominio.com/index.php?api=device_info&mac=AA:BB:CC:DD:EE:FF&device_key=CHAVE
```

## 📱 Configuração no APK Modificado

O APK modificado deve usar sua URL base:
```
Base URL: https://painel.seudominio.com/
Login URL: https://painel.seudominio.com/index.php?page=login
API Base: https://painel.seudominio.com/index.php?api=
```

## 🔐 Segurança

- ✅ HTTPS obrigatório
- ✅ Hash de senha com bcrypt (cost 10)
- ✅ Prepared statements (PDO)
- ✅ Headers de segurança
- ✅ Validação de MAC Address
- ✅ Sessões seguras (HttpOnly, Secure, Strict)

## 🛠️ Manutenção

### Backup do Banco
```bash
# Via painel: Configurações → Fazer Backup
# Ou linha de comando:
mysqldump -u lkplay_user -p lkplay_panel > backup_$(date +%F).sql
```

### Logs
- Apache: `/var/log/apache2/lkplay-panel-access.log` / `error.log`
- PHP: Configure `error_log` no `php.ini`

## 📝 Changelog

- **v1.0** - Versão inicial com dashboard, dispositivos, playlists, API compatível

## ⚠️ Importante

1. **SEMPRE USE HTTPS** - O APK rejeitará HTTP (cleartextTraffic=true apenas para desenvolvimento)
2. **ALTERE A SENHA PADRÃO** - `admin123` é inseguro
3. **FIREWALL** - Restrinja acesso ao painel por IP se possível
4. **BACKUPS REGULARES** - Configure cron para backups diários
5. **MONITORAMENTO** - Monitore logs de acesso suspeito

## 🆘 Suporte

Para dúvidas sobre a integração com o APK modificado, consulte a documentação técnica do APK.