<?php

// 設定ファイルを読み込み
import('app/config.php');
import('plugins/rewrite/config.php');

// コードカバレッジの記録を開始
if (!isset($_GET['_test'])) {
    service('coverage.php');
    service_coverage_start();
}

// ライブラリを読み込み（プラグインのモデルとサービスは自動で読み込まれない）
model('logs.php');
import('plugins/rewrite/app/models/rewrite_rules.php');
import('plugins/rewrite/app/services/rewrite_rule.php');

// リクエスト情報を用意
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'freo/2';

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'rewrite_rules;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

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
$data_rewrite_rules = [];
foreach ([1, 2, 3] as $index) {
    $data_rewrite_rules[] = [
        'id'       => '',
        'enabled'  => 1,
        'name'     => 'ルール' . $index,
        'url'      => '/sort' . $index,
        'rewrited' => '/entry',
        'type'     => 'view',
        'memo'     => '',
    ];
}

// トランザクションを開始
db_transaction();

// 正常登録テスト
{
    // データ
    $test_rewrite_rule = $data_rewrite_rule;

    // 登録（rewrite_post.php と同じく、values は明示的に組み立てる）
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
    $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);
    if (empty($warnings)) {
        service_rewrite_rule_insert([
            'values' => [
                'enabled'  => $test_rewrite_rule['enabled'],
                'name'     => $test_rewrite_rule['name'],
                'url'      => $test_rewrite_rule['url'],
                'rewrited' => $test_rewrite_rule['rewrited'],
                'type'     => $test_rewrite_rule['type'],
                'memo'     => $test_rewrite_rule['memo'],
                'sort'     => $test_rewrite_rule['sort'],
            ],
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $rewrite_rules = model('select_rewrite_rules', [
        'select'   => 'name, url, rewrited, type',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    $inserted_data = array_shift($rewrite_rules);
    $test_data     = [
        $inserted_data,
    ];
    test_array_subset('insert rewrite_rule', $test_data, [
        'name'     => $test_rewrite_rule['name'],
        'url'      => $test_rewrite_rule['url'],
        'rewrited' => $test_rewrite_rule['rewrited'],
        'type'     => $test_rewrite_rule['type'],
    ]);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'order_by' => 'id DESC',
    ]);

    test_equals('insert rewrite_rule log', count($logs), 1);
    test_equals('insert rewrite_rule log model', $logs[0]['model'], 'rewrite_rules');
    test_equals('insert rewrite_rule log exec', $logs[0]['exec'], 'insert');
    test_equals('insert rewrite_rule log ip', $logs[0]['ip'], '127.0.0.1');
}

// 登録したルールのIDを取得
$rewrite_rules = model('select_rewrite_rules', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($rewrite_rules[0]['id']);

// 更新テスト
{
    // データ
    $test_rewrite_rule = $data_rewrite_rule;
    $test_rewrite_rule['id']   = $inserted_id;
    $test_rewrite_rule['name'] = 'テストルール2';

    // 更新
    $test_rewrite_rule = model('normalize_rewrite_rules', $test_rewrite_rule);
    $warnings          = model('validate_rewrite_rules', $test_rewrite_rule);
    if (empty($warnings)) {
        service_rewrite_rule_update([
            'set'   => [
                'name' => $test_rewrite_rule['name'],
            ],
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
        'select' => 'name',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update rewrite_rule', $rewrite_rules[0]['name'], 'テストルール2');

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => [
            'exec = :exec',
            [
                'exec' => 'update',
            ],
        ],
        'order_by' => 'id DESC',
    ]);

    test_equals('update rewrite_rule log', count($logs), 1);
    test_equals('update rewrite_rule log model', $logs[0]['model'], 'rewrite_rules');
}

// 最終編集日時の確認テスト
{
    // 更新（編集開始後に更新されていないので、競合とは判定されない）
    service_rewrite_rule_update([
        'set'   => [
            'name' => 'テストルール3',
        ],
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ], [
        'id'     => $inserted_id,
        'update' => localdate('Y-m-d H:i:s'),
    ]);

    // 結果
    $rewrite_rules = model('select_rewrite_rules', [
        'select' => 'name',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update rewrite_rule (modified check)', $rewrite_rules[0]['name'], 'テストルール3');
}

// 操作ログの重複抑止テスト
{
    // 結果（service_log_record() は同じ model と exec の組み合わせを1リクエストにつき1回しか記録しない）
    $logs = model('select_logs', [
        'order_by' => 'id DESC',
    ]);

    test_equals('record rewrite_rule log once', count($logs), 2);
}

// 削除テスト
{
    // 削除（rewrite_delete.php と同じ条件）
    service_rewrite_rule_delete([
        'where' => [
            'rewrite_rules.id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果
    $rewrite_rules = model('select_rewrite_rules', [
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    test_equals('delete rewrite_rule', count($rewrite_rules), 0);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => [
            'exec = :exec',
            [
                'exec' => 'delete',
            ],
        ],
        'order_by' => 'id DESC',
    ]);

    test_equals('delete rewrite_rule log', count($logs), 1);
    test_equals('delete rewrite_rule log model', $logs[0]['model'], 'rewrite_rules');
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'rewrite_rules;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// トランザクションを開始
db_transaction();

// 並び順の一括変更テスト
{
    // 登録（並び順は正規化で 1, 2, 3 と採番される）
    foreach ($data_rewrite_rules as $rewrite_rule) {
        $rewrite_rule = model('normalize_rewrite_rules', $rewrite_rule);
        $warnings     = model('validate_rewrite_rules', $rewrite_rule);
        if (empty($warnings)) {
            unset($rewrite_rule['id']);

            service_rewrite_rule_insert([
                'values' => $rewrite_rule,
            ]);
        } else {
            debug($warnings);
        }
    }

    // 結果
    $rewrite_rules = model('select_rewrite_rules', [
        'select'   => 'id, sort',
        'order_by' => 'id',
    ]);

    test_equals('sort rewrite_rules (before)', array_map('intval', array_column($rewrite_rules, 'sort')), [1, 2, 3]);

    // 並び順を更新（IDは決め打ちにせず、登録済みのものを使う）
    $ids = array_column($rewrite_rules, 'id');

    service_rewrite_rule_sort([
        $ids[0] => 3,
        $ids[1] => 2,
        $ids[2] => 1,
    ]);

    // 結果
    $rewrite_rules = model('select_rewrite_rules', [
        'select'   => 'sort',
        'order_by' => 'id',
    ]);

    test_equals('sort rewrite_rules (after)', array_map('intval', array_column($rewrite_rules, 'sort')), [3, 2, 1]);

    // 結果（適用される順序も入れ替わること。bootstrap.php は並び順のとおりに調べて、最初に一致したルールだけを適用する）
    $rewrite_rules = model('select_rewrite_rules', [
        'where'    => 'enabled = 1',
        'order_by' => 'sort, id',
    ]);

    test_equals('sort rewrite_rules (apply order)', array_column($rewrite_rules, 'name'), ['ルール3', 'ルール2', 'ルール1']);

    // 結果（不正な値は無視されること）
    service_rewrite_rule_sort([
        $ids[0] => 'あ', // 並び順が数字でない
        'あ'    => 1,    // IDが不正
    ]);

    $rewrite_rules = model('select_rewrite_rules', [
        'select'   => 'sort',
        'order_by' => 'id',
    ]);

    test_equals('sort rewrite_rules (invalid)', array_map('intval', array_column($rewrite_rules, 'sort')), [3, 2, 1]);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'rewrite_rules;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'plugins/rewrite/app/services/rewrite_rule.php',
    ]);
}
