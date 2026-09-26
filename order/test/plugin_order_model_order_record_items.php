<?php

// 設定ファイルを読み込み
import('app/config.php');
import('plugins/order/config.php');

// コードカバレッジの記録を開始
if (!isset($_GET['_test'])) {
    service('coverage.php');
    service_coverage_start();
}

// ライブラリを読み込み（プラグインのモデルは自動で読み込まれない）
import('plugins/order/app/models/order_record_items.php');

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_record_items;');

// 正常データ（値は注文時に set_item_order_records() が組み立てる。このモデルには検証も正規化も無い）
$data_order_record_item = [
    'record_id'     => 1,
    'spec_id'       => 1,
    'name'          => '',
    'selling_price' => 1500,
    'cost_price'    => 650,
    'quantity'      => 3,
];

// トランザクションを開始
db_transaction();

// 初期値テスト
{
    // 確認
    $default_order_record_item = model('default_order_record_items');

    // 結果
    test_equals('default order_record_item id', $default_order_record_item['id'], null);
    test_equals('default order_record_item record_id', $default_order_record_item['record_id'], 0);
    test_equals('default order_record_item spec_id', $default_order_record_item['spec_id'], null);
    test_equals('default order_record_item name', $default_order_record_item['name'], null);
    test_equals('default order_record_item selling_price', $default_order_record_item['selling_price'], 0);
    test_equals('default order_record_item cost_price', $default_order_record_item['cost_price'], null);
    test_equals('default order_record_item quantity', $default_order_record_item['quantity'], 0);
    test_equals('default order_record_item deleted', $default_order_record_item['deleted'], null);
    test_regexp('default order_record_item created', $default_order_record_item['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 登録テスト
{
    // 登録
    model('insert_order_record_items', [
        'values' => $data_order_record_item,
    ]);

    // 結果
    $order_record_items = model('select_order_record_items', [
        'select'   => 'record_id, spec_id, selling_price, cost_price, quantity',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    $inserted_data = array_shift($order_record_items);
    $test_data     = [
        $inserted_data,
    ];
    test_array_subset('insert order_record_item', $test_data, [
        'record_id'     => $data_order_record_item['record_id'],
        'spec_id'       => $data_order_record_item['spec_id'],
        'selling_price' => $data_order_record_item['selling_price'],
        'cost_price'    => $data_order_record_item['cost_price'],
        'quantity'      => $data_order_record_item['quantity'],
    ]);
}

// 登録した明細のIDを取得
$order_record_items = model('select_order_record_items', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($order_record_items[0]['id']);

// 登録日時・更新日時テスト
{
    // 確認
    $order_record_items = db_select([
        'select' => 'created, modified, name',
        'from'   => DATABASE_PREFIX . 'order_record_items',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（登録時に自動で入ること）
    test_regexp('insert order_record_item created', $order_record_items[0]['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
    test_regexp('insert order_record_item modified', $order_record_items[0]['modified'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');

    // 結果（空文字は NULL になること。規格を指定した明細では項目名を使わない）
    test_equals('insert order_record_item name', $order_record_items[0]['name'], null);
}

// 更新テスト
{
    // 更新
    model('update_order_record_items', [
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
    $order_record_items = model('select_order_record_items', [
        'select' => 'quantity',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update order_record_item', intval($order_record_items[0]['quantity']), 5);
}

// 削除テスト
{
    // 削除
    model('delete_order_record_items', [
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（取得対象からは外れること）
    $order_record_items = model('select_order_record_items', [
        'where' => 'id = ' . $inserted_id,
    ]);

    test_equals('delete order_record_item', count($order_record_items), 0);

    // 結果（レコード自体は残り、削除日時が入ること）
    $order_record_items = db_select([
        'select' => 'deleted',
        'from'   => DATABASE_PREFIX . 'order_record_items',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    test_equals('delete order_record_item (record)', count($order_record_items), 1);
    test_not_equals('delete order_record_item (deleted)', $order_record_items[0]['deleted'], null);
}

// 物理削除テスト
{
    // 登録
    $test_order_record_item = $data_order_record_item;
    $test_order_record_item['record_id'] = 2;

    model('insert_order_record_items', [
        'values' => $test_order_record_item,
    ]);

    // 削除
    model('delete_order_record_items', [
        'where' => 'record_id = 2',
    ], [
        'softdelete' => false,
    ]);

    // 結果（レコード自体が消えること）
    $order_record_items = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'order_record_items',
        'where'  => 'record_id = 2',
    ]);

    test_equals('delete order_record_item (physical)', count($order_record_items), 0);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_record_items;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'plugins/order/app/models/order_record_items.php',
    ]);
}
