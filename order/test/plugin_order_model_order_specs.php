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
import('plugins/order/app/models/order_specs.php');
import('plugins/order/app/models/order_products.php');
import('plugins/order/app/models/order_stocks.php');

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_specs;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_products;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_stocks;');

// トランザクションを開始
db_transaction();

// 前提データ（規格をひも付けるエントリー。entries はほかのテストやシナリオでも使うので TRUNCATE せず、
// トランザクションで元に戻る1件だけを登録する）
db_insert([
    'insert_into' => DATABASE_PREFIX . 'entries',
    'values'      => [
        'created'   => localdate('Y-m-d H:i:s'),
        'modified'  => localdate('Y-m-d H:i:s'),
        'datetime'  => localdate('Y-m-d H:i:s'),
        'type_id'   => 1,
        'code'      => 'testspecentry',
        'title'     => 'テスト商品',
        'text_type' => 'none',
        'public'    => 'all',
        'comment'   => 'none',
        'approved'  => 1,
    ],
]);
$entries = db_select([
    'select'   => 'id',
    'from'     => DATABASE_PREFIX . 'entries',
    'where'    => 'code = \'testspecentry\'',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$entry_id = intval($entries[0]['id']);

// 正常データ
$data_order_spec = [
    'entry_id'      => $entry_id,
    'code'          => 'testspec1',
    'enabled'       => 1,
    'name'          => 'テスト規格1',
    'provide'       => 'delivery',
    'selling_price' => 1000,
    'regular_price' => 1200,
    'shipping_cost' => 500,
    'delivery_days' => 3,
    'sales_limit'   => 10,
    'memo'          => '',
    'sort'          => 1,
];

// 初期値テスト
{
    // 確認
    $default_order_spec = model('default_order_specs');

    // 結果
    test_equals('default order_spec id', $default_order_spec['id'], null);
    test_equals('default order_spec entry_id', $default_order_spec['entry_id'], 0);
    test_equals('default order_spec code', $default_order_spec['code'], '');
    test_equals('default order_spec enabled', $default_order_spec['enabled'], 1);
    test_equals('default order_spec name', $default_order_spec['name'], '');
    test_equals('default order_spec provide', $default_order_spec['provide'], '');
    test_equals('default order_spec selling_price', $default_order_spec['selling_price'], 0);
    test_equals('default order_spec regular_price', $default_order_spec['regular_price'], null);
    test_equals('default order_spec sort', $default_order_spec['sort'], 0);
    test_equals('default order_spec deleted', $default_order_spec['deleted'], null);
    test_regexp('default order_spec created', $default_order_spec['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 正常登録テスト
{
    // データ
    $test_order_spec = $data_order_spec;

    // 登録
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果（正常データでは警告が出ないこと）
    test_equals('validate order_spec', count($warnings), 0);

    if (empty($warnings)) {
        model('insert_order_specs', [
            'values' => $test_order_spec,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $order_specs = model('select_order_specs', [
        'select'   => 'code, name, provide, selling_price, sort',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    $inserted_data = array_shift($order_specs);
    $test_data     = [
        $inserted_data,
    ];
    test_array_subset('insert order_spec', $test_data, [
        'code'          => $test_order_spec['code'],
        'name'          => $test_order_spec['name'],
        'provide'       => $test_order_spec['provide'],
        'selling_price' => $test_order_spec['selling_price'],
        'sort'          => $test_order_spec['sort'],
    ]);
}

// 登録した規格のIDを取得
$order_specs = model('select_order_specs', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($order_specs[0]['id']);

// 関連データの取得テスト
{
    // 取得
    $order_specs = model('select_order_specs', [
        'where'    => 'order_specs.id = ' . $inserted_id,
        'order_by' => 'order_specs.sort, order_specs.id',
    ], [
        'associate' => true,
    ]);

    // 結果
    test_equals('select associate order_spec', count($order_specs), 1);
    test_equals('select associate order_spec entry_code', $order_specs[0]['entry_code'], 'testspecentry');
    test_equals('select associate order_spec entry_title', $order_specs[0]['entry_title'], 'テスト商品');
    test_equals('select associate order_spec type_code', $order_specs[0]['type_code'], 'entry');
}

// 販売価格の正規化（全角数字）テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['selling_price'] = '１０００';

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);

    // 結果
    test_equals('normalize order_spec selling_price', $test_order_spec['selling_price'], '1000');
}

// 通常価格・送料の正規化（全角数字）テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['regular_price'] = '１２００';
    $test_order_spec['shipping_cost'] = '５００';

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);

    // 結果
    test_equals('normalize order_spec regular_price', $test_order_spec['regular_price'], '1200');
    test_equals('normalize order_spec shipping_cost', $test_order_spec['shipping_cost'], '500');
}

// 配送日目安・販売制限数の正規化（全角数字）テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['delivery_days'] = '７';
    $test_order_spec['sales_limit']   = '９９';

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);

    // 結果
    test_equals('normalize order_spec delivery_days', $test_order_spec['delivery_days'], '7');
    test_equals('normalize order_spec sales_limit', $test_order_spec['sales_limit'], '99');
}

// 並び順の正規化（全角数字）テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['sort'] = '１２';

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);

    // 結果
    test_equals('normalize order_spec sort', $test_order_spec['sort'], '12');
}

// 並び順の正規化（自動採番）テスト
{
    // データ（並び順を持たない管理画面からの入力を想定）
    $test_order_spec = $data_order_spec;
    $test_order_spec['id'] = '';
    unset($test_order_spec['sort']);

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);

    // 結果（登録済みの最大値 1 に 1 を加えた値になること）
    test_equals('normalize order_spec sort auto', $test_order_spec['sort'], 2);
}

// 商品の必須テスト
{
    // データ（規格をひも付ける商品。画面では隠し項目で渡される。コードは登録済みのものと衝突させない）
    $test_order_spec = $data_order_spec;
    $test_order_spec['code']     = 'testspec3';
    $test_order_spec['entry_id'] = '';

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate required order_spec entry_id', count($warnings), 1);
}

// 規格管理コードの必須テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['code'] = '';

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate required order_spec code', count($warnings), 1);
}

// 規格管理コードの書式テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['code'] = 'あいうえお';

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate alpha_dash order_spec code', count($warnings), 1);
}

