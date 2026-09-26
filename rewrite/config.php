<?php

/*******************************************************************************

 設定ファイル

*******************************************************************************/

/* プラグインコード */
$GLOBALS['plugin']['rewrite']['code'] = 'rewrite';

/* プラグイン名 */
$GLOBALS['plugin']['rewrite']['name'] = 'リライト';

/* プラグイン概要 */
$GLOBALS['plugin']['rewrite']['description'] = 'URLを操作します。';

/* プラグイン詳細 */
$GLOBALS['plugin']['rewrite']['detail'] = <<< EOM
管理画面からルールを登録し、URLを操作します。
EOM;

/* プラグインバージョン */
$GLOBALS['plugin']['rewrite']['version'] = '0.0.0';

/* プラグイン更新日 */
$GLOBALS['plugin']['rewrite']['updated'] = '2026-05-21';

/* プラグイン製作者 */
$GLOBALS['plugin']['rewrite']['author'] = 'refirio';

/* プラグインURL */
$GLOBALS['plugin']['rewrite']['link'] = 'https://refirio.org/';

/* オプション項目 */
$GLOBALS['plugin']['rewrite']['option'] = [
    'rewrite_rule' => [
        // 有効
        'enabled' => [
            1 => '有効',
            0 => '無効',
        ],
        // タイプ
        'type' => [
            'view'     => '表示',
            'redirect' => '転送',
        ],
    ],
];
