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
import('plugins/order/app/models/order_records.php');
import('plugins/order/app/models/order_record_items.php');
import('plugins/order/app/models/order_specs.php');
import('plugins/order/app/models/order_products.php');
import('plugins/order/app/models/order_stocks.php');

// 在庫を減らすかどうかは設定で変わるので、テスト側で固定する（既定は無効）
$setting_use_stock = isset($GLOBALS['plugin']['order']['setting']['use_stock']) ? $GLOBALS['plugin']['order']['setting']['use_stock'] : null;
$GLOBALS['plugin']['order']['setting']['use_stock'] = false;

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_records;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_record_items;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_specs;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_products;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_stocks;');

// トランザクションを開始
db_transaction();

// 前提データ（在庫2件・規格1件・製品2件。規格1点につき、在庫Aを2個と在庫Bを1個消費する）
model('insert_order_stocks', [
    'values' => [
        'code'       => 'testrecstocka',
        'name'       => 'テスト在庫A',
        'kind'       => 'analog',
        'quantity'   => 100,
        'cost_price' => 300,
    ],
]);
model('insert_order_stocks', [
    'values' => [
        'code'       => 'testrecstockb',
        'name'       => 'テスト在庫B',
        'kind'       => 'analog',
        'quantity'   => '',
        'cost_price' => 50,
    ],
]);
$order_stocks = model('select_order_stocks', [
    'select'   => 'id, code',
    'order_by' => 'id',
]);
$stock_a_id = intval($order_stocks[0]['id']);
$stock_b_id = intval($order_stocks[1]['id']);

