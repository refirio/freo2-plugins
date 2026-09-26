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
import('plugins/order/app/models/order_stocks.php');

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_stocks;');

// 正常データ
$data_order_stock = [
    'code'       => 'teststock1',
    'name'       => 'テスト在庫1',
    'text'       => 'テストの在庫です。',
    'kind'       => 'analog',
    'download'   => '',
    'quantity'   => 10,
    'cost_price' => 100,
    'memo'       => '',
];

// トランザクションを開始
db_transaction();

// 初期値テスト
{
    // 確認
    $default_order_stock = model('default_order_stocks');

    // 結果
    test_equals('default order_stock id', $default_order_stock['id'], null);
    test_equals('default order_stock code', $default_order_stock['code'], '');
    test_equals('default order_stock name', $default_order_stock['name'], '');
    test_equals('default order_stock kind', $default_order_stock['kind'], '');
    test_equals('default order_stock text', $default_order_stock['text'], null);
    test_equals('default order_stock quantity', $default_order_stock['quantity'], null);
    test_equals('default order_stock cost_price', $default_order_stock['cost_price'], null);
    test_equals('default order_stock deleted', $default_order_stock['deleted'], null);
    test_regexp('default order_stock created', $default_order_stock['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 正常登録テスト
{
    // データ
    $test_order_stock = $data_order_stock;

    // 登録
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果（正常データでは警告が出ないこと）
    test_equals('validate order_stock', count($warnings), 0);

    if (empty($warnings)) {
        model('insert_order_stocks', [
            'values' => $test_order_stock,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $order_stocks = model('select_order_stocks', [
        'select'   => 'code, name, kind, quantity, cost_price',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    $inserted_data = array_shift($order_stocks);
    $test_data     = [
        $inserted_data,
    ];
    test_array_subset('insert order_stock', $test_data, [
        'code'       => $test_order_stock['code'],
        'name'       => $test_order_stock['name'],
        'kind'       => $test_order_stock['kind'],
        'quantity'   => $test_order_stock['quantity'],
        'cost_price' => $test_order_stock['cost_price'],
    ]);
}

// 登録した在庫のIDを取得
$order_stocks = model('select_order_stocks', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = $order_stocks[0]['id'];

// 登録日時・更新日時テスト
{
    // 確認
    $order_stocks = db_select([
        'select' => 'created, modified',
        'from'   => DATABASE_PREFIX . 'order_stocks',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（登録時に自動で入ること）
    test_regexp('insert order_stock created', $order_stocks[0]['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
    test_regexp('insert order_stock modified', $order_stocks[0]['modified'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 数の正規化（全角数字）テスト
{
    // データ
    $test_order_stock = $data_order_stock;
    $test_order_stock['quantity'] = '１２';

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);

    // 結果
    test_equals('normalize order_stock quantity', $test_order_stock['quantity'], '12');
}

// 原価の正規化（全角数字）テスト
{
    // データ
    $test_order_stock = $data_order_stock;
    $test_order_stock['cost_price'] = '３４５';

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);

    // 結果
    test_equals('normalize order_stock cost_price', $test_order_stock['cost_price'], '345');
}

// コードの必須テスト
{
    // データ
    $test_order_stock = $data_order_stock;
    $test_order_stock['code'] = '';

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate required order_stock code', count($warnings), 1);
}

// コードの書式テスト
{
    // データ
    $test_order_stock = $data_order_stock;
    $test_order_stock['code'] = 'あいうえお';

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate alpha_dash order_stock code', count($warnings), 1);
}

// コードの長さ（最小・境界値）テスト
{
    // データ（下限ちょうどのため警告は出ない）
    $test_order_stock = $data_order_stock;
    $test_order_stock['code'] = str_repeat('a', 2);

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate min_length order_stock code (boundary)', count($warnings), 0);
}

// コードの長さ（最小）テスト
{
    // データ
    $test_order_stock = $data_order_stock;
    $test_order_stock['code'] = str_repeat('a', 1);

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate min_length order_stock code', count($warnings), 1);
}

// コードの長さ（最大・境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_order_stock = $data_order_stock;
    $test_order_stock['code'] = str_repeat('a', 80);

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate max_length order_stock code (boundary)', count($warnings), 0);
}

// コードの長さ（最大）テスト
{
    // データ
    $test_order_stock = $data_order_stock;
    $test_order_stock['code'] = str_repeat('a', 81);

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate max_length order_stock code', count($warnings), 1);
}

// コードの重複テスト
{
    // データ（登録済みのコードと同じコードで新規登録）
    $test_order_stock = $data_order_stock;

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate duplicate order_stock code', count($warnings), 1);
}

// コードの重複（自分自身は除外）テスト
{
    // データ（登録済みの在庫自身を編集）
    $test_order_stock = $data_order_stock;
    $test_order_stock['id'] = $inserted_id;

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate duplicate order_stock code (self)', count($warnings), 0);
}

// コードの重複チェック無効テスト
{
    // データ（登録済みのコードと同じコード）
    $test_order_stock = $data_order_stock;

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock, [
        'duplicate' => false,
    ]);

    // 結果
    test_equals('validate duplicate order_stock code (disabled)', count($warnings), 0);
}

// 名前の必須テスト
{
    // データ
    $test_order_stock = $data_order_stock;
    $test_order_stock['code'] = 'teststock2';
    $test_order_stock['name'] = '';

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate required order_stock name', count($warnings), 1);
}

// 名前の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない。文字数で数えることの確認も兼ねてマルチバイト文字を使う）
    $test_order_stock = $data_order_stock;
    $test_order_stock['code'] = 'teststock2';
    $test_order_stock['name'] = str_repeat('あ', 20);

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate max_length order_stock name (boundary)', count($warnings), 0);
}

// 名前の長さテスト
{
    // データ
    $test_order_stock = $data_order_stock;
    $test_order_stock['code'] = 'teststock2';
    $test_order_stock['name'] = str_repeat('あ', 21);

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate max_length order_stock name', count($warnings), 1);
}

// 種類の必須テスト
{
    // データ
    $test_order_stock = $data_order_stock;
    $test_order_stock['code'] = 'teststock2';
    $test_order_stock['kind'] = '';

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate required order_stock kind', count($warnings), 1);
}

// 種類の選択肢テスト
{
    // データ（選択肢に無い値）
    $test_order_stock = $data_order_stock;
    $test_order_stock['code'] = 'teststock2';
    $test_order_stock['kind'] = 'unknown';

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate list order_stock kind', count($warnings), 1);
}

// 種類の選択肢（デジタルコンテンツ）テスト
{
    // データ（選択肢にある値）
    $test_order_stock = $data_order_stock;
    $test_order_stock['code'] = 'teststock2';
    $test_order_stock['kind'] = 'digital';

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate list order_stock kind (digital)', count($warnings), 0);
}

// 内容の未入力テスト
{
    // データ（内容は任意項目のため未入力でも警告は出ない）
    $test_order_stock = $data_order_stock;
    $test_order_stock['code'] = 'teststock2';
    $test_order_stock['text'] = '';

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate empty order_stock text', count($warnings), 0);
}

// 内容の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_order_stock = $data_order_stock;
    $test_order_stock['code'] = 'teststock2';
    $test_order_stock['text'] = str_repeat('あ', 5000);

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate max_length order_stock text (boundary)', count($warnings), 0);
}

// 内容の長さテスト
{
    // データ
    $test_order_stock = $data_order_stock;
    $test_order_stock['code'] = 'teststock2';
    $test_order_stock['text'] = str_repeat('あ', 5001);

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate max_length order_stock text', count($warnings), 1);
}

// ダウンロード案内の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_order_stock = $data_order_stock;
    $test_order_stock['code']     = 'teststock2';
    $test_order_stock['kind']     = 'digital';
    $test_order_stock['download'] = str_repeat('あ', 5000);

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate max_length order_stock download (boundary)', count($warnings), 0);
}

// ダウンロード案内の長さテスト
{
    // データ
    $test_order_stock = $data_order_stock;
    $test_order_stock['code']     = 'teststock2';
    $test_order_stock['kind']     = 'digital';
    $test_order_stock['download'] = str_repeat('あ', 5001);

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate max_length order_stock download', count($warnings), 1);
}

// 数の未入力テスト
{
    // データ（数は任意項目のため未入力でも警告は出ない。在庫を管理しない商品にあたる）
    $test_order_stock = $data_order_stock;
    $test_order_stock['code']     = 'teststock2';
    $test_order_stock['quantity'] = '';

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate empty order_stock quantity', count($warnings), 0);
}

// 数の書式テスト
{
    // データ
    $test_order_stock = $data_order_stock;
    $test_order_stock['code']     = 'teststock2';
    $test_order_stock['quantity'] = 'あ';

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate numeric order_stock quantity', count($warnings), 1);
}

// 数の桁数（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_order_stock = $data_order_stock;
    $test_order_stock['code']     = 'teststock2';
    $test_order_stock['quantity'] = str_repeat('1', 10);

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate max_length order_stock quantity (boundary)', count($warnings), 0);
}

// 数の桁数テスト
{
    // データ
    $test_order_stock = $data_order_stock;
    $test_order_stock['code']     = 'teststock2';
    $test_order_stock['quantity'] = str_repeat('1', 11);

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate max_length order_stock quantity', count($warnings), 1);
}

// 原価の書式テスト
{
    // データ
    $test_order_stock = $data_order_stock;
    $test_order_stock['code']       = 'teststock2';
    $test_order_stock['cost_price'] = 'あ';

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate numeric order_stock cost_price', count($warnings), 1);
}

// 原価の桁数（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_order_stock = $data_order_stock;
    $test_order_stock['code']       = 'teststock2';
    $test_order_stock['cost_price'] = str_repeat('1', 10);

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate max_length order_stock cost_price (boundary)', count($warnings), 0);
}

// 原価の桁数テスト
{
    // データ
    $test_order_stock = $data_order_stock;
    $test_order_stock['code']       = 'teststock2';
    $test_order_stock['cost_price'] = str_repeat('1', 11);

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate max_length order_stock cost_price', count($warnings), 1);
}

// 店舗用メモの長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_order_stock = $data_order_stock;
    $test_order_stock['code'] = 'teststock2';
    $test_order_stock['memo'] = str_repeat('あ', 5000);

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate max_length order_stock memo (boundary)', count($warnings), 0);
}

// 店舗用メモの長さテスト
{
    // データ
    $test_order_stock = $data_order_stock;
    $test_order_stock['code'] = 'teststock2';
    $test_order_stock['memo'] = str_repeat('あ', 5001);

    // 確認
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果
    test_equals('validate max_length order_stock memo', count($warnings), 1);
}

// 更新テスト
{
    // データ
    $test_order_stock = $data_order_stock;
    $test_order_stock['code']       = 'teststock2';
    $test_order_stock['name']       = 'テスト在庫2';
    $test_order_stock['kind']       = 'digital';
    $test_order_stock['quantity']   = 20;
    $test_order_stock['cost_price'] = 200;

    // 更新
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);
    if (empty($warnings)) {
        model('update_order_stocks', [
            'set'   => $test_order_stock,
            'where' => [
                'code = :code',
                [
                    'code' => 'teststock1',
                ],
            ],
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $order_stocks = model('select_order_stocks', [
        'select'   => 'code, name, kind, quantity, cost_price',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    $updated_data = array_shift($order_stocks);
    $test_data    = [
        $updated_data,
    ];
    test_array_subset('update order_stock', $test_data, [
        'code'       => $test_order_stock['code'],
        'name'       => $test_order_stock['name'],
        'kind'       => $test_order_stock['kind'],
        'quantity'   => $test_order_stock['quantity'],
        'cost_price' => $test_order_stock['cost_price'],
    ]);
}

// 削除テスト
{
    // 削除
    model('delete_order_stocks', [
        'where' => [
            'code = :code',
            [
                'code' => 'teststock2',
            ],
        ],
    ]);

    // 結果（取得対象からは外れること）
    $order_stocks = model('select_order_stocks', [
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    test_equals('delete order_stock', count($order_stocks), 0);

    // 結果（レコード自体は残り、削除日時とコードが書き換わること）
    $order_stocks = db_select([
        'select' => 'code, deleted',
        'from'   => DATABASE_PREFIX . 'order_stocks',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    test_equals('delete order_stock (record)', count($order_stocks), 1);
    test_not_equals('delete order_stock (deleted)', $order_stocks[0]['deleted'], null);
    test_regexp('delete order_stock (code)', $order_stocks[0]['code'], '^DELETED \d{14} teststock2$');
}

// 削除後の再登録テスト
{
    // データ（削除した在庫と同じコード。code は DB で UNIQUE なので、コードが退避されていないと INSERT が失敗する）
    $test_order_stock = $data_order_stock;
    $test_order_stock['code'] = 'teststock2';
    $test_order_stock['name'] = 'テスト在庫2（再登録）';

    // 登録
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);

    // 結果（検証を通ること）
    test_equals('validate order_stock code (deleted)', count($warnings), 0);

    if (empty($warnings)) {
        model('insert_order_stocks', [
            'values' => $test_order_stock,
        ]);
    } else {
        debug($warnings);
    }

    // 結果（登録できること）
    $order_stocks = model('select_order_stocks', [
        'select' => 'name',
        'where'  => [
            'code = :code',
            [
                'code' => 'teststock2',
            ],
        ],
    ]);

    test_equals('insert order_stock (deleted code)', count($order_stocks), 1);
}

// 物理削除テスト
{
    // データ
    $test_order_stock = $data_order_stock;
    $test_order_stock['code'] = 'teststock3';
    $test_order_stock['name'] = 'テスト在庫3';

    // 登録
    $test_order_stock = model('normalize_order_stocks', $test_order_stock);
    $warnings         = model('validate_order_stocks', $test_order_stock);
    if (empty($warnings)) {
        model('insert_order_stocks', [
            'values' => $test_order_stock,
        ]);
    } else {
        debug($warnings);
    }

    // 削除
    model('delete_order_stocks', [
        'where' => [
            'code = :code',
            [
                'code' => 'teststock3',
            ],
        ],
    ], [
        'softdelete' => false,
    ]);

    // 結果（レコード自体が消えること）
    $order_stocks = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'order_stocks',
        'where'  => [
            'code = :code',
            [
                'code' => 'teststock3',
            ],
        ],
    ]);

    test_equals('delete order_stock (physical)', count($order_stocks), 0);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'order_stocks;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'plugins/order/app/models/order_stocks.php',
    ]);
}
