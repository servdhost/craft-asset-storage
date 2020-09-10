<?php

namespace servd\AssetStorage\services;

use Craft;
use craft\base\Component;
use craft\elements\Asset;
use craft\errors\AssetLogicException;
use craft\helpers\Json;

class Optimise extends Component
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

    public function transformUrl(Asset $asset, $transform)
    {
        return $this->newTransformUrl($asset, $transform);
    }

    public function newTransformUrl(Asset $asset, $transform)
    {
        $bucket = 'cdn-assets-servd-host';
        //Not a secret, just a sanity check
        $signingKey = base64_decode('Nzh5NjQzb2h1aXF5cmEzdzdveTh1aWhhdzM0OW95ODg0dQ==');

        $path = $this->getUrlForAssetsAndTranform($asset, $transform);
        if (!$path) {
            return;
        }
        $hash = md5($signingKey . '/' . $path);
        $path .= '&s=' . $hash;

        $optimisePrefix = Craft::parseEnv($asset->volume->optimisePrefix);
        if (empty($optimisePrefix)) {
            $optimisePrefix = 'https://optimise2.assets-servd.host/';
        }

        $optimisePrefix = trim($optimisePrefix, '/');
        $optimisePrefix .= '/';

        return $optimisePrefix . $path;
    }

    public function getUrlForAssetsAndTranform(Asset $asset, $transform)
    {
        if (!$asset) {
            return;
        }

        $params = [];
        $volume = $asset->getVolume();

        $filePath = $asset->getPath();
        $filePath = preg_replace_callback('/[\s]|[^\x20-\x7f]/', function ($match) {
            return rawurlencode($match[0]);
        }, $filePath);
        $base = rtrim($volume->_subfolder(), '/') . '/' . $filePath;


        $base = rtrim($volume->_subfolder(), '/') . '/' . $asset->getPath();

        if ($transform) {
            //Get params
            $attr_map = [
                'width'   => 'w',
                'height'  => 'h',
                'quality' => 'q',
                'format'  => 'fm',
            ];

            foreach ($attr_map as $key => $value) {
                if (!empty($transform[$key])) {
                    $params[$value] = $transform[$key];
                }
            }

            $autoParams = [];
            if (empty($params['q'])) {
                $autoParams[] = 'compress';
            }
            if (empty($params['fm'])) {
                $autoParams[] = 'format';
            }
            if (!empty($autoParams)) {
                $params['auto'] = implode(',', $autoParams);
            }

            // Handle interlaced images
            //TODO: Consider adding to transform function
            // if (property_exists($transform, 'interlace')) {
            //     if (($transform->interlace != 'none')
            //         && (!empty($params['fm']))
            //         && ($params['fm'] == 'jpg')
            //     ) {
            //         $params['fm'] = 'pjpg';
            //     }
            // }

            switch ($transform->mode) {
                case 'fit':
                    $params['fit'] = 'clip';
                    break;

                case 'stretch':
                    $params['fit'] = 'scale';
                    break;

                default:
                    // Set a sane default
                    if (empty($transform->position)) {
                        $transform->position = 'center-center';
                    }
                    // Fit mode
                    $params['fit'] = 'crop';
                    $cropParams = [];
                    // Handle the focal point
                    $focalPoint = $asset->getFocalPoint();
                    if (!empty($focalPoint)) {
                        $params['fp-x'] = $focalPoint['x'];
                        $params['fp-y'] = $focalPoint['y'];
                        $cropParams[] = 'focalpoint';
                        $params['crop'] = implode(',', $cropParams);
                    } elseif (preg_match('/(top|center|bottom)-(left|center|right)/', $transform->position)) {
                        // Imgix defaults to 'center' if no param is present
                        $filteredCropParams = explode('-', $transform->position);
                        $filteredCropParams = array_diff($filteredCropParams, ['center']);
                        $cropParams[] = $filteredCropParams;
                        // Imgix
                        if (!empty($cropParams) && $transform->position !== 'center-center') {
                            $params['crop'] = implode(',', $cropParams);
                        }
                    }
                    break;
            }
        } else {
            $params['auto'] = 'format,compress';
        }

        return $base . "?" . http_build_query($params);
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
