# 幸运降临项目部署指南

## 项目概述
幸运降临是一个基于 PHP + MySQL + JavaScript 的抽奖游戏平台，包含用户管理、抽奖游戏、客服系统、管理后台等功能。

## 系统要求

### 服务器环境
- **操作系统**: Linux/Windows/macOS
- **Web服务器**: Apache 2.4+ 或 Nginx 1.18+
- **PHP**: 7.4+ (推荐 8.0+)
- **MySQL**: 5.7+ 或 MariaDB 10.3+
- **其他**: 支持 URL 重写和 .htaccess

### PHP 扩展需求
- `pdo_mysql` - MySQL PDO 支持
- `mbstring` - 多字节字符串处理
- `json` - JSON 数据处理
- `gd` 或 `imagick` - 图片处理（可选）

## 部署步骤

### 1. 下载项目文件
```bash
# 通过 Git 克隆（如果有仓库）
git clone [repository-url] lucky-draw
cd lucky-draw

# 或者直接解压项目文件到目标目录
unzip lucky-draw.zip -d /var/www/html/lucky-draw
```

### 2. 数据库配置

#### 2.1 创建数据库和用户
```sql
-- 创建数据库
CREATE DATABASE lucky_draw CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 创建专用用户（推荐）
CREATE USER 'web_user'@'localhost' IDENTIFIED BY 'web_password';
GRANT ALL PRIVILEGES ON lucky_draw.* TO 'web_user'@'localhost';
FLUSH PRIVILEGES;
```

#### 2.2 导入数据库结构
项目包含 22 张数据表，需要按以下顺序创建：

**核心用户表**
```sql
-- 1. 用户表
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nickname VARCHAR(50),
    avatar TEXT,
    balance DECIMAL(10,2) DEFAULT 1000.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_online TINYINT(1) DEFAULT 0,
    last_activity TIMESTAMP NULL,
    email VARCHAR(100),
    user_type ENUM('user','service','super_admin') DEFAULT 'user',
    secret_key VARCHAR(100),
    ip_whitelist TEXT,
    status ENUM('active','inactive') DEFAULT 'active',
    INDEX idx_last_activity (last_activity)
);
```

**奖品相关表**
```sql
-- 2. 奖品表
CREATE TABLE prizes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(20),
    image_url VARCHAR(255),
    value DECIMAL(10,2),
    rarity ENUM('common','rare','epic','legendary') DEFAULT 'common',
    game_type ENUM('lucky_drop','prize_draw','wheel'),
    probability DECIMAL(5,2),
    original_probability DECIMAL(10,4),
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    quantity INT
);

-- 3. 用户物品表
CREATE TABLE user_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    prize_id INT,
    name VARCHAR(100),
    icon VARCHAR(20),
    image_url VARCHAR(255),
    value DECIMAL(10,2),
    rarity ENUM('common','rare','epic','legendary') DEFAULT 'common',
    obtained_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    decomposed TINYINT(1) DEFAULT 0,
    decomposed_at TIMESTAMP NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_prize_id (prize_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (prize_id) REFERENCES prizes(id)
);
```

> **注意**: 完整的数据库结构请参考 `server/config/database.php` 文件中的详细说明。

#### 2.3 修改数据库配置
编辑 `server/config/database.php` 文件：
```php
// 根据实际情况修改数据库配置
define('DB_HOST', 'localhost');        // 数据库主机
define('DB_NAME', 'lucky_draw');       // 数据库名称
define('DB_USER', 'web_user');         // 数据库用户名
define('DB_PASS', 'web_password');     // 数据库密码
define('DB_CHARSET', 'utf8mb4');       // 字符集
```

### 3. Web 服务器配置

#### 3.1 Apache 配置
在项目根目录创建 `.htaccess` 文件：
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ server/api/$1 [L]

# 设置文件权限
<Files "*.php">
    Require all granted
</Files>

# 禁止直接访问配置文件
<FilesMatch "\.(ini|conf|config)$">
    Require all denied
