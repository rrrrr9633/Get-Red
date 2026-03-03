# 大红行动 - Lucky Draw Game

一个功能丰富、安全可靠的在线抽奖游戏平台，包含用户管理、多种抽奖模式、客服系统、商城系统和完整的管理后台。

## ✨ 核心特性

- 🎮 **多种抽奖模式** - 支持普通抽奖、限时掉落等多种玩法
- 👥 **完整用户系统** - 注册、登录、个人信息管理、单点登录
- 🎁 **物品系统** - 物品获得、展示、分解、交易功能
- 💰 **充值提现系统** - 多种充值方式、跑刀提现功能
- 🛒 **商城系统** - 皮肤兑换、刀具兑换、传奇兑换、金币护航
- 📝 **签到系统** - 每日签到奖励机制
- 💬 **客服系统** - 实时客服分配、聊天、工单管理
- 🛠️ **管理后台** - 完整的后台管理界面（用户、抽奖、提现、订单）
- 🔐 **企业级安全** - CSRF防护、频率限制、Session安全、完整日志
- 📊 **数据统计** - 详细的运营数据统计和分析
- 🎨 **主题管理** - 可自定义的界面主题

## 🚀 快速开始

### 环境要求

- **PHP**: 7.4+ (推荐 8.0+)
- **MySQL**: 5.7+ 或 MariaDB 10.3+
- **Web服务器**: Apache / Nginx / PHP内置服务器
- **系统资源**: 2GB+ RAM, 10GB+ 存储空间

### 本地部署（Windows + XAMPP）

1. **启动MySQL**
   ```bash
   # 启动XAMPP控制面板，启动MySQL
   ```

2. **创建数据库**
   ```bash
   mysql -u root
   CREATE DATABASE lucky_draw CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   exit;
   ```

3. **导入数据库**
   ```bash
   mysql -u root lucky_draw < database-init.sql
   ```

4. **配置数据库连接**
   编辑 `server/config/database.php`，确认配置：
   ```php
   private $host = "localhost";
   private $db_name = "lucky_draw";
   private $username = "root";
   private $password = "";  // XAMPP默认为空
   ```

5. **启动服务器**
   ```bash
   # 在Get-Red目录下执行
   php -S localhost:8000 router.php
   ```

6. **访问系统**
   - 主页：http://localhost:8000
   - 超级管理员创建：http://localhost:8000/super-admin.html

### 生产环境部署

详细部署说明请查看 `server/config-read/DEPLOYMENT_GUIDE.md`

## 📁 项目结构

