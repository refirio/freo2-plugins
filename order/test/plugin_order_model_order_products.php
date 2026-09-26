<?php

// 設定ファイルを読み込み（検証が選択肢を参照するので、プラグインの設定も読み込む）
import('app/config.php');
import('plugins/order/config.php');

// コードカバレッジの記録を開始
if (!isset($_GET['_test'])) {
    service('coverage.php');
    service_coverage_start();
}

// ライブラリを読み込み（プラグインのモデルは自動で読み込まれない）
import('plugins/order/app/models/order_products.php');
import('plugins/order/app/models/order_stocks.php');

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_products;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_stocks;');

// トランザクションを開始
db_transaction();

// 前提データ（製品がひも付ける在庫）
model('insert_order_stocks', [
    'values' => [
        'code'       => 'testproductstock',
        'name'       => 'テスト在庫',
        'text'       => '青、S',
        'kind'       => 'analog',
        'quantity'   => 50,
        'cost_price' => 300,
    ],
]);
$order_stocks = model('select_order_stocks', [
    'select' => 'id',
    'where'  => 'code = \'testproductstock\'',
]);
$stock_id = intval($order_stocks[0]['id']);

// 正常データ（規格は別のテストで確認するので、ひも付け先のIDだけを持たせる）
$data_order_product = [
    'spec_id'  => 1,
    'stock_id' => $stock_id,
    'quantity' => 2,
    'memo'     => '',
    'sort'     => 1,
];

