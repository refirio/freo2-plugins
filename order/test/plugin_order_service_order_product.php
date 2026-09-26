<?php

// 設定ファイルを読み込み
import('app/config.php');
import('plugins/order/config.php');

// コードカバレッジの記録を開始
if (!isset($_GET['_test'])) {
    service('coverage.php');
    service_coverage_start();
}

// ライブラリを読み込み（プラグインのモデルとサービスは自動で読み込まれない）
model('logs.php');
import('plugins/order/app/models/order_products.php');
import('plugins/order/app/services/order_product.php');

// リクエスト情報を用意
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'freo/2';

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_products;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// 正常データ
$data_order_product = [
    'spec_id'  => 1,
    'stock_id' => 1,
    'quantity' => 2,
    'sort'     => 1,
];
$data_order_products = [];
foreach ([1, 2, 3] as $index) {
    $data_order_products[] = [
        'spec_id'  => 1,
        'stock_id' => $index,
        'quantity' => 1,
        'sort'     => $index,
    ];
}

// トランザクションを開始
db_transaction();

// 正常登録テスト
{
    // データ
    $test_order_product = $data_order_product;

    // 登録
    $test_order_product = model('normalize_order_products', $test_order_product);
    $warnings           = model('validate_order_products', $test_order_product);
    if (empty($warnings)) {
        service_order_product_insert([
            'values' => $test_order_product,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $order_products = model('select_order_products', [
        'select'   => 'spec_id, stock_id, quantity',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    $inserted_data = array_shift($order_products);
    $test_data     = [
        $inserted_data,
    ];
    test_array_subset('insert order_product', $test_data, [
        'spec_id'  => $test_order_product['spec_id'],
        'stock_id' => $test_order_product['stock_id'],
        'quantity' => $test_order_product['quantity'],
    ]);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'order_by' => 'id DESC',
    ]);

    test_equals('insert order_product log', count($logs), 1);
    test_equals('insert order_product log model', $logs[0]['model'], 'order_products');
    test_equals('insert order_product log exec', $logs[0]['exec'], 'insert');
    test_equals('insert order_product log ip', $logs[0]['ip'], '127.0.0.1');
}

// 登録した製品のIDを取得
$order_products = model('select_order_products', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($order_products[0]['id']);

// 更新テスト
{
    // 更新
    service_order_product_update([
        'set'   => [
            'quantity' => 5,
        ],
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果
    $order_products = model('select_order_products', [
        'select' => 'quantity',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update order_product', intval($order_products[0]['quantity']), 5);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => [
            'exec = :exec',
            [
                'exec' => 'update',
            ],
        ],
        'order_by' => 'id DESC',
    ]);

    test_equals('update order_product log', count($logs), 1);
    test_equals('update order_product log model', $logs[0]['model'], 'order_products');
}

// 最終編集日時の確認テスト
{
    // 更新（編集開始後に更新されていないので、競合とは判定されない）
    service_order_product_update([
        'set'   => [
            'quantity' => 6,
        ],
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ], [
        'id'     => $inserted_id,
        'update' => localdate('Y-m-d H:i:s'),
    ]);

    // 結果
    $order_products = model('select_order_products', [
        'select' => 'quantity',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update order_product (modified check)', intval($order_products[0]['quantity']), 6);
}

// 操作ログの重複抑止テスト
{
    // 結果（service_log_record() は同じ model と exec の組み合わせを1リクエストにつき1回しか記録しない）
    $logs = model('select_logs', [
        'order_by' => 'id DESC',
    ]);

    test_equals('record order_product log once', count($logs), 2);
}

// 削除テスト
{
    // 削除
    service_order_product_delete([
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果
    $order_products = model('select_order_products', [
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    test_equals('delete order_product', count($order_products), 0);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => [
            'exec = :exec',
            [
                'exec' => 'delete',
            ],
        ],
        'order_by' => 'id DESC',
    ]);

    test_equals('delete order_product log', count($logs), 1);
    test_equals('delete order_product log model', $logs[0]['model'], 'order_products');
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_products;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// トランザクションを開始
db_transaction();

// 並び順の一括変更テスト
{
    // 登録
    foreach ($data_order_products as $order_product) {
        $order_product = model('normalize_order_products', $order_product);
        $warnings      = model('validate_order_products', $order_product);
        if (empty($warnings)) {
            service_order_product_insert([
                'values' => $order_product,
            ]);
        } else {
            debug($warnings);
        }
    }

    // 結果
    $order_products = model('select_order_products', [
        'select'   => 'id, sort',
        'order_by' => 'id',
    ]);

    test_equals('sort order_products (before)', array_map('intval', array_column($order_products, 'sort')), [1, 2, 3]);

    // 並び順を更新（IDは決め打ちにせず、登録済みのものを使う）
    $ids = array_column($order_products, 'id');

    service_order_product_sort([
        $ids[0] => 3,
        $ids[1] => 2,
        $ids[2] => 1,
    ]);

    // 結果
    $order_products = model('select_order_products', [
        'select'   => 'sort',
        'order_by' => 'id',
    ]);

    test_equals('sort order_products (after)', array_map('intval', array_column($order_products, 'sort')), [3, 2, 1]);

    // 結果（不正な値は無視されること）
    service_order_product_sort([
        $ids[0] => 'あ', // 並び順が数字でない
        'あ'    => 1,    // IDが不正
    ]);

    $order_products = model('select_order_products', [
        'select'   => 'sort',
        'order_by' => 'id',
    ]);

    test_equals('sort order_products (invalid)', array_map('intval', array_column($order_products, 'sort')), [3, 2, 1]);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_products;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'plugins/order/app/services/order_product.php',
    ]);
}
