<?php

// 設定ファイルを読み込み
import('app/config.php');
import('plugins/rewrite/config.php');

// コードカバレッジの記録を開始
if (!isset($_GET['_test'])) {
    service('coverage.php');
    service_coverage_start();
}

// ライブラリを読み込み（プラグインのモデルは自動で読み込まれない）
import('plugins/rewrite/app/models/rewrite_rules.php');

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'rewrite_rules;');

// 正常データ（画面と同じく id を空で渡す）
$data_rewrite_rule = [
    'id'       => '',
    'enabled'  => 1,
    'name'     => 'テストルール1',
    'url'      => '/news',
    'rewrited' => '/entry',
    'type'     => 'view',
    'memo'     => '',
];

// トランザクションを開始
db_transaction();

// 初期値テスト
{
    // 確認
    $default_rewrite_rule = model('default_rewrite_rules');

    // 結果
    test_equals('default rewrite_rule id', $default_rewrite_rule['id'], null);
    test_equals('default rewrite_rule enabled', $default_rewrite_rule['enabled'], 1);
    test_equals('default rewrite_rule name', $default_rewrite_rule['name'], '');
    test_equals('default rewrite_rule type', $default_rewrite_rule['type'], '');
    test_equals('default rewrite_rule memo', $default_rewrite_rule['memo'], null);
    test_equals('default rewrite_rule sort', $default_rewrite_rule['sort'], 0);
    test_equals('default rewrite_rule deleted', $default_rewrite_rule['deleted'], null);
    test_regexp('default rewrite_rule created', $default_rewrite_rule['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 正常登録テスト
{
    // データ
    $test_rewrite_rule = $data_rewrite_rule;

    // 登録
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
    $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);

    // 結果（正常データでは警告が出ないこと）
    test_equals('validate rewrite_rule', count($warnings), 0);

    // 結果（並び順が自動で採番されること。1件目なので 1）
    test_equals('normalize rewrite_rule sort (first)', intval($test_rewrite_rule['sort']), 1);

    if (empty($warnings)) {
        unset($test_rewrite_rule['id']);

        model('insert_rewrite_rules', [
            'values' => $test_rewrite_rule,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $rewrite_rules = model('select_rewrite_rules', [
        'select'   => 'enabled, name, url, rewrited, type, sort',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    $inserted_data = array_shift($rewrite_rules);
    $test_data     = [
        $inserted_data,
    ];
    test_array_subset('insert rewrite_rule', $test_data, [
        'enabled'  => $test_rewrite_rule['enabled'],
        'name'     => $test_rewrite_rule['name'],
        'url'      => $test_rewrite_rule['url'],
        'rewrited' => $test_rewrite_rule['rewrited'],
        'type'     => $test_rewrite_rule['type'],
        'sort'     => $test_rewrite_rule['sort'],
    ]);
}

// 登録したルールのIDを取得
$rewrite_rules = model('select_rewrite_rules', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($rewrite_rules[0]['id']);

// 並び順の自動採番テスト
{
    // データ
    $test_rewrite_rule = $data_rewrite_rule;

    // 確認
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);

    // 結果（登録済みの最大値 + 1 になること）
    test_equals('normalize rewrite_rule sort (next)', intval($test_rewrite_rule['sort']), 2);
}

// 並び順の自動採番（編集）テスト
{
    // データ（編集では並び順を補わない。並び替えは一覧の操作で行うため）
    $test_rewrite_rule = $data_rewrite_rule;
    $test_rewrite_rule['id'] = $inserted_id;

    // 確認
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);

    // 結果
    test_array_not_haskey('normalize rewrite_rule sort (edit)', $test_rewrite_rule, 'sort');
}

// 並び順の正規化（全角数字）テスト
{
    // データ
    $test_rewrite_rule = $data_rewrite_rule;
    $test_rewrite_rule['sort'] = '１２';

    // 確認
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);

    // 結果
    test_equals('normalize rewrite_rule sort (zenkaku)', $test_rewrite_rule['sort'], '12');
}

// 有効の書式テスト
{
    // データ
    $test_rewrite_rule = $data_rewrite_rule;
    $test_rewrite_rule['enabled'] = 'あ';

    // 確認
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
    $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);

    // 結果
    test_equals('validate boolean rewrite_rule enabled', count($warnings), 1);
}

// 有効の書式（無効）テスト
{
    // データ（0 も正しい値）
    $test_rewrite_rule = $data_rewrite_rule;
    $test_rewrite_rule['enabled'] = 0;

    // 確認
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
    $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);

    // 結果
    test_equals('validate boolean rewrite_rule enabled (disabled)', count($warnings), 0);
}

// 名前の必須テスト
{
    // データ
    $test_rewrite_rule = $data_rewrite_rule;
    $test_rewrite_rule['name'] = '';

    // 確認
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
    $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);

    // 結果
    test_equals('validate required rewrite_rule name', count($warnings), 1);
}

// 名前の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない。文字数で数えることの確認も兼ねてマルチバイト文字を使う）
    $test_rewrite_rule = $data_rewrite_rule;
    $test_rewrite_rule['name'] = str_repeat('あ', 20);

    // 確認
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
    $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);

    // 結果
    test_equals('validate max_length rewrite_rule name (boundary)', count($warnings), 0);
}

