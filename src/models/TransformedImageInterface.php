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

interface TransformedImageInterface
{
    /**
     * @return string
     */
    public function getPath():string;

    /**
     * @return string
     */
    public function getFilename():string;

    /**
     * @return string
     */
    public function getUrl():string;

    /**
     * @return string
     */
    public function getExtension():string;

    /**
     * @return string
     */
    public function getMimeType():string;

    /**
     * @return int
     */
    public function getWidth():int;

    /**
     * @return int
     */
    public function getHeight():int;

    /**
     * @param string $unit
     * @param int $precision
     * @return mixed
     */
    public function getSize(string $unit = 'b', int $precision = 2): mixed;

    /**
     * @return string
     */
    public function getDataUri():string;

    /**
     * @return string
     */
    public function getBase64Encoded():string;

    /**
     * @param array $settings
     * @return string
     */
    public function getPlaceholder(array $settings = []):string;

    /**
     * @return bool
     */
    public function getIsNew():bool;
}
