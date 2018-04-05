<?php
/**
 * hiAPI GoGetSSL plugin
 *
 * @link      https://github.com/hiqdev/hiapi-gogetssl
 * @package   hiapi-gogetssl
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2017-2018, HiQDev (http://hiqdev.com/)
 */

return [
    'container' => [
        'definitions' => [
            'gogetsslTool' => [
                'class' => \hiapi\gogetssl\GoGetSSLTool::class,
            ],
        ],
    ],
];
