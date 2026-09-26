<?php

import('libs/modules/validator.php');

/**
 * ルールの取得
 *
 * @param array $queries
 * @param array $options
 *
 * @return array
 */
function select_rewrite_rules($queries, $options = [])
{
    $queries = db_placeholder($queries);

    // ルールを取得
    $queries['from'] = DATABASE_PREFIX . 'rewrite_rules';

    // 削除済みデータは取得しない
    if (!isset($queries['where'])) {
        $queries['where'] = 'TRUE';
    }
    $queries['where'] = 'deleted IS NULL AND (' . $queries['where'] . ')';

    // データを取得
    $results = db_select($queries);

    return $results;
}

/**
 * ルールの登録
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function insert_rewrite_rules($queries, $options = [])
{
    $queries = db_placeholder($queries);

    // 初期値を取得
    $defaults = model('default_rewrite_rules');

    if (isset($queries['values']['created'])) {
        if ($queries['values']['created'] === false) {
            unset($queries['values']['created']);
        }
    } else {
        $queries['values']['created'] = $defaults['created'];
    }
    if (isset($queries['values']['modified'])) {
        if ($queries['values']['modified'] === false) {
            unset($queries['values']['modified']);
        }
    } else {
        $queries['values']['modified'] = $defaults['modified'];
    }

    // データを登録
    $queries['insert_into'] = DATABASE_PREFIX . 'rewrite_rules';

    $resource = db_insert($queries);
    if (!$resource) {
        return $resource;
    }

    return $resource;
}

/**
 * ルールの編集
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function update_rewrite_rules($queries, $options = [])
{
    $queries = db_placeholder($queries);

    // 初期値を取得
    $defaults = model('default_rewrite_rules');

    if (isset($queries['set']['modified'])) {
        if ($queries['set']['modified'] === false) {
            unset($queries['set']['modified']);
        }
    } else {
        $queries['set']['modified'] = $defaults['modified'];
    }

    // データを編集
    $queries['update'] = DATABASE_PREFIX . 'rewrite_rules';

    $resource = db_update($queries);
    if (!$resource) {
        return $resource;
    }

    return $resource;
}

/**
 * ルールの削除
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function delete_rewrite_rules($queries, $options = [])
{
    $queries = db_placeholder($queries);
    $options = [
        'softdelete' => isset($options['softdelete']) ? $options['softdelete'] : true,
    ];

    if ($options['softdelete'] === true) {
        // データを編集
        $resource = db_update([
            'update' => DATABASE_PREFIX . 'rewrite_rules AS rewrite_rules',
            'set'    => [
                'deleted' => localdate('Y-m-d H:i:s'),
            ],
            'where'  => isset($queries['where']) ? $queries['where'] : '',
            'limit'  => isset($queries['limit']) ? $queries['limit'] : '',
        ]);
        if (!$resource) {
            return $resource;
        }
    } else {
        // データを削除
        $resource = db_delete([
            'delete_from' => DATABASE_PREFIX . 'rewrite_rules',
            'where'       => isset($queries['where']) ? $queries['where'] : '',
            'limit'       => isset($queries['limit']) ? $queries['limit'] : '',
        ]);
        if (!$resource) {
            return $resource;
        }
    }

    return $resource;
}

/**
 * ルールの正規化
 *
 * @param array $queries
 * @param array $options
 *
 * @return array
 */
function normalize_rewrite_rules($queries, $options = [])
{
    // 並び順
    if (isset($queries['sort'])) {
        $queries['sort'] = mb_convert_kana($queries['sort'], 'n', MAIN_INTERNAL_ENCODING);
    } else {
        if (!$queries['id']) {
            $rewrite_rules = db_select([
                'select' => 'MAX(sort) AS sort',
                'from'   => DATABASE_PREFIX . 'rewrite_rules',
                'where'  => 'deleted IS NULL',
            ]);
            $queries['sort'] = $rewrite_rules[0]['sort'] + 1;
        }
    }

    return $queries;
}

/**
 * ルールの検証
 *
 * @param array $queries
 * @param array $options
 *
 * @return array
 */
function validate_rewrite_rules($queries, $options = [])
{
    $options = [
        'duplicate' => isset($options['duplicate']) ? $options['duplicate'] : true,
    ];

    $messages = [];

    // 有効
    if (isset($queries['enabled'])) {
        if (!validator_boolean($queries['enabled'])) {
            $messages['enabled'] = '有効の書式が不正です。';
        }
    }

    // 名前
    if (isset($queries['name'])) {
        if (!validator_required($queries['name'])) {
            $messages['name'] = '名前が入力されていません。';
        } elseif (!validator_max_length($queries['name'], 20)) {
            $messages['name'] = '名前は20文字以内で入力してください。';
        }
    }

    // リライト前
    if (isset($queries['url'])) {
        if (!validator_required($queries['url'])) {
            $messages['url'] = 'リライト前が入力されていません。';
        } elseif (!validator_max_length($queries['url'], 200)) {
            $messages['url'] = 'リライト前は200文字以内で入力してください。';
        } elseif (!validator_regexp($queries['url'], '^\/[\w\-\/]*$')) {
            $messages['url'] = 'リライト前はスラッシュから始まる半角英数字で入力してください。';
        }
    }

    // リライト後
    if (isset($queries['rewrited'])) {
        if (!validator_required($queries['rewrited'])) {
            $messages['rewrited'] = 'リライト後が入力されていません。';
        } elseif (!validator_max_length($queries['rewrited'], 200)) {
            $messages['rewrited'] = 'リライト後は200文字以内で入力してください。';
        } elseif (!validator_regexp($queries['rewrited'], '^\/[\w\-\/]*$')) {
            $messages['rewrited'] = 'リライト後はスラッシュから始まる半角英数字で入力してください。';
        }
    }

    // 挙動
    if (isset($queries['type'])) {
        if (!validator_required($queries['type'])) {
            $messages['type'] = '挙動が入力されていません。';
        } elseif (!validator_list($queries['type'], $GLOBALS['plugin']['rewrite']['option']['rewrite_rule']['type'])) {
            $messages['type'] = '挙動の値が不正です。';
        }
    }

    // メモ
    if (isset($queries['memo'])) {
        if (!validator_required($queries['memo'])) {
        } elseif (!validator_max_length($queries['memo'], 5000)) {
            $messages['memo'] = 'メモは5000文字以内で入力してください。';
        }
    }

    // 並び順
    if (isset($queries['sort'])) {
        if (!validator_required($queries['sort'])) {
            $messages['sort'] = '並び順が入力されていません。';
        } elseif (!validator_numeric($queries['sort'])) {
            $messages['sort'] = '並び順は半角数字で入力してください。';
        } elseif (!validator_max_length($queries['sort'], 5)) {
            $messages['sort'] = '並び順は5桁以内で入力してください。';
        }
    }

    return $messages;
}

/**
 * ルールの初期値
 *
 * @return array
 */
function default_rewrite_rules()
{
    return [
        'id'       => null,
        'created'  => localdate('Y-m-d H:i:s'),
        'modified' => localdate('Y-m-d H:i:s'),
        'deleted'  => null,
        'enabled'  => 1,
        'name'     => '',
        'url'      => '',
        'rewrited' => '',
        'type'     => '',
        'memo'     => null,
        'sort'     => 0,
    ];
}
