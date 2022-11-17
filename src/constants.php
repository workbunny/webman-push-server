<?php
/**
 * This file is part of workbunny.
 *
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    chaz6chez<250220719@qq.com>
 * @copyright chaz6chez<250220719@qq.com>
 * @link      https://github.com/workbunny/webman-multi-push
 * @license   https://github.com/workbunny/webman-multi-push/blob/main/LICENSE
 */
declare(strict_types=1);

namespace Workbunny\WebmanPushServer;

const EVENT_CONNECTION_ESTABLISHED     = 'pusher:connection_established';
const EVENT_ERROR                      = 'pusher:error';
const EVENT_PING                       = 'pusher:ping';
const EVENT_PONG                       = 'pusher:pong';
const EVENT_SUBSCRIBE                  = 'pusher:subscribe';
const EVENT_UNSUBSCRIBE                = 'pusher:unsubscribe';
const EVENT_MEMBER_ADDED               = 'pusher_internal:member_added';
const EVENT_MEMBER_REMOVED             = 'pusher_internal:member_removed';
const EVENT_SUBSCRIPTION_SUCCEEDED     = 'pusher_internal:subscription_succeeded';
const EVENT_UNSUBSCRIPTION_SUCCEEDED   = 'pusher_internal:unsubscription_succeeded';

const PUSH_SERVER_EVENT_MEMBER_ADDED     = 'member_added';
const PUSH_SERVER_EVENT_MEMBER_REMOVED   = 'member_removed';
const PUSH_SERVER_EVENT_CHANNEL_OCCUPIED = 'channel_occupied';
const PUSH_SERVER_EVENT_CHANNEL_VACATED  = 'channel_vacated';
const PUSH_SERVER_EVENT_CLIENT_EVENT     = 'client_event';
const PUSH_SERVER_EVENT_SERVER_EVENT     = 'server_event';

const CHANNEL_TYPE_PUBLIC              = 'public';
const CHANNEL_TYPE_PRIVATE             = 'private';
const CHANNEL_TYPE_PRESENCE            = 'presence';