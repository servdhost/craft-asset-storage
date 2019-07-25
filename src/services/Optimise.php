<?php

namespace servd\AssetStorage\services;

use Craft;
use craft\base\Component;
use craft\elements\Asset;
use craft\errors\AssetLogicException;
use craft\helpers\Json;

/** @noinspection MissingPropertyAnnotationsInspection */

/**
 * @author    nystudio107
 *
 * @since     1.0.0
 */
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
            if (!empty($position)) {
                if (preg_match('/(top|center|bottom)-(left|center|right)/', $position)) {
                    $positions = explode('-', $position);
                    $positions = array_diff($positions, ['center']);
                    if (!empty($positions) && 'center-center' !== $transform->position) {
                        $edits['resize']['position'] = implode(',', $positions);
                    }
                }
            }
            $mode = $edits['resize']['fit'];
            $edits['resize']['fit'] = self::TRANSFORM_MODES[$mode] ?? $mode ?? 'cover';
        }

        return $edits;
    }
}
