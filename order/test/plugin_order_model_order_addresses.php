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
import('plugins/order/app/models/order_addresses.php');

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_addresses;');

// 正常データ（会員ごとの住所録。注文時に選択させる処理は未実装）
$data_order_address = [
    'user_id'    => 1,
    'name_01'    => 'テスト',
    'name_02'    => '太郎',
    'kana_01'    => 'テスト',
    'kana_02'    => 'タロウ',
    'zipcode'    => '100-0001',
    'prefecture' => '東京都',
    'address_01' => '千代田区千代田1-1',
    'address_02' => '',
    'telephone'  => '0312345678',
];

// トランザクションを開始
db_transaction();

// 初期値テスト
{
    // 確認
    $default_order_address = model('default_order_addresses');

    // 結果
    test_equals('default order_address id', $default_order_address['id'], null);
    test_equals('default order_address user_id', $default_order_address['user_id'], 0);
    test_equals('default order_address name_01', $default_order_address['name_01'], '');
    test_equals('default order_address kana_01', $default_order_address['kana_01'], null);
    test_equals('default order_address zipcode', $default_order_address['zipcode'], '');
    test_equals('default order_address address_02', $default_order_address['address_02'], null);
    test_equals('default order_address telephone', $default_order_address['telephone'], '');
    test_equals('default order_address deleted', $default_order_address['deleted'], null);
    test_regexp('default order_address created', $default_order_address['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 正常登録テスト
{
    // データ
    $test_order_address = $data_order_address;

    // 登録
    $test_order_address = model('normalize_order_addresses', $test_order_address);
    $warnings           = model('validate_order_addresses', $test_order_address);

    // 結果（正常データでは警告が出ないこと）
    test_equals('validate order_address', count($warnings), 0);

    if (empty($warnings)) {
        model('insert_order_addresses', [
            'values' => $test_order_address,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $order_addresses = model('select_order_addresses', [
        'select'   => 'user_id, name_01, zipcode, address_01',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    $inserted_data = array_shift($order_addresses);
    $test_data     = [
        $inserted_data,
    ];
    test_array_subset('insert order_address', $test_data, [
        'user_id'    => $test_order_address['user_id'],
        'name_01'    => $test_order_address['name_01'],
        'zipcode'    => $test_order_address['zipcode'],
        'address_01' => $test_order_address['address_01'],
    ]);
}

// 登録した住所のIDを取得
$order_addresses = model('select_order_addresses', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($order_addresses[0]['id']);

// 郵便番号・電話番号の正規化（全角英数字）テスト
{
    // データ
    $test_order_address = $data_order_address;
    $test_order_address['zipcode']   = '１００－０００１';
    $test_order_address['telephone'] = '０３１２３４５６７８';

    // 確認
    $test_order_address = model('normalize_order_addresses', $test_order_address);

    // 結果
    test_equals('normalize order_address zipcode', $test_order_address['zipcode'], '100-0001');
    test_equals('normalize order_address telephone', $test_order_address['telephone'], '0312345678');
}

// 名前の必須テスト
{
    // データ
    $test_order_address = $data_order_address;
    $test_order_address['name_01'] = '';
    $test_order_address['name_02'] = '';

    // 確認
    $test_order_address = model('normalize_order_addresses', $test_order_address);
    $warnings           = model('validate_order_addresses', $test_order_address);

    // 結果
    test_equals('validate required order_address name', count($warnings), 2);
}

// 名前の長さテスト
{
    // データ
    $test_order_address = $data_order_address;
    $test_order_address['name_01'] = str_repeat('あ', 21);

    // 確認
    $test_order_address = model('normalize_order_addresses', $test_order_address);
    $warnings           = model('validate_order_addresses', $test_order_address);

    // 結果
    test_equals('validate max_length order_address name_01', count($warnings), 1);
}

// カナの未入力テスト
{
    // データ（カナは任意項目のため未入力でも警告は出ない）
    $test_order_address = $data_order_address;
    $test_order_address['kana_01'] = '';
    $test_order_address['kana_02'] = '';

    // 確認
    $test_order_address = model('normalize_order_addresses', $test_order_address);
    $warnings           = model('validate_order_addresses', $test_order_address);

    // 結果
    test_equals('validate empty order_address kana', count($warnings), 0);
}

// カナの書式テスト
{
    // データ（全角カタカナ以外）
    $test_order_address = $data_order_address;
    $test_order_address['kana_01'] = 'てすと';

    // 確認
    $test_order_address = model('normalize_order_addresses', $test_order_address);
    $warnings           = model('validate_order_addresses', $test_order_address);

    // 結果（注文記録・発送記録と同じく、全角カタカナで入力させる）
    test_equals('validate katakana order_address kana_01', count($warnings), 1);
}

// カナの長さテスト
{
    // データ
    $test_order_address = $data_order_address;
    $test_order_address['kana_01'] = str_repeat('ア', 21);

    // 確認
    $test_order_address = model('normalize_order_addresses', $test_order_address);
    $warnings           = model('validate_order_addresses', $test_order_address);

    // 結果
    test_equals('validate max_length order_address kana_01', count($warnings), 1);
}

// 郵便番号の必須テスト
{
    // データ
    $test_order_address = $data_order_address;
    $test_order_address['zipcode'] = '';

    // 確認
    $test_order_address = model('normalize_order_addresses', $test_order_address);
    $warnings           = model('validate_order_addresses', $test_order_address);

    // 結果
    test_equals('validate required order_address zipcode', count($warnings), 1);
}

// 郵便番号の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_order_address = $data_order_address;
    $test_order_address['zipcode'] = str_repeat('1', 8);

    // 確認
    $test_order_address = model('normalize_order_addresses', $test_order_address);
    $warnings           = model('validate_order_addresses', $test_order_address);

    // 結果
    test_equals('validate max_length order_address zipcode (boundary)', count($warnings), 0);
}

// 郵便番号の長さテスト
{
    // データ
    $test_order_address = $data_order_address;
    $test_order_address['zipcode'] = str_repeat('1', 9);

    // 確認
    $test_order_address = model('normalize_order_addresses', $test_order_address);
    $warnings           = model('validate_order_addresses', $test_order_address);

    // 結果
    test_equals('validate max_length order_address zipcode', count($warnings), 1);
}

// 都道府県の必須テスト
{
    // データ
    $test_order_address = $data_order_address;
    $test_order_address['prefecture'] = '';

    // 確認
    $test_order_address = model('normalize_order_addresses', $test_order_address);
    $warnings           = model('validate_order_addresses', $test_order_address);

    // 結果
    test_equals('validate required order_address prefecture', count($warnings), 1);
}

// 住所 1の必須テスト
{
    // データ
    $test_order_address = $data_order_address;
    $test_order_address['address_01'] = '';

    // 確認
    $test_order_address = model('normalize_order_addresses', $test_order_address);
    $warnings           = model('validate_order_addresses', $test_order_address);

    // 結果
    test_equals('validate required order_address address_01', count($warnings), 1);
}

// 住所の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_order_address = $data_order_address;
    $test_order_address['address_01'] = str_repeat('あ', 100);
    $test_order_address['address_02'] = str_repeat('あ', 100);

    // 確認
    $test_order_address = model('normalize_order_addresses', $test_order_address);
    $warnings           = model('validate_order_addresses', $test_order_address);

    // 結果
    test_equals('validate max_length order_address address (boundary)', count($warnings), 0);
}

// 住所の長さテスト
{
    // データ
    $test_order_address = $data_order_address;
    $test_order_address['address_01'] = str_repeat('あ', 101);
    $test_order_address['address_02'] = str_repeat('あ', 101);

    // 確認
    $test_order_address = model('normalize_order_addresses', $test_order_address);
    $warnings           = model('validate_order_addresses', $test_order_address);

    // 結果
    test_equals('validate max_length order_address address', count($warnings), 2);
}

// 住所 2の未入力テスト
{
    // データ（住所 2は任意項目のため未入力でも警告は出ない）
    $test_order_address = $data_order_address;
    $test_order_address['address_02'] = '';

    // 確認
    $test_order_address = model('normalize_order_addresses', $test_order_address);
    $warnings           = model('validate_order_addresses', $test_order_address);

    // 結果
    test_equals('validate empty order_address address_02', count($warnings), 0);
}

// 電話番号の必須テスト
{
    // データ
    $test_order_address = $data_order_address;
    $test_order_address['telephone'] = '';

    // 確認
    $test_order_address = model('normalize_order_addresses', $test_order_address);
    $warnings           = model('validate_order_addresses', $test_order_address);

    // 結果
    test_equals('validate required order_address telephone', count($warnings), 1);
}

// 電話番号の長さ（境界値）テスト
{
    // データ（ハイフン無しの11桁ちょうどのため警告は出ない）
    $test_order_address = $data_order_address;
    $test_order_address['telephone'] = str_repeat('1', 11);

    // 確認
    $test_order_address = model('normalize_order_addresses', $test_order_address);
    $warnings           = model('validate_order_addresses', $test_order_address);

    // 結果
    test_equals('validate max_length order_address telephone (boundary)', count($warnings), 0);
}

// 電話番号の長さテスト
{
    // データ（ハイフンを入れると12文字になり、上限を超える）
    $test_order_address = $data_order_address;
    $test_order_address['telephone'] = '03-1234-5678';

    // 確認
    $test_order_address = model('normalize_order_addresses', $test_order_address);
    $warnings           = model('validate_order_addresses', $test_order_address);

    // 結果
    test_equals('validate max_length order_address telephone', count($warnings), 1);
}

// 更新テスト
{
    // データ
    $test_order_address = $data_order_address;
    $test_order_address['name_02']    = '次郎';
    $test_order_address['address_01'] = '千代田区千代田2-2';

    // 更新
    $test_order_address = model('normalize_order_addresses', $test_order_address);
    $warnings           = model('validate_order_addresses', $test_order_address);
    if (empty($warnings)) {
        model('update_order_addresses', [
            'set'   => $test_order_address,
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
    $order_addresses = model('select_order_addresses', [
        'select' => 'name_02, address_01',
        'where'  => 'id = ' . $inserted_id,
    ]);

    $test_data = [
        $order_addresses[0],
    ];
    test_array_subset('update order_address', $test_data, [
        'name_02'    => $test_order_address['name_02'],
        'address_01' => $test_order_address['address_01'],
    ]);
}

// 削除テスト
{
    // 削除
    model('delete_order_addresses', [
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（取得対象からは外れること）
    $order_addresses = model('select_order_addresses', [
        'where' => 'id = ' . $inserted_id,
    ]);

    test_equals('delete order_address', count($order_addresses), 0);

    // 結果（レコード自体は残り、削除日時が入ること。コードを持たないので書き換えも無い）
    $order_addresses = db_select([
        'select' => 'deleted',
        'from'   => DATABASE_PREFIX . 'order_addresses',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    test_equals('delete order_address (record)', count($order_addresses), 1);
    test_not_equals('delete order_address (deleted)', $order_addresses[0]['deleted'], null);
}

// 物理削除テスト
{
    // データ
    $test_order_address = $data_order_address;
    $test_order_address['user_id'] = 2;

    // 登録
    model('insert_order_addresses', [
        'values' => model('normalize_order_addresses', $test_order_address),
    ]);

    // 削除
    model('delete_order_addresses', [
        'where' => 'user_id = 2',
    ], [
        'softdelete' => false,
    ]);

    // 結果（レコード自体が消えること）
    $order_addresses = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'order_addresses',
        'where'  => 'user_id = 2',
    ]);

    test_equals('delete order_address (physical)', count($order_addresses), 0);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_addresses;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'plugins/order/app/models/order_addresses.php',
    ]);
}