// 規格管理コードの長さ（最小・境界値）テスト
{
    // データ（下限ちょうどのため警告は出ない）
    $test_order_spec = $data_order_spec;
    $test_order_spec['code'] = str_repeat('a', 2);

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate min_length order_spec code (boundary)', count($warnings), 0);
}

// 規格管理コードの長さ（最小）テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['code'] = str_repeat('a', 1);

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate min_length order_spec code', count($warnings), 1);
}

// 規格管理コードの長さ（最大・境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_order_spec = $data_order_spec;
    $test_order_spec['code'] = str_repeat('a', 80);

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate max_length order_spec code (boundary)', count($warnings), 0);
}

// 規格管理コードの長さ（最大）テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['code'] = str_repeat('a', 81);

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate max_length order_spec code', count($warnings), 1);
}

// 規格管理コードの重複テスト
{
    // データ（登録済みのコードと同じコードで新規登録）
    $test_order_spec = $data_order_spec;

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate duplicate order_spec code', count($warnings), 1);
}

// 規格管理コードの重複（自分自身は除外）テスト
{
    // データ（登録済みの規格自身を編集）
    $test_order_spec = $data_order_spec;
    $test_order_spec['id'] = $inserted_id;

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate duplicate order_spec code (self)', count($warnings), 0);
}

// 規格管理コードの重複（編集で別の規格と衝突）テスト
{
    // データ（2件目を登録してから、1件目と同じコードに変更する）
    $test_order_spec = $data_order_spec;
    $test_order_spec['code'] = 'testspec2';
    $test_order_spec['name'] = 'テスト規格2';
    $test_order_spec['sort'] = 2;

    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);
    if (empty($warnings)) {
        model('insert_order_specs', [
            'values' => $test_order_spec,
        ]);
    } else {
        debug($warnings);
    }

    $order_specs = model('select_order_specs', [
        'select'   => 'id',
        'order_by' => 'id DESC',
        'limit'    => 1,
    ]);
    $second_id = intval($order_specs[0]['id']);

    // 確認
    $test_order_spec['id']   = $second_id;
    $test_order_spec['code'] = 'testspec1';

    $warnings = model('validate_order_specs', $test_order_spec);

    // 結果（ほかの規格と同じコードなので警告が出ること。編集時に在庫のテーブルを見ていると検出できない）
    test_equals('validate duplicate order_spec code (other)', count($warnings), 1);
}

