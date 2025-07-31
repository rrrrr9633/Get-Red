#!/bin/bash

# 幸运降临项目快速部署脚本
# 使用方法: chmod +x deploy.sh && ./deploy.sh

set -e

echo "========================================="
echo "             项目部署脚本                  "
echo "========================================="

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 检查 root 权限
if [[ $EUID -eq 0 ]]; then
   echo -e "${RED}请不要使用 root 用户运行此脚本${NC}"
   exit 1
fi

# 系统检查
echo -e "${YELLOW}正在检查系统环境...${NC}"

# 检查操作系统
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    OS="linux"
    echo "✓ 操作系统: Linux"
elif [[ "$OSTYPE" == "darwin"* ]]; then
    OS="macos"
    echo "✓ 操作系统: macOS"
else
    echo -e "${RED}✗ 不支持的操作系统: $OSTYPE${NC}"
    exit 1
fi

# 检查 PHP
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | head -n1 | cut -d' ' -f2 | cut -d'.' -f1,2)
    echo "✓ PHP 版本: $PHP_VERSION"
    
    # 检查 PHP 版本
    if (( $(echo "$PHP_VERSION >= 7.4" | bc -l) )); then
        echo "✓ PHP 版本符合要求"
    else
        echo -e "${RED}✗ PHP 版本过低，需要 7.4 或更高版本${NC}"
        exit 1
    fi
else
    echo -e "${RED}✗ 未找到 PHP，请先安装 PHP${NC}"
    exit 1
fi

# 检查 MySQL
if command -v mysql &> /dev/null; then
    echo "✓ MySQL 已安装"
else
    echo -e "${RED}✗ 未找到 MySQL，请先安装 MySQL${NC}"
    exit 1
fi

# 检查 Web 服务器
if command -v apache2 &> /dev/null || command -v httpd &> /dev/null; then
    WEB_SERVER="apache"
    echo "✓ Web 服务器: Apache"
elif command -v nginx &> /dev/null; then
    WEB_SERVER="nginx"
    echo "✓ Web 服务器: Nginx"
else
    echo -e "${YELLOW}! 未检测到 Web 服务器，请手动配置${NC}"
    WEB_SERVER="none"
fi

echo ""

# 获取部署路径
read -p "请输入部署路径 (默认: /var/www/html/lucky-draw): " DEPLOY_PATH
DEPLOY_PATH=${DEPLOY_PATH:-/var/www/html/lucky-draw}

# 获取数据库配置
echo -e "${YELLOW}请输入数据库配置信息:${NC}"
read -p "数据库主机 (默认: localhost): " DB_HOST
DB_HOST=${DB_HOST:-localhost}

read -p "数据库名称 (默认: lucky_draw): " DB_NAME
DB_NAME=${DB_NAME:-lucky_draw}

read -p "数据库用户名 (默认: web_user): " DB_USER
DB_USER=${DB_USER:-web_user}

read -s -p "数据库密码: " DB_PASS
echo ""

# 确认配置
echo ""
echo -e "${YELLOW}部署配置确认:${NC}"
echo "部署路径: $DEPLOY_PATH"
echo "数据库主机: $DB_HOST"
echo "数据库名称: $DB_NAME"
echo "数据库用户: $DB_USER"
echo ""

read -p "确认开始部署? (y/N): " CONFIRM
if [[ ! $CONFIRM =~ ^[Yy]$ ]]; then
    echo "部署已取消"
    exit 0
fi

echo ""
echo -e "${GREEN}开始部署...${NC}"

# 创建部署目录
echo "创建部署目录..."
sudo mkdir -p "$DEPLOY_PATH"

