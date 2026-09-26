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
import('plugins/order/app/models/order_shippings.php');
import('plugins/order/app/models/order_shipping_items.php');
import('plugins/order/app/models/order_records.php');
import('plugins/order/app/models/order_record_items.php');
import('plugins/order/app/services/order_shipping.php');

// リクエスト情報を用意
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'freo/2';

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_shippings;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_shipping_items;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_records;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_record_items;');

// トランザクションを開始
db_transaction();

// 前提データ（発送のもとになる注文記録と注文明細）
model('insert_order_records', [
    'values' => [
        'provide'       => 'delivery',
        'payment_id'    => 1,
        'payment_fee'   => 0,
        'delivery_cost' => 500,
        'discount'      => 0,
        'status'        => 'order',
    ],
]);
$order_records = model('select_order_records', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$record_id = intval($order_records[0]['id']);

model('insert_order_record_items', [
    'values' => [
        'record_id'     => $record_id,
        'spec_id'       => 1,
        'selling_price' => 1500,
        'quantity'      => 3,
    ],
]);
$order_record_items = model('select_order_record_items', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$record_item_id = intval($order_record_items[0]['id']);

// 正常データ
$data_order_shipping = [
    'record_id'     => $record_id,
    'delivery_id'   => 1,
    'delivery_cost' => 500,
    'status'        => 'preparing',
];

// 正常登録テスト
{
    // データ
    $test_order_shipping = $data_order_shipping;

    // 登録（発送明細も同時に登録する）
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);
    $warnings            = model('validate_order_shippings', $test_order_shipping);
    if (empty($warnings)) {
        service_order_shipping_insert([
            'values' => $test_order_shipping,
        ], [
            'items' => [
                [
                    'record_item_id' => $record_item_id,
                    'quantity'       => 1,
                ],
            ],
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $order_shippings = model('select_order_shippings', [
        'select'   => 'record_id, status',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    $inserted_data = array_shift($order_shippings);
    $test_data     = [
        $inserted_data,
    ];
    test_array_subset('insert order_shipping', $test_data, [
        'record_id' => $test_order_shipping['record_id'],
        'status'    => $test_order_shipping['status'],
    ]);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'order_by' => 'id DESC',
    ]);

    test_equals('insert order_shipping log', count($logs), 1);
    test_equals('insert order_shipping log model', $logs[0]['model'], 'order_shippings');
    test_equals('insert order_shipping log exec', $logs[0]['exec'], 'insert');
    test_equals('insert order_shipping log ip', $logs[0]['ip'], '127.0.0.1');
}

// 登録した発送記録のIDを取得
$order_shippings = model('select_order_shippings', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($order_shippings[0]['id']);

// 登録と同時の明細登録テスト
{
    // 結果（サービス経由でも明細が登録されること）
    $order_shipping_items = model('select_order_shipping_items', [
        'where' => 'shipping_id = ' . $inserted_id,
    ]);

    test_equals('insert order_shipping (items)', count($order_shipping_items), 1);
    test_equals('insert order_shipping (items quantity)', intval($order_shipping_items[0]['quantity']), 1);
}

// 更新テスト
{
    // 更新
    service_order_shipping_update([
        'set'   => [
            'status' => 'completed',
        ],
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果
    $order_shippings = model('select_order_shippings', [
        'select' => 'status',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update order_shipping', $order_shippings[0]['status'], 'completed');

    // 結果（明細を渡していないので、明細はそのまま残ること）
    $order_shipping_items = model('select_order_shipping_items', [
        'where' => 'shipping_id = ' . $inserted_id,
    ]);

    test_equals('update order_shipping (items keep)', count($order_shipping_items), 1);

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

    test_equals('update order_shipping log', count($logs), 1);
    test_equals('update order_shipping log model', $logs[0]['model'], 'order_shippings');
}

// 更新と同時の明細登録テスト
{
    // 更新（明細を渡すときは、注文記録のIDも一緒に渡す）
    service_order_shipping_update([
        'set'   => [
            'status' => 'preparing',
        ],
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ], [
        'id'        => $inserted_id,
        'record_id' => $record_id,
        'items'     => [
            [
                'record_item_id' => $record_item_id,
                'quantity'       => 2,
            ],
        ],
    ]);

    // 結果（明細が入れ替わること）
    $order_shipping_items = model('select_order_shipping_items', [
        'where' => 'shipping_id = ' . $inserted_id,
    ]);

    test_equals('update order_shipping (items)', count($order_shipping_items), 1);
    test_equals('update order_shipping (items quantity)', intval($order_shipping_items[0]['quantity']), 2);
}

// 最終編集日時の確認テスト
{
    // 更新（編集開始後に更新されていないので、競合とは判定されない）
    service_order_shipping_update([
        'set'   => [
            'memo' => '発送準備中',
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
    $order_shippings = model('select_order_shippings', [
        'select' => 'memo',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update order_shipping (modified check)', $order_shippings[0]['memo'], '発送準備中');
}

// 操作ログの重複抑止テスト
{
    // 結果（service_log_record() は同じ model と exec の組み合わせを1リクエストにつき1回しか記録しない）
    $logs = model('select_logs', [
        'order_by' => 'id DESC',
    ]);

    test_equals('record order_shipping log once', count($logs), 2);
}

// 削除テスト
{
    // 削除（管理画面と同じく、関連する明細も消す）
    service_order_shipping_delete([
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
    $order_shippings = model('select_order_shippings', [
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    test_equals('delete order_shipping', count($order_shippings), 0);

    // 結果（ひも付く明細も消えること）
    $order_shipping_items = model('select_order_shipping_items', [
        'where' => 'shipping_id = ' . $inserted_id,
    ]);

    test_equals('delete order_shipping (associate)', count($order_shipping_items), 0);

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

    test_equals('delete order_shipping log', count($logs), 1);
    test_equals('delete order_shipping log model', $logs[0]['model'], 'order_shippings');
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_shippings;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_shipping_items;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_records;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_record_items;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'plugins/order/app/services/order_shipping.php',
    ]);
}
