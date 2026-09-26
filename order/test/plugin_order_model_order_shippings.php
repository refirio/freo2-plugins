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
import('plugins/order/app/models/order_shippings.php');
import('plugins/order/app/models/order_shipping_items.php');
import('plugins/order/app/models/order_records.php');
import('plugins/order/app/models/order_record_items.php');

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_shippings;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_shipping_items;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_records;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_record_items;');

// トランザクションを開始
db_transaction();

// 前提データ（注文記録1件と、その注文明細2件）
model('insert_order_records', [
    'values' => [
        'provide'       => 'delivery',
        'payment_id'    => 1,
        'payment_fee'   => 0,
        'delivery_cost' => 500,
        'discount'      => 0,
        'status'        => 'order',
        'email'         => 'testshipping@example.com',
    ],
]);
$order_records = model('select_order_records', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$record_id = intval($order_records[0]['id']);

foreach ([3, 2] as $quantity) {
    model('insert_order_record_items', [
        'values' => [
            'record_id'     => $record_id,
            'spec_id'       => 1,
            'selling_price' => 1500,
            'quantity'      => $quantity,
        ],
    ]);
}
$order_record_items = model('select_order_record_items', [
    'select'   => 'id',
    'where'    => 'record_id = ' . $record_id,
    'order_by' => 'id',
]);
$record_item_a_id = intval($order_record_items[0]['id']);
$record_item_b_id = intval($order_record_items[1]['id']);

// 正常データ（注文記録とは別に配送方法・送料・宛先を持てる。分割発送に対応するため）
$data_order_shipping = [
    'record_id'     => $record_id,
    'delivery_id'   => 1,
    'delivery_cost' => 500,
    'shipping_date' => '2026-01-01',
    'status'        => 'preparing',
    'email'         => 'testshipping@example.com',
    'name_01'       => 'テスト',
    'name_02'       => '太郎',
    'kana_01'       => 'テスト',
    'kana_02'       => 'タロウ',
    'zipcode'       => '100-0001',
    'prefecture'    => '東京都',
    'address_01'    => '千代田区千代田1-1',
    'address_02'    => '',
    'telephone'     => '0312345678',
    'memo'          => '',
];

// 初期値テスト
{
    // 確認
    $default_order_shipping = model('default_order_shippings');

    // 結果
    test_equals('default order_shipping id', $default_order_shipping['id'], null);
    test_equals('default order_shipping record_id', $default_order_shipping['record_id'], 0);
    test_equals('default order_shipping delivery_id', $default_order_shipping['delivery_id'], null);
    test_equals('default order_shipping delivery_cost', $default_order_shipping['delivery_cost'], null);
    test_equals('default order_shipping shipping_date', $default_order_shipping['shipping_date'], null);
    test_equals('default order_shipping status', $default_order_shipping['status'], '');
    test_equals('default order_shipping email', $default_order_shipping['email'], null);
    test_equals('default order_shipping deleted', $default_order_shipping['deleted'], null);
    test_regexp('default order_shipping created', $default_order_shipping['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 正常登録テスト
{
    // データ
    $test_order_shipping = $data_order_shipping;

    // 登録
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);
    $warnings            = model('validate_order_shippings', $test_order_shipping);

    // 結果（正常データでは警告が出ないこと）
    test_equals('validate order_shipping', count($warnings), 0);

    if (empty($warnings)) {
        model('insert_order_shippings', [
            'values' => $test_order_shipping,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $order_shippings = model('select_order_shippings', [
        'select'   => 'record_id, status, delivery_cost, name_01',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    $inserted_data = array_shift($order_shippings);
    $test_data     = [
        $inserted_data,
    ];
    test_array_subset('insert order_shipping', $test_data, [
        'record_id'     => $test_order_shipping['record_id'],
        'status'        => $test_order_shipping['status'],
        'delivery_cost' => $test_order_shipping['delivery_cost'],
        'name_01'       => $test_order_shipping['name_01'],
    ]);
}

// 登録した発送記録のIDを取得
$order_shippings = model('select_order_shippings', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($order_shippings[0]['id']);

// 配送方法の正規化（未選択）テスト
{
    // データ
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['delivery_id'] = '';

    // 確認
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);

    // 結果
    test_equals('normalize order_shipping delivery_id', $test_order_shipping['delivery_id'], null);
}

// 送料の正規化（未入力）テスト
{
    // データ（注文記録と違い、送料は未入力を NULL として扱う）
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['delivery_cost'] = '';

    // 確認
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);

    // 結果
    test_equals('normalize order_shipping delivery_cost (empty)', $test_order_shipping['delivery_cost'], null);
}

// 送料の正規化（全角数字）テスト
{
    // データ
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['delivery_cost'] = '７００';

    // 確認
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);

    // 結果
    test_equals('normalize order_shipping delivery_cost', $test_order_shipping['delivery_cost'], '700');
}

// 配送日・郵便番号・電話番号の正規化（全角英数字）テスト
{
    // データ
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['shipping_date'] = '２０２６－０１－０１';
    $test_order_shipping['zipcode']       = '１００－０００１';
    $test_order_shipping['telephone']     = '０３１２３４５６７８';

    // 確認
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);

    // 結果
    test_equals('normalize order_shipping shipping_date', $test_order_shipping['shipping_date'], '2026-01-01');
    test_equals('normalize order_shipping zipcode', $test_order_shipping['zipcode'], '100-0001');
    test_equals('normalize order_shipping telephone', $test_order_shipping['telephone'], '0312345678');
}

// 状況の必須テスト
{
    // データ（発送記録で必須なのは状況だけ）
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['status'] = '';

    // 確認
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);
    $warnings            = model('validate_order_shippings', $test_order_shipping);

    // 結果
    test_equals('validate required order_shipping status', count($warnings), 1);
}

// 状況の選択肢テスト
{
    // データ（選択肢に無い値。注文記録とは別の語彙を使う）
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['status'] = 'order';

    // 確認
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);
    $warnings            = model('validate_order_shippings', $test_order_shipping);

    // 結果
    test_equals('validate list order_shipping status', count($warnings), 1);
}

// 状況の選択肢（発送済み）テスト
{
    // データ（選択肢にある値）
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['status'] = 'completed';

    // 確認
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);
    $warnings            = model('validate_order_shippings', $test_order_shipping);

    // 結果
    test_equals('validate list order_shipping status (completed)', count($warnings), 0);
}

// 宛先の未入力テスト
{
    // データ（注文記録と違い、宛先は提供方法に関係なくすべて任意項目）
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['email']      = '';
    $test_order_shipping['name_01']    = '';
    $test_order_shipping['name_02']    = '';
    $test_order_shipping['kana_01']    = '';
    $test_order_shipping['kana_02']    = '';
    $test_order_shipping['zipcode']    = '';
    $test_order_shipping['prefecture'] = '';
    $test_order_shipping['address_01'] = '';
    $test_order_shipping['telephone']  = '';

    // 確認
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);
    $warnings            = model('validate_order_shippings', $test_order_shipping);

    // 結果
    test_equals('validate empty order_shipping address', count($warnings), 0);
}

// 送料の書式テスト
{
    // データ
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['delivery_cost'] = 'あ';

    // 確認
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);
    $warnings            = model('validate_order_shippings', $test_order_shipping);

    // 結果
    test_equals('validate numeric order_shipping delivery_cost', count($warnings), 1);
}