// 規格管理コードの重複（在庫とは別の名前空間）テスト
{
    // データ（在庫を2件登録する。1件目は捨て駒で、2件目のIDを編集する規格のIDとずらすため。
    // IDが同じだと、編集時の除外条件 id != :id にたまたま当たって、テーブルを取り違えていても警告が出ない）
    foreach (['teststockfiller', 'teststockcode'] as $code) {
        model('insert_order_stocks', [
            'values' => [
                'code' => $code,
                'name' => 'テスト在庫',
                'kind' => 'analog',
            ],
        ]);
    }

    $order_stocks = model('select_order_stocks', [
        'select' => 'id',
        'where'  => 'code = \'teststockcode\'',
    ]);
    $stock_id = intval($order_stocks[0]['id']);

    $test_order_spec = $data_order_spec;
    $test_order_spec['id']   = $inserted_id;
    $test_order_spec['code'] = 'teststockcode';

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果（このテストが意味を持つよう、在庫と規格のIDが違うこと）
    test_not_equals('validate duplicate order_spec code (order_stock id)', $stock_id, $inserted_id);

    // 結果（在庫と規格でコードの名前空間は別なので、警告は出ないこと）
    test_equals('validate duplicate order_spec code (order_stock)', count($warnings), 0);
}

// 規格管理コードの重複チェック無効テスト
{
    // データ（登録済みのコードと同じコード）
    $test_order_spec = $data_order_spec;

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec, [
        'duplicate' => false,
    ]);

    // 結果
    test_equals('validate duplicate order_spec code (disabled)', count($warnings), 0);
}

// 有効の書式テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['code']    = 'testspec3';
    $test_order_spec['enabled'] = 'あ';

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate boolean order_spec enabled', count($warnings), 1);
}

// 有効の書式（無効）テスト
{
    // データ（0 も正しい値）
    $test_order_spec = $data_order_spec;
    $test_order_spec['code']    = 'testspec3';
    $test_order_spec['enabled'] = 0;

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate boolean order_spec enabled (disabled)', count($warnings), 0);
}

// 名前の必須テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['code'] = 'testspec3';
    $test_order_spec['name'] = '';

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate required order_spec name', count($warnings), 1);
}

// 名前の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない。文字数で数えることの確認も兼ねてマルチバイト文字を使う）
    $test_order_spec = $data_order_spec;
    $test_order_spec['code'] = 'testspec3';
    $test_order_spec['name'] = str_repeat('あ', 20);

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate max_length order_spec name (boundary)', count($warnings), 0);
}

// 名前の長さテスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['code'] = 'testspec3';
    $test_order_spec['name'] = str_repeat('あ', 21);

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate max_length order_spec name', count($warnings), 1);
}

// 提供方法の必須テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['code']    = 'testspec3';
    $test_order_spec['provide'] = '';

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate required order_spec provide', count($warnings), 1);
}

// 提供方法の選択肢テスト
{
    // データ（選択肢に無い値）
    $test_order_spec = $data_order_spec;
    $test_order_spec['code']    = 'testspec3';
    $test_order_spec['provide'] = 'unknown';

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate list order_spec provide', count($warnings), 1);
}

// 提供方法の選択肢（ダウンロード）テスト
{
    // データ（選択肢にある値）
    $test_order_spec = $data_order_spec;
    $test_order_spec['code']    = 'testspec3';
    $test_order_spec['provide'] = 'download';

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate list order_spec provide (download)', count($warnings), 0);
}

// 販売価格の必須テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['code']          = 'testspec3';
    $test_order_spec['selling_price'] = '';

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate required order_spec selling_price', count($warnings), 1);
}

// 販売価格の書式テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['code']          = 'testspec3';
    $test_order_spec['selling_price'] = 'あ';

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate numeric order_spec selling_price', count($warnings), 1);
}

// 販売価格の桁数（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_order_spec = $data_order_spec;
    $test_order_spec['code']          = 'testspec3';
    $test_order_spec['selling_price'] = str_repeat('1', 10);

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate max_length order_spec selling_price (boundary)', count($warnings), 0);
}

