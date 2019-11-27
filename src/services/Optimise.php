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
        $bucket = 'cdn.assets-servd.host';
        $volume = $asset->getVolume();
        $key = rtrim($volume->_subfolder(), '/').'/'.$asset->getPath();
        $edits = $this->transformToSharpEdits($asset, $transform);
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

        return 'https://optimise.assets-servd.host/'.base64_encode($strConfig);
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
    private function transformToSharpEdits($asset, $transform)
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
                    $position = $yPos.'-'.$xPos;
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