model('insert_order_specs', [
    'values' => [
        'entry_id'      => 1,
        'code'          => 'testrecspec',
        'enabled'       => 1,
        'name'          => 'テスト規格',
        'provide'       => 'delivery',
        'selling_price' => 1500,
        'sort'          => 1,
    ],
]);
$order_specs = model('select_order_specs', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$spec_id = intval($order_specs[0]['id']);

foreach ([[$stock_a_id, 2], [$stock_b_id, 1]] as $index => $product) {
    model('insert_order_products', [
        'values' => [
            'spec_id'  => $spec_id,
            'stock_id' => $product[0],
            'quantity' => $product[1],
            'sort'     => $index + 1,
        ],
    ]);
}

// 正常データ（配送での注文）
$data_order_record = [
    'provide'       => 'delivery',
    'payment_id'    => 1,
    'payment_fee'   => 0,
    'delivery_id'   => 1,
    'delivery_cost' => 500,
    'discount'      => 0,
    'shipping_date' => '',
    'status'        => 'order',
    'user_id'       => '',
    'email'         => 'testorder@example.com',
    'name_01'       => 'テスト',
    'name_02'       => '太郎',
    'kana_01'       => 'テスト',
    'kana_02'       => 'タロウ',
    'zipcode'       => '100-0001',
    'prefecture'    => '東京都',
    'address_01'    => '千代田区千代田1-1',
    'address_02'    => '',
    'telephone'     => '0312345678',
    'message'       => '',
    'memo'          => '',
];

// 初期値テスト
{
    // 確認
    $default_order_record = model('default_order_records');

    // 結果
    test_equals('default order_record id', $default_order_record['id'], null);
    test_equals('default order_record provide', $default_order_record['provide'], '');
    test_equals('default order_record payment_id', $default_order_record['payment_id'], 0);
    test_equals('default order_record payment_fee', $default_order_record['payment_fee'], 0);
    test_equals('default order_record delivery_id', $default_order_record['delivery_id'], null);
    test_equals('default order_record delivery_cost', $default_order_record['delivery_cost'], 0);
    test_equals('default order_record discount', $default_order_record['discount'], 0);
    test_equals('default order_record status', $default_order_record['status'], '');
    test_equals('default order_record user_id', $default_order_record['user_id'], null);
    test_equals('default order_record deleted', $default_order_record['deleted'], null);
    test_regexp('default order_record created', $default_order_record['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 正常登録テスト
{
    // データ
    $test_order_record = $data_order_record;

    // 登録
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果（正常データでは警告が出ないこと）
    test_equals('validate order_record', count($warnings), 0);

    if (empty($warnings)) {
        model('insert_order_records', [
            'values' => $test_order_record,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $order_records = model('select_order_records', [
        'select'   => 'provide, status, delivery_cost, email, name_01',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    $inserted_data = array_shift($order_records);
    $test_data     = [
        $inserted_data,
    ];
    test_array_subset('insert order_record', $test_data, [
        'provide'       => $test_order_record['provide'],
        'status'        => $test_order_record['status'],
        'delivery_cost' => $test_order_record['delivery_cost'],
        'email'         => $test_order_record['email'],
        'name_01'       => $test_order_record['name_01'],
    ]);
}

// 登録した注文記録のIDを取得
$order_records = model('select_order_records', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($order_records[0]['id']);

// 表示用データの作成テスト
{
    // 確認
    $view_data = model('view_order_records', ['status' => 'order']);

    // 結果（いまは何も変換せずにそのまま返す）
    test_equals('view order_record', $view_data, ['status' => 'order']);
}

// 配送方法の正規化（未選択）テスト
{
    // データ（配送以外では配送方法を選ばないので、空文字は NULL にする）
    $test_order_record = $data_order_record;
    $test_order_record['delivery_id'] = '';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);

    // 結果
    test_equals('normalize order_record delivery_id', $test_order_record['delivery_id'], null);
}

// 金額の正規化（全角数字）テスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['payment_fee']   = '１００';
    $test_order_record['delivery_cost'] = '５００';
    $test_order_record['discount']      = '５０';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);

    // 結果
    test_equals('normalize order_record payment_fee', $test_order_record['payment_fee'], '100');
    test_equals('normalize order_record delivery_cost', $test_order_record['delivery_cost'], '500');
    test_equals('normalize order_record discount', $test_order_record['discount'], '50');
}

// 郵便番号・電話番号の正規化（全角英数字）テスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['zipcode']       = '１００－０００１';
    $test_order_record['telephone']     = '０３１２３４５６７８';
    $test_order_record['shipping_date'] = '２０２６－０１－０１';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);

    // 結果（英数字と記号が半角になること。金額と違って mb_convert_kana のモードが 'a' なので、ハイフンも半角になる）
    test_equals('normalize order_record zipcode', $test_order_record['zipcode'], '100-0001');
    test_equals('normalize order_record telephone', $test_order_record['telephone'], '0312345678');
    test_equals('normalize order_record shipping_date', $test_order_record['shipping_date'], '2026-01-01');
}

// 提供方法の必須テスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['provide'] = '';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果（提供方法が配送でなくなるので、住所まわりの検証も外れて1件だけになる）
    test_equals('validate required order_record provide', count($warnings), 1);
}

// 提供方法の選択肢テスト
{
    // データ（選択肢に無い値）
    $test_order_record = $data_order_record;
    $test_order_record['provide'] = 'unknown';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate list order_record provide', count($warnings), 1);
}

// 支払方法の必須テスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['payment_id'] = '';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate required order_record payment_id', count($warnings), 1);
}

// 手数料の必須テスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['payment_fee'] = '';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate required order_record payment_fee', count($warnings), 1);
}

// 手数料の書式テスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['payment_fee'] = 'あ';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate numeric order_record payment_fee', count($warnings), 1);
}

// 配送方法の必須（配送）テスト
{
    // データ（提供方法が配送のときだけ必須）
    $test_order_record = $data_order_record;
    $test_order_record['delivery_id'] = '';

    // 確認
    $warnings = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate required order_record delivery_id', count($warnings), 1);
}

// 配送方法の必須（配送・正規化後）テスト
{
    // データ（画面は「正規化 → 検証」の順で通すので、正規化で NULL になった値でも必須にする）
    $test_order_record = $data_order_record;
    $test_order_record['delivery_id'] = '';
    $test_order_record = model('normalize_order_records', $test_order_record);

    // 確認
    test_equals('normalize order_record delivery_id (before validate)', $test_order_record['delivery_id'], null);
    $warnings = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate required order_record delivery_id (normalized)', count($warnings), 1);
    test_array_haskey('validate required order_record delivery_id (normalized) key', $warnings, 'delivery_id');
}

// 配送方法の必須（対面）テスト
{
    // データ（対面なら配送方法は不要。住所も不要になる）
    $test_order_record = $data_order_record;
    $test_order_record['provide']     = 'direct';
    $test_order_record['delivery_id'] = '';

    // 確認
    $warnings = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate required order_record delivery_id (direct)', count($warnings), 0);
}

// 配送方法の必須（ダウンロード・正規化後）テスト
{
    // データ（ダウンロードなら配送方法は不要。正規化で NULL になっても警告は出ない）
    $test_order_record = $data_order_record;
    $test_order_record['provide']     = 'download';
    $test_order_record['delivery_id'] = '';
    $test_order_record = model('normalize_order_records', $test_order_record);

    // 確認
    $warnings = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate required order_record delivery_id (download)', count($warnings), 0);
}

// 送料の必須テスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['delivery_cost'] = '';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate required order_record delivery_cost', count($warnings), 1);
}

