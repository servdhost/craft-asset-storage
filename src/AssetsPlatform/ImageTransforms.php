<?php

namespace servd\AssetStorage\AssetsPlatform;

use Craft;
use craft\elements\Asset;
use servd\AssetStorage\Plugin;
use servd\AssetStorage\Volume;

class ImageTransforms
{
    const TRANSFORM_RESIZE_ATTRIBUTES_MAP = [
        'width' => 'width',
        'height' => 'height',
        'mode' => 'fit',
    ];
    const TRANSFORM_MODES = [
        'fit' => 'contain',
        'crop' => 'cover',
        'stretch' => 'fill',
    ];

    public function transformUrl(Asset $asset, TransformOptions $transform)
    {

        $settings = Plugin::$plugin->getSettings();
        $volume = $asset->getVolume();

        if (get_class($volume) !== Volume::class) {
            return null;
        }

        if (!$asset) {
            return null;
        }

        $params = $this->getParamsForTransform($transform);
        $params['dm'] = $asset->dateUpdated->getTimestamp();

        //Full path of asset on the CDN platform
        $fullPath = $this->getFullPathForAssetAndTransform($asset, $params);
        if (!$fullPath) {
            return;
        }
        $signingKey = $this->getKeyForPath($fullPath);
        $params['s'] = $signingKey;

        // Use a custom URL template if one has been provided
        $customPattern = Craft::parseEnv($volume->optimiseUrlPattern);
        if (!empty($customPattern)) {
            $variables = [
                "environment" => $settings->getAssetsEnvironment(),
                "projectSlug" => $settings->getProjectSlug(),
                "subfolder" => trim($volume->customSubfolder, "/"),
                "filePath" => $asset->getPath(),
                "params" => '?' . http_build_query($params),
            ];
            $finalUrl = $customPattern;
            foreach ($variables as $key => $value) {
                $finalUrl = str_replace('{{' . $key . '}}', $value, $finalUrl);
            }
            return $finalUrl;
        }

        //Otherwise
        return 'https://optimise2.assets-servd.host/' . $fullPath . '&s=' . $signingKey;
    }

    public function getKeyForPath($path)
    {
        //Not a secret, just a sanity check
        $signingKey = base64_decode('Nzh5NjQzb2h1aXF5cmEzdzdveTh1aWhhdzM0OW95ODg0dQ==');
        $hash = md5($signingKey . '/' . $path);
        return $hash;
    }

    public function getFullPathForAssetAndTransform(Asset $asset, $params)
    {
        if (!$asset) {
            return;
        }

        /** @var \servd\AssetStorage\Volume */
        $volume = $asset->getVolume();

        $filePath = $asset->getPath();
        $filePath = preg_replace_callback('/[\s]|[^\x20-\x7f]/', function ($match) {
            return rawurlencode($match[0]);
        }, $filePath);
        $base = rtrim($volume->_subfolder(), '/') . '/' . $filePath;
        $base = ltrim($base, '/');

        return $base . "?" . http_build_query($params);
    }

    public function getParamsForTransform(TransformOptions $transform)
    {
        $params = [];
        $autoParams = [];

        if (!empty($transform->width)) {
            $params['w'] = $transform->width;
        }

        if (!empty($transform->height)) {
            $params['h'] = $transform->height;
        }

        if (!empty($transform->quality)) {
            $params['q'] = $transform->quality;
        } else {
            $autoParams[] = 'compress';
        }

        if (!empty($transform->format)) {
            $params['fm'] = $transform->format;
        } else {
            $autoParams[] = 'format';
        }

        if (!empty($autoParams)) {
            $params['auto'] = implode(',', $autoParams);
        }

        if (!empty($transform->fit) && in_array($transform->fit, ['fill', 'scale', 'crop', 'clip', 'min', 'max'])) {
            $params['fit'] = $transform->fit;
        } else {
            $params['fit'] = 'clip';
        }

        if (!empty($transform->fpx) && is_numeric($transform->fpx)) {
            $params['fp-x'] = $transform->fpx;
        }

        if (!empty($transform->fpy) && is_numeric($transform->fpy)) {
            $params['fp-y'] = $transform->fpy;
        }

        if (!empty($transform->fillColor)) {
            $params['fill-color'] = $transform->fillColor;
        }

        if (!empty($transform->dpr) && is_numeric($transform->dpr) && $transform->dpr != 1) {
            $params['dpr'] = $transform->dpr;
        }

        return $params;
    }

    public function outputWillBeSVG($asset, $transform)
    {
        if (empty($transform->format)) {
            $assetTransforms = Craft::$app->getAssetTransforms();
            $autoFormat = $assetTransforms->detectAutoTransformFormat($asset);
            if ('svg' == $autoFormat) {
                return true;
            }

            return false;
        }

        return 'svg' == $transform->format;
    }

    public function inputIsGif($asset)
    {
        $assetTransforms = Craft::$app->getAssetTransforms();
        $autoFormat = $assetTransforms->detectAutoTransformFormat($asset);
        return 'gif' == $autoFormat;
    }
}
