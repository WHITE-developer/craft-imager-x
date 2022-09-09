<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\helpers;

use craft\helpers\ArrayHelper;
use spacecatninja\imagerx\models\LocalSourceImageModel;
use spacecatninja\imagerx\models\LocalTargetImageModel;
use Craft;
use craft\base\Volume;
use craft\db\Query;
use craft\elements\Asset;
use craft\helpers\FileHelper;
use craft\models\AssetTransform;

use Imagine\Exception\InvalidArgumentException;
use Imagine\Image\Box;
use Imagine\Image\BoxInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;
use Imagine\Imagick\Image as ImagickImage;

use spacecatninja\imagerx\models\ConfigModel;
use spacecatninja\imagerx\services\ImagerService;
use spacecatninja\imagerx\exceptions\ImagerException;

use yii\base\InvalidConfigException;

class ImagerHelpers
{
    /**
     * Creates the Imagine instance depending on the chosen image driver.
     *
     * @return \Imagine\Gd\Imagine|\Imagine\Imagick\Imagine|null
     */
    public static function createImagineInstance()
    {
        try {
            if (ImagerService::$imageDriver === 'gd') {
                return new \Imagine\Gd\Imagine();
            }

            if (ImagerService::$imageDriver === 'imagick') {
                return new \Imagine\Imagick\Imagine();
            }
        } catch (\Throwable $e) {
            // just ignore for now
        }

        return null;
    }

    /**
     * @param LocalTargetImageModel $targetModel
     * @param array                 $transform
     *
     * @return bool
     */
    public static function shouldCreateTransform($targetModel, $transform): bool
    {
        /** @var ConfigModel $settings */
        $config = ImagerService::getConfig();

        return !$config->getSetting('cacheEnabled', $transform) ||
            !file_exists($targetModel->getFilePath()) ||
            (($config->getSetting('cacheDuration', $transform) !== false) && (FileHelper::lastModifiedTime($targetModel->getFilePath()) + $config->getSetting('cacheDuration', $transform) < time()));
    }

    /**
     * Creates the destination crop size box
     *
     * @param \Imagine\Image\Box|\Imagine\Image\BoxInterface|object $originalSize
     * @param array                                                 $transform
     * @param bool                                                  $allowUpscale
     * @param bool                                                  $usePadding
     *
     * @return Box
     * @throws \Imagine\Exception\InvalidArgumentException
     */
    public static function getCropSize($originalSize, $transform, $allowUpscale, $usePadding = true): Box
    {
        $width = $originalSize->getWidth();
        $height = $originalSize->getHeight();
        $padding = $transform['pad'] ?? [0, 0, 0, 0];

        if ($usePadding) {
            $padWidth = $padding[1] + $padding[3];
            $padHeight = $padding[0] + $padding[2];
        } else {
            $padWidth = 0;
            $padHeight = 0;
        }

        $aspect = $width / $height;

        if (isset($transform['width'], $transform['height'])) {
            $width = (int)$transform['width'];
            $height = (int)$transform['height'];
        } else {
            if (isset($transform['width'])) {
                $width = (int)$transform['width'];
                $height = (int)floor((int)$transform['width'] / $aspect);
            } else {
                if (isset($transform['height'])) {
                    $width = (int)floor((int)$transform['height'] * $aspect);
                    $height = (int)$transform['height'];
                }
            }
        }

        // check if we want to upscale. If not, adjust the transform here 
        if (!$allowUpscale) {
            [$width, $height] = self::enforceMaxSize($width, $height, $originalSize, true);
        }

        $width -= $padWidth;
        $height -= $padHeight;

        // ensure that size is larger than 0
        if ($width <= 0) {
            $width = 1;
        }
        if ($height <= 0) {
            $height = 1;
        }

        return new Box((int)$width, (int)$height);
    }

