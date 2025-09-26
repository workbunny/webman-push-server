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

# 说明

- **3.0：全新架构 【推荐】**
- **2.0：旧版架构 LTS版本 | [点击跳转2.0文档](https://github.com/workbunny/webman-push-server/blob/2.x/README.md)**
- **~~1.0：旧版架构~~，不再维护，请使用2.0 / fork自行维护 | [点击跳转1.0文档](https://github.com/workbunny/webman-push-server/blob/1.x/README.md)**

# 简介

- 全新重构的分布式推送服务，更高的性能，更简单的使用，更简单的部署，更简单的代码！
- 完整且高效的即时通讯服务，支持聊天、在线推送、数字大屏等双向通讯长连接业务场景；
- 高保真复刻的[Pusher-Channel](https://support.pusher.com/hc/en-us/categories/4411973917585-Channels)，可以利用现有的[Pusher-Channel](https://support.pusher.com/hc/en-us/categories/4411973917585-Channels)客户端，其他语言(Java Swift .NET Objective-C Unity Flutter Android IOS AngularJS等)客户端地址下载地址：
  https://pusher.com/docs/channels/channels_libraries/libraries/
- 本项目承接实现了诸多商业项目的即时通讯服务，最高日活连接达到20万+，最久的商业化项目已稳定运行3年+，性能与稳定性兼顾；
- 3.0与2.0相比，具备更低的广播延迟（上下界减少8%），具备更高的承载能力（QPS提升12%），具备更多样的部署方案和更多样的拓展开发能力；
- 如遇问题，欢迎 **[issue](https://github.com/workbunny/webman-push-server/issues) & PR**；

## 架构

- 摒弃了api-service服务需要挂载在Push-server的设计，独立化api-server，性能更好
- 使用redis Publish/Subscribe 代替workerman/channel作为分布式广播
- 使用redis Publish/Subscribe 代替HookServer队列作为事件监听中间件
- 简化Push-server的代码内容
- 简化了Api逻辑

```
                                   ┌─────────────┐     2 | 3
                             ┌───> | Push-server | ─── ─ · ─
                             |     └─────────────┘     1 | 4 ··· n
                             |       Hash | register     ↑
                             |            |          PUB | SUB
    ┌────────────────────┐ ──┘     ┌──────────────┐ <────┘                     
    | webman-push-server | ──────> | Redis-server | 
    └────────────────────┘ ──┐     └──────────────┘ <────┐     
                             |            |          PUB | SUB
                             |       Hash | register     ↓
                             |      ┌────────────┐     2 | 3
                             └────> | API-server | ─── ─ · ─
                                    └────────────┘     1 | 4 ··· n
                                     
```

## 约定

### 配置说明

配置信息及对应功能在代码注释中均有解释，详见对应代码注释；

```
|-- config
    |-- plugin
        |-- webman-push-server
            |-- app.php         # 主配置信息
            |-- bootstrap.php   # 自动加载
            |-- command.php     # 支持命令
            |-- log.php         # 日志配置
            |-- middlewares.php # 基础中间件
            |-- process.php     # 启动进程
            |-- redis.php       # redis配置
            |-- route.php       # APIs路由信息
            |-- registrar.php   # 分布式服务注册器配置
```

### 频道说明

#### push-server支持以下三种频道类型：

- 公共频道（public）：**客户端仅可监听公共频道，不可向公共频道推送消息；**
- 私有频道（private）：客户端可向私有频道推送/监听，一般用于端对端的通讯，服务端仅做转发；**该频道可以用于私聊场景；**
- 状态频道（presence）：与私有频道保持一致，区别在于状态频道还保存有客户端的信息，任何用户的上下线都会收到该频道的广播通知，如user_id、user_info；
**状态频道最多支持100个客户端（客户端限制，实际上可以放开）；**

### 事件说明

#### 1. 默认 event 遵守以下的约定规范：

- **client-** 前缀的事件：拥有 **client-** 前缀的事件是客户端发起的事件，客户端在推送消息时一定会带有该前缀；
- **pusher:** 前缀的事件：拥有 **pusher:** 前缀的事件一般用于服务端消息、公共消息，比如在公共频道由服务端推送的消息、客户端发起的订阅公共消息；
- **pusher_internal:** 前缀的事件：拥有 **pusher_internal:** 前缀的事件是服务端的回执通知，一般是由客户端发起订阅、取消订阅等操作时，由服务端回执的事件信息带有该前缀的事件；

#### 2. event支持自定义注册

# 使用

## 服务端

### 1. 环境依赖

- **php >=8.0**
- **webman >= 1.0**
- **redis >= 5.0**

### 2. 安装使用

- 使用composer安装

```
composer require workbunny/webman-push-server
```

- webman框架自动加载配置
- 在config/plugin/workbunny/webman-push-server/中配置对应文件
- webman启动

### 3. 服务说明

#### push-server服务

- push-server服务用于监听websocket消息，是实现即时通讯功能的主要服务
- push-server服务支持多进程，通讯方式及基础数据储存方式为redis
- config/plugin/workbunny/webman-push-server/process.php中可调节启动进程数，默认为cpu count
- config/plugin/workbunny/webman-push-server/app.php中可配置心跳等参数
- config/plugin/workbunny/webman-push-server/redis.php中可配置redis连接信息
- config/plugin/workbunny/webman-push-server/middlewares.php中可配置push-server消息中间件，可用于消息的拦截、过滤、路由等

#### api-server服务

- api-server服务用于监听http/https消息，对外提供REST风格的open-apis，API服务提供REST风格的http-APIs，接口内容与 [pusher-channel-api](https://pusher.com/docs/channels/library_auth_reference/rest-api/) 基本保持一致
- config/plugin/workbunny/webman-push-server/process.php中可调节启动进程数，默认为cpu count
- config/plugin/workbunny/webman-push-server/app.php中可配置流量统计间隔等参数
- config/plugin/workbunny/webman-push-server/route.php中为基础open-apis的实现
- config/plugin/workbunny/webman-push-server/middlewares.php中可配置api-server消息中间件，可用于消息的拦截、过滤、路由等

##### open-apis列表：

| method | url                                                  | 描述                                                                                                                               |
|:-------|:-----------------------------------------------------|:---------------------------------------------------------------------------------------------------------------------------------|
| POST   | /apps/[app_id]/events                                | [对应的pusher文档地址](https://pusher.com/docs/channels/library_auth_reference/rest-api/#post-event-trigger-an-event)                   |
| POST   | /apps/[app_id]/batch_events                          | [对应的pusher文档地址](https://pusher.com/docs/channels/library_auth_reference/rest-api/#post-batch-events-trigger-multiple-events)     |
| GET    | /apps/[app_id]/channels                              | [对应的pusher文档地址](https://pusher.com/docs/channels/library_auth_reference/rest-api/#get-channels-fetch-info-for-multiple-channels) |
| GET    | /apps/[app_id]/channels/[channel_name]               | [对应的pusher文档地址](https://pusher.com/docs/channels/library_auth_reference/rest-api/#get-channel-fetch-info-for-one-channel)        |
| POST   | /apps/[app_id]/users/[user_id]/terminate_connections | [对应的pusher文档地址](https://pusher.com/docs/channels/library_auth_reference/rest-api/#post-terminate-user-connections)               |
| GET    | /apps/[app_id]/channels/[channel_name]/users         | [对应的pusher文档地址](https://pusher.com/docs/channels/library_auth_reference/rest-api/#get-users)                                     |

## 客户端

### javascript客户端

#### 1. 安装

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

#### 5. wss代理(SSL)

- https下无法使用ws连接，需要使用wss连接。这种情况可以使用nginx代理wss，配置类似如下：

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

- 重启nginx后，使用以下方式连接服务端

```javascript
var connection = new Push({
    url: 'wss://example.com',
    app_key: '<app_key>'
});
```

##### Tips：
**wss开头，不写端口，必须使用ssl证书对应的域名连接**

---

### websocket-php客户端

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

---

### open-apis-php客户端

#### 1. 安装

1. 或者使用\Workbunny\WebmanPushServer\ApiClient **【建议使用】**

    ```
    composer require workbunny/webman-push-server
    ```

2. 使用pusher提供的api客户端 **【不建议使用，客户端请求没有使用keep-alive】**

    ```
    composer require pusher/pusher-php-server
    ```

#### 2. 推送

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

#### 3. 其他功能详见open-apis列表

---


### 其他客户端

- 兼容pusher，其他语言(Java Swift .NET Objective-C Unity Flutter Android IOS AngularJS等)客户端地址下载地址：
https://pusher.com/docs/channels/channels_libraries/libraries/

---

---

## 进阶用法

### 1. push-server中间件服务

在一些服务器监控场景下，我们需要获取全量的往来信息，包括客户端的消息和服务端的回执等

- 创建一个中间件服务类，use引入ChannelMethods
  - 客户端与服务端的任何通讯消息会触达`_subscribeResponse`方法，请在`_subscribeResponse`方法中实现对应业务逻辑，入日志等；

  - `_subscribeResponse`方法是经过业务处理后的方法，如果想要订阅原始数据，请实现`_subscribeRaw`方法
  
```php
<?php declare(strict_types=1);

namespace YourNamespace;

use Workbunny\WebmanPushServer\Traits\ChannelMethods;

class PushServerMiddleware
{
    use ChannelMethods;

    /** @inheritDoc */
    public static function _subscribeResponse(string $type, array $data): void
    {
        // TODO 业务类型中间件
    }

    /** @inheritDoc */
    public static function _subscribeRaw($channel, $raw): void
    {
        // TODO 订阅通道原始数据
    }
}
```

- 在项目config/process.php或config/plugin/workbunny/webman-push-server/process.php中添加配置

```php
    // push-server-middleware
    'push-server-middleware' => [
        'handler'     => YourNamespace\PushServerMiddleware::class,
        'count'       => 1,
    ],
```

- 启动webman即可

#### Tips：

- 中间件切记保持单进程运行，本质上是与push-server进程组监听同一个内部通讯通道
- `_subscribeResponse`方法中请勿执行耗时操作，否则将影响性能，建议异步执行，如投送到队列进行消费
- `_subscribeResponse`中`type`为`client`时为客户端消息，`type`为`server`时为服务端回执消息，其他则详见[AbstractPublishType.php](src/PublishTypes/AbstractPublishType.php)
- `_subscribeRaw`方法中请勿执行耗时操作，否则将影响性能，建议异步执行，如投送到队列进行消费
- `_subscribeRaw`中`channel`为订阅的通道名，`raw`为原始数据，通常为json字符串
- 该中间件更适合作为监控服务或者日志服务，如果作为拦截器等服务，可能存在调用链路较长的问题
- 样例查看，[PushServerMiddleware.php](tests/Examples/PushServerMiddleware.php)

### 2. push-server onMessage中间件

我们在使用过程中可能需要为push-server的onMessage做一些安全性考虑或者数据过滤和拦截的功能，那么消息中间件非常适合该场景

- 以拦截非websocket协议消息距离
- 在config/plugin/workbunny/webman-push-server/middlewares.php中添加中间件回调函数

```php
    // push server root middlewares
    'push-server' => [
        // 以拦截非websocket消息举例
        function (Closure $next, TcpConnection $connection, $data): void
        {
            // 拦截非websocket服务消息
            if (!$connection->protocol instanceof \Workerman\Protocols\Websocket) {
                $connection->close('Not Websocket');
                return;
            }
            $next($connection, $data);
        }
    ],
```

- 启动webman即可

#### Tips：

- push-server onMessage中间件由于传递了connection对象，所以我们可以使用PushServer类中针对connection操作的所有方法，无需使用open-apis等进行回执
- onMessage中间件可以使用例子中的Closure匿名函数方式，也可以使用任意callable函数，也可以使用类方法，只需要满足实例的入参和出参即可

### 3. 自定义事件响应

我们在使用过程中，可能需要自定义事件响应客户端的消息，那么我们可以创建一个自定义响应类

- 创建自定义响应类

```php
<?php declare(strict_types=1);

namespace Tests\Examples;

use Workbunny\WebmanPushServer\Events\AbstractEvent;
use Workerman\Connection\TcpConnection;

class OtherEvent extends AbstractEvent
{
    /**
     * @inheritDoc
     */
    public function response(TcpConnection $connection, array $request): void
    {
        // todo
    }
}
```

- 在服务启动前注册该相应类，注册方法可以放在webman的bootstrap中

```php
\Workbunny\WebmanPushServer\Events\AbstractEvent::register('other', \Tests\Examples\OtherEvent::class);
```

- 启动webman即可
- 当合法客户端发送event=other时，将会触发该事件响应

#### Tips：

- response传递了connection对象及request对象，所以我们可以使用PushServer类中针对connection操作的所有方法，无需使用open-apis等进行回执
- 样例查看，[OtherEvent.php](tests/Examples/OtherEvent.php)

### 4. 自定义内部广播事件

内部广播默认存在client事件和server事件，push-server默认只会响应该两种事件，如果我们需要对其他额外的内部事件进行处理时可使用该方案

- 创建自定义内部广播事件

```php
<?php declare(strict_types=1);

namespace Tests\Examples;

use Workbunny\WebmanPushServer\PublishTypes\AbstractPublishType;

class OtherType extends AbstractPublishType
{

    /** @inheritDoc */
    public static function response(array $data): void
    {
        static::verify($data, [
            ['appKey', 'is_string', true],
            ['channel', 'is_string', true],
            ['event', 'is_string', true],
            ['socketId', 'is_string', false]
        ]);
        // todo
    }
}
```

- 在服务启动前注册该相应类，注册方法可以放在webman的bootstrap中

```php
\Workbunny\WebmanPushServer\PublishTypes\AbstractPublishType::register('other', \Tests\Examples\OtherType::class);
```

- 启动webman即可
- 当使用内部广播发送type=other时，将会触发该事件响应

```php
\Workbunny\WebmanPushServer\PushServer::publish('other', [
    'a' => 'a'
])
```

#### Tips：
- 样例查看，[OtherType.php](tests/Examples/OtherType.php)

### 5. 高阶部署

#### 分布式部署

- 在不同的服务项目中引入该插件
- 配置redis指向同一个redis服务
- 启动所有服务项目即可

#### push-server api-server分离部署

- 在A服务项目中配置config/plugin/workbunny/webman-push-server/process.php中注释api-server进程配置
- 在B服务项目中配置config/plugin/workbunny/webman-push-server/process.php中注释push-server进程配置
- 分别启动A、B服务即可

#### Tips：
- 分布式部署与分离式部署可以相互结合，达到最小颗粒度的部署
- redis配置中可以独立配置storage与channel，以达到最高性能

### 6. 二次开发

在一些场景下，我们可能需要对push-server进行二次开发，那么我们可以使用组合式拓展开发，以实现对push-server的拓展

- 创建自定义push-server类
- 以PushServer为样例，引入Traits并实现其方法；或者继承PushServer类进行方法重写
- 修改process启动配置，将push-server替换为自定义push-server类
- 启动webman即可