// 販売価格の桁数テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['code']          = 'testspec3';
    $test_order_spec['selling_price'] = str_repeat('1', 11);

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate max_length order_spec selling_price', count($warnings), 1);
}

// 通常価格の未入力テスト
{
    // データ（通常価格は任意項目のため未入力でも警告は出ない）
    $test_order_spec = $data_order_spec;
    $test_order_spec['code']          = 'testspec3';
    $test_order_spec['regular_price'] = '';

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate empty order_spec regular_price', count($warnings), 0);
}

// 通常価格の書式テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['code']          = 'testspec3';
    $test_order_spec['regular_price'] = 'あ';

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate numeric order_spec regular_price', count($warnings), 1);
}

// 送料の未入力テスト
{
    // データ（送料は任意項目のため未入力でも警告は出ない）
    $test_order_spec = $data_order_spec;
    $test_order_spec['code']          = 'testspec3';
    $test_order_spec['shipping_cost'] = '';

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate empty order_spec shipping_cost', count($warnings), 0);
}

// 送料の桁数テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['code']          = 'testspec3';
    $test_order_spec['shipping_cost'] = str_repeat('1', 11);

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate max_length order_spec shipping_cost', count($warnings), 1);
}

// 配送日目安の書式テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['code']          = 'testspec3';
    $test_order_spec['delivery_days'] = 'あ';

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate numeric order_spec delivery_days', count($warnings), 1);
}

// 販売制限数の桁数テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['code']        = 'testspec3';
    $test_order_spec['sales_limit'] = str_repeat('1', 11);

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate max_length order_spec sales_limit', count($warnings), 1);
}

// メモの長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_order_spec = $data_order_spec;
    $test_order_spec['code'] = 'testspec3';
    $test_order_spec['memo'] = str_repeat('あ', 5000);

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate max_length order_spec memo (boundary)', count($warnings), 0);
}

// メモの長さテスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['code'] = 'testspec3';
    $test_order_spec['memo'] = str_repeat('あ', 5001);

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate max_length order_spec memo', count($warnings), 1);
}

// 並び順の必須テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['code'] = 'testspec3';
    $test_order_spec['sort'] = '';

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate required order_spec sort', count($warnings), 1);
}

// 並び順の書式テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['code'] = 'testspec3';
    $test_order_spec['sort'] = 'あ';

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate numeric order_spec sort', count($warnings), 1);
}

// 並び順の桁数（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_order_spec = $data_order_spec;
    $test_order_spec['code'] = 'testspec3';
    $test_order_spec['sort'] = str_repeat('1', 5);

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate max_length order_spec sort (boundary)', count($warnings), 0);
}

// 並び順の桁数テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['code'] = 'testspec3';
    $test_order_spec['sort'] = str_repeat('1', 6);

    // 確認
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果
    test_equals('validate max_length order_spec sort', count($warnings), 1);
}

