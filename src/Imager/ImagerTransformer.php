<?php

namespace servd\AssetStorage\Imager;

use Craft;

use craft\base\Component;
use craft\elements\Asset;
use craft\models\AssetTransform;
use spacecatninja\imagerx\models\ConfigModel;
use spacecatninja\imagerx\models\ImgixSettings;
use spacecatninja\imagerx\models\ImgixTransformedImageModel;
use spacecatninja\imagerx\services\ImagerService;
use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\helpers\ImgixHelpers;

use Imgix\UrlBuilder;
use servd\AssetStorage\AssetsPlatform\TransformOptions;
use servd\AssetStorage\Plugin;
use servd\AssetStorage\Volume;
use spacecatninja\imagerx\transformers\TransformerInterface;

class ImagerTransformer extends Component implements TransformerInterface
{

    public function __construct($config = [])
    {
        parent::__construct($config);
    }

    /**
     * Main transform method
     *
     * @param Asset|string $image
     * @param array        $transforms
     *
     * @return array|null
     * @throws ImagerException
     */
    public function transform($image, $transforms)
    {
        $transformedImages = [];

        if (is_string($image)) {
            return null;
        }

        $volume = $image->getVolume();

        if (get_class($volume) !== Volume::class) {
            return null;
        }

        foreach ($transforms as $transform) {
            $transformedImages[] = $this->getTransformedImage($image, $transform);
        }

        return $transformedImages;
    }

    private function getTransformedImage($image, $transform): ImagerTransformedImageModel
    {
        $transformOptions = $this->imagerTransformToTransformOptions($image, $transform);
        $params = Plugin::$plugin->assetsPlatform->imageTransforms->getParamsForTransform($transformOptions);
        $url = Plugin::$plugin->assetsPlatform->imageTransforms->transformUrl($image, $transformOptions);
        return new ImagerTransformedImageModel($url, $image, $params);
    }

    private function imagerTransformToTransformOptions($asset, $transform)
    {
        $config = ImagerService::getConfig();

        $transformOptions = new TransformOptions();

        $auto = [];

        $transformOptions->width = $transform['width'] ?? null;
        $transformOptions->height = $transform['height'] ?? null;
        $transformOptions->format = $transform['format'] ?? null;

        if (!empty($transformOptions->format)) {
            $transformOptions->quality = $this->getQualityFromExtension($transformOptions->format, $transform);
        } else {
            $transformOptions->quality = $this->getQualityFromExtension($asset->getExtension(), $transform);
        }

        if (empty($transformOptions->format)) {
            $auto[] = 'format';
        }
        if (empty($transformOptions->quality)) {
            $auto[] = 'quality';
        }
        if (!empty($auto)) {
            $transformOptions->auto = implode(',', $auto);
        }

        //Sort out fit mode
        if (!isset($transform['mode'])) {
            $transform['mode'] = 'crop';
        }
        if ($transform['mode'] == 'crop') {
            $transformOptions->fit = 'crop';
        }
        if ($transform['mode'] == 'fit') {
            $transformOptions->fit = 'clip';
        }
        if ($transform['mode'] == 'stretch') {
            $transformOptions->fit = 'scale';
        }
        if ($transform['mode'] == 'croponly') {
            $transformOptions->fit = 'crop';
        }
        if ($transform['mode'] == 'letterbox') {
            $transformOptions->fit = 'fill';
            //These functions are stolen directly from Imager codebase
            $letterboxDef = $config->getSetting('letterbox', $transform);
            $transformOptions->fillColor = $this->getLetterboxColor($letterboxDef);
        }

        // If fit is crop, and crop isn't specified, use position as focal point.
        if ($transformOptions->fit === 'crop') {
            $position = $config->getSetting('position', $transform);
            list($left, $top) = explode(' ', $position);
            $transformOptions->crop = 'focalpoint';
            $transformOptions->fpx = ((float)$left) / 100;
            $transformOptions->fpy = ((float)$top) / 100;
        }

        //Don't allow upscaling based on imager settings
        if (!empty($transformOptions->fit) && !$config->getSetting('allowUpscale', $transform)) {
            if ($transformOptions->fit === 'crop') {
                $transformOptions->fit = 'min';
            }
            if ($transformOptions->fit === 'clip') {
                $transformOptions->fit = 'max';
            }
        }

        return $transformOptions;
    }

    private function getLetterboxColor($letterboxDef): string
    {
        $color = $letterboxDef['color'];
        $opacity = $letterboxDef['opacity'];
        $color = str_replace('#', '', $color);

        if (\strlen($color) === 3) {
            $opacity = dechex($opacity * 15);
            return $opacity . $color;
        }
        if (\strlen($color) === 6) {
            $opacity = dechex($opacity * 255);
            $val = $opacity . $color;
            if (\strlen($val) === 7) {
                $val = '0' . $val;
            }
            return $val;
        }
        if (\strlen($color) === 4 || \strlen($color) === 8) { // assume color already is 4 or 8 digit rgba. 
            return $color;
        }
        return '0fff';
    }

    private function getQualityFromExtension($ext, $transform = null): string
    {
        /** @var ConfigModel $settings */
        $config = ImagerService::getConfig();
        switch ($ext) {
            case 'png':
                $pngCompression = $config->getSetting('pngCompressionLevel', $transform);
                return max(100 - ($pngCompression * 10), 1);
            case 'webp':
                return $config->getSetting('webpQuality', $transform);
        }
        return $config->getSetting('jpegQuality', $transform);
    }
}