// 送料の桁数テスト
{
    // データ
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['delivery_cost'] = str_repeat('1', 11);

    // 確認
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);
    $warnings            = model('validate_order_shippings', $test_order_shipping);

    // 結果
    test_equals('validate max_length order_shipping delivery_cost', count($warnings), 1);
}

// 配送日の書式テスト
{
    // データ（日付として存在しない値）
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['shipping_date'] = '2026-02-30';

    // 確認
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);
    $warnings            = model('validate_order_shippings', $test_order_shipping);

    // 結果
    test_equals('validate date order_shipping shipping_date', count($warnings), 1);
}

// メールアドレスの書式テスト
{
    // データ
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['email'] = 'test';

    // 確認
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);
    $warnings            = model('validate_order_shippings', $test_order_shipping);

    // 結果
    test_equals('validate email order_shipping email', count($warnings), 1);
}

// 名前の長さテスト
{
    // データ
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['name_01'] = str_repeat('あ', 21);

    // 確認
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);
    $warnings            = model('validate_order_shippings', $test_order_shipping);

    // 結果
    test_equals('validate max_length order_shipping name_01', count($warnings), 1);
}

// カナの書式テスト
{
    // データ（全角カタカナ以外）
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['kana_01'] = 'てすと';

    // 確認
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);
    $warnings            = model('validate_order_shippings', $test_order_shipping);

    // 結果
    test_equals('validate katakana order_shipping kana_01', count($warnings), 1);
}

// 郵便番号の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['zipcode'] = str_repeat('1', 8);

    // 確認
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);
    $warnings            = model('validate_order_shippings', $test_order_shipping);

    // 結果
    test_equals('validate max_length order_shipping zipcode (boundary)', count($warnings), 0);
}

// 郵便番号の長さテスト
{
    // データ
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['zipcode'] = str_repeat('1', 9);

    // 確認
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);
    $warnings            = model('validate_order_shippings', $test_order_shipping);

    // 結果
    test_equals('validate max_length order_shipping zipcode', count($warnings), 1);
}