// 更新テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['id']            = $inserted_id;
    $test_order_spec['name']          = 'テスト規格1（更新）';
    $test_order_spec['provide']       = 'direct';
    $test_order_spec['selling_price'] = 2000;

    // 更新
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);
    if (empty($warnings)) {
        $set = $test_order_spec;
        unset($set['id']);

        model('update_order_specs', [
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
    $order_specs = model('select_order_specs', [
        'select' => 'name, provide, selling_price',
        'where'  => 'id = ' . $inserted_id,
    ]);

    $test_data = [
        $order_specs[0],
    ];
    test_array_subset('update order_spec', $test_data, [
        'name'          => $test_order_spec['name'],
        'provide'       => $test_order_spec['provide'],
        'selling_price' => $test_order_spec['selling_price'],
    ]);
}

// 削除テスト
{
    // 削除
    model('delete_order_specs', [
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（取得対象からは外れること）
    $order_specs = model('select_order_specs', [
        'where' => 'id = ' . $inserted_id,
    ]);

    test_equals('delete order_spec', count($order_specs), 0);

    // 結果（レコード自体は残り、削除日時とコードが書き換わること）
    $order_specs = db_select([
        'select' => 'code, deleted',
        'from'   => DATABASE_PREFIX . 'order_specs',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    test_equals('delete order_spec (record)', count($order_specs), 1);
    test_not_equals('delete order_spec (deleted)', $order_specs[0]['deleted'], null);
    test_regexp('delete order_spec (code)', $order_specs[0]['code'], '^DELETED \d{14} testspec1$');
}

// 削除後の再登録テスト
{
    // データ（削除した規格と同じコード。code は DB で UNIQUE なので、コードが退避されていないと INSERT が失敗する）
    $test_order_spec = $data_order_spec;
    $test_order_spec['name'] = 'テスト規格1（再登録）';
    $test_order_spec['sort'] = 3;

    // 登録
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);

    // 結果（検証を通ること）
    test_equals('validate order_spec code (deleted)', count($warnings), 0);

    if (empty($warnings)) {
        model('insert_order_specs', [
            'values' => $test_order_spec,
        ]);
    } else {
        debug($warnings);
    }

    // 結果（登録できること）
    $order_specs = model('select_order_specs', [
        'select' => 'name',
        'where'  => 'code = \'testspec1\'',
    ]);

    test_equals('insert order_spec (deleted code)', count($order_specs), 1);
}

// 物理削除テスト
{
    // データ
    $test_order_spec = $data_order_spec;
    $test_order_spec['code'] = 'testspec4';
    $test_order_spec['name'] = 'テスト規格4';
    $test_order_spec['sort'] = 4;

    // 登録
    $test_order_spec = model('normalize_order_specs', $test_order_spec);
    $warnings        = model('validate_order_specs', $test_order_spec);
    if (empty($warnings)) {
        model('insert_order_specs', [
            'values' => $test_order_spec,
        ]);
    } else {
        debug($warnings);
    }

    // 削除
    model('delete_order_specs', [
        'where' => 'code = \'testspec4\'',
    ], [
        'softdelete' => false,
    ]);

    // 結果（レコード自体が消えること）
    $order_specs = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'order_specs',
        'where'  => 'code = \'testspec4\'',
    ]);

    test_equals('delete order_spec (physical)', count($order_specs), 0);
}

// 関連データの削除（複数件）テスト
{
    // データ（2件を登録し、それぞれに製品をひも付ける）
    $associate_ids = [];
    foreach (['testspec5', 'testspec6'] as $index => $code) {
        $test_order_spec = $data_order_spec;
        $test_order_spec['code'] = $code;
        $test_order_spec['name'] = 'テスト規格' . ($index + 5);
        $test_order_spec['sort'] = $index + 5;

        // 登録
        $test_order_spec = model('normalize_order_specs', $test_order_spec);
        $warnings        = model('validate_order_specs', $test_order_spec);
        if (empty($warnings)) {
            model('insert_order_specs', [
                'values' => $test_order_spec,
            ]);
        } else {
            debug($warnings);
        }

        $order_specs = model('select_order_specs', [
            'select'   => 'id',
            'order_by' => 'id DESC',
            'limit'    => 1,
        ]);
        $associate_ids[] = intval($order_specs[0]['id']);
    }

    // 無関係なひも付け（2件のIDを区切り文字なしで連結した値。区切り文字が無いと IN(56) のようになり、これを削除してしまう）
    $unrelated_id = intval(implode('', $associate_ids));

    foreach (array_merge($associate_ids, [$unrelated_id]) as $spec_id) {
        model('insert_order_products', [
            'values' => [
                'spec_id'  => $spec_id,
                'stock_id' => 1,
                'quantity' => 1,
                'sort'     => 1,
            ],
        ]);
    }

    // 削除
    model('delete_order_specs', [
        'where' => 'code IN(\'testspec5\', \'testspec6\')',
    ], [
        'associate' => true,
    ]);

    // 結果（削除した規格のひも付けは消えること）
    $order_products = model('select_order_products', [
        'where' => 'spec_id IN(' . implode(',', $associate_ids) . ')',
    ]);

    test_equals('delete order_spec (associate)', count($order_products), 0);

    // 結果（無関係なひも付けは残ること）
    $order_products = model('select_order_products', [
        'where' => 'spec_id = ' . $unrelated_id,
    ]);

    test_equals('delete order_spec (associate unrelated)', count($order_products), 1);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_specs;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_products;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_stocks;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'plugins/order/app/models/order_specs.php',
    ]);
}