```
Get-Red/
├── index.html                    # 主页面
├── super-admin.html              # 超级管理员登录
├── create-super-admin.html       # 创建超级管理员
├── router.php                    # PHP内置服务器路由（安全防护）
├── database-init.sql             # 数据库初始化脚本
│
├── pages/                        # 页面文件
│   ├── auth/                     # 认证页面
│   │   ├── login.html           # 用户登录
│   │   └── register.html        # 用户注册
│   ├── admin/                    # 管理后台
│   │   ├── users.html           # 用户管理
│   │   ├── draws.html           # 抽奖记录
│   │   ├── service-chat.html    # 客服聊天
│   │   └── ...
│   ├── user/                     # 用户页面
│   │   ├── profile.html         # 个人中心
│   │   ├── recharge.html        # 充值页面
│   │   └── operator.html        # 运营商页面
│   ├── shop/                     # 商城页面
│   │   ├── skin-exchange.html   # 皮肤兑换
│   │   ├── knife-exchange.html  # 刀具兑换
│   │   ├── legendary-exchange.html  # 传奇兑换
│   │   └── gold-escort.html     # 金币护航
│   └── modules/                  # 功能模块
│       ├── container.html        # 容器页面
│       └── checkin.html          # 签到页面
│
├── server/                       # 后端服务
│   ├── api/                      # API接口
│   │   ├── users.php            # 用户API（含CSRF、频率限制）
│   │   ├── admin.php            # 管理API
│   │   ├── super-admin.php      # 超级管理员API
│   │   ├── withdrawal.php       # 提现API（含CSRF）
│   │   ├── shop.php             # 商城API
│   │   ├── customer-service.php # 客服API
│   │   ├── draws.php            # 抽奖API
│   │   ├── games.php            # 游戏API
│   │   ├── items.php            # 物品API
│   │   ├── prizes.php           # 奖品API
│   │   ├── recharge.php         # 充值API
│   │   ├── checkin.php          # 签到API
│   │   ├── upload-avatar.php    # 头像上传（含安全验证）
│   │   └── ...
│   ├── config/                   # 配置文件
│   │   ├── database.php         # 数据库配置
│   │   ├── security.php         # 安全配置（CSRF、频率限制、日志）
│   │   └── auth-middleware.php  # 认证中间件
│   └── config-read/              # 部署文档
│       └── DEPLOYMENT_GUIDE.md  # 部署指南
│
├── css/                          # 样式文件
│   ├── style.css                # 主样式
│   ├── neon.css                 # 霓虹效果
│   ├── colors.css               # 颜色主题
│   ├── slot-machine.css         # 老虎机样式
│   ├── lucky-page.css           # 抽奖页面样式
│   └── customer-service.css     # 客服系统样式
│
├── js/                           # JavaScript文件
│   ├── main.js                  # 主逻辑
│   ├── auth.js                  # 认证逻辑（含CSRF Token）
│   ├── api-client.js            # API客户端（自动携带CSRF Token）
│   ├── slot-machine.js          # 老虎机逻辑
│   ├── effects.js               # 特效
│   └── customer-service.js      # 客服系统
│
├── images/                       # 图片资源
│   ├── shop/                    # 商城图片
│   └── thumbs/                  # 缩略图
│
├── uploads/                      # 上传文件（受保护）
│   └── avatars/                 # 用户头像
│       └── .htaccess            # Apache保护
│
└── logs/                         # 日志目录
    └── .htaccess                # 禁止访问
```

## 🔐 安全特性（企业级）

### 已实现的安全措施

#### 1. Session安全配置
- ✅ HttpOnly标志（防XSS窃取）
- ✅ Secure标志（HTTPS环境）
- ✅ SameSite=Strict（防CSRF）
- ✅ 严格的Session ID生成
- ✅ Session过期时间控制（1小时）

#### 2. CSRF防护
- ✅ 登录后生成64位随机Token
- ✅ 所有修改操作验证Token
- ✅ 支持HTTP头和JSON body传递
- ✅ 使用hash_equals防时序攻击

#### 3. 请求频率限制
- ✅ 登录：5分钟内最多5次
- ✅ 注册：5分钟内最多3次
- ✅ 修改密码：5分钟内最多3次
- ✅ 文件上传：5分钟内最多10次
- ✅ 提现申请：5分钟内最多3次

#### 4. 文件上传安全
- ✅ 扩展名白名单验证
- ✅ MIME类型验证
- ✅ getimagesize()真实性验证
- ✅ 图片尺寸限制（2000x2000）
- ✅ 文件大小限制（2MB）
- ✅ 文件重命名（防覆盖）

#### 5. 上传目录保护
- ✅ .htaccess禁止PHP执行（Apache）
- ✅ router.php路由保护（PHP内置服务器）
- ✅ 只允许访问图片文件

#### 6. SQL注入防护
- ✅ 所有查询使用PDO预处理语句
- ✅ 参数化查询
- ✅ 无直接SQL拼接

#### 7. 密码安全
- ✅ 密码强度验证（最少6位）
- ✅ password_hash加密存储
- ✅ password_verify验证

#### 8. 单点登录
- ✅ Session Token机制
- ✅ 记录登录IP和设备
- ✅ 定期验证会话有效性
- ✅ 异地登录自动踢出

