<?php

namespace servd\AssetStorage\ImageOptimize;

use Craft;
use nystudio107\imageoptimize\imagetransforms\ImageTransform;
use craft\models\ImageTransform as CraftImageTransformModel;
use craft\elements\Asset;
use Exception;
use servd\AssetStorage\AssetsPlatform\TransformOptions;
use servd\AssetStorage\Plugin;
use yii\base\InvalidConfigException;

class ImageOptimizeTransformer extends ImageTransform
{

    public static function displayName(): string
    {
        return 'Servd';
    }

    public function getTransformUrl(Asset $asset, CraftImageTransformModel|string|array|null $transform): ?string
    {
        $transformOptions = new TransformOptions();
        if (!is_null($transform)) {
            $transformOptions->fillFromCraftTransform($asset, $transform);
        }

        return Plugin::$plugin->assetsPlatform->imageTransforms->transformUrl($asset, $transformOptions);
    }

    public function getWebPUrl(string $url, Asset $asset, CraftImageTransformModel|string|array|null $transform): ?string
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

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('servd-asset-storage/imageOptimiseSettings', [
            'imageTransform' => $this,
        ]);
    }

    public function rules(): array
    {
        $rules = parent::rules();
        return $rules;
    }
}
