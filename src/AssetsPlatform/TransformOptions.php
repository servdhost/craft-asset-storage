<?php

namespace servd\AssetStorage\AssetsPlatform;

use craft\elements\Asset;
use craft\models\AssetTransform;

class TransformOptions
{

    public $fit = 'crop';
    public $width = null;
    public $height = null;
    public $format = 'jpeg';
    public $quality = null;
    public $aspectRatio = null;
    public $dpr = null;
    public $fillColor = null;
    public $crop = null;
    public $fpx = null;
    public $fpy = null;
    public $auto = null;

    public function fillFromCraftTransform(Asset $asset, AssetTransform $transform)
    {
        $auto = [];

        $this->width = $transform->width;
        $this->height = $transform->height;
        $this->quality = $transform->quality;
        $this->format = $transform->format;

        if (empty($this->format)) {
            $auto[] = 'format';
        }
        if (empty($this->quality)) {
            $auto[] = 'quality';
        }

        if (!empty($auto)) {
            $this->auto = implode(',', $auto);
        }

        switch ($transform->mode) {
            case 'fit':
                $this->fit = 'clip';
                break;
            case 'stretch':
                $this->fit = 'scale';
                break;
            default:
                // Set a sane default
                if (empty($transform->position)) {
                    $transform->position = 'center-center';
                }
                // Fit mode
                $this->fit = 'crop';
                $cropParams = [];
                // Handle the focal point
                $focalPoint = $asset->getFocalPoint();
                if (!empty($focalPoint)) {
                    $this->fpx = $focalPoint['x'];
                    $this->fpy = $focalPoint['y'];
                    $cropParams[] = 'focalpoint';
                    $this->crop = implode(',', $cropParams);
                } elseif (preg_match('/(top|center|bottom)-(left|center|right)/', $transform->position)) {
                    // Imgix defaults to 'center' if no param is present
                    $filteredCropParams = explode('-', $transform->position);
                    $filteredCropParams = array_diff($filteredCropParams, ['center']);
                    $cropParams[] = $filteredCropParams;
                    // Imgix
                    if (!empty($cropParams) && $transform->position !== 'center-center') {
                        $this->crop = implode(',', $cropParams);
                    }
                }
                break;
        }
    }
}
