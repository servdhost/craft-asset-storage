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
        $useLegacy = getenv('USE_LEGACY_ASSETS');
        if (!empty($useLegacy) && $useLegacy == 'true') {
            return $this->legacyTransformUrl($asset, $transform);
        } else {
            return $this->newTransformUrl($asset, $transform);
        }
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
        $path .= '&s='.$hash;

        return 'https://optimise2.assets-servd.host/' . $path;

    }

    public function getUrlForAssetsAndTranform(Asset $asset, $transform)
    {
        if (!$asset) {
            return;
        }

        $params = [];
        $volume = $asset->getVolume();
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

    public function legacyTransformUrl(Asset $asset, $transform)
    {
        $bucket = 'cdn.assets-servd.host';
        $volume = $asset->getVolume();
        $key = rtrim($volume->_subfolder(), '/') . '/' . $asset->getPath();
        $edits = $this->legacyTransformToSharpEdits($asset, $transform);
        $sharpConfig = [
            'bucket' => $bucket,
            'key' => $key,
        ];
        if (!empty($edits)) {
            $sharpConfig['edits'] = $edits;
        }

        $strConfig = Json::encode(
            $sharpConfig,
            JSON_FORCE_OBJECT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_NUMERIC_CHECK
        );

        return 'https://optimise.assets-servd.host/' . base64_encode($strConfig);
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

    /**
     * Thanks to NYStudio107 for the Craft Transform -> SharpJS edits array transformation logic
     * from Image Optimize (https://github.com/nystudio107/craft-imageoptimize).
     *
     * @param mixed $asset
     * @param mixed $transform
     */
    private function legacyTransformToSharpEdits($asset, $transform)
    {
        $assetTransforms = Craft::$app->getAssetTransforms();
        $edits = [];
        if (null !== $transform) {
            if (empty($transform->format)) {
                try {
                    $transform->format = $assetTransforms->detectAutoTransformFormat($asset);
                } catch (AssetLogicException $e) {
                    $transform->format = 'jpeg';
                }
            }
            $format = $transform->format;
            if ('jpg' == $format) {
                $format = 'jpeg';
            }

            $edits[$format]['quality'] = (int) ($transform->quality ?? 90);

            switch ($format) {
                case 'jpeg':
                    if (!empty($transform->interlace)) {
                        $edits[$format]['progressive'] = 'none' !== $transform->interlace;
                    }
                    $edits[$format]['trellisQuantisation'] = true;
                    $edits[$format]['overshootDeringing'] = true;
                    $edits[$format]['optimizeScans'] = true;

                    break;
                case 'png':
                    if (!empty($transform->interlace)) {
                        $edits[$format]['progressive'] = 'none' !== $transform->interlace;
                    }

                    break;
                case 'webp':
                    break;
            }

            foreach (self::TRANSFORM_RESIZE_ATTRIBUTES_MAP as $key => $value) {
                if (!empty($transform[$key])) {
                    $edits['resize'][$value] = $transform[$key];
                }
            }

            $position = $transform->position;
            if ($asset->getHasFocalPoint()) {
                $focalPoint = $asset->getFocalPoint();
                if (!empty($focalPoint)) {
                    if ($focalPoint['x'] < 0.33) {
                        $xPos = 'left';
                    } elseif ($focalPoint['x'] < 0.66) {
                        $xPos = 'center';
                    } else {
                        $xPos = 'right';
                    }
                    if ($focalPoint['y'] < 0.33) {
                        $yPos = 'top';
                    } elseif ($focalPoint['y'] < 0.66) {
                        $yPos = 'center';
                    } else {
                        $yPos = 'bottom';
                    }
                    $position = $yPos . '-' . $xPos;
                }
            }
            if (!empty($position)) {
                if (preg_match('/(top|center|bottom)-(left|center|right)/', $position)) {
                    $positions = explode('-', $position);
                    $positions = array_diff($positions, ['center']);
                    if (!empty($positions) && 'center-center' !== $transform->position) {
                        //Reverse them for sharp
                        $edits['resize']['position'] = implode(' ', array_reverse($positions));
                    }
                }
            }
            $mode = $edits['resize']['fit'];
            if ('fit' == $mode) {
                unset($edits['resize']['fit']);
                $edits['max'] = null;
            } else {
                $edits['resize']['fit'] = self::TRANSFORM_MODES[$mode] ?? $mode ?? 'cover';
            }
        }

        return $edits;
    }
}