    /**
     * Creates the resize size box
     *
     * @param \Imagine\Image\Box|\Imagine\Image\BoxInterface|object $originalSize
     * @param array                                                 $transform
     * @param bool                                                  $allowUpscale
     * @param bool                                                  $usePadding
     *
     * @return Box
     * @throws ImagerException
     */
    public static function getResizeSize($originalSize, $transform, $allowUpscale, $usePadding = true): Box
    {
        $width = $originalSize->getWidth();
        $height = $originalSize->getHeight();
        $padding = $transform['pad'] ?? [0, 0, 0, 0];
        $aspect = $width / $height;

        if ($usePadding) {
            $padWidth = $padding[1] + $padding[3];
            $padHeight = $padding[0] + $padding[2];
        } else {
            $padWidth = 0;
            $padHeight = 0;
        }

        $mode = $transform['mode'] ?? 'crop';

        if ($mode === 'crop' || $mode === 'fit' || $mode === 'letterbox') {

            if (isset($transform['width'], $transform['height'])) {
                $transformAspect = (int)$transform['width'] / (int)$transform['height'];

                if ($mode === 'crop') {
                    $cropZoomFactor = self::getCropZoomFactor($transform);

                    if ($transformAspect < $aspect) { // use height as guide
                        $height = (int)$transform['height'] * $cropZoomFactor;
                        $width = ceil($originalSize->getWidth() * ($height / $originalSize->getHeight()));
                    } else { // use width
                        $width = (int)$transform['width'] * $cropZoomFactor;
                        $height = ceil($originalSize->getHeight() * ($width / $originalSize->getWidth()));
                    }
                } else {
                    if ($transformAspect === $aspect) { // exactly the same, use original just to make sure no rounding errors happen
                        $height = (int)$transform['height'];
                        $width = (int)$transform['width'];
                    } else if ($transformAspect > $aspect) { // use height as guide
                        $height = (int)$transform['height'];
                        $width = ceil($originalSize->getWidth() * ($height / $originalSize->getHeight()));
                    } else { // use width
                        $width = (int)$transform['width'];
                        $height = ceil($originalSize->getHeight() * ($width / $originalSize->getWidth()));
                    }
                }
            } else {
                if (isset($transform['width'])) {
                    $width = (int)$transform['width'];
                    $height = ceil($width / $aspect);
                } else if (isset($transform['height'])) {
                    $height = (int)$transform['height'];
                    $width = ceil($height * $aspect);
                }
            }
        } else {
            if ($mode === 'croponly') {
                $width = $originalSize->getWidth();
                $height = $originalSize->getHeight();
            } else if ($mode === 'stretch') {
                $width = (int)$transform['width'];
                $height = (int)$transform['height'];
            }
        }

        // check if we want to upscale. If not, adjust the transform here 
        if (!$allowUpscale) {
            [$width, $height] = self::enforceMaxSize((int)$width, (int)$height, $originalSize, false, self::getCropZoomFactor($transform));
        }

        $width -= $padWidth;
        $height -= $padHeight;

        try {
            $box = new Box((int)$width, (int)$height);
        } catch (InvalidArgumentException $e) {
            Craft::error($e->getMessage(), __METHOD__);
            throw new ImagerException($e->getMessage(), $e->getCode(), $e);
        }

        return $box;
    }

    /**
     * @param LocalSourceImageModel $source
     *
     * @return array
     */
    public static function getSourceImageSize($source): array
    {
        // Try getimagesize first
        $sourceImageInfo = @getimagesize($source->getFilePath());

        if ($sourceImageInfo[0] !== 0 && $sourceImageInfo[1] !== 0) {
            return array_slice($sourceImageInfo, 0, 2);
        }

        // Check if we can get it from the source asset, if it is one
        if ($source->asset !== null) {
            return [$source->asset->width, $source->asset->height];
        }

        // We need to open it (oh no). Let's cache the result.
        $cache = Craft::$app->getCache();
        $key = 'imagerx-sourceimage-size-' . md5($source->getFilePath());
        
        $cachedSizeData = $cache->getOrSet($key, static function() use ($source) {
            $imagineInstance = self::createImagineInstance();

            if ($imagineInstance) {
                $imageInstance = $imagineInstance->open($source->getFilePath());
                $size = $imageInstance->getSize();

                return [$size->getWidth(), $size->getHeight()];
            }
            
            return null;
        });
        
        return $cachedSizeData ?? [0, 0];
    }

