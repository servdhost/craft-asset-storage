<?php

namespace servd\AssetStorage\StaticCache;

use Craft;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;

class Ledge
{

    public static $client = null;

    public static function purgeUrl($url)
    {

        $urlParts = parse_url($url);

        $handler = HandlerStack::create();
        $handler->push(Middleware::mapRequest(function (RequestInterface $request) use ($urlParts) {
            return $request->withHeader('Host', $urlParts['host']);
        }));
        $config['handler'] = $handler;

        if (static::$client == null) {
            $base = 'http://' . getenv('SERVD_PROJECT_SLUG') . '-' . getenv('ENVIRONMENT') . '.project-' . getenv('SERVD_PROJECT_SLUG') . '.svc.cluster.local';
            static::$client = Craft::createGuzzleClient([
                'base_uri' => $base,
                'handler' => $handler,
            ]);
        }

        $pathPart = $urlParts['path'];
        if (!empty($urlParts['query'])) {
            $pathPart .= '?' . $urlParts['query'];
        }

        try {
            static::$client->request('PURGE', $pathPart);
        } catch (BadResponseException $e) {
            //Nothing
        }
        try {
            static::$client->request('PURGE', $pathPart, [
                'headers' => [
                    'X-Requested-With' => 'XMLHttpRequest'
                ]
            ]);
        } catch (BadResponseException $e) {
            //Nothing
        }

        return true;
    }

    public static function purgeUrls($urls)
    {

        $hosts = [];
        foreach ($urls as $url) {
            $urlParts = parse_url($url);
            $urlHost = $urlParts['host'];
            if (!isset($hosts[$urlHost])) {
                $hosts[$urlHost] = [];
            }
            $hosts[$urlHost][] = str_ireplace('https://', 'http://', $url);
        }

        foreach ($hosts as $host => $hostUrls) {
            $handler = HandlerStack::create();
            $handler->push(Middleware::mapRequest(function (RequestInterface $request) use ($host) {
                return $request->withHeader('Host', $host);
            }));
            $config['handler'] = $handler;
            $base = 'http://' . getenv('SERVD_PROJECT_SLUG') . '-' . getenv('ENVIRONMENT') . '.project-' . getenv('SERVD_PROJECT_SLUG') . '.svc.cluster.local';
            $client = Craft::createGuzzleClient([
                'base_uri' => $base,
                'handler' => $handler,
            ]);
            try {
                $client->request('PURGE', '/', [
                    'json' => [
                        'uris' => $hostUrls,
                        'purge_mode' => 'invalidate',
                        'headers' => []
                    ]
                ]);
            } catch (BadResponseException $e) {
                //Nothing
            }
            try {
                $client->request('PURGE', '/', [
                    'json' => [
                        'uris' => $hostUrls,
                        'purge_mode' => 'invalidate',
                        'headers' => [
                            'X-Requested-With' => 'XMLHttpRequest'
                        ]
                    ]
                ]);
            } catch (BadResponseException $e) {
                //Nothing
            }
        }

        return true;
    }

    public static function purgeAllUrls($urls)
    {
        // TODO: Perhaps make this cleverer
        foreach ($urls as $url) {
            static::purgeUrl($url);
        }
    }
}
