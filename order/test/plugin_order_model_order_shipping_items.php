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
import('plugins/order/app/models/order_shipping_items.php');

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_shipping_items;');

// 正常データ（値は発送時に set_item_order_shippings() が組み立てる。このモデルには検証も正規化も無い）
$data_order_shipping_item = [
    'shipping_id'    => 1,
    'record_item_id' => 1,
    'quantity'       => 2,
];

// トランザクションを開始
db_transaction();

// 初期値テスト
{
    // 確認
    $default_order_shipping_item = model('default_order_shipping_items');

    // 結果
    test_equals('default order_shipping_item id', $default_order_shipping_item['id'], null);
    test_equals('default order_shipping_item shipping_id', $default_order_shipping_item['shipping_id'], 0);
    test_equals('default order_shipping_item record_item_id', $default_order_shipping_item['record_item_id'], 0);
    test_equals('default order_shipping_item quantity', $default_order_shipping_item['quantity'], 0);
    test_equals('default order_shipping_item deleted', $default_order_shipping_item['deleted'], null);
    test_regexp('default order_shipping_item created', $default_order_shipping_item['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 登録テスト
{
    // 登録
    model('insert_order_shipping_items', [
        'values' => $data_order_shipping_item,
    ]);

    // 結果
    $order_shipping_items = model('select_order_shipping_items', [
        'select'   => 'shipping_id, record_item_id, quantity',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    $inserted_data = array_shift($order_shipping_items);
    $test_data     = [
        $inserted_data,
    ];
    test_array_subset('insert order_shipping_item', $test_data, [
        'shipping_id'    => $data_order_shipping_item['shipping_id'],
        'record_item_id' => $data_order_shipping_item['record_item_id'],
        'quantity'       => $data_order_shipping_item['quantity'],
    ]);
}

// 登録した明細のIDを取得
$order_shipping_items = model('select_order_shipping_items', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($order_shipping_items[0]['id']);

// 登録日時・更新日時テスト
{
    // 確認
    $order_shipping_items = db_select([
        'select' => 'created, modified',
        'from'   => DATABASE_PREFIX . 'order_shipping_items',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（登録時に自動で入ること）
    test_regexp('insert order_shipping_item created', $order_shipping_items[0]['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
    test_regexp('insert order_shipping_item modified', $order_shipping_items[0]['modified'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 集計テスト
{
    // データ（同じ注文明細を、別の発送記録でもう1件発送する）
    $test_order_shipping_item = $data_order_shipping_item;
    $test_order_shipping_item['shipping_id'] = 2;
    $test_order_shipping_item['quantity']    = 3;

    model('insert_order_shipping_items', [
        'values' => $test_order_shipping_item,
    ]);

    // 確認（発送済み数の合計。画面の「発送状況」と set_item_order_shippings() が同じ形で集計する）
    $shipped_items = model('select_order_shipping_items', [
        'select'   => 'record_item_id, SUM(quantity) AS quantity',
        'where'    => 'record_item_id = 1',
        'group_by' => 'record_item_id',
    ]);

    // 結果
    test_equals('select order_shipping_item (sum)', count($shipped_items), 1);
    test_equals('select order_shipping_item (sum quantity)', intval($shipped_items[0]['quantity']), 5);
}

// 更新テスト
{
    // 更新
    model('update_order_shipping_items', [
        'set'   => [
            'quantity' => 4,
        ],
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果
    $order_shipping_items = model('select_order_shipping_items', [
        'select' => 'quantity',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update order_shipping_item', intval($order_shipping_items[0]['quantity']), 4);
}

// 削除テスト
{
    // 削除
    model('delete_order_shipping_items', [
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（取得対象からは外れること）
    $order_shipping_items = model('select_order_shipping_items', [
        'where' => 'id = ' . $inserted_id,
    ]);

    test_equals('delete order_shipping_item', count($order_shipping_items), 0);

    // 結果（レコード自体は残り、削除日時が入ること）
    $order_shipping_items = db_select([
        'select' => 'deleted',
        'from'   => DATABASE_PREFIX . 'order_shipping_items',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    test_equals('delete order_shipping_item (record)', count($order_shipping_items), 1);
    test_not_equals('delete order_shipping_item (deleted)', $order_shipping_items[0]['deleted'], null);
}

// 物理削除テスト
{
    // 削除（集計テストで登録した2件目）
    model('delete_order_shipping_items', [
        'where' => 'shipping_id = 2',
    ], [
        'softdelete' => false,
    ]);

    // 結果（レコード自体が消えること）
    $order_shipping_items = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'order_shipping_items',
        'where'  => 'shipping_id = 2',
    ]);

    test_equals('delete order_shipping_item (physical)', count($order_shipping_items), 0);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_shipping_items;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'plugins/order/app/models/order_shipping_items.php',
    ]);
}
