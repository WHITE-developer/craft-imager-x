<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\optimizers;

use function Tinify\setKey;
use function Tinify\validate;
use function Tinify\fromFile;
use Craft;

use Tinify\Exception;

class TinypngOptimizer implements ImagerOptimizeInterface
{

    public static function optimize(string $file, ?array $settings): void
    {
        try {
            setKey($settings['apiKey']);
            validate();
            fromFile($file)->toFile($file);
        } catch (Exception) {
            Craft::error('Could not validate connection to TinyPNG, image was not optimized.', __METHOD__);
        }
    }
}