// 送料の桁数テスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['delivery_cost'] = str_repeat('1', 11);

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate max_length order_record delivery_cost', count($warnings), 1);
}

// 値引き額の必須テスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['discount'] = '';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate required order_record discount', count($warnings), 1);
}

// 値引き額の書式テスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['discount'] = 'あ';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate numeric order_record discount', count($warnings), 1);
}

// 配送日の未入力テスト
{
    // データ（配送日は任意項目のため未入力でも警告は出ない）
    $test_order_record = $data_order_record;
    $test_order_record['shipping_date'] = '';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate empty order_record shipping_date', count($warnings), 0);
}

// 配送日の書式テスト
{
    // データ（日付として存在しない値）
    $test_order_record = $data_order_record;
    $test_order_record['shipping_date'] = '2026-02-30';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate date order_record shipping_date', count($warnings), 1);
}

// 状況の必須テスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['status'] = '';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate required order_record status', count($warnings), 1);
}

// 状況の選択肢テスト
{
    // データ（選択肢に無い値）
    $test_order_record = $data_order_record;
    $test_order_record['status'] = 'unknown';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate list order_record status', count($warnings), 1);
}

// メールアドレスの書式テスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['email'] = 'test';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate email order_record email', count($warnings), 1);
}

// メールアドレスの検証（対面）テスト
{
    // データ（対面では検証しない）
    $test_order_record = $data_order_record;
    $test_order_record['provide'] = 'direct';
    $test_order_record['email']   = 'test';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate email order_record email (direct)', count($warnings), 0);
}

// 名前の必須（配送）テスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['name_01'] = '';
    $test_order_record['name_02'] = '';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate required order_record name', count($warnings), 2);
}

// 名前の必須（ダウンロード）テスト
{
    // データ（ダウンロードでは住所も名前も不要）
    $test_order_record = $data_order_record;
    $test_order_record['provide']    = 'download';
    $test_order_record['name_01']    = '';
    $test_order_record['name_02']    = '';
    $test_order_record['zipcode']    = '';
    $test_order_record['prefecture'] = '';
    $test_order_record['address_01'] = '';
    $test_order_record['telephone']  = '';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate required order_record name (download)', count($warnings), 0);
}

// 名前の長さテスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['name_01'] = str_repeat('あ', 21);

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate max_length order_record name_01', count($warnings), 1);
}

// カナの書式テスト
{
    // データ（全角カタカナ以外）
    $test_order_record = $data_order_record;
    $test_order_record['kana_01'] = 'てすと';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate katakana order_record kana_01', count($warnings), 1);
}

// カナの未入力テスト
{
    // データ（カナは任意項目のため未入力でも警告は出ない）
    $test_order_record = $data_order_record;
    $test_order_record['kana_01'] = '';
    $test_order_record['kana_02'] = '';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate empty order_record kana', count($warnings), 0);
}

// 郵便番号の必須テスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['zipcode'] = '';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate required order_record zipcode', count($warnings), 1);
}

// 郵便番号の長さ（境界値）テスト
{
    // データ（ハイフンを含めて8文字ちょうどのため警告は出ない）
    $test_order_record = $data_order_record;
    $test_order_record['zipcode'] = str_repeat('1', 8);

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate max_length order_record zipcode (boundary)', count($warnings), 0);
}

// 郵便番号の長さテスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['zipcode'] = str_repeat('1', 9);

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate max_length order_record zipcode', count($warnings), 1);
}

// 都道府県の必須テスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['prefecture'] = '';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate required order_record prefecture', count($warnings), 1);
}

// 住所 1の必須テスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['address_01'] = '';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate required order_record address_01', count($warnings), 1);
}

// 住所 1の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_order_record = $data_order_record;
    $test_order_record['address_01'] = str_repeat('あ', 100);

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate max_length order_record address_01 (boundary)', count($warnings), 0);
}

// 住所 1の長さテスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['address_01'] = str_repeat('あ', 101);

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate max_length order_record address_01', count($warnings), 1);
}

// 住所 2の未入力テスト
{
    // データ（住所 2は任意項目のため未入力でも警告は出ない）
    $test_order_record = $data_order_record;
    $test_order_record['address_02'] = '';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate empty order_record address_02', count($warnings), 0);
}

// 電話番号の必須テスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['telephone'] = '';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate required order_record telephone', count($warnings), 1);
}

// 電話番号の長さ（境界値）テスト
{
    // データ（ハイフン無しの11桁ちょうどのため警告は出ない）
    $test_order_record = $data_order_record;
    $test_order_record['telephone'] = str_repeat('1', 11);

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate max_length order_record telephone (boundary)', count($warnings), 0);
}

