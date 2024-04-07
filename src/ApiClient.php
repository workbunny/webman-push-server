<?php
/**
 * This file is part of workbunny.
 *
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    chaz6chez<chaz6chez1993@outlook.com>
 * @copyright chaz6chez<chaz6chez1993@outlook.com>
 * @link      https://github.com/workbunny/webman-push-server
 * @license   https://github.com/workbunny/webman-push-server/blob/main/LICENSE
 */
declare(strict_types=1);

namespace Workbunny\WebmanPushServer;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Workbunny\WebmanPushServer\Events\Subscribe;
use GuzzleHttp\Client;

class ApiClient
{

    /** @var Client|null  */
    protected Client|null $client = null;

    /** @var array  */
    protected array $settings = [
        'secret'                => '',
        'app_id'                => '',
        'base_path'             => '',
        'auth_key'              => '',

        'host'                  => 'http://127.0.0.1:80',
        'timeout'               => 30,
        'keep-alive'            => true
    ];

    /**
     * @param string $authKey
     * @param string $secret
     * @param string $appId
     * @param array $options
     */
    public function __construct(string $authKey, string $secret, string $appId, array $options = [])
    {
        $this->settings['auth_key'] = $authKey;
        $this->settings['secret'] = $secret;
        $this->settings['app_id'] = $appId;
        $this->settings['base_path'] = '/apps/' . $this->settings['app_id'];

        foreach ($options as $key => $value) {
            if (isset($this->settings[$key])) {
                $this->settings[$key] = $value;
            }
        }
        $this->getClient(true);
    }

    /**
     * 获取Client
     *
     * @param bool $init
     * @return Client
     */
    public function getClient(bool $init = false): Client
    {
        if ($init and !$this->client instanceof Client) {
            $this->client = new Client([
                'timeout'  => $this->settings['timeout'],
                'base_uri' => $this->settings['host']
            ]);
        }
        return $this->client;
    }

    /**
     * 请求
     *
     * @param string $method
     * @param string $path
     * @param string $body
     * @param array $queryParams
     * @param array $headers
     * @return array
     * @throws ClientException
     */
    public function request(string $method, string $path, string $body, array $queryParams = [], array $headers = []): array
    {
        $path = $this->settings['base_path'] . $path;
        $queryParams['body_md5'] = md5($body);
        try {
            $response = $this->getClient()->request($method, $path, [
                RequestOptions::QUERY       => $this->sign($path, $method, $queryParams),
                RequestOptions::BODY        => $body,
                RequestOptions::HEADERS     => [
                        'Content-Type'  => 'application/json',
                        'Connection'    => $this->settings['keep-alive'] ? 'keep-alive' : 'close',
                        'X-Push-Client' => 'push-server ' . VERSION
                    ] + $headers,
                RequestOptions::HTTP_ERRORS => true,
            ]);
            return json_decode($response->getBody()->getContents(), true) ?: [];
        } catch (RequestException $e) {
            throw new ClientException(
                $e->getResponse()?->getBody()->getContents() ?: $e->getMessage(),
                $e->getResponse()?->getStatusCode() ?: 0
            );
        } catch (\Throwable $throwable) {
            throw new ClientException(
                "Push client request failed. [{$throwable->getMessage()}]", $throwable->getCode(), $throwable
            );
        }
    }

    /**
     * @param string $channel
     * @param array $params
     * @return array
     * @throws ClientException
     */
    public function getChannelInfo(string $channel, array $params = []): array
    {
        return $this->request('GET','/channels/' . $channel, '', $params);
    }

    /**
     * @param array $params
     * @return array
     * @throws ClientException
     */
    public function getChannels(array $params = []): array
    {
        return $this->request('GET', '/channels', '', $params);
    }

    /**
     * @param string $channel
     * @return array
     */
    public function getPresenceUsers(string $channel): array
    {
        return $this->request('GET', "/channels/$channel/users", '');
    }