// 名前の長さテスト
{
    // データ
    $test_rewrite_rule = $data_rewrite_rule;
    $test_rewrite_rule['name'] = str_repeat('あ', 21);

    // 確認
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
    $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);

    // 結果
    test_equals('validate max_length rewrite_rule name', count($warnings), 1);
}

// リライト前・リライト後は同じ検証なので、まとめて確認する
foreach (['url', 'rewrited'] as $key) {
    // 必須テスト
    {
        // データ
        $test_rewrite_rule = $data_rewrite_rule;
        $test_rewrite_rule[$key] = '';

        // 確認
        $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
        $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);

        // 結果
        test_equals('validate required rewrite_rule ' . $key, count($warnings), 1);
    }

    // 長さ（境界値）テスト
    {
        // データ（上限ちょうどのため警告は出ない。書式を満たすよう、スラッシュ + 半角英字で作る）
        $test_rewrite_rule = $data_rewrite_rule;
        $test_rewrite_rule[$key] = '/' . str_repeat('a', 199);

        // 確認
        $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
        $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);

        // 結果
        test_equals('validate max_length rewrite_rule ' . $key . ' (boundary)', count($warnings), 0);
    }

    // 長さテスト
    {
        // データ
        $test_rewrite_rule = $data_rewrite_rule;
        $test_rewrite_rule[$key] = '/' . str_repeat('a', 200);

        // 確認
        $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
        $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);

        // 結果
        test_equals('validate max_length rewrite_rule ' . $key, count($warnings), 1);
    }

    // 書式（通る値）テスト
    {
        // データ（スラッシュだけ・階層・ダッシュ・アンダーバー・末尾のスラッシュ）
        $valid_values = ['/', '/entry/detail/hello', '/news-2026_09/'];

        // 確認
        $counts = [];
        foreach ($valid_values as $value) {
            $test_rewrite_rule = $data_rewrite_rule;
            $test_rewrite_rule[$key] = $value;

            $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
            $counts[]          = count(model('validate_rewrite_rules', $test_rewrite_rule));
        }

        // 結果
        test_equals('validate regexp rewrite_rule ' . $key . ' (valid)', $counts, [0, 0, 0]);
    }

    // 書式（通らない値）テスト
    {
        // データ（スラッシュで始まらない・日本語・クエリ文字列・ドット・空白）
        $invalid_values = ['news', '/ニュース', '/news?page=1', '/news.html', '/news list'];

        // 確認
        $counts = [];
        foreach ($invalid_values as $value) {
            $test_rewrite_rule = $data_rewrite_rule;
            $test_rewrite_rule[$key] = $value;

            $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
            $counts[]          = count(model('validate_rewrite_rules', $test_rewrite_rule));
        }

        // 結果
        test_equals('validate regexp rewrite_rule ' . $key . ' (invalid)', $counts, [1, 1, 1, 1, 1]);
    }
}

// 挙動の必須テスト
{
    // データ
    $test_rewrite_rule = $data_rewrite_rule;
    $test_rewrite_rule['type'] = '';

    // 確認
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
    $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);

    // 結果
    test_equals('validate required rewrite_rule type', count($warnings), 1);
}

// 挙動の選択肢（転送）テスト
{
    // データ
    $test_rewrite_rule = $data_rewrite_rule;
    $test_rewrite_rule['type'] = 'redirect';

    // 確認
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
    $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);

    // 結果
    test_equals('validate list rewrite_rule type (redirect)', count($warnings), 0);
}

// 挙動の選択肢テスト
{
    // データ（選択肢に無い値。保存できると、URLが一致しても何もしないルールになる）
    $test_rewrite_rule = $data_rewrite_rule;
    $test_rewrite_rule['type'] = 'forward';

    // 確認
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
    $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);

    // 結果
    test_equals('validate list rewrite_rule type', count($warnings), 1);
}

// 挙動の選択肢（ラベル）テスト
{
    // データ（比較するのは選択肢のキーで、ラベルでは通らない）
    $test_rewrite_rule = $data_rewrite_rule;
    $test_rewrite_rule['type'] = '表示';

    // 確認
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
    $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);

    // 結果
    test_equals('validate list rewrite_rule type (label)', count($warnings), 1);
}

// メモの未入力テスト
{
    // データ（メモは任意項目のため未入力でも警告は出ない）
    $test_rewrite_rule = $data_rewrite_rule;
    $test_rewrite_rule['memo'] = '';

    // 確認
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
    $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);

    // 結果
    test_equals('validate empty rewrite_rule memo', count($warnings), 0);
}

// メモの長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_rewrite_rule = $data_rewrite_rule;
    $test_rewrite_rule['memo'] = str_repeat('あ', 5000);

    // 確認
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
    $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);

    // 結果
    test_equals('validate max_length rewrite_rule memo (boundary)', count($warnings), 0);
}

