<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创建客服用户</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #0c0c0c, #1a1a1a);
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            background: rgba(0, 0, 0, 0.9);
            border: 2px solid #ffd700;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
        }
        h1 {
            color: #ffd700;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #ffd700;
        }
        input {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid #444;
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
        }
        input:focus {
            outline: none;
            border-color: #ffd700;
        }
        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            border: none;
            border-radius: 8px;
            color: #000;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.4);
        }
        .message {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .success {
            background: rgba(74, 222, 128, 0.2);
            border: 1px solid #4ade80;
            color: #4ade80;
        }
        .error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid #ef4444;
            color: #ef4444;
        }
        .info {
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid #3b82f6;
            color: #3b82f6;
            margin-bottom: 20px;
            text-align: left;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>创建客服用户</h1>
        
        <div class="info">
            <strong>说明：</strong><br>
            - 客服用户可以查看和回复分配给自己的用户消息<br>
            - 客服用户可以查看用户信息和抽奖记录<br>
            - 客服用户不能删除用户或修改系统配置
        </div>
        
        <form id="createServiceForm">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" required placeholder="请输入用户名">
            </div>
            
            <div class="form-group">
                <label>昵称</label>
                <input type="text" name="nickname" required placeholder="请输入昵称">
            </div>
            
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" required placeholder="请输入密码（至少6位）">
            </div>
            
            <button type="submit">创建客服用户</button>
        </form>
        
        <div id="message"></div>
    </div>

    <script>
        document.getElementById('createServiceForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = {
                username: formData.get('username'),
                nickname: formData.get('nickname'),
                password: formData.get('password')
            };
            
            const messageDiv = document.getElementById('message');
            messageDiv.innerHTML = '<div class="message info">创建中...</div>';
            
            try {
                const response = await fetch('/server/api/super-admin.php?action=createUser', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        ...data,
                        user_type: 'service'
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    messageDiv.innerHTML = `
                        <div class="message success">
                            <strong>创建成功！</strong><br>
                            用户名: ${data.username}<br>
                            昵称: ${data.nickname}<br>
                            类型: 客服用户<br><br>
                            <a href="super-admin.html" style="color: #ffd700;">返回登录</a>
                        </div>
                    `;
                    e.target.reset();
                } else {
                    messageDiv.innerHTML = `<div class="message error">${result.error || '创建失败'}</div>`;
                }
            } catch (error) {
                console.error('创建失败:', error);
                messageDiv.innerHTML = '<div class="message error">网络错误，请稍后重试</div>';
            }
        });
    </script>
</body>
</html>