# 复制项目文件
echo "复制项目文件..."
sudo cp -r ./* "$DEPLOY_PATH/"

# 设置文件权限
echo "设置文件权限..."
sudo chmod -R 755 "$DEPLOY_PATH"
sudo chmod -R 644 "$DEPLOY_PATH"/*.html
sudo chmod -R 644 "$DEPLOY_PATH"/css/*
sudo chmod -R 644 "$DEPLOY_PATH"/js/*
sudo chmod -R 755 "$DEPLOY_PATH"/server/api/
sudo chmod 644 "$DEPLOY_PATH"/server/config/database.php

# 创建上传目录
echo "创建上传目录..."
sudo mkdir -p "$DEPLOY_PATH"/images/thumbs
sudo mkdir -p "$DEPLOY_PATH"/uploads
sudo chmod -R 755 "$DEPLOY_PATH"/images
sudo chmod -R 755 "$DEPLOY_PATH"/uploads

# 设置 Web 服务器用户权限
if [[ "$OS" == "linux" ]]; then
    WEB_USER="www-data"
else
    WEB_USER="_www"
fi

sudo chown -R "$WEB_USER":"$WEB_USER" "$DEPLOY_PATH"/images
sudo chown -R "$WEB_USER":"$WEB_USER" "$DEPLOY_PATH"/uploads

# 更新数据库配置
echo "更新数据库配置..."
sudo sed -i.bak \
    -e "s/define('DB_HOST', 'localhost');/define('DB_HOST', '$DB_HOST');/" \
    -e "s/define('DB_NAME', 'lucky_draw');/define('DB_NAME', '$DB_NAME');/" \
    -e "s/define('DB_USER', 'web_user');/define('DB_USER', '$DB_USER');/" \
    -e "s/define('DB_PASS', 'web_password');/define('DB_PASS', '$DB_PASS');/" \
    "$DEPLOY_PATH"/server/config/database.php

# 创建数据库和用户
echo "配置数据库..."
read -s -p "请输入 MySQL root 密码: " MYSQL_ROOT_PASS
echo ""

mysql -u root -p"$MYSQL_ROOT_PASS" << EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'$DB_HOST' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'$DB_HOST';
FLUSH PRIVILEGES;
EOF

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ 数据库配置完成${NC}"
else
    echo -e "${RED}✗ 数据库配置失败${NC}"
    echo "请手动创建数据库和用户"
fi

# 测试数据库连接
echo "测试数据库连接..."
cd "$DEPLOY_PATH"
php -r "
require_once 'server/config/database.php';
try {
    \$db = new Database();
    \$conn = \$db->getConnection();
    echo '✓ 数据库连接成功!\n';
} catch (Exception \$e) {
    echo '✗ 数据库连接失败: ' . \$e->getMessage() . '\n';
    exit(1);
}
"

# Web 服务器配置提示
echo ""
echo -e "${YELLOW}Web 服务器配置提示:${NC}"

if [[ "$WEB_SERVER" == "apache" ]]; then
    echo "Apache 配置:"
    echo "1. 确保启用了 mod_rewrite 模块"
    echo "2. 在虚拟主机配置中设置 DocumentRoot 为: $DEPLOY_PATH"
    echo "3. 确保 AllowOverride All 已启用"
elif [[ "$WEB_SERVER" == "nginx" ]]; then
    echo "Nginx 配置:"
    echo "请在配置文件中添加以下配置:"
    echo ""
    echo "server {"
    echo "    listen 80;"
    echo "    server_name your-domain.com;"
    echo "    root $DEPLOY_PATH;"
    echo "    index index.html;"
    echo ""
    echo "    location /api/ {"
    echo "        try_files \$uri \$uri/ /server/api/\$1;"
    echo "    }"
    echo ""
    echo "    location ~ \.php\$ {"
    echo "        fastcgi_pass unix:/var/run/php/php-fpm.sock;"
    echo "        fastcgi_index index.php;"
    echo "        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;"
    echo "        include fastcgi_params;"
    echo "    }"
    echo "}"
fi

echo ""
echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN}           部署完成！${NC}"
echo -e "${GREEN}=========================================${NC}"
echo ""
echo "下一步操作:"
echo "1. 配置 Web 服务器虚拟主机"
echo "2. 访问 create-super-admin.html 创建管理员账户"
echo "3. 登录管理后台进行初始配置"
echo ""
echo "项目路径: $DEPLOY_PATH"
echo "数据库名: $DB_NAME"
echo ""
echo "有关详细配置信息，请查看 DEPLOYMENT_GUIDE.md 文档"
echo ""