    /**
     * Enforces a max size if allowUpscale is false
     *
     * @param int          $width
     * @param int          $height
     * @param BoxInterface $originalSize
     * @param bool         $maintainAspect
     * @param float        $zoomFactor
     *
     * @return array
     */
    public static function enforceMaxSize($width, $height, $originalSize, $maintainAspect, $zoomFactor = 1.0): array
    {
        $adjustedWidth = $width;
        $adjustedHeight = $height;

        if ($adjustedWidth > $originalSize->getWidth() * $zoomFactor) {
            $adjustedWidth = floor($originalSize->getWidth() * $zoomFactor);

            if ($maintainAspect) {
                $adjustedHeight = floor($height * ($adjustedWidth / $width));
            }
        }

        if ($adjustedHeight > $originalSize->getHeight() * $zoomFactor) {
            $adjustedHeight = floor($originalSize->getHeight() * $zoomFactor);

            if ($maintainAspect) {
                $adjustedWidth = floor($width * ($adjustedHeight / $height));
            }
        }

        return [$adjustedWidth, $adjustedHeight];
    }

    /**
     * Get the crop zoom factor
     *
     * @param array $transform
     *
     * @return float
     */
    public static function getCropZoomFactor($transform): float
    {
        if (isset($transform['cropZoom'])) {
            return (float)$transform['cropZoom'];
        }

        return 1.0;
    }

    /**
     * Gets crop point
     *
     * @param \Imagine\Image\Box $resizeSize
     * @param \Imagine\Image\Box $cropSize
     * @param string             $position
     *
     * @return \Imagine\Image\Point
     * @throws ImagerException
     */
    public static function getCropPoint($resizeSize, $cropSize, $position): Point
    {
        // Get the offsets, left and top, now as an int, representing the % offset
        [$leftOffset, $topOffset] = explode(' ', $position);

        // Get position that crop should center around
        $leftPos = floor($resizeSize->getWidth() * ($leftOffset / 100)) - floor($cropSize->getWidth() / 2);
        $topPos = floor($resizeSize->getHeight() * ($topOffset / 100)) - floor($cropSize->getHeight() / 2);

        // Make sure the point is within the boundaries and return the point
        try {
            $point = new Point(
                min(max($leftPos, 0), $resizeSize->getWidth() - $cropSize->getWidth()),
                min(max($topPos, 0), $resizeSize->getHeight() - $cropSize->getHeight())
            );
        } catch (InvalidArgumentException $e) {
            Craft::error($e->getMessage(), __METHOD__);
            throw new ImagerException($e->getMessage(), $e->getCode(), $e);
        }

        return $point;
    }

    /**
     * Returns the transform path for a given asset.
     *
     * @param Asset $asset
     *
     * @return string
     * @throws ImagerException
     */
    public static function getTransformPathForAsset($asset): string
    {
        /** @var Volume $volume */
        try {
            $volume = $asset->getVolume();
        } catch (InvalidConfigException $e) {
            Craft::error($e->getMessage(), __METHOD__);
            throw new ImagerException($e->getMessage(), $e->getCode(), $e);
        }

        /** @var ConfigModel $settings */
        $config = ImagerService::getConfig();

        $hashPath = $config->hashPath;
        $addVolumeToPath = $config->addVolumeToPath;

        if ($hashPath) {
            return FileHelper::normalizePath('/'.md5('/'.($addVolumeToPath ? mb_strtolower($volume->handle).'/' : '').$asset->folderPath.'/').'/'.$asset->id.'/');
        }

        $path = FileHelper::normalizePath('/'.($addVolumeToPath ? mb_strtolower($volume->handle).'/' : '').$asset->folderPath.'/'.$asset->id.'/');

        if (strpos($path, '//') === 0) {
            $path = substr($path, 1);
        }

        return $path;
    }