#### 9. 安全日志
- ✅ 记录所有关键操作
- ✅ 包含用户信息、IP、时间戳
- ✅ 记录成功和失败的操作
- ✅ 便于安全审计和问题追溯

#### 10. 错误处理
- ✅ 生产环境关闭错误显示
- ✅ 错误记录到日志文件
- ✅ 防止信息泄露

### 安全等级：A（优秀）

## 👥 用户角色

### 1. 普通用户（user）
- 注册登录
- 个人信息管理
- 充值提现
- 抽奖游戏
- 商城购买
- 客服咨询

### 2. 客服用户（service）
- 查看用户信息
- 处理客服咨询
- 查看抽奖记录
- 处理提现申请
- 处理购买订单

### 3. 超级管理员（super_admin）
- 所有客服权限
- 用户管理（增删改查）
- 奖品配置
- 系统配置
- 数据统计
- 删除敏感数据（需身份验证码）

## 🎮 主要功能

### 用户端功能

#### 认证系统
- 用户注册（含密码强度验证）
- 用户登录（含频率限制）
- 单点登录（异地登录自动踢出）
- 个人信息管理
- 头像上传（安全验证）
- 密码修改（含CSRF保护）

#### 游戏系统
- 多种抽奖模式
- 实时动画效果
- 物品获得展示
- 物品分解系统
- 仓库管理

#### 充值提现
- 多种充值方式
- 跑刀提现功能
- 提现记录查询
- 实时余额更新

#### 商城系统
- 皮肤兑换
- 刀具兑换
- 传奇兑换
- 金币护航
- 订单管理

#### 客服系统
- 自动客服分配
- 实时聊天
- 历史记录查询
- 在线状态显示

#### 签到系统
- 每日签到奖励
- 连续签到加成
- 签到记录查询

### 管理端功能

#### 用户管理
- 用户列表查看
- 用户详情查看
- 用户信息编辑
- 用户余额管理
- 用户仓库查看
- 用户删除（需验证）

#### 抽奖管理
- 抽奖记录查看
- 用户抽奖历史
- 抽奖统计分析

#### 提现管理
- 待处理申请列表
- 提现审核（批准/拒绝）
- 提现历史记录
- 小红点提醒

#### 订单管理
- 购买订单列表
- 订单处理（完成/取消）
- 订单状态跟踪

#### 客服管理
- 客服分配管理
- 聊天记录查看
- 客服工作量统计

#### 系统配置
- 奖品配置
- 主题管理
- 系统参数设置

## 📊 数据库结构

### 核心表

- `users` - 用户表（含session_token、login_ip、login_device）
- `transactions` - 交易记录表
- `user_items` - 用户物品表
- `prizes` - 奖品表
- `draw_records` - 抽奖记录表
- `withdrawal_requests` - 提现申请表
- `withdrawal_history` - 提现历史表
- `withdrawal_config` - 提现配置表
- `shop_purchases` - 商城购买记录表
- `chat_sessions` - 聊天会话表
- `chat_messages` - 聊天消息表
- `service_user_assignments` - 客服分配表
- `security_logs` - 安全日志表（新增）
- `system_settings` - 系统设置表

## 🔧 配置说明

### 数据库配置

编辑 `server/config/database.php`：

```php
private $host = "localhost";
private $db_name = "lucky_draw";
private $username = "root";
private $password = "";
private $charset = "utf8mb4";
```

### 安全配置

`server/config/security.php` 包含：
- Session安全配置
- CSRF Token生成和验证
- 请求频率限制
- 密码强度验证
- 安全日志记录
- 错误处理配置

### 默认账号

**超级管理员**（需通过super-admin.html创建）：
- 用户名：自定义
- 密码：自定义
- 身份码：自定义（用于敏感操作验证）

## 🚀 启动服务

### 使用PHP内置服务器（推荐开发环境）

```bash
cd Get-Red
php -S localhost:8000 router.php
```

访问：http://localhost:8000

### 使用Apache/Nginx