// 電話番号の長さテスト
{
    // データ（ハイフンを入れると12文字になり、上限を超える）
    $test_order_record = $data_order_record;
    $test_order_record['telephone'] = '03-1234-5678';

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate max_length order_record telephone', count($warnings), 1);
}

// お問い合わせ内容の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_order_record = $data_order_record;
    $test_order_record['message'] = str_repeat('あ', 1000);

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate max_length order_record message (boundary)', count($warnings), 0);
}

// お問い合わせ内容の長さテスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['message'] = str_repeat('あ', 1001);

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate max_length order_record message', count($warnings), 1);
}

// 店舗用メモの長さテスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['memo'] = str_repeat('あ', 5001);

    // 確認
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);

    // 結果
    test_equals('validate max_length order_record memo', count($warnings), 1);
}

// 注文明細の登録テスト
{
    // 登録（規格を指定した明細。販売価格は規格から、原価は製品と在庫から集計される）
    model('set_item_order_records', $inserted_id, [
        [
            'order_spec_id' => $spec_id,
            'quantity'      => 3,
        ],
    ]);

    // 結果
    $order_record_items = model('select_order_record_items', [
        'where' => 'record_id = ' . $inserted_id,
    ]);

    test_equals('set item order_record', count($order_record_items), 1);
    test_equals('set item order_record spec_id', intval($order_record_items[0]['spec_id']), $spec_id);
    test_equals('set item order_record quantity', intval($order_record_items[0]['quantity']), 3);

    // 結果（販売価格は規格の値）
    test_equals('set item order_record selling_price', intval($order_record_items[0]['selling_price']), 1500);

    // 結果（原価は 在庫Aの原価300×2個 + 在庫Bの原価50×1個 = 650。規格1点あたりで集計する）
    test_equals('set item order_record cost_price', intval($order_record_items[0]['cost_price']), 650);
}

// 注文明細の登録（在庫を減らさない）テスト
{
    // 結果（use_stock が無効なので在庫は減らない）
    $order_stocks = model('select_order_stocks', [
        'select' => 'quantity',
        'where'  => 'id = ' . $stock_a_id,
    ]);

    test_equals('set item order_record stock (disabled)', intval($order_stocks[0]['quantity']), 100);
}

// 注文明細の登録（在庫を減らす）テスト
{
    // データ（在庫を減らす設定にする）
    $GLOBALS['plugin']['order']['setting']['use_stock'] = true;

    // 登録（規格1点につき在庫Aを2個使うので、3点で6個減る）
    model('set_item_order_records', $inserted_id, [
        [
            'order_spec_id' => $spec_id,
            'quantity'      => 3,
        ],
    ]);

    // 結果
    $order_stocks = model('select_order_stocks', [
        'select' => 'quantity',
        'where'  => 'id = ' . $stock_a_id,
    ]);

    test_equals('set item order_record stock', intval($order_stocks[0]['quantity']), 94);

    // 結果（数を管理しない在庫（NULL）は減らないこと）
    $order_stocks = model('select_order_stocks', [
        'select' => 'quantity',
        'where'  => 'id = ' . $stock_b_id,
    ]);

    test_equals('set item order_record stock (null)', $order_stocks[0]['quantity'], null);

    // 設定を戻す
    $GLOBALS['plugin']['order']['setting']['use_stock'] = false;
}

// 注文明細の入れ替えテスト
{
    // 登録（規格を指定しない、直接入力の明細に入れ替える）
    model('set_item_order_records', $inserted_id, [
        [
            'order_spec_id' => '',
            'name'          => 'ラッピング',
            'selling_price' => 200,
            'quantity'      => 1,
        ],
    ]);

    // 結果（古い明細は消えて、新しい明細だけになること）
    $order_record_items = model('select_order_record_items', [
        'where' => 'record_id = ' . $inserted_id,
    ]);

    test_equals('set item order_record (replace)', count($order_record_items), 1);
    test_equals('set item order_record (replace name)', $order_record_items[0]['name'], 'ラッピング');
    test_equals('set item order_record (replace spec_id)', $order_record_items[0]['spec_id'], null);
    test_equals('set item order_record (replace selling_price)', intval($order_record_items[0]['selling_price']), 200);

    // 結果（直接入力の明細には原価が無いこと）
    test_equals('set item order_record (replace cost_price)', $order_record_items[0]['cost_price'], null);
}

