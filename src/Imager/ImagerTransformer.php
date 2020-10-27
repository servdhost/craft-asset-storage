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
use servd\AssetStorage\Plugin;
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

        foreach ($transforms as $transform) {
            $realTransformProps = [
                'height' => $transform['height'],
                'width' => $transform['width'],
                'interlace' => 'line',
            ];
            if (isset($transform['mode']) && in_array($transform['mode'], ['crop', 'fit', 'stretch'])) {
                $realTransformProps['mode'] = $transform['mode'];
            }
            $realTransform = new AssetTransform($realTransformProps);
            $transformedImages[] = $this->getTransformedImage($image, $realTransform);
        }

        return $transformedImages;
    }

    private function getTransformedImage($image, $transform): ImagerTransformedImageModel
    {

        $params = Plugin::$plugin->assetsPlatform->imageTransforms->getParamsForTransform($image, $transform);
        $url = Plugin::$plugin->assetsPlatform->imageTransforms->transformUrl($image, $transform);

        return new ImagerTransformedImageModel($url, $image, $params);
    }
}
