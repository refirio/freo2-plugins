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
import('plugins/order/app/models/order_stocks.php');
import('plugins/order/app/services/order_stock.php');

// リクエスト情報を用意
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'freo/2';

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_stocks;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// 正常データ
$data_order_stock = [
    'code'       => 'teststock1',
    'name'       => 'テスト在庫1',
    'kind'       => 'analog',
    'quantity'   => 10,
    'cost_price' => 100,
];

// トランザクションを開始
db_transaction();

// 正常登録テスト
{
    // データ
    $test_order_stock = $data_order_stock;

    // 登録
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);
    if (empty($warnings)) {
        service_order_stock_insert([
            'values' => $test_order_stock,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $order_stocks = model('select_order_stocks', [
        'select'   => 'code, name, kind',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    $inserted_data = array_shift($order_stocks);
    $test_data     = [
        $inserted_data,
    ];
    test_array_subset('insert order_stock', $test_data, [
        'code' => $test_order_stock['code'],
        'name' => $test_order_stock['name'],
        'kind' => $test_order_stock['kind'],
    ]);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'order_by' => 'id DESC',
    ]);

    test_equals('insert order_stock log', count($logs), 1);
    test_equals('insert order_stock log model', $logs[0]['model'], 'order_stocks');
    test_equals('insert order_stock log exec', $logs[0]['exec'], 'insert');
    test_equals('insert order_stock log ip', $logs[0]['ip'], '127.0.0.1');
}

// 登録した在庫のIDを取得
$order_stocks = model('select_order_stocks', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($order_stocks[0]['id']);

// 更新テスト
{
    // データ
    $test_order_stock = $data_order_stock;
    $test_order_stock['id']   = $inserted_id;
    $test_order_stock['name'] = 'テスト在庫2';

    // 更新
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);
    if (empty($warnings)) {
        service_order_stock_update([
            'set'   => [
                'name' => $test_order_stock['name'],
            ],
            'where' => [
                'id = :id',
                [
                    'id' => $inserted_id,
                ],
            ],
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $order_stocks = model('select_order_stocks', [
        'select' => 'name',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update order_stock', $order_stocks[0]['name'], 'テスト在庫2');

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

    test_equals('update order_stock log', count($logs), 1);
    test_equals('update order_stock log model', $logs[0]['model'], 'order_stocks');
}

// 最終編集日時の確認テスト
{
    // 更新（編集開始後に更新されていないので、競合とは判定されない）
    service_order_stock_update([
        'set'   => [
            'name' => 'テスト在庫3',
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
    $order_stocks = model('select_order_stocks', [
        'select' => 'name',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update order_stock (modified check)', $order_stocks[0]['name'], 'テスト在庫3');
}

// 操作ログの重複抑止テスト
{
    // 結果（service_log_record() は同じ model と exec の組み合わせを1リクエストにつき1回しか記録しない）
    $logs = model('select_logs', [
        'order_by' => 'id DESC',
    ]);

    test_equals('record order_stock log once', count($logs), 2);
}

// 削除テスト
{
    // 削除
    service_order_stock_delete([
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果
    $order_stocks = model('select_order_stocks', [
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    test_equals('delete order_stock', count($order_stocks), 0);

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

    test_equals('delete order_stock log', count($logs), 1);
    test_equals('delete order_stock log model', $logs[0]['model'], 'order_stocks');
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_stocks;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'plugins/order/app/services/order_stock.php',
    ]);
}