    /**
     * @param array $channels
     * @param string $event
     * @param mixed $data
     * @param array $params
     * @return array
     */
    public function trigger(array $channels, string $event, mixed $data, array $params = []): array
    {
        $socketId = $params['socket_id'] ?? null;
        unset($params['socket_id']);
        return $this->request('POST', '/events', json_encode([
            'channels'  => $channels,
            'name'      => $event,
            'data'      => $data,
            'socket_id' => $socketId
        ],JSON_UNESCAPED_UNICODE), $params);
    }

    /**
     * @param array $batch
     * @param array $params
     * @return array
     */
    public function triggerBatch(array $batch, array $params = []): array
    {
        return $this->request('POST', '/batch_events', json_encode([
            'batch'  => $batch,
        ],JSON_UNESCAPED_UNICODE), $params);
    }

    /**
     * @param string $userId
     * @return array
     */
    public function terminateUserConnections(string $userId): array
    {
        return $this->request('POST', "/users/$userId/terminate_connections", '');
    }

    /**
     * @param string $appKey
     * @param string $appSecret
     * @param string $socketId
     * @param string $channel
     * @param array $channelData
     * @return string
     */
    public static function subscribeAuth(string $appKey, string $appSecret, string $socketId, string $channel, array $channelData = []): string
    {
        return Subscribe::auth($appKey, $appSecret, $socketId, $channel, $channelData);
    }

    /**
     * @param string $appKey
     * @param string $appSecret
     * @param string $httpMethod
     * @param string $httpPath
     * @param array $query
     * @return mixed
     */
    public static function routeAuth(string $appKey, string $appSecret, string $httpMethod, string $httpPath, array $query): mixed
    {
        return Server::isDebug() ? 'test' : self::build_auth_query_params($appKey, $appSecret, $httpMethod, $httpPath, $query)['auth_signature'];
    }

    /**
     * Build the required HMAC'd auth string.
     *
     * @param string $auth_key
     * @param string $auth_secret
     * @param string $request_method
     * @param string $request_path
     * @param array $query_params [optional]
     * @param string $auth_version [optional]
     * @param string|null $auth_timestamp [optional]
     * @return array
     */
    public static function build_auth_query_params(
        string $auth_key,
        string $auth_secret,
        string $request_method,
        string $request_path,
        array $query_params = [],
        string $auth_version = '1.0',
        string $auth_timestamp = null
    ): array {
        $params = [];
        $params['auth_key'] = $auth_key;
        $params['auth_timestamp'] = (is_null($auth_timestamp) ? time() : $auth_timestamp);
        $params['auth_version'] = $auth_version;

        $params = array_merge($params, $query_params);
        ksort($params);

        $string_to_sign = "$request_method\n" . $request_path . "\n" . self::array_implode('=', '&', $params);

        $auth_signature = hash_hmac('sha256', $string_to_sign, $auth_secret, false);

        $params['auth_signature'] = $auth_signature;

        return $params;
    }

    /**
     * Implode an array with the key and value pair giving
     * a glue, a separator between pairs and the array
     * to implode.
     *
     * @param string       $glue      The glue between key and value
     * @param string       $separator Separator between pairs
     * @param array|string $array     The array to implode
     *
     * @return string The imploded array
     */
    public static function array_implode(string $glue, string $separator, $array): string
    {
        if (!is_array($array)) {
            return $array;
        }

        $string = [];
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $val = implode(',', $val);
            }
            $string[] = "{$key}{$glue}{$val}";
        }

        return implode($separator, $string);
    }

    /**
     * Utility function used to generate signing headers
     *
     * @param string $path
     * @param string $request_method
     * @param array $query_params [optional]
     *
     * @return array
     */
    private function sign(string $path, string $request_method = 'GET', array $query_params = []): array
    {
        return self::build_auth_query_params(
            $this->settings['auth_key'],
            $this->settings['secret'],
            $request_method,
            $path,
            $query_params
        );
    }
}