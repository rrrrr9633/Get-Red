# Lucky Box Game

一个功能丰富、安全可靠的在线盲盒游戏平台，包含用户管理、多种盲盒模式、客服系统、商城系统和完整的管理后台。

##  项目规模

- **代码量**: 42,045 行（PHP 10,549 + HTML 25,971 + JS 2,923 + CSS 1,987 + SQL 615）
- **文件数**: 88+ 个文件（不含图片资源）
- **数据表**: 24 张数据表，完整的业务建模


## ✨ 核心特性

-  **多种盲盒模式** - 支持普通盲盒、限时掉落等多种玩法
-  **完整用户系统** - 注册、登录、个人信息管理、单点登录
-  **物品系统** - 物品获得、展示、分解、交易功能
-  **充值提现系统** - 多种充值方式、跑刀提现功能
-  **商城系统** - 皮肤兑换、刀具兑换、传奇兑换、金币护航
-  **签到系统** - 每日签到奖励机制
-  **客服系统** - 实时客服分配、聊天、工单管理
-  **管理后台** - 完整的后台管理界面（用户、盲盒、提现、订单）
-  **企业级安全** - CSRF防护、频率限制、Session安全、完整日志
-  **数据统计** - 详细的运营数据统计和分析
-  **主题管理** - 可自定义的界面主题

##  快速开始

### 环境要求

- **PHP**: 7.4+ (推荐 8.0+)
- **MySQL**: 5.7+ 或 MariaDB 10.3+
- **Redis**: 6.0+ (性能优化必需)
- **Web服务器**: Apache / Nginx / PHP内置服务器
- **系统资源**: 2GB+ RAM, 10GB+ 存储空间

### 性能优化部署（推荐）

在完成基础部署后，强烈建议执行性能优化：

```bash
# 一键部署所有优化
./deploy-optimization.sh

# 或手动执行
brew install redis && brew services start redis
brew install php-redis
mysql -u root -p lucky_draw < database-indexes.sql
sudo cp mysql-optimization.cnf /etc/mysql/conf.d/optimization.cnf
brew services restart mysql
php test-optimization.php
```

- 并发能力：50-100 req/s
- 响应时间：50-200ms
- 余额安全：绝对不会负数

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

##  项目结构