// 注文明細の削除テスト
{
    // 登録（空の配列を渡すと明細が無くなる）
    model('set_item_order_records', $inserted_id, []);

    // 結果
    $order_record_items = model('select_order_record_items', [
        'where' => 'record_id = ' . $inserted_id,
    ]);

    test_equals('set item order_record (empty)', count($order_record_items), 0);
}

// 登録と同時の明細登録テスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['email'] = 'testorder2@example.com';

    // 登録
    $test_order_record = model('normalize_order_records', $test_order_record);

    model('insert_order_records', [
        'values' => $test_order_record,
    ], [
        'items' => [
            [
                'order_spec_id' => $spec_id,
                'quantity'      => 1,
            ],
        ],
    ]);

    $order_records = model('select_order_records', [
        'select'   => 'id',
        'order_by' => 'id DESC',
        'limit'    => 1,
    ]);
    $second_id = intval($order_records[0]['id']);

    // 結果
    $order_record_items = model('select_order_record_items', [
        'where' => 'record_id = ' . $second_id,
    ]);

    test_equals('insert order_record (items)', count($order_record_items), 1);
    test_equals('insert order_record (items selling_price)', intval($order_record_items[0]['selling_price']), 1500);
}

// 更新テスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['status'] = 'paid';
    $test_order_record['memo']   = '入金を確認';

    // 更新
    $test_order_record = model('normalize_order_records', $test_order_record);
    $warnings          = model('validate_order_records', $test_order_record);
    if (empty($warnings)) {
        model('update_order_records', [
            'set'   => $test_order_record,
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
    $order_records = model('select_order_records', [
        'select' => 'status, memo',
        'where'  => 'id = ' . $inserted_id,
    ]);

    $test_data = [
        $order_records[0],
    ];
    test_array_subset('update order_record', $test_data, [
        'status' => $test_order_record['status'],
        'memo'   => $test_order_record['memo'],
    ]);
}

// 更新と同時の明細登録テスト
{
    // 更新
    model('update_order_records', [
        'set'   => [
            'status' => 'shipping',
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
                'order_spec_id' => $spec_id,
                'quantity'      => 2,
            ],
        ],
    ]);

    // 結果
    $order_record_items = model('select_order_record_items', [
        'where' => 'record_id = ' . $inserted_id,
    ]);

    test_equals('update order_record (items)', count($order_record_items), 1);
    test_equals('update order_record (items quantity)', intval($order_record_items[0]['quantity']), 2);
}

// 削除テスト
{
    // 削除
    model('delete_order_records', [
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（取得対象からは外れること）
    $order_records = model('select_order_records', [
        'where' => 'id = ' . $inserted_id,
    ]);

    test_equals('delete order_record', count($order_records), 0);

    // 結果（レコード自体は残り、削除日時が入ること）
    $order_records = db_select([
        'select' => 'deleted',
        'from'   => DATABASE_PREFIX . 'order_records',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    test_equals('delete order_record (record)', count($order_records), 1);
    test_not_equals('delete order_record (deleted)', $order_records[0]['deleted'], null);

    // 結果（オプションを渡していないので、明細は残ること）
    $order_record_items = model('select_order_record_items', [
        'where' => 'record_id = ' . $inserted_id,
    ]);

    test_equals('delete order_record (items)', count($order_record_items), 1);
}

// 関連データの削除テスト
{
    // 削除
    model('delete_order_records', [
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ], [
        'associate' => true,
    ]);

    // 結果（明細も削除されること）
    $order_record_items = model('select_order_record_items', [
        'where' => 'record_id = ' . $inserted_id,
    ]);

    test_equals('delete order_record (associate)', count($order_record_items), 0);
}

// 物理削除テスト
{
    // データ
    $test_order_record = $data_order_record;
    $test_order_record['email'] = 'testorder3@example.com';

    // 登録
    $test_order_record = model('normalize_order_records', $test_order_record);
    model('insert_order_records', [
        'values' => $test_order_record,
    ]);

    // 削除
    model('delete_order_records', [
        'where' => 'email = \'testorder3@example.com\'',
    ], [
        'softdelete' => false,
    ]);

    // 結果（レコード自体が消えること）
    $order_records = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'order_records',
        'where'  => 'email = \'testorder3@example.com\'',
    ]);

    test_equals('delete order_record (physical)', count($order_records), 0);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_records;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_record_items;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_specs;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_products;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_stocks;');

// 設定を戻す
if ($setting_use_stock === null) {
    unset($GLOBALS['plugin']['order']['setting']['use_stock']);
} else {
    $GLOBALS['plugin']['order']['setting']['use_stock'] = $setting_use_stock;
}

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'plugins/order/app/models/order_records.php',
    ]);
}
