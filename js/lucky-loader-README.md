# Lucky Loader 使用说明

## 概述

`lucky-loader.js` 是统一Lucky页面系统的核心模块，负责动态加载Lucky实例的配置、奖品和价格信息。

## 功能

1. **URL参数解析** - 从URL获取Lucky实例ID（默认为1）
2. **配置加载** - 调用 `api/lucky-config.php` 获取实例配置
3. **奖品加载** - 调用 `api/prizes.php` 获取奖品列表
4. **价格加载** - 调用 `api/draw-prices.php` 获取抽奖价格
5. **页面渲染** - 动态更新背景图、标题、奖品列表和按钮

## 使用方法

### 1. 引入脚本

在HTML页面中引入 `lucky-loader.js`：

```html
<script src="../js/api-client.js"></script>
<script src="../js/effects.js"></script>
<script src="../js/lucky-loader.js"></script>
```

### 2. 初始化

在页面加载时调用初始化方法：

```javascript
async function initPage() {
    // 初始化 lucky-loader
    const result = await window.luckyLoader.initialize();
    
    if (result.success) {
        console.log('Lucky数据加载完成');
        // 使用加载的数据
        const prizes = window.luckyPrizes;
        const prices = window.luckyPrices;
        const config = window.luckyConfig;
    } else {
        console.error('加载失败:', result.error);
    }
}
```

### 3. 访问数据

初始化完成后，可以通过全局变量访问数据：

```javascript
// Lucky实例ID
const luckyId = window.LUCKY_INSTANCE_ID;

// 配置信息
const config = window.luckyConfig;
console.log(config.display_name); // "零号大坝(普通)"
console.log(config.background_url); // "images/shop/lucky1.png"

// 奖品列表
const prizes = window.luckyPrizes;
prizes.forEach(prize => {
    console.log(prize.name, prize.value);
});

// 价格配置
const prices = window.luckyPrices;
console.log(prices.single);     // 10
console.log(prices.triple);     // 30
console.log(prices.quintuple);  // 50
```

### 4. 监听数据加载事件

可以监听 `luckyDataLoaded` 事件来响应数据加载完成：

```javascript
window.addEventListener('luckyDataLoaded', (event) => {
    const { luckyId, config, prizes, prices } = event.detail;
    console.log('Lucky数据已加载:', luckyId);
    
    // 执行依赖于Lucky数据的操作
    initSlotMachine();
    bindEvents();
});
```

## URL参数

### lucky.html?id=1

访问ID为1的Lucky实例（零号大坝普通）

### lucky.html?id=2

访问ID为2的Lucky实例（零号大坝机密）

### lucky.html

不带参数时默认访问ID为1的实例

## API接口

### 1. lucky-config.php

**请求:** `GET /api/lucky-config.php?id=1`

**响应:**
```json
{
  "success": true,
  "instance": {
    "id": 1,
    "name": "lucky1",
    "display_name": "零号大坝(普通)",
    "description": "零号大坝危机四伏",
    "thumbnail_url": "images/thumbs/lucky1.png",
    "background_url": "images/shop/lucky1.png",
    "group_id": 1,
    "is_active": 1
  }
}
```

### 2. prizes.php

**请求:** `GET /api/prizes.php?lucky_id=1`

**响应:**
```json
{
  "success": true,
  "prizes": [
    {
      "id": 1,
      "name": "iPhone 15 Pro",
      "icon": "📱",
      "image_url": "uploads/prizes/iphone15.jpg",
      "value": 8000,
      "rarity": "legendary",
      "lucky_id": 1
    }
  ],
  "count": 8
}
```

### 3. draw-prices.php

**请求:** `GET /api/draw-prices.php?lucky_id=1`

**响应:**
```json
{
  "success": true,
  "lucky_id": 1,
  "prices": {
    "single": {
      "price": 10,
      "button_name": "单抽"
    },
    "triple": {
      "price": 30,
      "button_name": "三连抽"
    },
    "quintuple": {
      "price": 50,
      "button_name": "五连抽"
    }
  }
}
```

## 错误处理

如果加载失败，lucky-loader会：

1. 在控制台输出错误信息
2. 在页面上显示友好的错误提示
3. 提供刷新按钮让用户重试

```javascript
const result = await window.luckyLoader.initialize();
if (!result.success) {
    console.error('加载失败:', result.error);
    // 页面会自动显示错误提示
}
```

## 需求映射

- **需求 1.1, 1.2** - URL参数解析和默认值处理
- **需求 2.1, 2.2** - 配置加载和页面应用
- **需求 3.1, 3.2, 3.5** - 奖品数据加载和渲染
- **需求 4.1, 4.2** - 价格配置加载和按钮更新

## 测试

使用 `test-lucky-loader.html` 进行功能测试：

```bash
# 在浏览器中打开
open test-lucky-loader.html

# 测试不同的Lucky实例
open test-lucky-loader.html?id=1
open test-lucky-loader.html?id=2
```

## 注意事项

1. **依赖关系** - lucky-loader.js 不依赖其他JS模块，可以独立使用
2. **全局变量** - 初始化后会创建以下全局变量：
   - `window.LUCKY_INSTANCE_ID`
   - `window.luckyConfig`
   - `window.luckyPrizes`
   - `window.luckyPrices`
   - `window.luckyLoader`
3. **错误恢复** - 如果API调用失败，会使用默认值并在控制台警告
4. **安全性** - API不返回敏感信息（如概率、库存等）

## 向后兼容

lucky-loader.js 与现有的lucky页面完全兼容：

- 保留了 `prizeData` 和 `drawPrices` 全局变量
- 不影响现有的抽奖逻辑
- 可以逐步迁移旧页面到新系统