配置虚拟主机指向 `Get-Red` 目录，确保：
1. 启用 `.htaccess` 支持（Apache）
2. 配置 URL 重写规则
3. 设置正确的文件权限

## 📝 使用流程

### 首次部署

1. 导入数据库：`mysql -u root lucky_draw < database-init.sql`
2. 配置数据库连接：编辑 `server/config/database.php`
3. 启动服务器：`php -S localhost:8000 router.php`
4. 创建超级管理员：访问 `http://localhost:8000/super-admin.html`
5. 登录管理后台：使用创建的超级管理员账号

### 日常运营

1. **用户管理**：查看用户、处理问题用户
2. **提现审核**：及时处理用户提现申请
3. **订单处理**：处理商城购买订单
4. **客服支持**：回复用户咨询
5. **数据分析**：查看运营数据统计
6. **安全监控**：定期查看安全日志

## 🛡️ 安全建议

### 生产环境部署

1. **使用HTTPS**：强制使用SSL证书
2. **修改默认配置**：更改数据库密码
3. **定期备份**：每天备份数据库
4. **监控日志**：定期检查 `security_logs` 表
5. **更新系统**：及时更新PHP和MySQL版本
6. **限制访问**：配置防火墙规则
7. **文件权限**：
   ```bash
   chmod 755 -R ./
   chmod 777 uploads/ logs/
   ```

### 安全检查清单

- [ ] 数据库密码已修改
- [ ] HTTPS已启用
- [ ] 文件权限已正确设置
- [ ] 上传目录保护已启用
- [ ] 错误显示已关闭（生产环境）
- [ ] 定期备份已配置
- [ ] 安全日志监控已启用

## 🐛 故障排查

### 常见问题

1. **无法登录**
   - 检查数据库连接
   - 清除浏览器缓存和Cookie
   - 检查Session配置

2. **权限检查失败**
   - 确保没有重复调用 `session_start()`
   - 检查 `checkUserPermission()` 函数
   - 查看浏览器控制台错误

3. **文件上传失败**
   - 检查 `uploads/` 目录权限（777）
   - 检查PHP上传大小限制
   - 查看 `security_logs` 表

4. **CSRF验证失败**
   - 确保登录后获取了Token
   - 检查请求是否携带Token
   - 清除浏览器缓存

### 日志位置

- **PHP错误日志**：`logs/php_errors.log`
- **安全日志**：数据库 `security_logs` 表
- **Web服务器日志**：根据服务器配置

## 📚 相关文档

- `server/config-read/DEPLOYMENT_GUIDE.md` - 详细部署指南
- `SECURITY_FIXES_COMPLETE.md` - 安全修复报告
- `SECURITY_TEST_REPORT.md` - 安全测试报告
- `PERMISSION_CHECK_FIX.md` - 权限检查修复报告

## 🔄 更新日志

### v2.0.0 (2026-03-03) - 安全加固版本

- ✅ 实现企业级Session安全配置
- ✅ 添加CSRF防护机制
- ✅ 实现请求频率限制
- ✅ 加强文件上传安全验证
- ✅ 添加上传目录保护
- ✅ 实现完整的安全日志系统
- ✅ 添加密码强度验证
- ✅ 修复重复session_start问题
- ✅ 删除所有测试文件和数据
- ✅ 优化权限检查机制
- ✅ 安全等级从B+提升到A

### v1.0.0 - 初始版本

- 基础用户系统
- 抽奖游戏功能
- 客服系统
- 管理后台

## 📄 许可证

本项目采用自定义许可证，仅供学习和个人使用。

## 💡 技术支持

如遇到问题，请按以下顺序排查：

1. 检查数据库连接配置
2. 查看PHP错误日志
3. 检查文件权限设置
4. 查看安全日志表
5. 清除浏览器缓存
6. 重启Web服务器

---

**注意**：本系统已通过安全审计，安全等级为A（优秀）。在生产环境部署前，请务必：
1. 修改所有默认密码
2. 启用HTTPS
3. 配置定期备份
4. 启用安全监控