// 住所の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['address_01'] = str_repeat('あ', 100);
    $test_order_shipping['address_02'] = str_repeat('あ', 100);

    // 確認
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);
    $warnings            = model('validate_order_shippings', $test_order_shipping);

    // 結果
    test_equals('validate max_length order_shipping address (boundary)', count($warnings), 0);
}

// 住所の長さテスト
{
    // データ
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['address_01'] = str_repeat('あ', 101);
    $test_order_shipping['address_02'] = str_repeat('あ', 101);

    // 確認
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);
    $warnings            = model('validate_order_shippings', $test_order_shipping);

    // 結果
    test_equals('validate max_length order_shipping address', count($warnings), 2);
}

// 電話番号の長さテスト
{
    // データ（ハイフンを入れると12文字になり、上限を超える）
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['telephone'] = '03-1234-5678';

    // 確認
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);
    $warnings            = model('validate_order_shippings', $test_order_shipping);

    // 結果
    test_equals('validate max_length order_shipping telephone', count($warnings), 1);
}

// 店舗用メモの長さテスト
{
    // データ
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['memo'] = str_repeat('あ', 5001);

    // 確認
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);
    $warnings            = model('validate_order_shippings', $test_order_shipping);

    // 結果
    test_equals('validate max_length order_shipping memo', count($warnings), 1);
}

// 発送明細の登録テスト
{
    // 登録（注文明細を指定して、発送した数を記録する）
    model('set_item_order_shippings', $inserted_id, $record_id, [
        [
            'record_item_id' => $record_item_a_id,
            'quantity'       => 1,
        ],
        [
            'record_item_id' => $record_item_b_id,
            'quantity'       => 2,
        ],
    ]);

    // 結果
    $order_shipping_items = model('select_order_shipping_items', [
        'where'    => 'shipping_id = ' . $inserted_id,
        'order_by' => 'id',
    ]);

    test_equals('set item order_shipping', count($order_shipping_items), 2);
    test_equals('set item order_shipping record_item_id', intval($order_shipping_items[0]['record_item_id']), $record_item_a_id);
    test_equals('set item order_shipping quantity', intval($order_shipping_items[0]['quantity']), 1);
    test_equals('set item order_shipping quantity (2)', intval($order_shipping_items[1]['quantity']), 2);
}

// 発送明細の入れ替えテスト
{
    // 登録（1件だけに入れ替える）
    model('set_item_order_shippings', $inserted_id, $record_id, [
        [
            'record_item_id' => $record_item_a_id,
            'quantity'       => 3,
        ],
    ]);

    // 結果（古い明細は消えて、新しい明細だけになること）
    $order_shipping_items = model('select_order_shipping_items', [
        'where' => 'shipping_id = ' . $inserted_id,
    ]);

    test_equals('set item order_shipping (replace)', count($order_shipping_items), 1);
    test_equals('set item order_shipping (replace quantity)', intval($order_shipping_items[0]['quantity']), 3);
}

// 発送明細の超過テスト
{
    // 登録（注文数3に対して5を発送。イレギュラーな配送に対応するため、超過していても登録できる）
    model('set_item_order_shippings', $inserted_id, $record_id, [
        [
            'record_item_id' => $record_item_a_id,
            'quantity'       => 5,
        ],
    ]);

    // 結果（弾かれずに登録されること。超過の警告は /admin/shipping の画面側で出す）
    $order_shipping_items = model('select_order_shipping_items', [
        'where' => 'shipping_id = ' . $inserted_id,
    ]);

    test_equals('set item order_shipping (over)', count($order_shipping_items), 1);
    test_equals('set item order_shipping (over quantity)', intval($order_shipping_items[0]['quantity']), 5);
}

// 発送明細の削除テスト
{
    // 登録（空の配列を渡すと明細が無くなる）
    model('set_item_order_shippings', $inserted_id, $record_id, []);

    // 結果
    $order_shipping_items = model('select_order_shipping_items', [
        'where' => 'shipping_id = ' . $inserted_id,
    ]);

    test_equals('set item order_shipping (empty)', count($order_shipping_items), 0);
}

// 登録と同時の明細登録テスト
{
    // データ
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['memo'] = '2便目';

    // 登録
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);

    model('insert_order_shippings', [
        'values' => $test_order_shipping,
    ], [
        // 登録では、注文記録のIDを values から取るのでオプションには渡さない
        'items' => [
            [
                'record_item_id' => $record_item_b_id,
                'quantity'       => 1,
            ],
        ],
    ]);

    $order_shippings = model('select_order_shippings', [
        'select'   => 'id',
        'order_by' => 'id DESC',
        'limit'    => 1,
    ]);
    $second_id = intval($order_shippings[0]['id']);

    // 結果
    $order_shipping_items = model('select_order_shipping_items', [
        'where' => 'shipping_id = ' . $second_id,
    ]);

    test_equals('insert order_shipping (items)', count($order_shipping_items), 1);
    test_equals('insert order_shipping (items quantity)', intval($order_shipping_items[0]['quantity']), 1);
}

