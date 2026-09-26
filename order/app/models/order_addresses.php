<?php

import('libs/modules/validator.php');

/**
 * 住所の取得
 *
 * @param array $queries
 * @param array $options
 *
 * @return array
 */
function select_order_addresses($queries, $options = [])
{
    $queries = db_placeholder($queries);

    // 住所を取得
    $queries['from'] = DATABASE_PREFIX . 'order_addresses';

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
 * 住所の登録
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function insert_order_addresses($queries, $options = [])
{
    $queries = db_placeholder($queries);

    // 初期値を取得
    $defaults = model('default_order_addresses');

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
    $queries['insert_into'] = DATABASE_PREFIX . 'order_addresses';

    $resource = db_insert($queries);
    if (!$resource) {
        return $resource;
    }

    return $resource;
}

/**
 * 住所の編集
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function update_order_addresses($queries, $options = [])
{
    $queries = db_placeholder($queries);

    // 初期値を取得
    $defaults = model('default_order_addresses');

    if (isset($queries['set']['modified'])) {
        if ($queries['set']['modified'] === false) {
            unset($queries['set']['modified']);
        }
    } else {
        $queries['set']['modified'] = $defaults['modified'];
    }

    // データを編集
    $queries['update'] = DATABASE_PREFIX . 'order_addresses';

    $resource = db_update($queries);
    if (!$resource) {
        return $resource;
    }

    return $resource;
}

/**
 * 住所の削除
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function delete_order_addresses($queries, $options = [])
{
    $queries = db_placeholder($queries);
    $options = [
        'softdelete' => isset($options['softdelete']) ? $options['softdelete'] : true,
    ];

    if ($options['softdelete'] === true) {
        // データを編集
        $resource = db_update([
            'update' => DATABASE_PREFIX . 'order_addresses AS order_addresses',
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
            'delete_from' => DATABASE_PREFIX . 'order_addresses',
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
 * 住所の正規化
 *
 * @param array $queries
 * @param array $options
 *
 * @return array
 */
function normalize_order_addresses($queries, $options = [])
{
    // 郵便番号
    if (isset($queries['zipcode'])) {
        $queries['zipcode'] = mb_convert_kana($queries['zipcode'], 'a', MAIN_INTERNAL_ENCODING);
    }

    // 電話番号
    if (isset($queries['telephone'])) {
        $queries['telephone'] = mb_convert_kana($queries['telephone'], 'a', MAIN_INTERNAL_ENCODING);
    }

    return $queries;
}

/**
 * 住所の検証
 *
 * @param array $queries
 * @param array $options
 *
 * @return array
 */
function validate_order_addresses($queries, $options = [])
{
    $messages = [];

    // 名前 姓
    if (isset($queries['name_01'])) {
        if (!validator_required($queries['name_01'])) {
            $messages['name_01'] = '名前 姓が入力されていません。';
        } elseif (!validator_max_length($queries['name_01'], 20)) {
            $messages['name_01'] = '名前 姓は20文字以内で入力してください。';
        }
    }

    // 名前 名
    if (isset($queries['name_02'])) {
        if (!validator_required($queries['name_02'])) {
            $messages['name_02'] = '名前 名が入力されていません。';
        } elseif (!validator_max_length($queries['name_02'], 20)) {
            $messages['name_02'] = '名前 名は20文字以内で入力してください。';
        }
    }

    // カナ 姓
    if (isset($queries['kana_01'])) {
        if (!validator_required($queries['kana_01'])) {
        } elseif (!validator_katakana($queries['kana_01'])) {
            $messages['kana_01'] = 'カナ 姓は全角カタカナで入力してください。';
        } elseif (!validator_max_length($queries['kana_01'], 20)) {
            $messages['kana_01'] = 'カナ 姓は20文字以内で入力してください。';
        }
    }

    // カナ 名
    if (isset($queries['kana_02'])) {
        if (!validator_required($queries['kana_02'])) {
        } elseif (!validator_katakana($queries['kana_02'])) {
            $messages['kana_02'] = 'カナ 名は全角カタカナで入力してください。';
        } elseif (!validator_max_length($queries['kana_02'], 20)) {
            $messages['kana_02'] = 'カナ 名は20文字以内で入力してください。';
        }
    }

    // 郵便番号
    if (isset($queries['zipcode'])) {
        if (!validator_required($queries['zipcode'])) {
            $messages['zipcode'] = '郵便番号が入力されていません。';
        } elseif (!validator_max_length($queries['zipcode'], 8)) {
            $messages['zipcode'] = '郵便番号は8文字以内で入力してください。';
        }
    }

    // 都道府県
    if (isset($queries['prefecture'])) {
        if (!validator_required($queries['prefecture'])) {
            $messages['prefecture'] = '都道府県が入力されていません。';
        }
    }

    // 住所 1
    if (isset($queries['address_01'])) {
        if (!validator_required($queries['address_01'])) {
            $messages['address_01'] = '住所 1が入力されていません。';
        } elseif (!validator_max_length($queries['address_01'], 100)) {
            $messages['address_01'] = '住所 1は100文字以内で入力してください。';
        }
    }

    // 住所 2
    if (isset($queries['address_02'])) {
        if (!validator_required($queries['address_02'])) {
        } elseif (!validator_max_length($queries['address_02'], 100)) {
            $messages['address_02'] = '住所 2は100文字以内で入力してください。';
        }
    }

    // 電話番号
    if (isset($queries['telephone'])) {
        if (!validator_required($queries['telephone'])) {
            $messages['telephone'] = '電話番号が入力されていません。';
        } elseif (!validator_max_length($queries['telephone'], 11)) {
            $messages['telephone'] = '電話番号は11文字以内で入力してください。';
        }
    }

    return $messages;
}

/**
 * 住所の初期値
 *
 * @return array
 */
function default_order_addresses()
{
    return [
        'id'         => null,
        'created'    => localdate('Y-m-d H:i:s'),
        'modified'   => localdate('Y-m-d H:i:s'),
        'deleted'    => null,
        'user_id'    => 0,
        'name_01'    => '',
        'name_02'    => '',
        'kana_01'    => null,
        'kana_02'    => null,
        'zipcode'    => '',
        'prefecture' => '',
        'address_01' => '',
        'address_02' => null,
        'telephone'  => '',
    ];
}
