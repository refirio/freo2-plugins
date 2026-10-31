<?php

// モデルを読み込み
import('plugins/rewrite/app/models/rewrite_rules.php');

// メニューを追加
$menu_contents = [];
foreach ($GLOBALS['menu_contents']['admin']['contents'] as $key => $value) {
    if ($key === 'widget') {
        $menu_contents['rewrite'] = [
            'name'   => 'リライト管理',
            'link'   => '/admin/rewrite',
            'active' => '/^rewrite(_|$)/',
            'icon'   => '#symbol-list-ul',
            'show'   => true,
        ];
    }
    $menu_contents[$key] = $value;
}
$GLOBALS['menu_contents']['admin']['contents'] = $menu_contents;

/**
 * HTML出力で出力するページのURLを取得
 *
 * 有効な「表示」のルールのURL（転送のルールと、前方一致の続きのURLは、HTMLでは再現できないので出力しない）
 *
 * @return array
 */
function plugin_rewrite_export_urls()
{
    $rewrite_rules = model('select_rewrite_rules', [
        'where'    => [
            'enabled = 1 AND type = :type',
            [
                'type' => 'view',
            ],
        ],
        'order_by' => 'sort, id',
    ]);

    return array_column($rewrite_rules, 'url');
}

// 現在のURLを取得（クエリ文字列は判定に含めない。表示ではそのまま $_GET に残し、転送では転送先に引き継ぐ）
$url = '/';
if (!empty($_params)) {
    $url .= implode('/', $_params);
}
$query = '';
if (!empty($_SERVER['QUERY_STRING'])) {
    $query = '?' . $_SERVER['QUERY_STRING'];
}

// ルールを取得
$rewrite_rules = model('select_rewrite_rules', [
    'where'    => 'enabled = 1',
    'order_by' => 'sort, id',
]);

// リライトを実行
foreach ($rewrite_rules as $rewrite_rule) {
    if (preg_match('/^' . preg_quote($rewrite_rule['url'], '/') . '/', $url)) {
        $url = preg_replace('/^' . preg_quote($rewrite_rule['url'], '/') . '/', $rewrite_rule['rewrited'], $url);

        if ($rewrite_rule['type'] === 'view') {
            $_params = explode('/', $url);
            array_shift($_params);

            if (empty($_params[0])) {
                $_REQUEST['_mode'] = MAIN_DEFAULT_MODE;
            } else {
                $_REQUEST['_mode'] = $_params[0];
            }
            if (empty($_params[1])) {
                $_REQUEST['_work'] = MAIN_DEFAULT_WORK;
            } else {
                $_REQUEST['_work'] = $_params[1];
            }
        } elseif ($rewrite_rule['type'] === 'redirect') {
            redirect($url . $query);
        }

        break;
    }
}
