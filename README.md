<p align="center"><img width="260px" src="https://chaz6chez.cn/images/workbunny-logo.png" alt="workbunny"></p>

**<p align="center">workbunny/webman-push-server</p>**

**<p align="center">🐇  Webman plugin for push server implementation. 🐇</p>**

<div align="center">
    <a href="https://github.com/workbunny/webman-push-server/actions">
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

## 简介

- **目前版本为beta版**，尚未发布正式，如遇问题，欢迎 **[issue](https://github.com/workbunny/webman-push-server/issues) & PR**；
- **本项目 fork from [webman/push](https://www.workerman.net/plugin/2)**，利用redis实现了多进程持久化存储；
- **1：1复刻 pusher-channel 服务，是完整的推送服务器实现；**
- 本插件可用于实现消息推送、单聊、群聊、直播间、站内推送等多种即时通讯场景；
- 本插件兼容 pusher-channel 的客户端，包含JS、安卓(java)、IOS(swift)、IOS(Obj-C)、uniapp等；后端推送SDK支持PHP、Node、Ruby、Asp、Java、Python、Go等；客户端自带心跳和断线自动重连，使用起来非常简单稳定；
- 本插件包含
	- **Server.php：** 基于websocket的消息推送服务 
    - **ApiService.php：** 基于http的推送APIs
	- **ApiClient.php：** 基于http-api的后端推送SDK
    - **Client.php：** 基于websocket的后端客户端
	- **HookServer.php：** 基于redis-stream的持久化服务端事件订阅服务

## 依赖

- **php >= 7.4**
- **redis >= 5.0**

## 安装

```
composer require workbunny/webman-push-server
```

## 说明

### 配置说明

配置信息及对应功能在代码注释中均有解释，详见对应代码注释；

```
|-- config
    |-- plugin
        |-- webman-push-server
            |-- app.php        # 主配置信息
            |-- bootstrap.php  # 自动加载
            |-- command.php    # 支持命令
            |-- process.php    # 启动进程
            |-- route.php      # APIs路由信息
```

push-server会启动以下三种类型进程：

- push-server：主服务；负责启动推送服务及其service
- hook-server：事件消费服务；负责消费服务内部的钩子事件
- channel-server：进程通道服务；负责多进程通讯

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

#### 1.引入javascript客户端

```javascript
<script src="/plugin/workbunny/webman-push-server/push.js"> </script>
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
    auth: 'http://127.0.0.1:8002/subscribe/private/auth' // 该接口是样例接口，请根据源码自行实现业务
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

```javascript
// 方法一

// 订阅发生前，浏览器会发起一个ajax鉴权请求(ajax地址为new Push时auth参数配置的地址)，开发者可以在这里判断，当前用户是否有权限监听这个频道。这样就保证了订阅的安全性。
var connection = new Push({
    url: 'ws://127.0.0.1:8001', // websocket地址
    app_key: '<app_key>',
    auth: 'http://127.0.0.1:8002/subscribe/presence/auth' // 该接口是样例接口，请根据源码自行实现业务
});

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
    auth: 'http://127.0.0.1:8002/subscribe/presence/auth', // 该接口是样例接口，请根据源码自行实现业务
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

#### 1. 订阅/退订

- 订阅
```php
use Workbunny\WebmanPushServer\Client;
use Workerman\Connection\AsyncTcpConnection;
use Workbunny\WebmanPushServer\EVENT_SUBSCRIBE;
use Workbunny\WebmanPushServer\EVENT_SUBSCRIPTION_SUCCEEDED;

$client = Client::connection('127.0.0.1:8001', [
    'apk_key'        => 'workbunny',
    'heartbeat'      => 60,
    'query'          => [],
    'context_option' => []
])
// 注册订阅成功回调
$client->on(EVENT_SUBSCRIPTION_SUCCEEDED, function (AsyncTcpConnection $connection, string $buffer) {
    // TODO 
    dump($buffer);
})

// private通道
$client->trigger('private-test', EVENT_SUBSCRIBE);

// presence通道
$client->trigger('presence-test', EVENT_SUBSCRIBE, [
    'user_id'   => 100,
    'user_info' => "{\'name\':\'John\',\'sex\':\'man\'}"
]);
```

- 退订
```php
use Workbunny\WebmanPushServer\Client;
use Workerman\Connection\AsyncTcpConnection;
use Workbunny\WebmanPushServer\EVENT_UNSUBSCRIBE;
use Workbunny\WebmanPushServer\EVENT_UNSUBSCRIPTION_SUCCEEDED;

$client = Client::connection('127.0.0.1:8001', [
    'apk_key'        => 'workbunny',
    'heartbeat'      => 60,
    'query'          => [],
    'context_option' => []
])
// 注册退订成功回调
$client->on(EVENT_UNSUBSCRIPTION_SUCCEEDED, function (AsyncTcpConnection $connection, string $buffer) {
    // TODO 
    dump($buffer);
})

// private通道
$client->trigger('private-test', EVENT_UNSUBSCRIBE);

// presence通道
$client->trigger('presence-test', EVENT_UNSUBSCRIBE);

// 退订所有
foreach ($client->getChannels() as $channel){
    $client->trigger($channel, EVENT_UNSUBSCRIBE);
}
```

#### 2. 发布/监听

- 发布
```php
use Workbunny\WebmanPushServer\Client;
use Workerman\Connection\AsyncTcpConnection;
use Workbunny\WebmanPushServer\EVENT_SUBSCRIBE;

$client = Client::connection('127.0.0.1:8001', [
    'apk_key'        => 'workbunny',
    'heartbeat'      => 60,
    'query'          => [],
    'context_option' => []
])

// 订阅private通道
$client->trigger('private-test', EVENT_SUBSCRIBE);

// 发送
if($client->getChannels('private-test')){
    $client->trigger('private-test', 'client-test', [
        'message' => 'hello world!'
    ]);
}
```

- 监听
```php
use Workbunny\WebmanPushServer\Client;
use Workerman\Connection\AsyncTcpConnection;
use Workbunny\WebmanPushServer\EVENT_SUBSCRIBE;

$client = Client::connection('127.0.0.1:8001', [
    'apk_key'        => 'workbunny',
    'heartbeat'      => 60,
    'query'          => [],
    'context_option' => []
])

// 订阅 client-test 事件
$client->on('client-test', function (AsyncTcpConnection $connection, string $buffer){
    if($data = json_decode($buffer, true)){
        if($data['channel'] === 'private-test'){
            dump($data['data']);
            dump($data['event']);
        }
    }
});
```

### 服务端使用

服务端会分别启动一下服务进程：
   - push-server
     - 主服务进程，用于监听websocket协议信息
     - 配置位于config/plugin/workbunny/webman-push-server/app.php
     - api-service子服务
       - api子服务，用于提供http-api接口服务
       - 路由配置位于config/plugin/workbunny/webman-push-server/route.php
   - hook-server
     - hook多进程消费服务，用于消费事件钩子，进行webhook通知
     - 配置位于config/plugin/workbunny/webman-push-server/app.php

#### 1.HOOK服务

##### 支持的HOOK事件：

- 通道类型事件
  - channel_occupied：当通道被建立时，该事件触发
  - channel_vacated：当通道被销毁时，该事件被触发
- 用户类型事件
  - member_added：当用户加入通道时，该事件被触发
  - member_removed：当用户被移除通道时，该事件被触发
- 消息类型事件
  - client_event：当通道产生客户端消息时，该事件被触发
  - server-event：当通道产生服务端消息（服务端推送消息、服务端回执消息）时，该事件被触发

##### 事件处理器：

Hook服务是多进程消费队列，消费方式是通过http的请求进行webhook通知；
对应配置详见**config/plugin/workbunny/webman-push-server/app.php**；

#### 2.API子服务

API子服务提供REST风格的http-APIs，接口内容与 [pusher-channel-api](https://pusher.com/docs/channels/library_auth_reference/rest-api/) 基本保持一致；

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

1. 使用pusher提供的api客户端

```
composer require pusher/pusher-php-server
```

2. 或者使用\Workbunny\WebmanPushServer\ApiClient

**Tpis: ApiClient 既是 pusher/pusher-php-server**

**服务端推送（PHP示例）：**

```php
use Workbunny\WebmanPushServer\ApiClient;

try {
    $pusher = new ApiClient(
        'APP_KEY', 
        'APP_SECRET',
        'APP_ID',
        //["host":webhook API 地址]
        ['host'=>"HOOK_ADDS",'scheme'=>'HTTP/HTTPS']
    );
    $pusher->trigger(
        "private-d", // 频道（channel）
        "client-a", // 事件
        "23423432"// 消息体
    );
    
    # or
    
    $pusher->trigger(
        [
            "private-a",
            "private-d",
        ], // 频道（channel）
        "client-a", // 事件
        "23423432"// 消息体
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