    /**
     * Returns the transform path for a given local path.
     *
     * @param $path
     *
     * @return string
     */
    public static function getTransformPathForPath($path): string
    {
        /** @var ConfigModel $settings */
        $config = ImagerService::getConfig();

        $hashPath = $config->hashPath;
        $pathParts = pathinfo($path);

        if ($hashPath) {
            return FileHelper::normalizePath('/'.md5($pathParts['dirname']));
        }

        return FileHelper::normalizePath($pathParts['dirname']);
    }

    /**
     * Returns the transform path for a given url.
     *
     * @param $url
     *
     * @return string
     */
    public static function getTransformPathForUrl($url): string
    {
        /** @var ConfigModel $settings */
        $config = ImagerService::getConfig();

        $urlParts = parse_url($url);
        $pathParts = pathinfo($urlParts['path']);
        $hashRemoteUrl = $config->getSetting('hashRemoteUrl');
        $hashPath = $config->getSetting('hashPath');
        $shortHashLength = $config->getSetting('shortHashLength');
        $transformPath = $pathParts['dirname'];

        if ($hashPath) {
            $transformPath = '/'.md5($pathParts['dirname']);
        }

        if ($hashRemoteUrl) {
            if (\is_string($hashRemoteUrl) && $hashRemoteUrl === 'host') {
                $transformPath = '/'.substr(md5($urlParts['host']), 0, $shortHashLength).$transformPath;
            } else {
                $transformPath = '/'.md5($urlParts['host'].$pathParts['dirname']);
            }
        } else {
            $transformPath = '/'.str_replace('.', '_', $urlParts['host']).$transformPath;
        }

        return FileHelper::normalizePath($transformPath);
    }

    /**
     * Creates additional file string that is appended to filename
     *
     * @param array $transform
     *
     * @return string
     */
    public static function createTransformFilestring($transform): string
    {
        $r = '';

        foreach ($transform as $k => $v) {
            if ($k === 'effects' || $k === 'preEffects') {
                $effectString = '';
                foreach ($v as $eff => $param) {
                    if (ArrayHelper::isAssociative($param)) {
                        $effectString .= '_'.$eff.self::encodeTransformObject($param);
                    } elseif (\is_array($param)) {
                        if (\is_array($param[0])) {
                            $effectString .= '_'.$eff;
                            foreach ($param as $paramArr) {
                                $effectString .= '-'.implode('-', $paramArr);
                            }
                        } else {
                            $effectString .= '_'.$eff.'-'.implode('-', $param);
                        }
                    } else {
                        $effectString .= '_'.$eff.'-'.$param;
                    }
                }

                $r .= '_'.(ImagerService::$transformKeyTranslate[$k] ?? $k).$effectString;
            } else {
                if ($k === 'watermark') {
                    $watermarkString = '';

                    foreach ($v as $eff => $param) {
                        if ($eff === 'image') {
                            if ($param instanceof Asset) {
                                $watermarkString .= '-i-'.$param->id;
                            } else {
                                $watermarkString .= '-i-'.$param;
                            }
                        } else if ($eff === 'position') {
                            $watermarkString .= '-pos';

                            foreach ($param as $posKey => $posVal) {
                                $watermarkString .= '-'.$posKey.'-'.$posVal;
                            }
                        } else {
                            $watermarkString .= '-'.$eff.'-'.(\is_array($param) ? implode('-', $param) : $param);
                        }
                    }

                    $watermarkString = substr($watermarkString, 1);

                    $r .= '_'.(ImagerService::$transformKeyTranslate[$k] ?? $k).'_'.mb_substr(md5($watermarkString), 0, 10);
                } elseif ($k === 'webpImagickOptions') {
                    $optString = '';

                    foreach ($v as $optK => $optV) {
                        $optString .= ($optK.'-'.$optV.'-');
                    }

                    $r .= '_'.(ImagerService::$transformKeyTranslate[$k] ?? $k).'_'.mb_substr($optString, 0, strlen($optString) - 1);
                } else {
                    $r .= '_'.(ImagerService::$transformKeyTranslate[$k] ?? $k).(\is_array($v) ? implode('-', $v) : $v);
                }
            }
        }

        return str_replace([' ', '.', ',', '#', '(', ')'], ['-', '-', '-', '', '', ''], $r);
    }

