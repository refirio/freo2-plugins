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
import('plugins/order/app/models/order_specs.php');
import('plugins/order/app/models/order_products.php');
import('plugins/order/app/services/order_spec.php');

// リクエスト情報を用意
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'freo/2';

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_specs;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_products;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// 正常データ
$data_order_spec = [
    'entry_id'      => 1,
    'code'          => 'testspec1',
    'enabled'       => 1,
    'name'          => 'テスト規格1',
    'provide'       => 'delivery',
    'selling_price' => 1500,
    'sort'          => 1,
];
$data_order_specs = [];
foreach ([1, 2, 3] as $index) {
    $data_order_specs[] = [
        'entry_id'      => 1,
        'code'          => 'sort' . $index,
        'enabled'       => 1,
        'name'          => '規格' . $index,
        'provide'       => 'delivery',
        'selling_price' => 1000,
        'sort'          => $index,
    ];
}

// トランザクションを開始
db_transaction();

// 正常登録テスト
{
    // データ
    $test_order_spec = $data_order_spec;

    // 登録
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);
    if (empty($warnings)) {
        service_order_spec_insert([
            'values' => $test_order_spec,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $order_specs = model('select_order_specs', [
        'select'   => 'code, name, provide',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    $inserted_data = array_shift($order_specs);
    $test_data     = [
        $inserted_data,
    ];
    test_array_subset('insert order_spec', $test_data, [
        'code'    => $test_order_spec['code'],
        'name'    => $test_order_spec['name'],
        'provide' => $test_order_spec['provide'],
    ]);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'order_by' => 'id DESC',
    ]);

    test_equals('insert order_spec log', count($logs), 1);
    test_equals('insert order_spec log model', $logs[0]['model'], 'order_specs');
    test_equals('insert order_spec log exec', $logs[0]['exec'], 'insert');
    test_equals('insert order_spec log ip', $logs[0]['ip'], '127.0.0.1');
}

// 登録した規格のIDを取得
$order_specs = model('select_order_specs', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($order_specs[0]['id']);

// 更新テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['id']   = $inserted_id;
    $test_order_spec['name'] = 'テスト規格2';

    // 更新
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);
    if (empty($warnings)) {
        service_order_spec_update([
            'set'   => [
                'name' => $test_order_spec['name'],
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
    $order_specs = model('select_order_specs', [
        'select' => 'name',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update order_spec', $order_specs[0]['name'], 'テスト規格2');

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

    test_equals('update order_spec log', count($logs), 1);
    test_equals('update order_spec log model', $logs[0]['model'], 'order_specs');
}

// 最終編集日時の確認テスト
{
    // 更新（編集開始後に更新されていないので、競合とは判定されない）
    service_order_spec_update([
        'set'   => [
            'name' => 'テスト規格3',
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
    $order_specs = model('select_order_specs', [
        'select' => 'name',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update order_spec (modified check)', $order_specs[0]['name'], 'テスト規格3');
}

// 操作ログの重複抑止テスト
{
    // 結果（service_log_record() は同じ model と exec の組み合わせを1リクエストにつき1回しか記録しない）
    $logs = model('select_logs', [
        'order_by' => 'id DESC',
    ]);

    test_equals('record order_spec log once', count($logs), 2);
}

// 削除テスト
{
    // データ（規格にひも付く製品も登録しておく）
    model('insert_order_products', [
        'values' => [
            'spec_id'  => $inserted_id,
            'stock_id' => 1,
            'quantity' => 1,
            'sort'     => 1,
        ],
    ]);

    // 削除（管理画面と同じく、関連する製品も消す）
    service_order_spec_delete([
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
    $order_specs = model('select_order_specs', [
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    test_equals('delete order_spec', count($order_specs), 0);

    // 結果（ひも付く製品も消えること）
    $order_products = model('select_order_products', [
        'where' => 'spec_id = ' . $inserted_id,
    ]);

    test_equals('delete order_spec (associate)', count($order_products), 0);

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

    test_equals('delete order_spec log', count($logs), 1);
    test_equals('delete order_spec log model', $logs[0]['model'], 'order_specs');
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_specs;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_products;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// トランザクションを開始
db_transaction();

// 並び順の一括変更テスト
{
    // 登録
    foreach ($data_order_specs as $order_spec) {
        $order_spec = model('normalize_order_specs', $order_spec);
        $warnings   = model('validate_order_specs', $order_spec);
        if (empty($warnings)) {
            service_order_spec_insert([
                'values' => $order_spec,
            ]);
        } else {
            debug($warnings);
        }
    }

    // 結果
    $order_specs = model('select_order_specs', [
        'select'   => 'id, sort',
        'order_by' => 'id',
    ]);

    test_equals('sort order_specs (before)', array_map('intval', array_column($order_specs, 'sort')), [1, 2, 3]);

    // 並び順を更新（IDは決め打ちにせず、登録済みのものを使う）
    $ids = array_column($order_specs, 'id');

    service_order_spec_sort([
        $ids[0] => 3,
        $ids[1] => 2,
        $ids[2] => 1,
    ]);

    // 結果
    $order_specs = model('select_order_specs', [
        'select'   => 'sort',
        'order_by' => 'id',
    ]);

    test_equals('sort order_specs (after)', array_map('intval', array_column($order_specs, 'sort')), [3, 2, 1]);

    // 結果（不正な値は無視されること）
    service_order_spec_sort([
        $ids[0] => 'あ', // 並び順が数字でない
        'あ'    => 1,    // IDが不正
    ]);

    $order_specs = model('select_order_specs', [
        'select'   => 'sort',
        'order_by' => 'id',
    ]);

    test_equals('sort order_specs (invalid)', array_map('intval', array_column($order_specs, 'sort')), [3, 2, 1]);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_specs;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_products;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'plugins/order/app/services/order_spec.php',
    ]);
}
