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
import('plugins/order/app/models/order_deliveries.php');
import('plugins/order/app/services/order_delivery.php');

// リクエスト情報を用意
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'freo/2';

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_deliveries;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// 正常データ
$data_order_delivery = [
    'enabled'   => 1,
    'name'      => '宅配便',
    'cost'      => 500,
    'calculate' => 'order',
];

// トランザクションを開始
db_transaction();

// 正常登録テスト
{
    // データ
    $test_order_delivery = $data_order_delivery;

    // 登録
    $test_order_delivery = model('normalize_order_deliveries', $test_order_delivery);
    $warnings            = model('validate_order_deliveries', $test_order_delivery);
    if (empty($warnings)) {
        service_order_delivery_insert([
            'values' => $test_order_delivery,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $order_deliveries = model('select_order_deliveries', [
        'select'   => 'enabled, name, cost, calculate',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    $inserted_data = array_shift($order_deliveries);
    $test_data     = [
        $inserted_data,
    ];
    test_array_subset('insert order_delivery', $test_data, [
        'enabled'   => $test_order_delivery['enabled'],
        'name'      => $test_order_delivery['name'],
        'cost'      => $test_order_delivery['cost'],
        'calculate' => $test_order_delivery['calculate'],
    ]);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'order_by' => 'id DESC',
    ]);

    test_equals('insert order_delivery log', count($logs), 1);
    test_equals('insert order_delivery log model', $logs[0]['model'], 'order_deliveries');
    test_equals('insert order_delivery log exec', $logs[0]['exec'], 'insert');
    test_equals('insert order_delivery log ip', $logs[0]['ip'], '127.0.0.1');
}

// 登録した配送方法のIDを取得
$order_deliveries = model('select_order_deliveries', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($order_deliveries[0]['id']);

// 更新テスト
{
    // 更新
    service_order_delivery_update([
        'set'   => [
            'name' => 'メール便',
            'cost' => 200,
        ],
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果
    $order_deliveries = model('select_order_deliveries', [
        'select' => 'name, cost',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update order_delivery', $order_deliveries[0]['name'], 'メール便');
    test_equals('update order_delivery cost', intval($order_deliveries[0]['cost']), 200);

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

    test_equals('update order_delivery log', count($logs), 1);
    test_equals('update order_delivery log model', $logs[0]['model'], 'order_deliveries');
}

// 最終編集日時の確認テスト
{
    // 更新（編集開始後に更新されていないので、競合とは判定されない）
    service_order_delivery_update([
        'set'   => [
            'name' => '店頭受け取り',
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
    $order_deliveries = model('select_order_deliveries', [
        'select' => 'name',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update order_delivery (modified check)', $order_deliveries[0]['name'], '店頭受け取り');
}

// 操作ログの重複抑止テスト
{
    // 結果（service_log_record() は同じ model と exec の組み合わせを1リクエストにつき1回しか記録しない）
    $logs = model('select_logs', [
        'order_by' => 'id DESC',
    ]);

    test_equals('record order_delivery log once', count($logs), 2);
}

// 削除テスト
{
    // 削除
    service_order_delivery_delete([
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果
    $order_deliveries = model('select_order_deliveries', [
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    test_equals('delete order_delivery', count($order_deliveries), 0);

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

    test_equals('delete order_delivery log', count($logs), 1);
    test_equals('delete order_delivery log model', $logs[0]['model'], 'order_deliveries');
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_deliveries;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'plugins/order/app/services/order_delivery.php',
    ]);
}