// 初期値テスト
{
    // 確認
    $default_order_product = model('default_order_products');

    // 結果
    test_equals('default order_product id', $default_order_product['id'], null);
    test_equals('default order_product spec_id', $default_order_product['spec_id'], 0);
    test_equals('default order_product stock_id', $default_order_product['stock_id'], 0);
    test_equals('default order_product quantity', $default_order_product['quantity'], 0);
    test_equals('default order_product memo', $default_order_product['memo'], null);
    test_equals('default order_product sort', $default_order_product['sort'], 0);
    test_equals('default order_product deleted', $default_order_product['deleted'], null);
    test_regexp('default order_product created', $default_order_product['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 正常登録テスト
{
    // データ
    $test_order_product = $data_order_product;

    // 登録
    $test_order_product = model('normalize_order_products', $test_order_product);
    $warnings           = model('validate_order_products', $test_order_product);

    // 結果（正常データでは警告が出ないこと）
    test_equals('validate order_product', count($warnings), 0);

    if (empty($warnings)) {
        model('insert_order_products', [
            'values' => $test_order_product,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $order_products = model('select_order_products', [
        'select'   => 'spec_id, stock_id, quantity, sort',
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
        'sort'     => $test_order_product['sort'],
    ]);
}

// 登録した製品のIDを取得
$order_products = model('select_order_products', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($order_products[0]['id']);

// 関連データの取得テスト
{
    // 取得
    $order_products = model('select_order_products', [
        'where'    => 'order_products.id = ' . $inserted_id,
        'order_by' => 'order_products.sort, order_products.id',
    ], [
        'associate' => true,
    ]);

    // 結果（在庫の内容が付いて返ること。注文時の在庫の引き当てと原価の集計に使う）
    test_equals('select associate order_product', count($order_products), 1);
    test_equals('select associate order_product order_stock_code', $order_products[0]['order_stock_code'], 'testproductstock');
    test_equals('select associate order_product order_stock_name', $order_products[0]['order_stock_name'], 'テスト在庫');
    test_equals('select associate order_product order_stock_kind', $order_products[0]['order_stock_kind'], 'analog');
    test_equals('select associate order_product order_stock_quantity', intval($order_products[0]['order_stock_quantity']), 50);
    test_equals('select associate order_product order_stock_cost_price', intval($order_products[0]['order_stock_cost_price']), 300);
}

// 関連データの取得（在庫が見つからない）テスト
{
    // データ（存在しない在庫をひも付けた製品）
    model('insert_order_products', [
        'values' => [
            'spec_id'  => 3,
            'stock_id' => 99999,
            'quantity' => 1,
            'sort'     => 1,
        ],
    ]);

    // 取得
    $order_products = model('select_order_products', [
        'where' => 'order_products.spec_id = 3',
    ], [
        'associate' => true,
    ]);

    // 結果（LEFT JOIN なので、在庫が見つからなくても製品自体は取得できる）
    test_equals('select associate order_product (no stock)', count($order_products), 1);
    test_equals('select associate order_product (no stock code)', $order_products[0]['order_stock_code'], null);
    test_equals('select associate order_product (no stock quantity)', $order_products[0]['order_stock_quantity'], null);
}

// 数の正規化（全角数字）テスト
{
    // データ
    $test_order_product = $data_order_product;
    $test_order_product['quantity'] = '１２';

    // 確認
    $test_order_product = model('normalize_order_products', $test_order_product);

    // 結果
    test_equals('normalize order_product quantity', $test_order_product['quantity'], '12');
}

// 並び順の正規化（全角数字）テスト
{
    // データ
    $test_order_product = $data_order_product;
    $test_order_product['sort'] = '３４';

    // 確認
    $test_order_product = model('normalize_order_products', $test_order_product);

    // 結果
    test_equals('normalize order_product sort', $test_order_product['sort'], '34');
}

// 並び順の正規化（自動採番）テスト
{
    // データ（並び順を持たない管理画面からの入力を想定）
    $test_order_product = $data_order_product;
    $test_order_product['id'] = '';
    unset($test_order_product['sort']);

    // 確認
    $test_order_product = model('normalize_order_products', $test_order_product);

    // 結果（登録済みの最大値 1 に 1 を加えた値になること）
    test_equals('normalize order_product sort auto', $test_order_product['sort'], 2);
}

// 規格の必須テスト
{
    // データ（製品をひも付ける規格。画面では隠し項目で渡される）
    $test_order_product = $data_order_product;
    $test_order_product['spec_id'] = '';

    // 確認
    $test_order_product = model('normalize_order_products', $test_order_product);
    $warnings           = model('validate_order_products', $test_order_product);

    // 結果
    test_equals('validate required order_product spec_id', count($warnings), 1);
}

// 在庫の必須テスト
{
    // データ
    $test_order_product = $data_order_product;
    $test_order_product['stock_id'] = '';

    // 確認
    $test_order_product = model('normalize_order_products', $test_order_product);
    $warnings           = model('validate_order_products', $test_order_product);

    // 結果
    test_equals('validate required order_product stock_id', count($warnings), 1);
}

// 数の必須テスト
{
    // データ
    $test_order_product = $data_order_product;
    $test_order_product['quantity'] = '';

    // 確認
    $test_order_product = model('normalize_order_products', $test_order_product);
    $warnings           = model('validate_order_products', $test_order_product);

    // 結果
    test_equals('validate required order_product quantity', count($warnings), 1);
}

// 数の書式テスト
{
    // データ
    $test_order_product = $data_order_product;
    $test_order_product['quantity'] = 'あ';

    // 確認
    $test_order_product = model('normalize_order_products', $test_order_product);
    $warnings           = model('validate_order_products', $test_order_product);

    // 結果
    test_equals('validate numeric order_product quantity', count($warnings), 1);
}

// 数の桁数（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_order_product = $data_order_product;
    $test_order_product['quantity'] = str_repeat('1', 10);

    // 確認
    $test_order_product = model('normalize_order_products', $test_order_product);
    $warnings           = model('validate_order_products', $test_order_product);

    // 結果
    test_equals('validate max_length order_product quantity (boundary)', count($warnings), 0);
}

// 数の桁数テスト
{
    // データ
    $test_order_product = $data_order_product;
    $test_order_product['quantity'] = str_repeat('1', 11);

    // 確認
    $test_order_product = model('normalize_order_products', $test_order_product);
    $warnings           = model('validate_order_products', $test_order_product);

    // 結果
    test_equals('validate max_length order_product quantity', count($warnings), 1);
}

// 店舗用メモの未入力テスト
{
    // データ（店舗用メモは任意項目のため未入力でも警告は出ない）
    $test_order_product = $data_order_product;
    $test_order_product['memo'] = '';

    // 確認
    $test_order_product = model('normalize_order_products', $test_order_product);
    $warnings           = model('validate_order_products', $test_order_product);

    // 結果
    test_equals('validate empty order_product memo', count($warnings), 0);
}

// 店舗用メモの長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_order_product = $data_order_product;
    $test_order_product['memo'] = str_repeat('あ', 5000);

    // 確認
    $test_order_product = model('normalize_order_products', $test_order_product);
    $warnings           = model('validate_order_products', $test_order_product);

    // 結果
    test_equals('validate max_length order_product memo (boundary)', count($warnings), 0);
}

// 店舗用メモの長さテスト
{
    // データ
    $test_order_product = $data_order_product;
    $test_order_product['memo'] = str_repeat('あ', 5001);

    // 確認
    $test_order_product = model('normalize_order_products', $test_order_product);
    $warnings           = model('validate_order_products', $test_order_product);

    // 結果
    test_equals('validate max_length order_product memo', count($warnings), 1);
}

// 並び順の必須テスト
{
    // データ
    $test_order_product = $data_order_product;
    $test_order_product['sort'] = '';

    // 確認
    $test_order_product = model('normalize_order_products', $test_order_product);
    $warnings           = model('validate_order_products', $test_order_product);

    // 結果
    test_equals('validate required order_product sort', count($warnings), 1);
}

// 並び順の書式テスト
{
    // データ
    $test_order_product = $data_order_product;
    $test_order_product['sort'] = 'あ';

    // 確認
    $test_order_product = model('normalize_order_products', $test_order_product);
    $warnings           = model('validate_order_products', $test_order_product);

    // 結果
    test_equals('validate numeric order_product sort', count($warnings), 1);
}

// 並び順の桁数（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_order_product = $data_order_product;
    $test_order_product['sort'] = str_repeat('1', 5);

    // 確認
    $test_order_product = model('normalize_order_products', $test_order_product);
    $warnings           = model('validate_order_products', $test_order_product);

    // 結果
    test_equals('validate max_length order_product sort (boundary)', count($warnings), 0);
}

// 並び順の桁数テスト
{
    // データ
    $test_order_product = $data_order_product;
    $test_order_product['sort'] = str_repeat('1', 6);

    // 確認
    $test_order_product = model('normalize_order_products', $test_order_product);
    $warnings           = model('validate_order_products', $test_order_product);

    // 結果
    test_equals('validate max_length order_product sort', count($warnings), 1);
}

// 重複チェックのオプションテスト
{
    // データ（同じ規格と在庫の組み合わせをもう1件。製品にはコードが無く、重複チェック自体が無い）
    $test_order_product = $data_order_product;

    // 確認
    $test_order_product = model('normalize_order_products', $test_order_product);
    $warnings           = model('validate_order_products', $test_order_product);

    // 結果（duplicate オプションを受け取るが、重複チェックは無いので警告は出ない）
    test_equals('validate duplicate order_product', count($warnings), 0);
}

// 更新テスト
{
    // データ
    $test_order_product = $data_order_product;
    $test_order_product['id']       = $inserted_id;
    $test_order_product['quantity'] = 5;
    $test_order_product['memo']     = 'セット販売用';

    // 更新
    $test_order_product = model('normalize_order_products', $test_order_product);
    $warnings           = model('validate_order_products', $test_order_product);
    if (empty($warnings)) {
        $set = $test_order_product;
        unset($set['id']);

        model('update_order_products', [
            'set'   => $set,
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
    $order_products = model('select_order_products', [
        'select' => 'quantity, memo',
        'where'  => 'id = ' . $inserted_id,
    ]);

    $test_data = [
        $order_products[0],
    ];
    test_array_subset('update order_product', $test_data, [
        'quantity' => $test_order_product['quantity'],
        'memo'     => $test_order_product['memo'],
    ]);
}

// 削除テスト
{
    // 削除
    model('delete_order_products', [
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（取得対象からは外れること）
    $order_products = model('select_order_products', [
        'where' => 'id = ' . $inserted_id,
    ]);

    test_equals('delete order_product', count($order_products), 0);

    // 結果（レコード自体は残り、削除日時が入ること。製品にはコードが無いので書き換えも無い）
    $order_products = db_select([
        'select' => 'deleted',
        'from'   => DATABASE_PREFIX . 'order_products',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    test_equals('delete order_product (record)', count($order_products), 1);
    test_not_equals('delete order_product (deleted)', $order_products[0]['deleted'], null);
}

// 物理削除テスト
{
    // データ
    $test_order_product = $data_order_product;
    $test_order_product['spec_id'] = 2;
    $test_order_product['sort']    = 2;

    // 登録
    $test_order_product = model('normalize_order_products', $test_order_product);
    $warnings           = model('validate_order_products', $test_order_product);
    if (empty($warnings)) {
        model('insert_order_products', [
            'values' => $test_order_product,
        ]);
    } else {
        debug($warnings);
    }

    // 削除
    model('delete_order_products', [
        'where' => 'spec_id = 2',
    ], [
        'softdelete' => false,
    ]);

    // 結果（レコード自体が消えること）
    $order_products = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'order_products',
        'where'  => 'spec_id = 2',
    ]);

    test_equals('delete order_product (physical)', count($order_products), 0);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_products;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_stocks;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'plugins/order/app/models/order_products.php',
    ]);
}
