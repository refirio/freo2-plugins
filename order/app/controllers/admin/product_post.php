<?php

import('plugins/order/app/services/order_product.php');

// フォワードを確認
if (forward() === null) {
    error('不正なアクセスです。');
}

// 投稿データを確認
if (empty($_SESSION['post'])) {
    // リダイレクト
    redirect('/admin/product_form');
}

// トランザクションを開始
db_transaction();

if (empty($_SESSION['post']['order_product']['id'])) {
    // 製品を登録
    $resource = service_order_product_insert([
        'values' => [
            'spec_id'  => $_SESSION['post']['order_product']['spec_id'],
            'stock_id' => $_SESSION['post']['order_product']['stock_id'],
            'quantity' => $_SESSION['post']['order_product']['quantity'],
            'memo'     => $_SESSION['post']['order_product']['memo'],
            'sort'     => $_SESSION['post']['order_product']['sort'],
        ],
    ]);
    if (!$resource) {
        error('データを登録できません。');
    }
} else {
    // 製品を編集
    $resource = service_order_product_update([
        'set'   => [
            'spec_id'  => $_SESSION['post']['order_product']['spec_id'],
            'stock_id' => $_SESSION['post']['order_product']['stock_id'],
            'quantity' => $_SESSION['post']['order_product']['quantity'],
            'memo'     => $_SESSION['post']['order_product']['memo'],
        ],
        'where' => [
            'id = :id',
            [
                'id' => $_SESSION['post']['order_product']['id'],
            ],
        ],
    ], [
        'id'     => intval($_SESSION['post']['order_product']['id']),
        'update' => $_SESSION['update']['order_product'],
    ]);
    if (!$resource) {
        error('データを編集できません。');
    }
}

// トランザクションを終了
db_commit();

// 規格IDを取得
$spec_id = $_SESSION['post']['order_product']['spec_id'];

// エントリーIDを取得
$entry_id = $_SESSION['post']['order_product']['entry_id'];

// 投稿セッションを初期化
unset($_SESSION['post']);
unset($_SESSION['update']);

// リダイレクト
redirect('/admin/product?entry_id=' . $entry_id . '&spec_id=' . $spec_id . '&ok=post');
