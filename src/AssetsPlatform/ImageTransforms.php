<?php

namespace servd\AssetStorage\AssetsPlatform;

use Craft;
use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\ImageTransforms as HelpersImageTransforms;
use servd\AssetStorage\models\Settings;
use servd\AssetStorage\Plugin;

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
        $fs = $asset->getVolume()->getFs();

        if (get_class($fs) !== Fs::class) {
            return null;
        }

        if (!$asset) {
            return null;
        }

        $params = $this->getParamsForTransform($transform, $asset);
        $params['dm'] = $asset->dateUpdated->getTimestamp();

        //Full path of asset on the CDN platform
        $fullPath = $this->getFullPathForAssetAndTransform($asset, $params);
        if (!$fullPath) {
            return;
        }

        $signingKey = $this->getKeyForPath($fullPath);

        $params['s'] = $signingKey;

        $normalizedCustomSubfolder = App::parseEnv($fs->customSubfolder);

        // Use a custom URL template if one has been provided
        $customPattern = App::parseEnv($fs->optimiseUrlPattern);
        if (!empty($customPattern)) {
            $variables = [
                "environment" => $settings->getAssetsEnvironment(),
                "projectSlug" => $settings->getProjectSlug(),
                "subfolder" => trim($normalizedCustomSubfolder, "/"),
                "filePath" => $this->encodeFilenameInFilePath($asset->getPath()),
                "params" => '?' . http_build_query($params),
            ];
            $finalUrl = $customPattern;
            foreach ($variables as $key => $value) {
                $finalUrl = str_replace('{{' . $key . '}}', $value, $finalUrl);
            }
            return $finalUrl;
        }

        //Otherwise
        if (Settings::$CURRENT_TYPE == 'wasabi') {
            $base = 'https://' . $settings->getProjectSlug() . '.transforms.svdcdn.com/';
        } else {
            $base = 'https://optimise2.assets-servd.host/';
        }

        return $base . $fullPath . '&s=' . $signingKey;
    }

    public function getKeyForPath($path)
    {
        //Not a secret, just a sanity check
        $signingKey = base64_decode('Nzh5NjQzb2h1aXF5cmEzdzdveTh1aWhhdzM0OW95ODg0dQ==');
        $hash = md5($signingKey . '/' . $path);
        return $hash;
    }

    private function encodeFilenameInFilePath($path)
    {
        $path = \Normalizer::normalize($path, \Normalizer::FORM_C); //Remove any accent combining characters which break Wasabi
        $path = preg_replace_callback('/[\s]|[^\x20-\x7f]/', function ($match) {
            return rawurlencode($match[0]);
        }, $path);

        return $path;
        // $parts = explode('/', $path);
        // //urlencode the final part
        // $parts[count($parts) - 1] = rawurlencode($parts[count($parts) - 1]);
        // return implode('/', $parts);
    }

    public function getFullPathForAssetAndTransform(Asset $asset, $params)
    {
        if (!$asset) {
            return;
        }

        /** @var \servd\AssetStorage\AssetsPlatform\Fs */
        $fs = $asset->getVolume()->getFs();

        $filePath = $this->encodeFilenameInFilePath($asset->getPath());

        $base = rtrim($fs->_subfolder(), '/') . '/';
        $base = ltrim($base, '/');

        // Fix ampersands
        $baseAndPath = $base . $filePath;
        $baseAndPath = str_replace('&', '%26', $baseAndPath);

        if (Settings::$CURRENT_TYPE == 'wasabi') {
            $baseAndPath = str_replace('(', '%28', $baseAndPath);
            $baseAndPath = str_replace(')', '%29', $baseAndPath);
        }

        return $baseAndPath . "?" . http_build_query($params);
    }

    public function getParamsForTransform(TransformOptions $transform, Asset $asset)
    {

        $settings = Plugin::$plugin->getSettings();

        $params = [];
        $autoParams = [];

        $targetWidth = $transform->width;
        $targetHeight = $transform->height;

        if (!$transform->upscale) {
            if ($transform->fit === 'crop') {
                $transform->fit = 'min';
            }
            if ($transform->fit === 'clip') {
                $transform->fit = 'max';
            }
            if ($transform->fit === 'fill') {
                $transform->fit = 'fillmax';
            }
        }

        if (!empty($targetWidth)) {
            $params['w'] = $targetWidth;
        }
        if (!empty($targetHeight)) {
            $params['h'] = $targetHeight;
        }

        if (!empty($transform->quality)) {
            $params['q'] = $transform->quality;
        } else {
            $default = Craft::$app->getConfig()->getGeneral()->defaultImageQuality;
            if (empty($default) || $default == 82) { // 82 is the Craft default
                $autoParams[] = 'compress';
            } else {
                $params['q'] = $default;
            }
        }

        if (!empty($transform->format)) {
            $params['fm'] = $transform->format;
        } elseif ($settings->imageAutoConversion == 'webp') {
            $autoParams[] = 'format';
        } elseif ($settings->imageAutoConversion == 'avif') {
            $autoParams[] = 'format';
            $autoParams[] = 'avif';
        }

        if (!empty($autoParams)) {
            $params['auto'] = implode(',', $autoParams);
        }

        if (!empty($transform->fit) && in_array($transform->fit, ['fillmax', 'fill', 'scale', 'crop', 'clip', 'min', 'max'])) {
            $params['fit'] = $transform->fit;
        } else {
            $params['fit'] = 'clip';
        }

        $params['crop'] = $transform->crop;
        if ($transform->crop == 'focalpoint') {
            if (!empty($transform->fpx) && is_numeric($transform->fpx)) {
                $params['fp-x'] = $transform->fpx;
            }

            if (!empty($transform->fpy) && is_numeric($transform->fpy)) {
                $params['fp-y'] = $transform->fpy;
            }
        }

        if (!empty($transform->fill)) {
            $params['fill'] = $transform->fill;
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
            $autoFormat = HelpersImageTransforms::detectTransformFormat($asset);
            if ('svg' == $autoFormat) {
                return true;
            }

            return false;
        }

        return 'svg' == $transform->format;
    }

    public function inputIsGif($asset)
    {
        $autoFormat = HelpersImageTransforms::detectTransformFormat($asset);
        return 'gif' == $autoFormat;
        return false;
    }
}
