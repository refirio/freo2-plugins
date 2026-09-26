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
import('plugins/order/app/models/order_payments.php');

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_payments;');

// 正常データ（支払方法は注文画面で選ばせるためのマスタ。決済の実処理は未実装）
$data_order_payment = [
    'enabled' => 1,
    'name'    => '銀行振込',
    'text'    => 'ご注文後、指定の口座にお振り込みください。',
    'fee'     => 330,
    'memo'    => '',
];

// トランザクションを開始
db_transaction();

// 初期値テスト
{
    // 確認
    $default_order_payment = model('default_order_payments');

    // 結果
    test_equals('default order_payment id', $default_order_payment['id'], null);
    test_equals('default order_payment enabled', $default_order_payment['enabled'], 1);
    test_equals('default order_payment name', $default_order_payment['name'], '');
    test_equals('default order_payment text', $default_order_payment['text'], null);
    test_equals('default order_payment fee', $default_order_payment['fee'], 0);
    test_equals('default order_payment memo', $default_order_payment['memo'], null);
    test_equals('default order_payment deleted', $default_order_payment['deleted'], null);
    test_regexp('default order_payment created', $default_order_payment['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 正常登録テスト
{
    // データ
    $test_order_payment = $data_order_payment;

    // 登録
    $test_order_payment = model('normalize_order_payments', $test_order_payment);
    $warnings           = model('validate_order_payments', $test_order_payment);

    // 結果（正常データでは警告が出ないこと）
    test_equals('validate order_payment', count($warnings), 0);

    if (empty($warnings)) {
        model('insert_order_payments', [
            'values' => $test_order_payment,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $order_payments = model('select_order_payments', [
        'select'   => 'enabled, name, fee',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    $inserted_data = array_shift($order_payments);
    $test_data     = [
        $inserted_data,
    ];
    test_array_subset('insert order_payment', $test_data, [
        'enabled' => $test_order_payment['enabled'],
        'name'    => $test_order_payment['name'],
        'fee'     => $test_order_payment['fee'],
    ]);
}

// 登録した支払方法のIDを取得
$order_payments = model('select_order_payments', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($order_payments[0]['id']);

// 手数料の正規化（全角数字）テスト
{
    // データ
    $test_order_payment = $data_order_payment;
    $test_order_payment['fee'] = '３３０';

    // 確認
    $test_order_payment = model('normalize_order_payments', $test_order_payment);

    // 結果
    test_equals('normalize order_payment fee', $test_order_payment['fee'], '330');
}

// 有効の書式テスト
{
    // データ
    $test_order_payment = $data_order_payment;
    $test_order_payment['enabled'] = 'あ';

    // 確認
    $test_order_payment = model('normalize_order_payments', $test_order_payment);
    $warnings           = model('validate_order_payments', $test_order_payment);

    // 結果
    test_equals('validate boolean order_payment enabled', count($warnings), 1);
}

// 有効の書式（無効）テスト
{
    // データ（0 も正しい値）
    $test_order_payment = $data_order_payment;
    $test_order_payment['enabled'] = 0;

    // 確認
    $test_order_payment = model('normalize_order_payments', $test_order_payment);
    $warnings           = model('validate_order_payments', $test_order_payment);

    // 結果
    test_equals('validate boolean order_payment enabled (disabled)', count($warnings), 0);
}

// 名前の必須テスト
{
    // データ
    $test_order_payment = $data_order_payment;
    $test_order_payment['name'] = '';

    // 確認
    $test_order_payment = model('normalize_order_payments', $test_order_payment);
    $warnings           = model('validate_order_payments', $test_order_payment);

    // 結果
    test_equals('validate required order_payment name', count($warnings), 1);
}

// 名前の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない。文字数で数えることの確認も兼ねてマルチバイト文字を使う）
    $test_order_payment = $data_order_payment;
    $test_order_payment['name'] = str_repeat('あ', 20);

    // 確認
    $test_order_payment = model('normalize_order_payments', $test_order_payment);
    $warnings           = model('validate_order_payments', $test_order_payment);

    // 結果
    test_equals('validate max_length order_payment name (boundary)', count($warnings), 0);
}

// 名前の長さテスト
{
    // データ
    $test_order_payment = $data_order_payment;
    $test_order_payment['name'] = str_repeat('あ', 21);

    // 確認
    $test_order_payment = model('normalize_order_payments', $test_order_payment);
    $warnings           = model('validate_order_payments', $test_order_payment);

    // 結果
    test_equals('validate max_length order_payment name', count($warnings), 1);
}

// 内容の未入力テスト
{
    // データ（内容は任意項目のため未入力でも警告は出ない）
    $test_order_payment = $data_order_payment;
    $test_order_payment['text'] = '';

    // 確認
    $test_order_payment = model('normalize_order_payments', $test_order_payment);
    $warnings           = model('validate_order_payments', $test_order_payment);

    // 結果
    test_equals('validate empty order_payment text', count($warnings), 0);
}

// 内容の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_order_payment = $data_order_payment;
    $test_order_payment['text'] = str_repeat('あ', 5000);

    // 確認
    $test_order_payment = model('normalize_order_payments', $test_order_payment);
    $warnings           = model('validate_order_payments', $test_order_payment);

    // 結果
    test_equals('validate max_length order_payment text (boundary)', count($warnings), 0);
}

// 内容の長さテスト
{
    // データ
    $test_order_payment = $data_order_payment;
    $test_order_payment['text'] = str_repeat('あ', 5001);

    // 確認
    $test_order_payment = model('normalize_order_payments', $test_order_payment);
    $warnings           = model('validate_order_payments', $test_order_payment);

    // 結果
    test_equals('validate max_length order_payment text', count($warnings), 1);
}

// 手数料の必須テスト
{
    // データ（手数料は無料でも 0 を入れる）
    $test_order_payment = $data_order_payment;
    $test_order_payment['fee'] = '';

    // 確認
    $test_order_payment = model('normalize_order_payments', $test_order_payment);
    $warnings           = model('validate_order_payments', $test_order_payment);

    // 結果
    test_equals('validate required order_payment fee', count($warnings), 1);
}

// 手数料の書式テスト
{
    // データ
    $test_order_payment = $data_order_payment;
    $test_order_payment['fee'] = 'あ';

    // 確認
    $test_order_payment = model('normalize_order_payments', $test_order_payment);
    $warnings           = model('validate_order_payments', $test_order_payment);

    // 結果
    test_equals('validate numeric order_payment fee', count($warnings), 1);
}

// 手数料の桁数（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_order_payment = $data_order_payment;
    $test_order_payment['fee'] = str_repeat('1', 10);

    // 確認
    $test_order_payment = model('normalize_order_payments', $test_order_payment);
    $warnings           = model('validate_order_payments', $test_order_payment);

    // 結果
    test_equals('validate max_length order_payment fee (boundary)', count($warnings), 0);
}

// 手数料の桁数テスト
{
    // データ
    $test_order_payment = $data_order_payment;
    $test_order_payment['fee'] = str_repeat('1', 11);

    // 確認
    $test_order_payment = model('normalize_order_payments', $test_order_payment);
    $warnings           = model('validate_order_payments', $test_order_payment);

    // 結果
    test_equals('validate max_length order_payment fee', count($warnings), 1);
}

// 店舗用メモの長さテスト
{
    // データ
    $test_order_payment = $data_order_payment;
    $test_order_payment['memo'] = str_repeat('あ', 5001);

    // 確認
    $test_order_payment = model('normalize_order_payments', $test_order_payment);
    $warnings           = model('validate_order_payments', $test_order_payment);

    // 結果
    test_equals('validate max_length order_payment memo', count($warnings), 1);
}

// 更新テスト
{
    // データ
    $test_order_payment = $data_order_payment;
    $test_order_payment['enabled'] = 0;
    $test_order_payment['name']    = '代金引換';
    $test_order_payment['fee']     = 440;

    // 更新
    $test_order_payment = model('normalize_order_payments', $test_order_payment);
    $warnings           = model('validate_order_payments', $test_order_payment);
    if (empty($warnings)) {
        model('update_order_payments', [
            'set'   => $test_order_payment,
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
    $order_payments = model('select_order_payments', [
        'select' => 'enabled, name, fee',
        'where'  => 'id = ' . $inserted_id,
    ]);

    $test_data = [
        $order_payments[0],
    ];
    test_array_subset('update order_payment', $test_data, [
        'enabled' => $test_order_payment['enabled'],
        'name'    => $test_order_payment['name'],
        'fee'     => $test_order_payment['fee'],
    ]);
}

// 削除テスト
{
    // 削除
    model('delete_order_payments', [
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（取得対象からは外れること）
    $order_payments = model('select_order_payments', [
        'where' => 'id = ' . $inserted_id,
    ]);

    test_equals('delete order_payment', count($order_payments), 0);

    // 結果（レコード自体は残り、削除日時が入ること。コードを持たないので書き換えも無い）
    $order_payments = db_select([
        'select' => 'deleted',
        'from'   => DATABASE_PREFIX . 'order_payments',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    test_equals('delete order_payment (record)', count($order_payments), 1);
    test_not_equals('delete order_payment (deleted)', $order_payments[0]['deleted'], null);
}

// 物理削除テスト
{
    // データ
    $test_order_payment = $data_order_payment;
    $test_order_payment['name'] = 'クレジットカード';

    // 登録
    model('insert_order_payments', [
        'values' => model('normalize_order_payments', $test_order_payment),
    ]);

    // 削除
    model('delete_order_payments', [
        'where' => 'name = \'クレジットカード\'',
    ], [
        'softdelete' => false,
    ]);

    // 結果（レコード自体が消えること）
    $order_payments = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'order_payments',
        'where'  => 'name = \'クレジットカード\'',
    ]);

    test_equals('delete order_payment (physical)', count($order_payments), 0);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_payments;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'plugins/order/app/models/order_payments.php',
    ]);
}
