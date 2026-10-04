<?php

declare(strict_types=1);

use yii\console\Application;
use yii\i18n\PhpMessageSource;

$pluginRoot = dirname(__DIR__);

defined('CRAFT_BASE_PATH') || define('CRAFT_BASE_PATH', $pluginRoot);
defined('CRAFT_VENDOR_PATH') || define('CRAFT_VENDOR_PATH', CRAFT_BASE_PATH . '/vendor');
defined('CRAFT_STORAGE_PATH') || define('CRAFT_STORAGE_PATH', CRAFT_BASE_PATH . '/tests/_craft/storage');
defined('CRAFT_RUNTIME_PATH') || define('CRAFT_RUNTIME_PATH', CRAFT_STORAGE_PATH . '/runtime');

if (!is_dir(CRAFT_RUNTIME_PATH)) {
    mkdir(CRAFT_RUNTIME_PATH, 0775, true);
}

require_once CRAFT_VENDOR_PATH . '/autoload.php';
require_once CRAFT_VENDOR_PATH . '/yiisoft/yii2/Yii.php';
require_once CRAFT_VENDOR_PATH . '/craftcms/cms/src/Craft.php';

if (!Craft::$app) {
    new Application([
        'id' => 'footnotes-tests',
        'basePath' => CRAFT_BASE_PATH,
        'vendorPath' => CRAFT_VENDOR_PATH,
        'runtimePath' => CRAFT_RUNTIME_PATH,
        'components' => [
            'i18n' => [
                'translations' => [
                    'footnotes' => [
                        'class' => PhpMessageSource::class,
                        'basePath' => CRAFT_BASE_PATH . '/src/translations',
                        'sourceLanguage' => 'en-US',
                    ],
                ],
            ],
        ],
    ]);
}