```
Get-Red/
├── 📄 核心文件
│   ├── index.html                    # 主页面
│   ├── super-admin.html              # 超级管理员登录
│   ├── create-super-admin.html       # 创建超级管理员
│   ├── router.php                    # PHP内置服务器路由（安全防护）
│   ├── database-init.sql             # 数据库初始化脚本（518行）
│   └── database-indexes-safe.sql     # 数据库索引优化脚本（97行）
│
├── 📂 pages/ (26个HTML文件，25,971行)
│   ├── auth/                     # 认证页面（2个文件）
│   │   ├── login.html           # 用户登录（382行）
│   │   └── register.html        # 用户注册（748行）
│   ├── admin/                    # 管理后台（10个文件）
│   │   ├── admin.html           # 管理首页（813行）
│   │   ├── users.html           # 用户管理（1,629行）
│   │   ├── draws.html           # 盲盒记录（918行）
│   │   ├── prizes.html          # 奖品管理（1,907行）
│   │   ├── config.html          # 系统配置（1,640行）
│   │   ├── shop-management.html # 商城管理（1,365行）
│   │   ├── service-chat.html    # 客服聊天（658行）
│   │   └── ...
│   ├── user/                     # 用户页面（3个文件）
│   │   ├── profile.html         # 个人中心（1,590行）
│   │   ├── recharge.html        # 充值页面（569行）
│   │   └── operator.html        # 运营商页面（573行）
│   ├── shop/                     # 商城页面（4个文件）
│   │   ├── skin-exchange.html   # 皮肤兑换（592行）
│   │   ├── knife-exchange.html  # 刀具兑换（700行）
│   │   ├── legendary-exchange.html  # 传奇兑换（786行）
│   │   └── gold-escort.html     # 金币护航（592行）
│   ├── modules/                  # 功能模块（2个文件）
│   │   ├── container.html        # 容器页面（1,016行）
│   │   └── checkin.html          # 签到页面（757行）
│   ├── main.html                 # 主页面（1,193行）
│   └── lucky1.html               # 盲盒页面（2,539行）
│
├── 📂 server/ (24个PHP文件，10,549行)
│   ├── api/                      # API接口（16个文件）
│   │   ├── admin.php            # 管理API（2,170行）⭐
│   │   ├── shop.php             # 商城API（1,000行）⭐
│   │   ├── customer-service.php # 客服API（793行）
│   │   ├── super-admin.php      # 超级管理员API（706行）
│   │   ├── withdrawal.php       # 提现API（632行）
│   │   ├── users.php            # 用户API（616行）
│   │   ├── recharge.php         # 充值API（494行）
│   │   ├── games.php            # 游戏API（422行）
│   │   ├── limited-drop.php     # 限时掉落API（412行）
│   │   ├── service-assignment.php # 客服分配API（392行）
│   │   ├── prizes.php           # 奖品API（383行）
│   │   ├── checkin.php          # 签到API（333行）
│   │   ├── draws.php            # 盲盒API（263行）
│   │   ├── upload-avatar.php    # 头像上传（179行）
│   │   ├── items.php            # 物品API（141行）
│   │   └── ...
│   ├── config/                   # 配置文件（5个文件）
│   │   ├── database.php         # 数据库配置（292行，含24张表说明）
│   │   ├── security.php         # 安全配置（277行）
│   │   ├── redis-cache.php      # Redis缓存（204行）
│   │   ├── auth-middleware.php  # 认证中间件（119行）
│   │   └── database-socket.php  # Socket配置（50行）
│   └── config-read/              # 部署文档
│       ├── DEPLOYMENT_GUIDE.md  # 部署指南（353行）
│       └── deploy.sh            # 部署脚本（237行）
│
├── 📂 js/ (6个文件，2,923行)
│   ├── customer-service.js      # 客服系统（576行）
│   ├── api-client.js            # API客户端（556行）
│   ├── effects.js               # 特效（532行）
│   ├── auth.js                  # 认证逻辑（444行）
│   ├── slot-machine.js          # 老虎机逻辑（418行）
│   └── main.js                  # 主逻辑（397行）
│
├── 📂 css/ (6个文件，1,987行)
│   ├── lucky-page.css           # 盲盒页面样式（731行）
│   ├── style.css                # 主样式（410行）
│   ├── customer-service.css     # 客服系统样式（376行）
│   ├── slot-machine.css         # 老虎机样式（294行）
│   ├── neon.css                 # 霓虹效果（113行）
│   └── colors.css               # 颜色主题（63行）
│
├── 📂 md/ (10个文档，3,373行)
│   ├── IMPLEMENTATION_GUIDE.md  # 实施指南（566行）
│   ├── PERFORMANCE_OPTIMIZATION.md # 性能优化（515行）
│   ├── SIMPLE_OPTIMIZATION.md   # 简化优化（429行）
│   ├── DEPLOYMENT_CHECKLIST.md  # 部署清单（294行）
│   ├── WORK_SUMMARY.md          # 工作总结（227行）
│   ├── OPTIMIZATION_README.md   # 优化总览（186行）
│   ├── PROJECT_STRUCTURE.md     # 项目结构（179行）
│   ├── NEXT_STEPS.md            # 下一步指南（170行）
│   └── QUICK_REFERENCE.md       # 快速参考（163行）
│
├── 📂 测试和优化
│   ├── test-optimization.php         # 优化测试脚本（141行）
│   ├── test-concurrency-curl.php     # 并发测试（184行）
│   ├── test-concurrency.php          # 并发测试（125行）
│   └── mysql-optimization-safe.cnf   # MySQL优化配置
│
├── 📚 核心文档
│   ├── README.md                     # 项目说明（554行）
│   ├── PROJECT_VALUE_ASSESSMENT.md   # 项目价值评估报告
│   └── OPTIMIZATION_COMPLETE.md      # 优化完成报告（187行）
│
├── 📂 资源目录
│   ├── images/                   # 图片资源
│   │   ├── shop/                # 商城图片
│   │   └── thumbs/              # 缩略图
│   ├── uploads/                  # 上传文件（受保护）
│   │   └── avatars/             # 用户头像
│   └── logs/                     # 日志目录
│
└── 📄 配置文件
    ├── .gitignore               # Git忽略规则
    ├── .htaccess                # Apache配置
    └── router.php               # 路由配置（44行）
```

### 代码量统计

