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
    public $fill = null;
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
            case 'letterbox':
                $this->fit = 'fill';
                $this->fill = 'solid';
                $this->fillColor = str_replace('#', '', ($transform->fill ?? '000000'));
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
                if (!empty($focalPoint) && ($focalPoint['x'] != 0.5 || $focalPoint['y'] != 0.5)) {
                    $this->fpx = $focalPoint['x'];
                    $this->fpy = $focalPoint['y'];
                    $this->crop = 'focalpoint';
                } elseif (preg_match('/(top|center|bottom)-(left|center|right)/', $transform->position)) {
                    $filteredCropParams = explode('-', $transform->position);
                    $filteredCropParams = array_diff($filteredCropParams, ['center']);
                    $cropParams = implode(',', $filteredCropParams);
                    if (!empty($cropParams) && $transform->position !== 'center-center') {
                        $this->crop = $cropParams;
                    }
                }
                break;
        }
    }

    public function sanitizeProperties() {

        //Max width 4000, scaling height if needed to match
        if (!empty($this->width) && $this->width > 4000) {
            $orgWidth = $this->width;
            $this->width = 4000;
            if (!empty($this->height)) {
                $this->height = round($this->height * (4000 / $orgWidth));
            }
        }
        // Same for height
        if (!empty($this->height) && $this->height > 4000) {
            $orgHeight = $this->height;
            $this->height = 4000;
            if (!empty($this->width)) {
                $this->width = round($this->width * (4000 / $orgHeight));
            }
        }

    }
}
