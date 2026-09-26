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
import('plugins/order/app/models/order_addresses.php');
import('plugins/order/app/services/order_address.php');

// リクエスト情報を用意
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'freo/2';

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_addresses;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// 正常データ
$data_order_address = [
    'user_id'    => 1,
    'name_01'    => 'テスト',
    'name_02'    => '太郎',
    'zipcode'    => '100-0001',
    'prefecture' => '東京都',
    'address_01' => '千代田区千代田1-1',
    'telephone'  => '0312345678',
];

// トランザクションを開始
db_transaction();

// 正常登録テスト
{
    // データ
    $test_order_address = $data_order_address;

    // 登録
    $test_order_address = model('normalize_order_addresses', $test_order_address);
    $warnings           = model('validate_order_addresses', $test_order_address);
    if (empty($warnings)) {
        service_order_address_insert([
            'values' => $test_order_address,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $order_addresses = model('select_order_addresses', [
        'select'   => 'user_id, name_01, zipcode',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    $inserted_data = array_shift($order_addresses);
    $test_data     = [
        $inserted_data,
    ];
    test_array_subset('insert order_address', $test_data, [
        'user_id' => $test_order_address['user_id'],
        'name_01' => $test_order_address['name_01'],
        'zipcode' => $test_order_address['zipcode'],
    ]);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'order_by' => 'id DESC',
    ]);

    test_equals('insert order_address log', count($logs), 1);
    test_equals('insert order_address log model', $logs[0]['model'], 'order_addresses');
    test_equals('insert order_address log exec', $logs[0]['exec'], 'insert');
    test_equals('insert order_address log ip', $logs[0]['ip'], '127.0.0.1');
}

// 登録した住所のIDを取得
$order_addresses = model('select_order_addresses', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($order_addresses[0]['id']);

// 更新テスト
{
    // 更新
    service_order_address_update([
        'set'   => [
            'name_02' => '次郎',
        ],
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果
    $order_addresses = model('select_order_addresses', [
        'select' => 'name_02',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update order_address', $order_addresses[0]['name_02'], '次郎');

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

    test_equals('update order_address log', count($logs), 1);
    test_equals('update order_address log model', $logs[0]['model'], 'order_addresses');
}

// 最終編集日時の確認テスト
{
    // 更新（編集開始後に更新されていないので、競合とは判定されない）
    service_order_address_update([
        'set'   => [
            'address_01' => '千代田区千代田2-2',
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
    $order_addresses = model('select_order_addresses', [
        'select' => 'address_01',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update order_address (modified check)', $order_addresses[0]['address_01'], '千代田区千代田2-2');
}

// 操作ログの重複抑止テスト
{
    // 結果（service_log_record() は同じ model と exec の組み合わせを1リクエストにつき1回しか記録しない）
    $logs = model('select_logs', [
        'order_by' => 'id DESC',
    ]);

    test_equals('record order_address log once', count($logs), 2);
}

// 削除テスト
{
    // 削除
    service_order_address_delete([
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果
    $order_addresses = model('select_order_addresses', [
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    test_equals('delete order_address', count($order_addresses), 0);

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

    test_equals('delete order_address log', count($logs), 1);
    test_equals('delete order_address log model', $logs[0]['model'], 'order_addresses');
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_addresses;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'plugins/order/app/services/order_address.php',
    ]);
}