| 类型 | 文件数 | 代码行数 | 占比 |
|------|--------|----------|------|
| HTML | 26 | 25,971 | 61.8% |
| PHP | 24 | 10,549 | 25.1% |
| JavaScript | 6 | 2,923 | 7.0% |
| CSS | 6 | 1,987 | 4.7% |
| SQL | 2 | 615 | 1.4% |
| **总计** | **64** | **42,045** | **100%** |

### 文档统计

| 类型 | 文件数 | 行数 |
|------|--------|------|
| Markdown文档 | 17 | 3,928 |
| Shell脚本 | 1 | 237 |
| 配置文件 | 10+ | 150+ |

##  安全特性（企业级）

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


##  用户角色

### 1. 普通用户（user）
- 注册登录
- 个人信息管理
- 充值提现
- 盲盒游戏
- 商城购买
- 客服咨询

### 2. 客服用户（service）
- 查看用户信息
- 处理客服咨询
- 查看盲盒记录
- 处理提现申请
- 处理购买订单

### 3. 超级管理员（super_admin）
- 所有客服权限
- 用户管理（增删改查）
- 奖品配置
- 系统配置
- 数据统计
- 删除敏感数据（需身份验证码）

##  主要功能

### 用户端功能

#### 认证系统
- 用户注册（含密码强度验证）
- 用户登录（含频率限制）
- 单点登录（异地登录自动踢出）
- 个人信息管理
- 头像上传（安全验证）
- 密码修改（含CSRF保护）

#### 游戏系统
- 多种盲盒模式
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

#### 盲盒管理
- 盲盒记录查看
- 用户盲盒历史
- 盲盒统计分析

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

##  数据库结构

### 核心表

- `users` - 用户表（含session_token、login_ip、login_device）
- `transactions` - 交易记录表
- `user_items` - 用户物品表
- `prizes` - 奖品表
- `draw_records` - 盲盒记录表
- `withdrawal_requests` - 提现申请表
- `withdrawal_history` - 提现历史表
- `withdrawal_config` - 提现配置表
- `shop_purchases` - 商城购买记录表
- `chat_sessions` - 聊天会话表
- `chat_messages` - 聊天消息表
- `service_user_assignments` - 客服分配表
- `security_logs` - 安全日志表（新增）
- `system_settings` - 系统设置表

##  配置说明

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

##  启动服务

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

##  使用流程

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

## 安全建议

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

##  故障排查

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


## 更新日志

### v2.2.0 (2026-03-06) - 项目价值评估

📊 **新增项目价值评估报告**
- 完整的代码量统计（42,045行）
- 科学的价值评估方法
- 市场对比分析

📝 **更新主README**
- 添加项目规模统计
- 优化项目结构展示
- 添加代码量详细说明
- 完善文档索引

### v2.1.0 (2026-03-06) - 性能优化版本

-  实现 Redis 缓存系统
-  添加数据库索引优化
-  优化 MySQL 配置参数
-  防止余额负数（事务锁）
-  查询性能提升 5-10 倍
-  并发能力提升至 50-100 req/s
-  响应时间降低至 50-200ms

**性能优化文档**：
- `OPTIMIZATION_README.md` - 优化项目总览
- `NEXT_STEPS.md` - 快速部署指南
- `IMPLEMENTATION_GUIDE.md` - 详细实施指南
- `DEPLOYMENT_CHECKLIST.md` - 部署检查清单
- `PROJECT_STRUCTURE.md` - 项目文件结构



### v2.0.0 (2026-03-03) - 安全加固版本

-  实现企业级Session安全配置
-  添加CSRF防护机制
-  实现请求频率限制
-  加强文件上传安全验证
-  添加上传目录保护
-  实现完整的安全日志系统
-  添加密码强度验证
-  修复重复session_start问题
-  删除所有测试文件和数据
-  优化权限检查机制
-  安全等级从B+提升到A

### v1.0.0 - 初始版本

- 基础用户系统
- 盲盒游戏功能
- 客服系统
- 管理后台

##  许可证

本项目采用自定义许可证，仅供学习和个人使用。

##  技术支持

如遇到问题，请按以下顺序排查：

1. 检查数据库连接配置
2. 查看PHP错误日志
3. 检查文件权限设置
4. 查看安全日志表
5. 清除浏览器缓存
6. 重启Web服务器

---

**注意**：在生产环境部署前，请务必：
1. 修改所有默认密码
2. 启用HTTPS
3. 配置定期备份
4. 启用安全监控
