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

    public static function purgeUrls($urls)
    {
        $hosts = [];
        foreach ($urls as $url) {
            $urlParts = parse_url($url);
            $urlHost = $urlParts['host'];
            if (!isset($hosts[$urlHost])) {
                $hosts[$urlHost] = [];
            }
            $withPort = str_ireplace($urlHost, $urlHost . ':8080', $url);
            $hosts[$urlHost][] = str_ireplace('https://', 'http://', $withPort);
        }

        $base = 'http://' . getenv('SERVD_PROJECT_SLUG') . '-' . getenv('ENVIRONMENT') . '.project-' . getenv('SERVD_PROJECT_SLUG') . '.svc.cluster.local';

        $batchSize = is_numeric(getenv('SERVD_PURGE_BATCH_SIZE'))
            ? intval(getenv('SERVD_PURGE_BATCH_SIZE'))
            : 1000;

        foreach ($hosts as $host => $hostUrls) {
            $urlBatches = array_chunk($hostUrls, $batchSize);
            foreach ($urlBatches as $i => $hostUrls) {
                $handler = HandlerStack::create();
                $handler->push(Middleware::mapRequest(function (RequestInterface $request) use ($host) {
                    return $request->withHeader('Host', $host);
                }));
                $config['handler'] = $handler;
                $client = Craft::createGuzzleClient([
                    'base_uri' => $base,
                    'handler' => $handler,
                    'proxy' => null,
                ]);
                try {
                    $client->request('PURGE', '/', [
                        'json' => [
                            'uris' => $hostUrls,
                            'purge_mode' => 'invalidate'
                        ],
                        'allow_redirects' => false
                    ]);
                } catch (BadResponseException $e) {
                    //Nothing
                }
            }
        }

        return true;
    }
}
