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
import('plugins/order/app/models/order_payments.php');
import('plugins/order/app/services/order_payment.php');

// リクエスト情報を用意
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'freo/2';

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_payments;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// 正常データ
$data_order_payment = [
    'enabled' => 1,
    'name'    => '銀行振込',
    'fee'     => 330,
];

// トランザクションを開始
db_transaction();

// 正常登録テスト
{
    // データ
    $test_order_payment = $data_order_payment;

    // 登録
    $test_order_payment = model('normalize_order_payments', $test_order_payment);
    $warnings           = model('validate_order_payments', $test_order_payment);
    if (empty($warnings)) {
        service_order_payment_insert([
            'values' => $test_order_payment,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $order_payments = model('select_order_payments', [
        'select'   => 'enabled, name, fee',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    $inserted_data = array_shift($order_payments);
    $test_data     = [
        $inserted_data,
    ];
    test_array_subset('insert order_payment', $test_data, [
        'enabled' => $test_order_payment['enabled'],
        'name'    => $test_order_payment['name'],
        'fee'     => $test_order_payment['fee'],
    ]);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'order_by' => 'id DESC',
    ]);

    test_equals('insert order_payment log', count($logs), 1);
    test_equals('insert order_payment log model', $logs[0]['model'], 'order_payments');
    test_equals('insert order_payment log exec', $logs[0]['exec'], 'insert');
    test_equals('insert order_payment log ip', $logs[0]['ip'], '127.0.0.1');
}

// 登録した支払方法のIDを取得
$order_payments = model('select_order_payments', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($order_payments[0]['id']);

// 更新テスト
{
    // 更新
    service_order_payment_update([
        'set'   => [
            'name' => '代金引換',
        ],
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果
    $order_payments = model('select_order_payments', [
        'select' => 'name',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update order_payment', $order_payments[0]['name'], '代金引換');

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

    test_equals('update order_payment log', count($logs), 1);
    test_equals('update order_payment log model', $logs[0]['model'], 'order_payments');
}

// 最終編集日時の確認テスト
{
    // 更新（編集開始後に更新されていないので、競合とは判定されない）
    service_order_payment_update([
        'set'   => [
            'name' => 'クレジットカード',
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
    $order_payments = model('select_order_payments', [
        'select' => 'name',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update order_payment (modified check)', $order_payments[0]['name'], 'クレジットカード');
}

// 操作ログの重複抑止テスト
{
    // 結果（service_log_record() は同じ model と exec の組み合わせを1リクエストにつき1回しか記録しない）
    $logs = model('select_logs', [
        'order_by' => 'id DESC',
    ]);

    test_equals('record order_payment log once', count($logs), 2);
}

// 削除テスト
{
    // 削除
    service_order_payment_delete([
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果
    $order_payments = model('select_order_payments', [
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    test_equals('delete order_payment', count($order_payments), 0);

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

    test_equals('delete order_payment log', count($logs), 1);
    test_equals('delete order_payment log model', $logs[0]['model'], 'order_payments');
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_payments;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'plugins/order/app/services/order_payment.php',
    ]);
}
