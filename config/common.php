<?php
/**
 * hiAPI GoGetSSL plugin
 *
 * @link      https://github.com/hiqdev/hiapi-gogetssl
 * @package   hiapi-gogetssl
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2017-2018, HiQDev (http://hiqdev.com/)
 */

$definitions = [
    'gogetsslTool' => [
        '__class' => \hiapi\gogetssl\GoGetSSLTool::class,
    ],
];

return class_exists('Yiisoft\Factory\Definitions\Reference') ? $definitions : ['container' => ['definitions' => $definitions]];
