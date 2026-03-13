# 用户头像上传目录

此目录用于存储用户上传的头像图片。

## 文件命名规则
- 格式：`avatar_{用户ID}_{时间戳}.{扩展名}`
- 示例：`avatar_123_1234567890.jpg`

## 支持的格式
- JPG/JPEG
- PNG
- GIF
- WEBP

## 文件大小限制
- 最大 2MB

## 权限设置
确保此目录有写入权限：
```bash
chmod 755 uploads/avatars
```