</FilesMatch>
```

#### 3.2 Nginx 配置
在 Nginx 配置文件中添加：
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/html/lucky-draw;
    index index.html index.php;

    # API 重写规则
    location /api/ {
        try_files $uri $uri/ /server/api/$1;
    }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 静态资源
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### 4. 文件权限设置
```bash
# 设置适当的文件权限
chmod -R 755 /var/www/html/lucky-draw
chmod -R 644 /var/www/html/lucky-draw/*.html
chmod -R 644 /var/www/html/lucky-draw/css/*
chmod -R 644 /var/www/html/lucky-draw/js/*
chmod -R 755 /var/www/html/lucky-draw/server/api/
chmod 644 /var/www/html/lucky-draw/server/config/database.php

# 创建上传目录并设置权限
mkdir -p /var/www/html/lucky-draw/images/thumbs
mkdir -p /var/www/html/lucky-draw/uploads
chmod -R 755 /var/www/html/lucky-draw/images
chmod -R 755 /var/www/html/lucky-draw/uploads
chown -R www-data:www-data /var/www/html/lucky-draw/images
chown -R www-data:www-data /var/www/html/lucky-draw/uploads
```

### 5. 初始化系统数据

#### 5.1 创建超级管理员
访问 `create-super-admin.html` 页面创建第一个超级管理员账户。

#### 5.2 基础配置
1. 登录管理后台 (`admin.html`)
2. 配置系统基本设置
3. 添加奖品数据
4. 设置客服信息

### 6. 测试部署

#### 6.1 基础功能测试
```bash
# 测试数据库连接
php -r "
require_once 'server/config/database.php';
try {
    \$db = new Database();
    \$conn = \$db->getConnection();
    echo '数据库连接成功!\n';
} catch (Exception \$e) {
    echo '数据库连接失败: ' . \$e->getMessage() . '\n';
}
"
```

#### 6.2 API 测试
```bash
# 测试用户注册 API
curl -X POST http://your-domain.com/api/users.php \
  -H "Content-Type: application/json" \
  -d '{"action":"register","username":"test","password":"123456"}'
```

### 7. 安全配置

#### 7.1 数据库安全
- 使用专用数据库用户，避免使用 root
- 定期更换数据库密码
- 启用 MySQL 慢查询日志监控

#### 7.2 文件安全
```bash
# 隐藏敏感文件
echo "deny from all" > server/config/.htaccess
chmod 600 server/config/database.php
```

#### 7.3 PHP 安全配置
在 `php.ini` 中设置：
```ini
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log
post_max_size = 10M
upload_max_filesize = 5M
```

## 目录结构说明

```
lucky-draw/
├── index.html              # 主页面
├── create-super-admin.html  # 超级管理员创建页面
├── super-admin.html        # 超级管理员登录页面
├── css/                    # 样式文件
│   ├── style.css
│   └── neon.css
├── js/                     # JavaScript 文件
│   ├── main.js
│   ├── auth.js
│   └── api-client.js
├── pages/                  # 页面文件
│   ├── auth/              # 认证相关页面
│   ├── admin/             # 管理后台页面
│   ├── user/              # 用户页面
│   └── modules/           # 功能模块页面
├── server/                # 后端服务
│   ├── api/               # API 接口
│   └── config/            # 配置文件
├── images/                # 图片资源
│   └── thumbs/           # 缩略图
└── uploads/              # 上传文件目录
```

## 功能特性

### 核心功能
- **用户系统**: 注册、登录、个人信息管理
- **抽奖游戏**: 多种抽奖模式，概率配置
- **物品系统**: 物品获得、分解、价值管理
- **充值系统**: 多种充值方式，金币管理
- **签到系统**: 每日签到奖励

### 管理功能
- **用户管理**: 用户查看、编辑、状态管理
- **奖品管理**: 奖品配置、概率调整
- **抽奖记录**: 详细的抽奖日志和统计
- **客服管理**: 客服分配、聊天记录
- **系统配置**: 主题设置、基础配置

### 安全特性
- **访问控制**: 基于角色的权限管理
- **安全日志**: 详细的操作审计日志
- **会话管理**: 安全的登录会话控制
- **数据验证**: 严格的输入数据验证

## 常见问题

### Q1: 数据库连接失败
**解决方案**:
1. 检查数据库服务是否运行
2. 验证数据库配置信息
3. 确认用户权限设置
4. 检查防火墙设置

### Q2: 文件上传失败
**解决方案**:
1. 检查上传目录权限
2. 确认 PHP 上传限制设置
3. 验证文件大小和类型限制

### Q3: API 请求 404 错误
**解决方案**:
1. 检查 URL 重写配置
2. 确认 .htaccess 文件设置
3. 验证服务器模块启用状态

### Q4: 管理后台无法访问
**解决方案**:
1. 确认超级管理员账户已创建
2. 检查用户类型和权限设置
3. 验证会话和 Cookie 配置

## 维护建议

### 定期维护
- **数据备份**: 每日自动备份数据库
- **日志清理**: 定期清理过期日志文件
- **性能监控**: 监控服务器资源使用情况
- **安全更新**: 及时更新 PHP 和 MySQL 版本

### 监控指标
- 数据库连接数和查询性能
- API 响应时间和错误率
- 用户活跃度和抽奖频率
- 服务器资源使用率

## 技术支持

如遇到部署问题，请检查：
1. 系统日志文件
2. PHP 错误日志
3. 数据库连接状态
4. 文件权限设置

确保所有配置步骤都已正确完成，特别是数据库配置和文件权限设置。