    /**
     * Converts a native asset transform object into an Imager transform.
     *
     * @param AssetTransform $assetTransform
     *
     * @return array
     */
    public static function normalizeAssetTransformToObject($assetTransform): array
    {
        $transformArray = $assetTransform->toArray();
        $validParams = ['width', 'height', 'format', 'mode', 'position', 'interlace', 'quality'];

        $r = [];

        foreach ($validParams as $param) {
            if (isset($transformArray[$param])) {
                $r[$param] = $transformArray[$param];
            }
        }

        return $r;
    }

    /**
     * Returns something that can be used as a fallback image for the transform method.
     *
     * @param string|Asset|int|null $configValue
     *
     * @return Asset|string|null
     */
    public static function getTransformableFromConfigSetting($configValue)
    {
        if (is_string($configValue) || $configValue instanceof Asset) {
            return $configValue;
        }

        if (is_int($configValue)) {
            /** @var Query $query */
            $query = Asset::find();
            $criteria['id'] = $configValue;
            Craft::configure($query, $criteria);

            return $query->one();
        }

        return null;
    }

    /**
     * @param ImagickImage|ImageInterface $imageInstance
     * @param array                       $transform
     */
    public static function processMetaData(&$imageInstance, $transform)
    {
        if ($imageInstance instanceof ImagickImage) {
            $config = ImagerService::getConfig();
            $imagick = $imageInstance->getImagick();
            $supportsImageProfiles = method_exists($imagick, 'getimageprofiles');
            $iccProfiles = null;

            if ($config->preserveColorProfiles && $supportsImageProfiles) {
                $iccProfiles = $imagick->getImageProfiles('icc', true);
            }

            $imagick->stripImage();

            if (!empty($iccProfiles)) {
                $imagick->profileImage('icc', $iccProfiles['icc'] ?? '');
            }
        }
    }

    /**
     * Moves a named key in an associative array to a given position
     *
     * @param string $key
     * @param int    $pos
     * @param array  $arr
     *
     * @return array
     */
    public static function moveArrayKeyToPos($key, $pos, $arr): array
    {
        if (!isset($arr[$key])) {
            return $arr;
        }

        $tempValue = $arr[$key];
        unset($arr[$key]);

        if ($pos === 0) {
            return [$key => $tempValue] + $arr;
        }

        if ($pos > \count($arr)) {
            return $arr + [$key => $tempValue];
        }

        $new_arr = [];
        $i = 1;

        foreach ($arr as $arr_key => $arr_value) {
            if ($i === $pos) {
                $new_arr[$key] = $tempValue;
            }

            $new_arr[$arr_key] = $arr_value;
            ++$i;
        }

        return $new_arr;
    }


    /**
     * Fixes slashes in path
     *
     * @param string     $str
     * @param bool|false $removeInitial
     * @param bool|false $removeTrailing
     *
     * @return mixed|string
     */
    public static function fixSlashes($str, $removeInitial = false, $removeTrailing = false)
    {
        $r = str_replace('//', '/', $str);

        if (\strlen($r) > 0) {
            if ($removeInitial && ($r[0] === '/')) {
                $r = substr($r, 1);
            }

            if ($removeTrailing && ($r[\strlen($r) - 1] === '/')) {
                $r = substr($r, 0, \strlen($r) - 1);
            }
        }

        return $r;
    }

    /**
     * Strip trailing slash
     *
     * @param $str
     *
     * @return string
     */
    public static function stripTrailingSlash($str): string
    {
        return rtrim($str, '/');
    }

    /**
     * @param array $obj
     *
     * @return string
     */
    public static function encodeTransformObject($obj): string
    {
        ksort($obj);
        $json = json_encode($obj);

        return mb_strtolower(str_replace(['{', '}', '/', '"', ':', ','], ['-', '', '', '', '-', '_'], $json));
    }
}
