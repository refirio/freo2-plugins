<?php

/*******************************************************************************

 設定ファイル

*******************************************************************************/

/* プラグインコード */
$GLOBALS['plugin']['gallery']['code'] = 'gallery';

/* プラグイン名 */
$GLOBALS['plugin']['gallery']['name'] = 'ギャラリー';

/* プラグイン概要 */
$GLOBALS['plugin']['gallery']['description'] = 'ギャラリーを作成します。';

/* プラグイン詳細 */
$GLOBALS['plugin']['gallery']['detail'] = <<< EOM
管理画面から作品を登録し、ギャラリーとして表示します。
EOM;

/* プラグインバージョン */
$GLOBALS['plugin']['gallery']['version'] = '0.0.0';

/* プラグイン更新日 */
$GLOBALS['plugin']['gallery']['updated'] = '2026-02-08';

/* プラグイン製作者 */
$GLOBALS['plugin']['gallery']['author'] = 'refirio';

/* プラグインURL */
$GLOBALS['plugin']['gallery']['link'] = 'https://refirio.org/';

/* HTML出力で出力するページ（URLの一覧を返す関数。app/bootstrap.php で定義する） */
$GLOBALS['plugin']['gallery']['export'] = 'plugin_gallery_export_urls';

/* プラグイン設定項目 */
$GLOBALS['plugin']['gallery']['setting_define'] = [
    'use_approve' => [
        'name'        => 'ギャラリーの承認',
        'explanation' => '公開には管理者の承認が必要になります。',
        'type'        => 'boolean',
        'required'    => false,
    ],
    'use_pictures' => [
        'name'        => '写真の入力',
        'explanation' => null,
        'type'        => 'boolean',
        'required'    => false,
    ],
    'use_thumbnail' => [
        'name'        => 'サムネイルの入力',
        'explanation' => null,
        'type'        => 'boolean',
        'required'    => false,
    ],
    'default_code' => [
        'name'        => 'コードの初期値',
        'explanation' => 'YmdHisが年月日時分秒になります。',
        'type'        => 'text',
        'required'    => false,
    ],
    'text_type' => [
        'name'        => '本文の入力項目',
        'explanation' => null,
        'type'        => 'select',
        'required'    => false,
        'kind'        => [
            'none'     => 'なし',
            'textarea' => '複数行入力',
            'html'     => 'HTML直接入力',
            'wysiwyg'  => 'WYSIWYGエディタ',
        ],
    ],
    'text_index' => [
        'name'        => '文言 ギャラリー',
        'explanation' => null,
        'type'        => 'textarea',
        'required'    => false,
    ],
    'text_detail' => [
        'name'        => '文言 ギャラリー詳細',
        'explanation' => null,
        'type'        => 'textarea',
        'required'    => false,
    ],
];

/* プラグイン設定初期値 */
$GLOBALS['plugin']['gallery']['setting_default'] = [
    'use_approve'   => false,
    'use_pictures'  => true,
    'use_thumbnail' => true,
    'default_code'  => null,
    'text_type'     => 'wysiwyg',
    'text_index'    => '',
    'text_detail'   => '',
];