// メモの長さテスト
{
    // データ
    $test_rewrite_rule = $data_rewrite_rule;
    $test_rewrite_rule['memo'] = str_repeat('あ', 5001);

    // 確認
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
    $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);

    // 結果
    test_equals('validate max_length rewrite_rule memo', count($warnings), 1);
}

// 並び順の書式テスト
{
    // データ
    $test_rewrite_rule = $data_rewrite_rule;
    $test_rewrite_rule['sort'] = 'あ';

    // 確認
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
    $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);

    // 結果
    test_equals('validate numeric rewrite_rule sort', count($warnings), 1);
}

// 並び順の桁数（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_rewrite_rule = $data_rewrite_rule;
    $test_rewrite_rule['sort'] = str_repeat('1', 5);

    // 確認
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
    $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);

    // 結果
    test_equals('validate max_length rewrite_rule sort (boundary)', count($warnings), 0);
}

// 並び順の桁数テスト
{
    // データ
    $test_rewrite_rule = $data_rewrite_rule;
    $test_rewrite_rule['sort'] = str_repeat('1', 6);

    // 確認
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
    $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);

    // 結果
    test_equals('validate max_length rewrite_rule sort', count($warnings), 1);
}

// 更新テスト
{
    // データ
    $test_rewrite_rule = $data_rewrite_rule;
    $test_rewrite_rule['id']       = $inserted_id;
    $test_rewrite_rule['enabled']  = 0;
    $test_rewrite_rule['name']     = 'テストルール2';
    $test_rewrite_rule['rewrited'] = '/page/news';
    $test_rewrite_rule['type']     = 'redirect';

    // 更新
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
    $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);
    if (empty($warnings)) {
        unset($test_rewrite_rule['id']);

        model('update_rewrite_rules', [
            'set'   => $test_rewrite_rule,
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
    $rewrite_rules = model('select_rewrite_rules', [
        'select' => 'enabled, name, rewrited, type',
        'where'  => 'id = ' . $inserted_id,
    ]);

    $test_data = [
        $rewrite_rules[0],
    ];
    test_array_subset('update rewrite_rule', $test_data, [
        'enabled'  => $test_rewrite_rule['enabled'],
        'name'     => $test_rewrite_rule['name'],
        'rewrited' => $test_rewrite_rule['rewrited'],
        'type'     => $test_rewrite_rule['type'],
    ]);
}

// 有効なルールの取得テスト
{
    // データ（有効なルールを2件、並び順を逆にして登録する。1件目は更新テストで無効になっている）
    foreach ([['テストルール3', '/b', 20], ['テストルール4', '/a', 10]] as $rule) {
        model('insert_rewrite_rules', [
            'values' => [
                'enabled'  => 1,
                'name'     => $rule[0],
                'url'      => $rule[1],
                'rewrited' => '/entry',
                'type'     => 'view',
                'sort'     => $rule[2],
            ],
        ]);
    }

    // 確認（bootstrap.php と同じ条件で取得する）
    $rewrite_rules = model('select_rewrite_rules', [
        'where'    => 'enabled = 1',
        'order_by' => 'sort, id',
    ]);

    // 結果（無効なルールは含まれず、並び順のとおりに並ぶこと。最初に一致したルールだけが適用されるので、順序が重要）
    test_equals('select enabled rewrite_rules', array_column($rewrite_rules, 'name'), ['テストルール4', 'テストルール3']);
}

// 削除テスト
{
    // 削除
    model('delete_rewrite_rules', [
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（取得対象からは外れること）
    $rewrite_rules = model('select_rewrite_rules', [
        'where' => 'id = ' . $inserted_id,
    ]);

    test_equals('delete rewrite_rule', count($rewrite_rules), 0);

    // 結果（レコード自体は残り、削除日時が入ること。コードを持たないので書き換えも無い）
    $rewrite_rules = db_select([
        'select' => 'deleted',
        'from'   => DATABASE_PREFIX . 'rewrite_rules',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    test_equals('delete rewrite_rule (record)', count($rewrite_rules), 1);
    test_not_equals('delete rewrite_rule (deleted)', $rewrite_rules[0]['deleted'], null);
}

// 並び順の自動採番（削除済みを除く）テスト
{
    // データ（削除済みのルールは採番の対象にしない。残っているのは並び順 10・20 の2件）
    $test_rewrite_rule = $data_rewrite_rule;

    // 確認
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);

    // 結果
    test_equals('normalize rewrite_rule sort (deleted)', intval($test_rewrite_rule['sort']), 21);
}

// 物理削除テスト
{
    // 削除
    model('delete_rewrite_rules', [
        'where' => 'name = \'テストルール3\'',
    ], [
        'softdelete' => false,
    ]);

    // 結果（レコード自体が消えること）
    $rewrite_rules = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'rewrite_rules',
        'where'  => 'name = \'テストルール3\'',
    ]);

    test_equals('delete rewrite_rule (physical)', count($rewrite_rules), 0);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'rewrite_rules;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'plugins/rewrite/app/models/rewrite_rules.php',
    ]);
}
