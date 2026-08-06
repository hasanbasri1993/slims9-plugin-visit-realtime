<?php

if (!defined('INDEX_AUTH')) {
    die("can not access this file directly");
}

/**
 * Minimal replacement for pusher/pusher-php-server, covering only
 * trigger() (the single call this plugin actually uses). Implements
 * Pusher Channels' REST API auth signing directly with curl +
 * hash_hmac, so the plugin needs no vendor/ tree at all.
 *
 * @see https://pusher.com/docs/channels/library_auth_reference/rest-api/
 */
class SimplePusher
{
    public function __construct(
        private string $key,
        private string $secret,
        private string $appId,
        private string $cluster = 'ap1',
        private bool $useTls = true
    ) {
    }

    /**
     * Trigger an event on a channel. Returns true on a 200 response
     * from Pusher, false otherwise (network error or non-2xx).
     *
     * Pass $excludeSocketId (the triggering client's own Pusher connection
     * socket_id) to stop that same client from receiving the event back.
     */
    public function trigger(string $channel, string $event, array $data, ?string $excludeSocketId = null): bool
    {
        $payload = [
            'name'     => $event,
            'channels' => [$channel],
            'data'     => json_encode($data),
        ];
        if (!empty($excludeSocketId)) {
            $payload['socket_id'] = $excludeSocketId;
        }
        $body = json_encode($payload);

        $path = "/apps/{$this->appId}/events";

        $params = [
            'auth_key'       => $this->key,
            'auth_timestamp' => time(),
            'auth_version'   => '1.0',
            'body_md5'       => md5($body),
        ];
        ksort($params);
        $queryString = http_build_query($params);

        $signature = hash_hmac('sha256', "POST\n{$path}\n{$queryString}", $this->secret);
        $queryString .= '&auth_signature=' . $signature;

        $scheme = $this->useTls ? 'https' : 'http';
        $url = "{$scheme}://api-{$this->cluster}.pusher.com{$path}?{$queryString}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 5,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            error_log("SimplePusher: {$curlError}");
        }

        return $httpCode === 200;
    }
}
