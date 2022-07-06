<?php

namespace servd\AssetStorage\ImageOptimize;

use Craft;
use nystudio107\imageoptimize\imagetransforms\ImageTransform;
use craft\models\AssetTransform;
use craft\elements\Asset;
use Exception;
use servd\AssetStorage\AssetsPlatform\TransformOptions;
use servd\AssetStorage\Plugin;
use yii\base\InvalidConfigException;
use craft\helpers\Image as ImageHelper;
use servd\AssetStorage\AssetsPlatform\AssetsPlatform;

class ImageOptimizeTransformer extends ImageTransform
{

    public static function displayName(): string
    {
        return 'Servd';
    }

    /**
     * @param Asset               $asset
     * @param AssetTransform|null $transform
     *
     * @return string|null
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    public function getTransformUrl(Asset $asset, $transform)
    {
        $assetsPlatform = Plugin::$plugin->assetsPlatform;
        $transformOptions = new TransformOptions();

        if (!ImageHelper::canManipulateAsImage(pathinfo($asset->filename, PATHINFO_EXTENSION))) {
            return $assetsPlatform->getFileUrl($asset);
        }

        if (!is_null($transform)) {
            $transformOptions->fillFromCraftTransform($asset, $transform);
        }

        return $assetsPlatform->imageTransforms->transformUrl($asset, $transformOptions);
    }

    /**
     * @param string              $url
     * @param Asset               $asset
     * @param AssetTransform|null $transform
     *
     * @return string
     */
    public function getWebPUrl(string $url, Asset $asset, $transform): string
    {
        if ($transform) {
            $transform->format = 'webp';
        }
        try {
            $webPUrl = $this->getTransformUrl($asset, $transform);
        } catch (InvalidConfigException $e) {
            Craft::error($e->getMessage(), __METHOD__);
        } catch (Exception $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }

        return $webPUrl ?? '';
    }

    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('servd-asset-storage/imageOptimiseSettings', [
            'imageTransform' => $this,
        ]);
    }

    public function rules()
    {
        $rules = parent::rules();
        return $rules;
    }
}
