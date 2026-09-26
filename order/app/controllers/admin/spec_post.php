<?php

import('plugins/order/app/services/order_spec.php');

// フォワードを確認
if (forward() === null) {
    error('不正なアクセスです。');
}

// 投稿データを確認
if (empty($_SESSION['post'])) {
    // リダイレクト
    redirect('/admin/spec_form');
}

// トランザクションを開始
db_transaction();

if (empty($_SESSION['post']['order_spec']['id'])) {
    // ルールを登録
    $resource = service_order_spec_insert([
        'values' => [
            'entry_id'      => $_SESSION['post']['order_spec']['entry_id'],
            'code'          => $_SESSION['post']['order_spec']['code'],
            'enabled'       => $_SESSION['post']['order_spec']['enabled'],
            'name'          => $_SESSION['post']['order_spec']['name'],
            'provide'       => $_SESSION['post']['order_spec']['provide'],
            'selling_price' => $_SESSION['post']['order_spec']['selling_price'],
            'regular_price' => $_SESSION['post']['order_spec']['regular_price'],
            'shipping_cost' => $_SESSION['post']['order_spec']['shipping_cost'],
            'delivery_days' => $_SESSION['post']['order_spec']['delivery_days'],
            'sales_limit'   => $_SESSION['post']['order_spec']['sales_limit'],
            'memo'          => $_SESSION['post']['order_spec']['memo'],
            'sort'          => $_SESSION['post']['order_spec']['sort'],
        ],
    ]);
    if (!$resource) {
        error('データを登録できません。');
    }
} else {
    // ルールを編集
    $resource = service_order_spec_update([
        'set'   => [
            'entry_id'      => $_SESSION['post']['order_spec']['entry_id'],
            'code'          => $_SESSION['post']['order_spec']['code'],
            'enabled'       => $_SESSION['post']['order_spec']['enabled'],
            'name'          => $_SESSION['post']['order_spec']['name'],
            'provide'       => $_SESSION['post']['order_spec']['provide'],
            'selling_price' => $_SESSION['post']['order_spec']['selling_price'],
            'regular_price' => $_SESSION['post']['order_spec']['regular_price'],
            'shipping_cost' => $_SESSION['post']['order_spec']['shipping_cost'],
            'delivery_days' => $_SESSION['post']['order_spec']['delivery_days'],
            'sales_limit'   => $_SESSION['post']['order_spec']['sales_limit'],
            'memo'          => $_SESSION['post']['order_spec']['memo'],
        ],
        'where' => [
            'id = :id',
            [
                'id' => $_SESSION['post']['order_spec']['id'],
            ],
        ],
    ], [
        'id'     => intval($_SESSION['post']['order_spec']['id']),
        'update' => $_SESSION['update']['order_spec'],
    ]);
    if (!$resource) {
        error('データを編集できません。');
    }
}

// トランザクションを終了
db_commit();

// エントリーIDを取得
$entry_id = $_SESSION['post']['order_spec']['entry_id'];

// 投稿セッションを初期化
unset($_SESSION['post']);
unset($_SESSION['update']);

// リダイレクト
redirect('/admin/spec?entry_id=' . $entry_id . '&ok=post');
