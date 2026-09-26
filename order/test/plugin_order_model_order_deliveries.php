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
import('plugins/order/app/models/order_deliveries.php');

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_deliveries;');

// 正常データ（配送方法のマスタ。送料計算の実処理は未実装）
$data_order_delivery = [
    'enabled'    => 1,
    'name'       => '宅配便',
    'text'       => '最短翌日にお届けします。',
    'cost'       => 500,
    'surcharge'  => '',
    'calculate'  => 'order',
    'threshold'  => 5000,
    'discounted' => 0,
    'memo'       => '',
];

// トランザクションを開始
db_transaction();

// 初期値テスト
{
    // 確認
    $default_order_delivery = model('default_order_deliveries');

    // 結果
    test_equals('default order_delivery id', $default_order_delivery['id'], null);
    test_equals('default order_delivery enabled', $default_order_delivery['enabled'], 1);
    test_equals('default order_delivery name', $default_order_delivery['name'], '');
    test_equals('default order_delivery text', $default_order_delivery['text'], null);
    test_equals('default order_delivery cost', $default_order_delivery['cost'], 0);
    test_equals('default order_delivery surcharge', $default_order_delivery['surcharge'], null);
    test_equals('default order_delivery calculate', $default_order_delivery['calculate'], '');
    test_equals('default order_delivery threshold', $default_order_delivery['threshold'], null);
    test_equals('default order_delivery deleted', $default_order_delivery['deleted'], null);
    test_regexp('default order_delivery created', $default_order_delivery['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 正常登録テスト
{
    // データ
    $test_order_delivery = $data_order_delivery;

    // 登録
    $test_order_delivery = model('normalize_order_deliveries', $test_order_delivery);
    $warnings            = model('validate_order_deliveries', $test_order_delivery);

    // 結果（正常データでは警告が出ないこと）
    test_equals('validate order_delivery', count($warnings), 0);

    if (empty($warnings)) {
        model('insert_order_deliveries', [
            'values' => $test_order_delivery,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $order_deliveries = model('select_order_deliveries', [
        'select'   => 'enabled, name, cost, calculate',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    $inserted_data = array_shift($order_deliveries);
    $test_data     = [
        $inserted_data,
    ];
    test_array_subset('insert order_delivery', $test_data, [
        'enabled'   => $test_order_delivery['enabled'],
        'name'      => $test_order_delivery['name'],
        'cost'      => $test_order_delivery['cost'],
        'calculate' => $test_order_delivery['calculate'],
    ]);
}

// 登録した配送方法のIDを取得
$order_deliveries = model('select_order_deliveries', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($order_deliveries[0]['id']);

// 金額の正規化（全角数字）テスト
{
    // データ
    $test_order_delivery = $data_order_delivery;
    $test_order_delivery['cost']       = '５００';
    $test_order_delivery['threshold']  = '５０００';
    $test_order_delivery['discounted'] = '１００';

    // 確認
    $test_order_delivery = model('normalize_order_deliveries', $test_order_delivery);

    // 結果
    test_equals('normalize order_delivery cost', $test_order_delivery['cost'], '500');
    test_equals('normalize order_delivery threshold', $test_order_delivery['threshold'], '5000');
    test_equals('normalize order_delivery discounted', $test_order_delivery['discounted'], '100');
}

// 有効の書式テスト
{
    // データ
    $test_order_delivery = $data_order_delivery;
    $test_order_delivery['enabled'] = 'あ';

    // 確認
    $test_order_delivery = model('normalize_order_deliveries', $test_order_delivery);
    $warnings            = model('validate_order_deliveries', $test_order_delivery);

    // 結果
    test_equals('validate boolean order_delivery enabled', count($warnings), 1);
}

// 有効の書式（無効）テスト
{
    // データ（0 も正しい値）
    $test_order_delivery = $data_order_delivery;
    $test_order_delivery['enabled'] = 0;

    // 確認
    $test_order_delivery = model('normalize_order_deliveries', $test_order_delivery);
    $warnings            = model('validate_order_deliveries', $test_order_delivery);

    // 結果
    test_equals('validate boolean order_delivery enabled (disabled)', count($warnings), 0);
}

// 名前の必須テスト
{
    // データ
    $test_order_delivery = $data_order_delivery;
    $test_order_delivery['name'] = '';

    // 確認
    $test_order_delivery = model('normalize_order_deliveries', $test_order_delivery);
    $warnings            = model('validate_order_deliveries', $test_order_delivery);

    // 結果
    test_equals('validate required order_delivery name', count($warnings), 1);
}

// 名前の長さテスト
{
    // データ
    $test_order_delivery = $data_order_delivery;
    $test_order_delivery['name'] = str_repeat('あ', 21);

    // 確認
    $test_order_delivery = model('normalize_order_deliveries', $test_order_delivery);
    $warnings            = model('validate_order_deliveries', $test_order_delivery);

    // 結果
    test_equals('validate max_length order_delivery name', count($warnings), 1);
}

// 内容の長さテスト
{
    // データ
    $test_order_delivery = $data_order_delivery;
    $test_order_delivery['text'] = str_repeat('あ', 5001);

    // 確認
    $test_order_delivery = model('normalize_order_deliveries', $test_order_delivery);
    $warnings            = model('validate_order_deliveries', $test_order_delivery);

    // 結果
    test_equals('validate max_length order_delivery text', count($warnings), 1);
}

// 送料の必須テスト
{
    // データ（送料は無料でも 0 を入れる）
    $test_order_delivery = $data_order_delivery;
    $test_order_delivery['cost'] = '';

    // 確認
    $test_order_delivery = model('normalize_order_deliveries', $test_order_delivery);
    $warnings            = model('validate_order_deliveries', $test_order_delivery);

    // 結果
    test_equals('validate required order_delivery cost', count($warnings), 1);
}

// 送料の書式テスト
{
    // データ
    $test_order_delivery = $data_order_delivery;
    $test_order_delivery['cost'] = 'あ';

    // 確認
    $test_order_delivery = model('normalize_order_deliveries', $test_order_delivery);
    $warnings            = model('validate_order_deliveries', $test_order_delivery);

    // 結果
    test_equals('validate numeric order_delivery cost', count($warnings), 1);
}

// 送料の桁数テスト
{
    // データ
    $test_order_delivery = $data_order_delivery;
    $test_order_delivery['cost'] = str_repeat('1', 11);

    // 確認
    $test_order_delivery = model('normalize_order_deliveries', $test_order_delivery);
    $warnings            = model('validate_order_deliveries', $test_order_delivery);

    // 結果
    test_equals('validate max_length order_delivery cost', count($warnings), 1);
}

// 送料（上乗せ）の未入力テスト
{
    // データ（都道府県ごとの上乗せ料金を JSON で持つ項目。任意）
    $test_order_delivery = $data_order_delivery;
    $test_order_delivery['surcharge'] = '';

    // 確認
    $test_order_delivery = model('normalize_order_deliveries', $test_order_delivery);
    $warnings            = model('validate_order_deliveries', $test_order_delivery);

    // 結果
    test_equals('validate empty order_delivery surcharge', count($warnings), 0);
}

// 送料（上乗せ）の長さテスト
{
    // データ
    $test_order_delivery = $data_order_delivery;
    $test_order_delivery['surcharge'] = str_repeat('あ', 5001);

    // 確認
    $test_order_delivery = model('normalize_order_deliveries', $test_order_delivery);
    $warnings            = model('validate_order_deliveries', $test_order_delivery);

    // 結果
    test_equals('validate max_length order_delivery surcharge', count($warnings), 1);
}

// 送料計算の必須テスト
{
    // データ
    $test_order_delivery = $data_order_delivery;
    $test_order_delivery['calculate'] = '';

    // 確認
    $test_order_delivery = model('normalize_order_deliveries', $test_order_delivery);
    $warnings            = model('validate_order_deliveries', $test_order_delivery);

    // 結果
    test_equals('validate required order_delivery calculate', count($warnings), 1);
}

// 送料計算の選択肢テスト
{
    // データ（選択肢に無い値）
    $test_order_delivery = $data_order_delivery;
    $test_order_delivery['calculate'] = 'unknown';

    // 確認
    $test_order_delivery = model('normalize_order_deliveries', $test_order_delivery);
    $warnings            = model('validate_order_deliveries', $test_order_delivery);

    // 結果
    test_equals('validate list order_delivery calculate', count($warnings), 1);
}

// 送料計算の選択肢（1商品ごと）テスト
{
    // データ（選択肢にある値）
    $test_order_delivery = $data_order_delivery;
    $test_order_delivery['calculate'] = 'product';

    // 確認
    $test_order_delivery = model('normalize_order_deliveries', $test_order_delivery);
    $warnings            = model('validate_order_deliveries', $test_order_delivery);

    // 結果
    test_equals('validate list order_delivery calculate (product)', count($warnings), 0);
}

// 値引きの未入力テスト
{
    // データ（閾値と値引き後の送料は任意項目。送料無料の設定をしない場合は空にする）
    $test_order_delivery = $data_order_delivery;
    $test_order_delivery['threshold']  = '';
    $test_order_delivery['discounted'] = '';

    // 確認
    $test_order_delivery = model('normalize_order_deliveries', $test_order_delivery);
    $warnings            = model('validate_order_deliveries', $test_order_delivery);

    // 結果
    test_equals('validate empty order_delivery threshold', count($warnings), 0);
}

// 値引きの書式テスト
{
    // データ
    $test_order_delivery = $data_order_delivery;
    $test_order_delivery['threshold']  = 'あ';
    $test_order_delivery['discounted'] = 'あ';

    // 確認
    $test_order_delivery = model('normalize_order_deliveries', $test_order_delivery);
    $warnings            = model('validate_order_deliveries', $test_order_delivery);

    // 結果
    test_equals('validate numeric order_delivery threshold', count($warnings), 2);
}

// 値引きの桁数テスト
{
    // データ
    $test_order_delivery = $data_order_delivery;
    $test_order_delivery['discounted'] = str_repeat('1', 11);

    // 確認
    $test_order_delivery = model('normalize_order_deliveries', $test_order_delivery);
    $warnings            = model('validate_order_deliveries', $test_order_delivery);

    // 結果
    test_equals('validate max_length order_delivery discounted', count($warnings), 1);
}

// 店舗用メモの長さテスト
{
    // データ
    $test_order_delivery = $data_order_delivery;
    $test_order_delivery['memo'] = str_repeat('あ', 5001);

    // 確認
    $test_order_delivery = model('normalize_order_deliveries', $test_order_delivery);
    $warnings            = model('validate_order_deliveries', $test_order_delivery);

    // 結果
    test_equals('validate max_length order_delivery memo', count($warnings), 1);
}

// 更新テスト
{
    // データ
    $test_order_delivery = $data_order_delivery;
    $test_order_delivery['name']      = 'メール便';
    $test_order_delivery['cost']      = 200;
    $test_order_delivery['calculate'] = 'product';

    // 更新
    $test_order_delivery = model('normalize_order_deliveries', $test_order_delivery);
    $warnings            = model('validate_order_deliveries', $test_order_delivery);
    if (empty($warnings)) {
        model('update_order_deliveries', [
            'set'   => $test_order_delivery,
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
    $order_deliveries = model('select_order_deliveries', [
        'select' => 'name, cost, calculate',
        'where'  => 'id = ' . $inserted_id,
    ]);

    $test_data = [
        $order_deliveries[0],
    ];
    test_array_subset('update order_delivery', $test_data, [
        'name'      => $test_order_delivery['name'],
        'cost'      => $test_order_delivery['cost'],
        'calculate' => $test_order_delivery['calculate'],
    ]);
}

// 削除テスト
{
    // 削除
    model('delete_order_deliveries', [
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（取得対象からは外れること）
    $order_deliveries = model('select_order_deliveries', [
        'where' => 'id = ' . $inserted_id,
    ]);

    test_equals('delete order_delivery', count($order_deliveries), 0);

    // 結果（レコード自体は残り、削除日時が入ること。コードを持たないので書き換えも無い）
    $order_deliveries = db_select([
        'select' => 'deleted',
        'from'   => DATABASE_PREFIX . 'order_deliveries',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    test_equals('delete order_delivery (record)', count($order_deliveries), 1);
    test_not_equals('delete order_delivery (deleted)', $order_deliveries[0]['deleted'], null);
}

// 物理削除テスト
{
    // データ
    $test_order_delivery = $data_order_delivery;
    $test_order_delivery['name'] = '店頭受け取り';

    // 登録
    model('insert_order_deliveries', [
        'values' => model('normalize_order_deliveries', $test_order_delivery),
    ]);

    // 削除
    model('delete_order_deliveries', [
        'where' => 'name = \'店頭受け取り\'',
    ], [
        'softdelete' => false,
    ]);

    // 結果（レコード自体が消えること）
    $order_deliveries = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'order_deliveries',
        'where'  => 'name = \'店頭受け取り\'',
    ]);

    test_equals('delete order_delivery (physical)', count($order_deliveries), 0);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_deliveries;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'plugins/order/app/models/order_deliveries.php',
    ]);
}
