<p align="center"><img width="260px" src="https://chaz6chez.cn/images/workbunny-logo.png" alt="workbunny"></p>

**<p align="center">workbunny/webman-push-server</p>**

**<p align="center">🐇  Webman plugin for push server implementation. 🐇</p>**

# Webman-push-server

## 简介

- **本项目 fork from [webman/push](https://www.workerman.net/plugin/2)，是webman/push的多进程持久化存储版本的push-server；**
- **可以把该项目理解为 1：1 复刻的 pusher-channel服务，它既是一个插件，也是一个完整项目；**
- workbunny/webman-push-server 是一个推送插件，客户端基于订阅模式，兼容 pusher，拥有众多客户端如JS、安卓(java)、IOS(swift)、IOS(Obj-C)、uniapp。后端推送SDK支持PHP、Node、Ruby、Asp、Java、Python、Go等。客户端自带心跳和断线自动重连，使用起来非常简单稳定。适用于消息推送、聊天等诸多即时通讯场景。


## 依赖

- **php >= 7.4**
- **redis >= 5.0**

## 安装

```
composer require workbunny/webman-push-server
```

## 使用说明

### 频道类型：

- 公共频道（public）

**客户端仅可监听公共频道，不可向公共频道推送消息；** 通常来说服务端启动时可启动一个公共频道，所有客户端在连接时订阅该频道，即可实现全站广播；

- 私有频道（private）

客户端可向私有频道推送/监听，一般用于端对端的通讯，服务端仅做转发；**该频道可以用于私聊场景；**

- 状态频道（presence）

与私有频道保持一致，区别在于状态频道还保存有客户端的信息，任何用户的上下线都会收到该频道的广播通知，如user_id、user_info；
**状态频道最多支持100个客户端；可以用于群聊场景；**

### 事件类型：

- **pusher:client-** 前缀的事件

拥有 **pusher:client-** 前缀的事件是客户端发起的事件，客户端在推送消息时一定会带有该前缀；

- **pusher:** 前缀的事件

拥有 **pusher:** 前缀，但不包含 **client-** 前缀的事件一般用于服务端消息、公共消息，比如在公共频道由服务端推送的消息、客户端发起的订阅公共消息；

- **pusher_internal:** 前缀的事件

拥有 **pusher_internal:** 前缀的事件是服务端的回执通知，一般是由客户端发起订阅、取消订阅等操作时，由服务端回执的事件信息带有该前缀的事件；

### 客户端 (javascript) 使用

#### 1.引入javascript客户端

```javascript
<script src="/plugin/workbunny/webman-push-server/push.js"> </script>
```

#### 2.客户端订阅公共频道

```javascript
// 建立连接
var connection = new Push({
url: 'ws://127.0.0.1:3131', // websocket地址
app_key: '<app_key，在config/plugin/webman/push/app.php里获取>',
auth: '/plugin/webman/push/auth' // 订阅鉴权(仅限于私有频道)
});
// 假设用户uid为1
var uid = 1;
// 浏览器监听user-1频道的消息，也就是用户uid为1的用户消息
var user_channel = connection.subscribe('user-' + uid);

// 当user-1频道有message事件的消息时
user_channel.on('message', function(data) {
// data里是消息内容
console.log(data);
});
// 当user-1频道有friendApply事件时消息时
user_channel.on('friendApply', function (data) {
// data里是好友申请相关信息
console.log(data);
});

// 假设群组id为2
var group_id = 2;
// 浏览器监听group-2频道的消息，也就是监听群组2的群消息
var group_channel = connection.subscribe('group-' + group_id);
// 当群组2有message消息事件时
group_channel.on('message', function(data) {
// data里是消息内容
console.log(data);
});
```
**TIps：以上例子中subscribe实现频道订阅，message friendApply 是频道上的事件。频道和事件是任意字符串，不需要服务端预先配置。**

#### 3.客户端（javascript）订阅私有/状态频道

**Tips：您需要先实现一个用于鉴权的接口服务**

```javascript
var connection = new Push({
url: 'ws://127.0.0.1:3131', // websocket地址
app_key: '<app_key>',
auth: '<YOUR_AUTH_URL>' // 您需要实现订阅鉴权接口服务
});

// 假设用户uid为1
var uid = 1;
// 浏览器监听private-user-1私有频道的消息
var user_channel = connection.subscribe('private-user-' + uid);
// 浏览器监听presence-group-1状态频道的消息
var push = {
    channel_data : {
        user_id: 1,
        user_info: {
            name: 'SomeBody',
            sex: 'Unknown'
        }
    }
};
var user_channel = connection.subscribe('presence-group-' + uid, push);

// 订阅发生前，浏览器会发起一个ajax鉴权请求(ajax地址为new Push时auth参数配置的地址)，开发者可以在这里判断，当前用户是否有权限监听这个频道。这样就保证了订阅的安全性。
```

#### 4.客户端（javascript）推送

##### Tips：

- **客户端间推送仅支持私有频道(private-开头的频道)，并且客户端只能触发以 client- 开头的事件。**
客户端触发事件推送的例子
- **以下代码给所有订阅了 private-user-1 的客户端推送 client-message 事件的数据，而当前客户端不会收到自己的推送消息**

```javascript
var user_channel = connection.subscribe('private-user-1');
user_channel.on('client-message', function (data) {
//
});
user_channel.trigger('client-message', {form_uid:2, content:"hello"});
```

### 服务端使用

服务端会分别启动一下服务进程：
- main-server
  - 主服务进程，用于监听websocket协议信息，拉起子服务
  - 配置位于config/plugin/workbunny/webman-push-server/app.php
- api-service
  - api子服务，用于提供http-api接口服务
  - 配置位于config/plugin/workbunny/webman-push-server/services.php
- hook-service
  - hook子服务，用户启动钩子程序的消费者队列
  - 配置位于config/plugin/workbunny/webman-push-server/services.php

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
app_key: '<app_key，在config/plugin/webman/push/app.php里获取>',
auth: '/plugin/webman/push/auth' // 订阅鉴权(仅限于私有频道)
});
```

**Tips：wss开头，不写端口，必须使用ssl证书对应的域名连接**

#### 其他客户端地址

兼容pusher，其他语言(Java Swift .NET Objective-C Unity Flutter Android IOS AngularJS等)客户端地址下载地址：
https://pusher.com/docs/channels/channels_libraries/libraries/