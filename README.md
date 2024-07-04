<p align="center"><img width="260px" src="https://chaz6chez.cn/images/workbunny-logo.png" alt="workbunny"></p>

**<p align="center">workbunny/webman-push-server</p>**

**<p align="center">🐇  Webman plugin for push server implementation. 🐇</p>**

<div align="center">
    <a href="https://github.com/workbunny/webman-push-server/actions?query=branch%3Amain">
        <img src="https://github.com/workbunny/webman-push-server/actions/workflows/CI.yml/badge.svg" alt="Build Status">
    </a>
    <a href="https://github.com/workbunny/webman-push-server/releases">
        <img alt="Latest Stable Version" src="https://badgen.net/packagist/v/workbunny/webman-push-server/latest">
    </a>
    <a href="https://github.com/workbunny/webman-push-server/blob/main/composer.json">
        <img alt="PHP Version Require" src="https://badgen.net/packagist/php/workbunny/webman-push-server">
    </a>
    <a href="https://github.com/workbunny/webman-push-server/blob/main/LICENSE">
        <img alt="GitHub license" src="https://badgen.net/packagist/license/workbunny/webman-push-server">
    </a>
</div>

## 说明

- **3.x：全新架构，目前为beta版**
- **2.x：旧版架构，长期技术支持版本（LTS），[点击跳转2.x文档](https://github.com/workbunny/webman-push-server/blob/2.x/README.md)**
- **1.x：旧版架构，不再维护，请使用2.x / fork自行维护，[点击跳转1.x文档](https://github.com/workbunny/webman-push-server/blob/1.x/README.md)**

## 简介

- 全新重构的分布式推送服务，更简单的使用，更简单的部署，更简单的代码！
- 完整且高效的即时通讯服务，支持聊天、在线推送、数字大屏等业务场景；
- 高保真复刻的[Pusher-Channel](https://support.pusher.com/hc/en-us/categories/4411973917585-Channels)，可以利用现有的[Pusher-Channel](https://support.pusher.com/hc/en-us/categories/4411973917585-Channels)客户端，其他语言(Java Swift .NET Objective-C Unity Flutter Android IOS AngularJS等)客户端地址下载地址：
  https://pusher.com/docs/channels/channels_libraries/libraries/
- 本项目1.x/2.x版本承接实现了诸多商业项目的即时通讯服务，最高日活连接达到20万+，最久的商业化项目已稳定运行3年，性能与稳定性兼顾；
- 如遇问题，欢迎 **[issue](https://github.com/workbunny/webman-push-server/issues) & PR**；

## 依赖

- **php >=8.0**
- **redis >= 5.0**

## 安装

```
composer require workbunny/webman-push-server
```

## 简介

### 架构设计：

- 摒弃了api-service服务需要挂载在Push-server的设计，独立化api-server，性能更好
- 使用redis Publish/Subscribe 代替workerman/channel作为分布式广播
- 使用redis Publish/Subscribe 代替HookServer队列作为事件监听中间件
- 简化Push-server的代码内容
- 简化了Api逻辑

```
                                   ┌─────────────┐     2 | 3
                             ┌───> | Push-server | ─── ─ · ─
                             |     └─────────────┘     1 | 4 ··· n
                             |       Hash |              ↑
                             |            |          PUB | SUB
    ┌────────────────────┐ ──┘     ┌──────────────┐ <────┘                     
    | webman-push-server | ──────> | Redis-server | 
    └────────────────────┘ ──┐     └──────────────┘ <────┐     
                             |            |          PUB | SUB
                             |       Hash |              ↓
                             |      ┌────────────┐     2 | 3
                             └────> | API-server | ─── ─ · ─
                                    └────────────┘     1 | 4 ··· n
                                     
```

### 配置说明：

配置信息及对应功能在代码注释中均有解释，详见对应代码注释；

```
|-- config
    |-- plugin
        |-- webman-push-server
            |-- app.php        # 主配置信息
            |-- bootstrap.php  # 自动加载
            |-- command.php    # 支持命令
            |-- log.php        # 日志配置
            |-- process.php    # 启动进程
            |-- redis.php      # redis配置
            |-- route.php      # APIs路由信息
```

### 频道说明：

push-server支持以下三种频道类型：

- 公共频道（public）：**客户端仅可监听公共频道，不可向公共频道推送消息；**
- 私有频道（private）：客户端可向私有频道推送/监听，一般用于端对端的通讯，服务端仅做转发；**该频道可以用于私聊场景；**
- 状态频道（presence）：与私有频道保持一致，区别在于状态频道还保存有客户端的信息，任何用户的上下线都会收到该频道的广播通知，如user_id、user_info；
**状态频道最多支持100个客户端（客户端限制，实际上可以放开）；**

### 事件说明：

推送的 event 须遵守以下的约定规范：

- **client-** 前缀的事件：拥有 **client-** 前缀的事件是客户端发起的事件，客户端在推送消息时一定会带有该前缀；
- **pusher:** 前缀的事件：拥有 **pusher:** 前缀的事件一般用于服务端消息、公共消息，比如在公共频道由服务端推送的消息、客户端发起的订阅公共消息；
- **pusher_internal:** 前缀的事件：拥有 **pusher_internal:** 前缀的事件是服务端的回执通知，一般是由客户端发起订阅、取消订阅等操作时，由服务端回执的事件信息带有该前缀的事件；


## 使用

### 客户端 (javascript) 使用

#### 1.javascript客户端

- 引入

```javascript
<script src="/plugin/workbunny/webman-push-server/push.js"> </script>
```

- 创建连接

**TIps：每 new 一个 Push 会创建一个连接。**

```javascript
// 建立连接
var connection = new Push({
    url: 'ws://127.0.0.1:8001', // websocket地址
    app_key: '<app_key>', // 在config/plugin/workbunny/webman-push-server/app.php里配置
});
```

#### 2.客户端订阅公共频道

**TIps：频道和事件可以是任意符合约定前缀的字符串，不需要服务端预先配置。**

```javascript
// 建立连接
var connection = new Push({
    url: 'ws://127.0.0.1:8001', // websocket地址
    app_key: '<app_key>', // 在config/plugin/workbunny/webman-push-server/app.php里配置
});

// 监听 public-test 公共频道
var user_channel = connection.subscribe('public-test');

// 当 public-test 频道有message事件的消息回调
user_channel.on('message', function(data) {
    // data里是消息内容
    console.log(data);
});
// 取消监听 public-test 频道
connection.unsubscribe('public-test')
// 取消所有频道的监听
connection.unsubscribeAll()
```

#### 3.客户端订阅私有/状态频道

**Tips：您需要先实现用于鉴权的接口服务**

- 私有频道

**Tips：样例鉴权接口详见 config/plugin/workbunny/webman-push-server/route.php**

```javascript
// 订阅发生前，浏览器会发起一个ajax鉴权请求(ajax地址为new Push时auth参数配置的地址)，开发者可以在这里判断，当前用户是否有权限监听这个频道。这样就保证了订阅的安全性。
var connection = new Push({
    url: 'ws://127.0.0.1:8001', // websocket地址
    app_key: '<app_key>',
    auth: 'http://127.0.0.1:8002/subscribe/auth' // 该接口是样例接口，请根据源码自行实现业务
});
// 监听 private-test 私有频道
var user_channel = connection.subscribe('private-test');
// 当 private-test 频道有message事件的消息回调
user_channel.on('message', function(data) {
    // data里是消息内容
    console.log(data);
});
// 取消监听 private-test 频道
connection.unsubscribe('private-test')
// 取消所有频道的监听
connection.unsubscribeAll()
```

- 状态频道
  
**Tips：样例鉴权接口详见 config/plugin/workbunny/webman-push-server/route.php**

- 方法一

```javascript
// 方法一

// 订阅发生前，浏览器会发起一个ajax鉴权请求(ajax地址为new Push时auth参数配置的地址)，开发者可以在这里判断，当前用户是否有权限监听这个频道。这样就保证了订阅的安全性。
var connection = new Push({
    url: 'ws://127.0.0.1:8001', // websocket地址
    app_key: '<app_key>',
    auth: 'http://127.0.0.1:8002/subscribe/auth' // 该接口是样例接口，请根据源码自行实现业务
});
```

- 方法二

```javascript
// 方法二

// 先通过接口查询获得用户信息，组装成如下
var channel_data = {
    user_id: '100',
    user_info: "{\'name\':\'John\',\'sex\':\'man\'}"
}
// 订阅发生前，浏览器会发起一个ajax鉴权请求(ajax地址为new Push时auth参数配置的地址)，开发者可以在这里判断，当前用户是否有权限监听这个频道。这样就保证了订阅的安全性。
var connection = new Push({
    url: 'ws://127.0.0.1:8001', // websocket地址
    app_key: '<app_key>',
    auth: 'http://127.0.0.1:8002/subscribe/auth', // 该接口是样例接口，请根据源码自行实现业务
    channel_data: channel_data
});

// 监听 presence-test 状态频道
var user_channel = connection.subscribe('presence-test');
// 当 presence-test 频道有message事件的消息回调
user_channel.on('message', function(data) {
    // data里是消息内容
    console.log(data);
});
// 取消监听 presence-test 频道
connection.unsubscribe('presence-test')
// 取消所有频道的监听
connection.unsubscribeAll()
```

#### 4.客户端推送

##### Tips：

- **客户端间推送仅支持私有频道(private-)/状态频道（presence-），并且客户端只能触发以 client- 开头的事件。**
客户端触发事件推送的例子
- **以下代码给所有订阅了 private-user-1 的客户端推送 client-message 事件的数据，而当前客户端不会收到自己的推送消息**

```javascript
// 以上省略

// 私有频道
var user_channel = connection.subscribe('private-user-1');
user_channel.on('client-message', function (data) {
//
});
user_channel.trigger('client-message', {form_uid:2, content:"hello"});

// 状态频道
var user_channel = connection.subscribe('presence-user-1');
user_channel.on('client-message', function (data) {
//
});
user_channel.trigger('client-message', {form_uid:2, content:"hello"});
```

### 客户端（PHP）使用

**Tips：区别于 HTTP-apis；HTTP-APIs 用于服务端管理等工作；**

#### 1. 创建连接

```php
use Workbunny\WebmanPushServer\WsClient;
use Workerman\Connection\AsyncTcpConnection;
use Workbunny\WebmanPushServer\EVENT_SUBSCRIBE;
use Workbunny\WebmanPushServer\EVENT_SUBSCRIPTION_SUCCEEDED;

// 创建连接
$client = WsClient::instance('127.0.0.1:8001', [
    'app_key'        => 'workbunny',
    'heartbeat'      => 60,
    'auth'           => 'http://127.0.0.1:8002/subscribe/auth',
    'channel_data'   => []  // channel_data
    'query'          => [], // query
    'context_option' => []
])
// 建立连接
$client->connect();
// 关闭连接
$client->disconnect();
```

#### 2. 订阅/退订

```php
use Workbunny\WebmanPushServer\WsClient;
use Workerman\Connection\AsyncTcpConnection;

// 创建连接
$client = WsClient::instance('127.0.0.1:8001', [
    'app_key'        => 'workbunny',
    'heartbeat'      => 60,
    'auth'           => 'http://127.0.0.1:8002/subscribe/auth',
    'channel_data'   => []  // channel_data
    'query'          => [], // query
    'context_option' => []
])

// 订阅一个私有通道，订阅成功后会执行回调函数
$client->subscribe('private-test', function (AsyncTcpConnection $connection, array $data) {
    // 订阅成功后打印
    dump($data);
});
// 订阅一个私有通道，不注册订阅成功后的回调
$client->subscribe('private-test');

// 取消订阅一个私有通道
$client->unsubscribe('private-test', function (AsyncTcpConnection $connection, array $data) {
    // 取消订阅成功后打印
    dump($data);
});
// 取消订阅一个私有通道，不注册订阅成功后的回调
$client->unsubscribe('private-test');

// 取消全部订阅
$client->unsubscribeAll();
```

#### 3. 触发消息

```php
// 向 private-test 通道发送 client-test 事件消息
$client->trigger('private-test', 'client-test', [
    'message' => 'hello workbunny!'
]);

// 向 presence-test 通道发送 client-test 事件消息
$client->trigger('presence-test', 'client-test', [
    'message' => 'hello workbunny!'
]);

// 事件不带 client- 前缀会抛出RuntimeException
try {
    $client->trigger('presence-test', 'test', [
        'message' => 'hello workbunny!'
    ]);
} catch (RuntimeException $exception){
    dump($exception);
}
```

#### 4. 事件注册回调

```php
use Workerman\Connection\AsyncTcpConnection;

// 注册关注private-test通道的client-test事件
$client->eventOn('private-test', 'client-test', function(AsyncTcpConnection $connection, array $data) {
    // 打印事件数据
    dump($data);
});
// 取消关注private-test通道的client-test事件
$client->eventOff('private-test', 'client-test');

// 获取所有注册事件回调
$client->getEvents();
```

#### 5. 其他

```php

// 获取客户端id，当连接创建前该方法返回null
$client->getSocketId();

// 获取已订阅通道，订阅触发前该方法返回空数组
$client->getChannels();

// 发布消息
$client->publish();

// 更多详见 WsClient.php
```

### 服务端使用

服务端会分别启动一下服务进程：
   - push-server
     - 主服务进程，用于监听websocket协议信息
     - 配置位于config/plugin/workbunny/webman-push-server/app.php
   - api-server
     - 用于提供http-api接口服务
     - 路由配置位于config/plugin/workbunny/webman-push-server/route.php
#### 1.API服务

API服务提供REST风格的http-APIs，接口内容与 [pusher-channel-api](https://pusher.com/docs/channels/library_auth_reference/rest-api/) 基本保持一致；

##### 支持的http-api接口：

| method | url                                                  | 描述                                                                                                                               |
|:-------|:-----------------------------------------------------|:---------------------------------------------------------------------------------------------------------------------------------|
| POST   | /apps/[app_id]/events                                | [对应的pusher文档地址](https://pusher.com/docs/channels/library_auth_reference/rest-api/#post-event-trigger-an-event)                   |
| POST   | /apps/[app_id]/batch_events                          | [对应的pusher文档地址](https://pusher.com/docs/channels/library_auth_reference/rest-api/#post-batch-events-trigger-multiple-events)     |
| GET    | /apps/[app_id]/channels                              | [对应的pusher文档地址](https://pusher.com/docs/channels/library_auth_reference/rest-api/#get-channels-fetch-info-for-multiple-channels) |
| GET    | /apps/[app_id]/channels/[channel_name]               | [对应的pusher文档地址](https://pusher.com/docs/channels/library_auth_reference/rest-api/#get-channel-fetch-info-for-one-channel)        |
| POST   | /apps/[app_id]/users/[user_id]/terminate_connections | [对应的pusher文档地址](https://pusher.com/docs/channels/library_auth_reference/rest-api/#post-terminate-user-connections)               |
| GET    | /apps/[app_id]/channels/[channel_name]/users         | [对应的pusher文档地址](https://pusher.com/docs/channels/library_auth_reference/rest-api/#get-users)                                     |

##### API客户端

1. 使用pusher提供的api客户端 **【不建议使用，客户端请求没有使用keep-alive】**

    ```
    composer require pusher/pusher-php-server
    ```

2. 或者使用\Workbunny\WebmanPushServer\ApiClient **【建议使用】**

**服务端推送（PHP示例）：**

```php
use Workbunny\WebmanPushServer\ApiClient;

try {
    $pusher = new ApiClient(
        'APP_KEY', 
        'APP_SECRET',
        'APP_ID',
        [
            'host'       =>"http://127.0.0.1:8001",
            'timeout'    => 60,
            'keep-alive' => true
        ]
    );
    $pusher->trigger(
        // 频道（channel）支持多个通道
        ["private-d"], 
        // 事件
        "client-a", 
        // 消息体
        [
            'message' => 'hello workbunny!'
        ],
        // query
        []
    );
} catch (GuzzleException|ApiErrorException|PusherException $e) {
    dump($e);
}
```

### 其他

#### wss代理(SSL)

https下无法使用ws连接，需要使用wss连接。这种情况可以使用nginx代理wss，配置类似如下：

```
server {
# .... 这里省略了其它配置 ...

    location /app
    {
        proxy_pass http://127.0.0.1:3131;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

重启nginx后，使用以下方式连接服务端

```javascript
var connection = new Push({
    url: 'wss://example.com',
    app_key: '<app_key>'
});
```

**Tips：wss开头，不写端口，必须使用ssl证书对应的域名连接**

#### 其他客户端地址

兼容pusher，其他语言(Java Swift .NET Objective-C Unity Flutter Android IOS AngularJS等)客户端地址下载地址：
https://pusher.com/docs/channels/channels_libraries/libraries/