// 発送可能数から除外する発送記録の取得テスト
{
    // データ（配送失敗と返送の発送記録を1件ずつ追加する）
    foreach (['failed', 'returned'] as $status) {
        $test_order_shipping = $data_order_shipping;
        $test_order_shipping['status'] = $status;

        model('insert_order_shippings', [
            'values' => model('normalize_order_shippings', $test_order_shipping),
        ]);
    }

    // 確認
    $excluded_ids = model('select_unsuccessful_order_shippings');

    // 結果（配送失敗・返送の2件だけが返ること）
    test_equals('select unsuccessful order_shipping', count($excluded_ids), 2);
    test_equals('select unsuccessful order_shipping (int)', is_int($excluded_ids[0]), true);

    // 結果（絞り込みの条件を足せること）
    $excluded_ids = model('select_unsuccessful_order_shippings', 'record_id = ' . $record_id);

    test_equals('select unsuccessful order_shipping (where)', count($excluded_ids), 2);

    $excluded_ids = model('select_unsuccessful_order_shippings', 'record_id = 0');

    test_equals('select unsuccessful order_shipping (where nothing)', count($excluded_ids), 0);
}

// 更新テスト
{
    // データ
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['status'] = 'completed';
    $test_order_shipping['memo']   = '発送済み';

    // 更新
    $test_order_shipping = model('normalize_order_shippings', $test_order_shipping);
    $warnings            = model('validate_order_shippings', $test_order_shipping);
    if (empty($warnings)) {
        model('update_order_shippings', [
            'set'   => $test_order_shipping,
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
    $order_shippings = model('select_order_shippings', [
        'select' => 'status, memo',
        'where'  => 'id = ' . $inserted_id,
    ]);

    $test_data = [
        $order_shippings[0],
    ];
    test_array_subset('update order_shipping', $test_data, [
        'status' => $test_order_shipping['status'],
        'memo'   => $test_order_shipping['memo'],
    ]);
}

// 更新と同時の明細登録テスト
{
    // 更新
    model('update_order_shippings', [
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
                'record_item_id' => $record_item_a_id,
                'quantity'       => 2,
            ],
        ],
    ]);

    // 結果
    $order_shipping_items = model('select_order_shipping_items', [
        'where' => 'shipping_id = ' . $inserted_id,
    ]);

    test_equals('update order_shipping (items)', count($order_shipping_items), 1);
    test_equals('update order_shipping (items quantity)', intval($order_shipping_items[0]['quantity']), 2);
}

// 削除テスト
{
    // 削除
    model('delete_order_shippings', [
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（取得対象からは外れること）
    $order_shippings = model('select_order_shippings', [
        'where' => 'id = ' . $inserted_id,
    ]);

    test_equals('delete order_shipping', count($order_shippings), 0);

    // 結果（レコード自体は残り、削除日時が入ること）
    $order_shippings = db_select([
        'select' => 'deleted',
        'from'   => DATABASE_PREFIX . 'order_shippings',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    test_equals('delete order_shipping (record)', count($order_shippings), 1);
    test_not_equals('delete order_shipping (deleted)', $order_shippings[0]['deleted'], null);

    // 結果（オプションを渡していないので、明細は残ること）
    $order_shipping_items = model('select_order_shipping_items', [
        'where' => 'shipping_id = ' . $inserted_id,
    ]);

    test_equals('delete order_shipping (items)', count($order_shipping_items), 1);
}

// 関連データの削除テスト
{
    // 削除
    model('delete_order_shippings', [
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
    $order_shipping_items = model('select_order_shipping_items', [
        'where' => 'shipping_id = ' . $inserted_id,
    ]);

    test_equals('delete order_shipping (associate)', count($order_shipping_items), 0);
}

// 物理削除テスト
{
    // データ
    $test_order_shipping = $data_order_shipping;
    $test_order_shipping['memo'] = '物理削除';

    // 登録
    model('insert_order_shippings', [
        'values' => model('normalize_order_shippings', $test_order_shipping),
    ]);

    // 削除
    model('delete_order_shippings', [
        'where' => 'memo = \'物理削除\'',
    ], [
        'softdelete' => false,
    ]);

    // 結果（レコード自体が消えること）
    $order_shippings = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'order_shippings',
        'where'  => 'memo = \'物理削除\'',
    ]);

    test_equals('delete order_shipping (physical)', count($order_shippings), 0);
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
        'plugins/order/app/models/order_shippings.php',
    ]);
}
