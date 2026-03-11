# 阿里云短信验证码集成指南

## 已完成的工作

### 1. 数据库更新
- 已创建数据库迁移脚本：`database-add-phone-simple.sql`
- 为`users`表添加了`phone_number`字段
- 添加了唯一索引确保手机号唯一性

### 2. 短信服务类
- 创建了 `SmsService` 类 (`server/config/sms-service.php`)
- 支持阿里云短信API调用
- 包含频率限制和验证码验证逻辑
- 支持开发环境模拟发送

### 3. 短信API接口
- 创建了短信API接口 (`server/api/sms.php`)
- 支持发送验证码、验证验证码、检查发送频率
- 完整的错误处理和日志记录

### 4. 前端组件
- 创建了短信验证码管理类 (`js/sms-captcha.js`)
- 创建了短信验证码样式 (`css/sms-captcha.css`)
- 修改了注册页面使用短信验证码

### 5. 用户注册API更新
- 更新了用户注册API以支持手机号字段
- 添加了手机号格式验证和唯一性检查
- 集成了短信验证码验证

## 配置步骤

### 1. 数据库更新
```sql
-- 运行数据库迁移
mysql -u username -p lucky_draw < database-add-phone-simple.sql
```

### 2. 环境配置
1. 复制环境变量文件：
   ```bash
   cp .env.sms.example .env.sms
   ```

2. 配置阿里云短信服务：
   ```bash
   # .env.sms 文件内容
   ALIYUN_SMS_ACCESS_KEY_ID=your_access_key_id
   ALIYUN_SMS_ACCESS_KEY_SECRET=your_access_key_secret
   ALIYUN_SMS_SIGN_NAME=无敌俱乐部
   ALIYUN_SMS_TEMPLATE_CODE=SMS_123456789
   APP_ENV=development
   ```

### 3. 阿里云短信服务配置
1. 登录阿里云控制台，开通短信服务
2. 创建短信签名（如：无敌俱乐部）
3. 创建短信模板（验证码模板）
4. 获取AccessKey ID和Secret

### 4. 服务器配置
1. 安装阿里云SDK（如果使用实际发送）：
   ```bash
   composer require alibabacloud/sdk
   ```

2. 配置PHP环境变量：
   ```bash
   # 在服务器环境变量中设置
   export ALIYUN_SMS_ACCESS_KEY_ID=your_key
   export ALIYUN_SMS_ACCESS_KEY_SECRET=your_secret
   ```

### 5. 测试配置
1. 开发环境：短信不会实际发送，验证码会在日志中显示
2. 生产环境：需要配置正确的阿里云AccessKey

## 使用说明

### 发送验证码流程
1. 用户输入手机号
2. 前端调用 `/server/api/sms.php?action=send` 发送验证码
3. 用户收到短信验证码
4. 用户输入验证码完成验证

### 频率限制
- 同一手机号60秒内只能发送一次
- 同一手机号每天最多发送10次
- 验证码有效期5分钟

### 安全特性
- 验证码有效期5分钟
- 验证后立即删除验证码
- 防止暴力破解：验证码错误后需要重新获取
- IP和手机号频率限制

## 测试方法

### 开发环境测试
1. 启动开发服务器
2. 访问注册页面
3. 输入手机号获取验证码
4. 查看服务器日志查看验证码

### 生产环境测试
1. 配置阿里云AccessKey
2. 设置APP_ENV=production
3. 测试完整注册流程

## 故障排除

### 常见问题
1. **验证码发送失败**
   - 检查阿里云配置
   - 检查手机号格式
   - 检查频率限制

2. **验证码验证失败**
   - 检查Redis连接
   - 检查验证码是否过期
   - 检查验证码输入是否正确

3. **数据库错误**
   - 检查数据库连接
   - 检查users表phone_number字段

### 监控和日志
- 短信发送日志在服务器日志中
- Redis中存储验证码和频率限制信息
- 数据库记录用户注册信息

## 性能优化建议
1. 使用Redis缓存验证码
2. 使用连接池管理数据库连接
3. 异步发送短信避免阻塞
4. 使用消息队列处理高并发

## 安全建议
1. 定期更换阿里云AccessKey
2. 监控异常发送行为
3. 设置IP访问频率限制
4. 定期清理过期验证码

## 部署检查清单
- [ ] 数据库已添加phone_number字段
- [ ] 阿里云短信服务已开通
- [ ] 环境变量已配置
- [ ] 短信签名和模板已审核通过
- [ ] 测试发送和验证流程
- [ ] 监控和告警配置

## 故障恢复
1. 短信发送失败时自动重试
2. Redis故障时降级到Session存储
3. 阿里云服务异常时降级到开发模式

## 联系方式
如有问题，请联系系统管理员或查看服务器日志。