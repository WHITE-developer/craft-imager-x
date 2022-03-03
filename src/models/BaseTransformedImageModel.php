<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\models;

use spacecatninja\imagerx\ImagerX;
use spacecatninja\imagerx\exceptions\ImagerException;


class BaseTransformedImageModel
{
    /**
     * @var mixed
     */
    public mixed $source = null;

    /**
     * @var string
     */
    public string $path = '';

    /**
     * @var string
     */
    public string $filename = '';

    /**
     * @var string
     */
    public string $url = '';

    /**
     * @var string
     */
    public string $extension = '';

    /**
     * @var string
     */
    public string $mimeType = '';

    /**
     * @var int
     */
    public int $width = 0;

    /**
     * @var int
     */
    public int $height = 0;

    /**
     * @var int|float
     */
    public int|float $size = 0;

    /**
     * @var bool
     */
    public bool $isNew = false;

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getExtension(): string
    {
        return $this->extension;
    }

    /**
     * @return string
     */
    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * @return int
     */
    public function getWidth(): int
    {
        return (int)$this->width;
    }

    /**
     * @return int
     */
    public function getHeight(): int
    {
        return (int)$this->height;
    }

    /**
     * @param string $unit
     * @param int    $precision
     *
     * @return float|int
     */
    public function getSize(string $unit = 'b', int $precision = 2): float|int
    {
        return 0;
    }

    /**
     * @param array $settings
     *
     * @return string
     * @throws ImagerException
     */
    public function getPlaceholder(array $settings = []): string
    {
        if ($settings) {
            if (!isset($settings['width'])) {
                $settings['width'] = $this->width;
            }
            if (!isset($settings['height'])) {
                $settings['height'] = $this->height;
            }
        }

        return ImagerX::$plugin->placeholder->placeholder($settings);
    }

    /**
     * @return bool
     */
    public function getIsNew(): bool
    {
        return $this->isNew;
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
     * @return string
     */
    public function getBlurhash(): string
    {
        return '';
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string)$this->url;
    }

}
