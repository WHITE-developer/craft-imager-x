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

use craft\helpers\ConfigHelper;
use craft\helpers\FileHelper;
use craft\base\Model;

class Settings extends Model
{
    public $transformer = 'craft';
    public $imagerSystemPath = '@webroot/imager/';
    public $imagerUrl = '/imager/';

    public $cacheEnabled = true;
    public $cacheRemoteFiles = true;
    /**
     * @var int|bool|string
     */
    public string|int|bool $cacheDuration = 1_209_600;
    public $cacheDurationRemoteFiles = 1_209_600;
    public $cacheDurationExternalStorage = 1_209_600;
    public $cacheDurationNonOptimized = 300;

    public $jpegQuality = 80;
    public $pngCompressionLevel = 2;
    public $webpQuality = 80;
    public $avifQuality = 80;
    public $jxlQuality = 80;

    public $webpImagickOptions = [];
    public $interlace = false;
    public $allowUpscale = true;
    public $resizeFilter = 'lanczos';
    public $smartResizeEnabled = false;
    public $removeMetadata = false;
    public $preserveColorProfiles = false;
    public $safeFileFormats = ['jpg', 'jpeg', 'gif', 'png'];
    public $bgColor = '';
    public $position = '50% 50%';
    public $letterbox = ['color' => '#000', 'opacity' => 0];
    public $useFilenamePattern = true;
    public $filenamePattern = '{basename}_{transformString|hash}.{extension}';
    public $shortHashLength = 10;
    public $hashFilename = 'postfix'; // deprecated
    public $hashPath = false;
    public $addVolumeToPath = true;
    public $hashRemoteUrl = false;
    public $useRemoteUrlQueryString = false;
    public $instanceReuseEnabled = false;
    public $noop = false;
    public $suppressExceptions = false;
    public $convertToRGB = false;
    public $skipExecutableExistCheck = false;
    public $removeTransformsOnAssetFileops = false;
    public $curlOptions = [];
    public $runJobsImmediatelyOnAjaxRequests = true;
    public $fillTransforms = false;
    public $fillAttribute = 'width';
    public $fillInterval = '200';
    public $fallbackImage = null;
    public $mockImage = null;
    public $useRawExternalUrl = true;
    public $clearKey = '';

    public $useForNativeTransforms = false;
    public $useForCpThumbs = false;
    public $hideClearCachesForUserGroups = [];

    public $imgixProfile = 'default';
    public $imgixApiKey = '';
    public $imgixEnableAutoPurging = true;
    public $imgixEnablePurgeElementAction = true;
    public $imgixConfig = [
        'default' => [
            'domain' => '',
            'useHttps' => true,
            'signKey' => '',
            'sourceIsWebProxy' => false,
            'useCloudSourcePath' => true,
            'getExternalImageDimensions' => true,
            'defaultParams' => [],
            'apiKey' => '',
            'excludeFromPurge' => false,
        ]
    ];

    public $optimizeType = 'job';
    public $optimizers = [];
    public $optimizerConfig = [
        'jpegoptim' => [
            'extensions' => ['jpg'],
            'path' => '/usr/bin/jpegoptim',
            'optionString' => '-s',
        ],
        'jpegtran' => [
            'extensions' => ['jpg'],
            'path' => '/usr/bin/jpegtran',
            'optionString' => '-optimize -copy none',
        ],
        'mozjpeg' => [
            'extensions' => ['jpg'],
            'path' => '/usr/bin/mozjpeg',
            'optionString' => '-optimize -copy none',
        ],
        'optipng' => [
            'extensions' => ['png'],
            'path' => '/usr/bin/optipng',
            'optionString' => '-o2',
        ],
        'pngquant' => [
            'extensions' => ['png'],
            'path' => '/usr/bin/pngquant',
            'optionString' => '--strip --skip-if-larger',
        ],
        'gifsicle' => [
            'extensions' => ['gif'],
            'path' => '/usr/bin/gifsicle',
            'optionString' => '--optimize=3 --colors 256',
        ],
        'tinypng' => [
            'extensions' => ['png', 'jpg'],
            'apiKey' => '',
        ],
        'kraken' => [
            'extensions' => ['png', 'jpg', 'gif'],
            'apiKey' => '',
            'apiSecret' => '',
            'additionalParams' => [
                'lossy' => true,
            ]
        ],
        'imageoptim' => [
            'extensions' => ['png', 'jpg', 'gif'],
            'apiUsername' => '',
            'quality' => 'medium'
        ],
    ];

    public $storages = [];
    public $storageConfig = [
        'aws' => [
            'accessKey' => '',
            'secretAccessKey' => '',
            'region' => '',
            'bucket' => '',
            'folder' => '',
            'requestHeaders' => [],
            'storageType' => 'standard',
            'public' => 'true',
            'cloudfrontInvalidateEnabled' => false,
            'cloudfrontDistributionId' => '',
        ],
        'gcs' => [
            'keyFile' => '',
            'bucket' => '',
            'folder' => '',
        ],
    ];

    public $customEncoders = [];
    public $transformerConfig = null;

    /* deprecated */
    public $useCwebp = false;
    public $cwebpPath = '/usr/bin/cwebp';
    public $cwebpOptions = '';
    public $avifEncoderPath = '';
    public $avifEncoderOptions = [];
    public $avifConvertString = '{src} {dest}';


    /**
     * Settings constructor.
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        parent::__construct($config);

        if (!empty($config)) {
            \Yii::configure($this, $config);
        }

        $this->init();
    }

    public function init(): void
    {
        // Set default based on devMode. Overridable through config.  
        $this->suppressExceptions = !\Craft::$app->getConfig()->general->devMode;
    }
}
