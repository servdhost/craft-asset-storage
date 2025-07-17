<?php

namespace servd\AssetStorage\Imager;

use craft\elements\Asset;
use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\helpers\ImgixHelpers;
use spacecatninja\imagerx\models\BaseTransformedImageModel;
use spacecatninja\imagerx\models\TransformedImageInterface;

class ImagerTransformedImageModel extends BaseTransformedImageModel implements TransformedImageInterface
{

    /**
     * @var array|null
     */
    private $params;

    /**
     * @var string
     */
    private $imgixPath;

    /**
     * ImgixTransformedImageModel constructor.
     *
     * @param string|null        $imageUrl
     * @param Asset|string|null  $source
     * @param array|null         $params
     *
     * @throws ImagerException
     */
    public function __construct($imageUrl = null, $source = null, $params = null)
    {
        $this->source = $source;
        $this->params = $params;
        $this->path = '';
        $this->extension = '';
        $this->mimeType = '';
        $this->size = 0;

        if ($imageUrl !== null) {
            $this->url = $imageUrl;
        }

        $this->width = 0;
        $this->height = 0;

        if (isset($params['w'], $params['h'])) {
            $this->width = (int)$params['w'];
            $this->height = (int)$params['h'];

            if (($source !== null) && ($params['fit'] === 'min' || $params['fit'] === 'max')) {
                list($sourceWidth, $sourceHeight) = $this->getSourceImageDimensions($source);

                $paramsW = (int)$params['w'];
                $paramsH = (int)$params['h'];

                if ($sourceWidth !== 0 && $sourceHeight !== 0) {
                    if ($sourceWidth / $sourceHeight < $paramsW / $paramsH) {
                        $useW = min($paramsW, $sourceWidth);
                        $this->width = $useW;
                        $this->height = round($useW * ($paramsH / $paramsW));
                    } else {
                        $useH = min($paramsH, $sourceHeight);
                        $this->width = round($useH * ($paramsW / $paramsH));
                        $this->height = $useH;
                    }
                }
            } elseif ($source !== null && $params['fit'] === 'clip') {
                list($sourceWidth, $sourceHeight) = $this->getSourceImageDimensions($source);

                $paramsW = (int)$params['w'];
                $paramsH = (int)$params['h'];

                if ($sourceWidth !== 0 && $sourceHeight !== 0) {
                    if ($sourceWidth / $sourceHeight > $paramsW / $paramsH) {
                        $useW = min($paramsW, $sourceWidth);
                        $this->width = $useW;
                        $this->height = round($useW * ($sourceHeight / $sourceWidth));
                    } else {
                        $useH = min($paramsH, $sourceHeight);
                        $this->width = round($useH * ($sourceWidth / $sourceHeight));
                        $this->height = $useH;
                    }
                }
            }
        } else {
            if (isset($params['w']) || isset($params['h'])) {
                if ($source !== null && $params !== null) {
                    list($sourceWidth, $sourceHeight) = $this->getSourceImageDimensions($source);

                    if ((int)$sourceWidth === 0 || (int)$sourceHeight === 0) {
                        if (isset($params['w'])) {
                            $this->width = (int)$params['w'];
                        }
                        if (isset($params['h'])) {
                            $this->height = (int)$params['h'];
                        }
                    } else {
                        list($w, $h) = $this->calculateTargetSize($params, $sourceWidth, $sourceHeight);

                        $this->width = $w;
                        $this->height = $h;
                    }
                }
            } else {
                // Neither is set, image is not resized. Just get dimensions and return.
                list($sourceWidth, $sourceHeight) = $this->getSourceImageDimensions($source);

                $this->width = $sourceWidth;
                $this->height = $sourceHeight;
            }
        }
    }

    /**
     * @param $source
     *
     * @return array
     * @throws ImagerException
     */
    protected function getSourceImageDimensions($source): array
    {
        if ($source instanceof Asset) {
            return [$source->getWidth(), $source->getHeight()];
        }

        return [0, 0];
    }

    /**
     * @param $params
     * @param $sourceWidth
     * @param $sourceHeight
     *
     * @return array
     */
    protected function calculateTargetSize($params, $sourceWidth, $sourceHeight): array
    {
        $fit = $params['fit']; // clamp, clip, crop, facearea, fill, fillmax, max, min, and scale. 
        $ratio = $sourceWidth / $sourceHeight;

        $w = $params['w'] ?? null;
        $h = $params['h'] ?? null;

        switch ($fit) {
            case 'clip':
            case 'fill':
            case 'crop':
            case 'clamp':
            case 'scale':
                if ($w) {
                    return [$w, round($w / $ratio)];
                }
                if ($h) {
                    return [round($h * $ratio), $h];
                }
                break;
            case 'min':
            case 'max':
                if ($w) {
                    $useWidth = min($w, $sourceWidth);

                    return [$useWidth, round($useWidth / $ratio)];
                }
                if ($h) {
                    $useHeigth = min($h, $sourceHeight);

                    return [round($useHeigth * $ratio), $useHeigth];
                }
                break;
        }

        return [$w ?: 0, $h ?: 0];
    }

    /**
     * @param string $unit
     * @param int    $precision
     *
     * @return float|int
     */
    public function getSize($unit = 'b', $precision = 2)
    {
        return $this->size;
    }

    /**
     * @return string
     */
    public function getDataUri(): string
    {
        return '';
    }

    /**
     * @return string
     */
    public function getBase64Encoded(): string
    {
        return '';
    }

    /**
     * @return bool
     */
    public function getIsNew(): bool
    {
        return false;
    }
}
