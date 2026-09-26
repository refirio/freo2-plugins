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
import('plugins/order/app/models/order_records.php');
import('plugins/order/app/models/order_record_items.php');
import('plugins/order/app/models/order_specs.php');
import('plugins/order/app/models/order_products.php');
import('plugins/order/app/models/order_stocks.php');
import('plugins/order/app/services/order_record.php');

// リクエスト情報を用意
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'freo/2';

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_records;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_record_items;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// 正常データ（宛先の検証を通る必要がないダウンロード販売にする）
$data_order_record = [
    'provide'       => 'download',
    'payment_id'    => 1,
    'payment_fee'   => 0,
    'delivery_cost' => 0,
    'discount'      => 0,
    'status'        => 'order',
    'email'         => 'testorder@example.com',
];

// トランザクションを開始
db_transaction();

// 正常登録テスト
{
    // データ
    $test_order_record = $data_order_record;

    // 登録（明細も同時に登録する。規格を指定しない直接入力の明細）
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);
    if (empty($warnings)) {
        service_order_record_insert([
            'values' => $test_order_record,
        ], [
            'items' => [
                [
                    'order_spec_id' => '',
                    'name'          => 'テスト商品',
                    'selling_price' => 1200,
                    'quantity'      => 2,
                ],
            ],
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $order_records = model('select_order_records', [
        'select'   => 'provide, status, email',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    $inserted_data = array_shift($order_records);
    $test_data     = [
        $inserted_data,
    ];
    test_array_subset('insert order_record', $test_data, [
        'provide' => $test_order_record['provide'],
        'status'  => $test_order_record['status'],
        'email'   => $test_order_record['email'],
    ]);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'order_by' => 'id DESC',
    ]);

    test_equals('insert order_record log', count($logs), 1);
    test_equals('insert order_record log model', $logs[0]['model'], 'order_records');
    test_equals('insert order_record log exec', $logs[0]['exec'], 'insert');
    test_equals('insert order_record log ip', $logs[0]['ip'], '127.0.0.1');
}

// 登録した注文記録のIDを取得
$order_records = model('select_order_records', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($order_records[0]['id']);

// 登録と同時の明細登録テスト
{
    // 結果（サービス経由でも明細が登録されること）
    $order_record_items = model('select_order_record_items', [
        'where' => 'record_id = ' . $inserted_id,
    ]);

    test_equals('insert order_record (items)', count($order_record_items), 1);
    test_equals('insert order_record (items name)', $order_record_items[0]['name'], 'テスト商品');
}

// 更新テスト
{
    // 更新
    service_order_record_update([
        'set'   => [
            'status' => 'paid',
        ],
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果
    $order_records = model('select_order_records', [
        'select' => 'status',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update order_record', $order_records[0]['status'], 'paid');

    // 結果（明細を渡していないので、明細はそのまま残ること）
    $order_record_items = model('select_order_record_items', [
        'where' => 'record_id = ' . $inserted_id,
    ]);

    test_equals('update order_record (items keep)', count($order_record_items), 1);

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

    test_equals('update order_record log', count($logs), 1);
    test_equals('update order_record log model', $logs[0]['model'], 'order_records');
}

// 更新と同時の明細登録テスト
{
    // 更新
    service_order_record_update([
        'set'   => [
            'status' => 'completed',
        ],
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ], [
        'id'    => $inserted_id,
        'items' => [
            [
                'order_spec_id' => '',
                'name'          => 'テスト商品（変更）',
                'selling_price' => 1500,
                'quantity'      => 1,
            ],
        ],
    ]);

    // 結果（明細が入れ替わること）
    $order_record_items = model('select_order_record_items', [
        'where' => 'record_id = ' . $inserted_id,
    ]);

    test_equals('update order_record (items)', count($order_record_items), 1);
    test_equals('update order_record (items name)', $order_record_items[0]['name'], 'テスト商品（変更）');
}

// 最終編集日時の確認テスト
{
    // 更新（編集開始後に更新されていないので、競合とは判定されない）
    service_order_record_update([
        'set'   => [
            'memo' => '確認済み',
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
    $order_records = model('select_order_records', [
        'select' => 'memo',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update order_record (modified check)', $order_records[0]['memo'], '確認済み');
}

// 操作ログの重複抑止テスト
{
    // 結果（service_log_record() は同じ model と exec の組み合わせを1リクエストにつき1回しか記録しない）
    $logs = model('select_logs', [
        'order_by' => 'id DESC',
    ]);

    test_equals('record order_record log once', count($logs), 2);
}

// 削除テスト
{
    // 削除（管理画面と同じく、関連する明細も消す）
    service_order_record_delete([
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ], [
        'associate' => true,
    ]);

    // 結果
    $order_records = model('select_order_records', [
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    test_equals('delete order_record', count($order_records), 0);

    // 結果（ひも付く明細も消えること）
    $order_record_items = model('select_order_record_items', [
        'where' => 'record_id = ' . $inserted_id,
    ]);

    test_equals('delete order_record (associate)', count($order_record_items), 0);

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

    test_equals('delete order_record log', count($logs), 1);
    test_equals('delete order_record log model', $logs[0]['model'], 'order_records');
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_records;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_record_items;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'plugins/order/app/services/order_record.php',
    ]);
}
