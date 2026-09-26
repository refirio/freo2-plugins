<?php

import('libs/modules/validator.php');

/**
 * 在庫の取得
 *
 * @param array $queries
 * @param array $options
 *
 * @return array
 */
function select_order_stocks($queries, $options = [])
{
    $queries = db_placeholder($queries);

    // 在庫を取得
    $queries['from'] = DATABASE_PREFIX . 'order_stocks';

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
 * 在庫の登録
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function insert_order_stocks($queries, $options = [])
{
    $queries = db_placeholder($queries);

    // 初期値を取得
    $defaults = model('default_order_stocks');

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
    $queries['insert_into'] = DATABASE_PREFIX . 'order_stocks';

    $resource = db_insert($queries);
    if (!$resource) {
        return $resource;
    }

    return $resource;
}

/**
 * 在庫の編集
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function update_order_stocks($queries, $options = [])
{
    $queries = db_placeholder($queries);

    // 初期値を取得
    $defaults = model('default_order_stocks');

    if (isset($queries['set']['modified'])) {
        if ($queries['set']['modified'] === false) {
            unset($queries['set']['modified']);
        }
    } else {
        $queries['set']['modified'] = $defaults['modified'];
    }

    // データを編集
    $queries['update'] = DATABASE_PREFIX . 'order_stocks';

    $resource = db_update($queries);
    if (!$resource) {
        return $resource;
    }

    return $resource;
}

/**
 * 在庫の削除
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function delete_order_stocks($queries, $options = [])
{
    $queries = db_placeholder($queries);
    $options = [
        'softdelete' => isset($options['softdelete']) ? $options['softdelete'] : true,
    ];

    if ($options['softdelete'] === true) {
        // データを編集
        $resource = db_update([
            'update' => DATABASE_PREFIX . 'order_stocks AS order_stocks',
            'set'    => [
                'deleted' => localdate('Y-m-d H:i:s'),
                'code'    => ['CONCAT(\'DELETED ' . localdate('YmdHis') . ' \', code)'],
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
            'delete_from' => DATABASE_PREFIX . 'order_stocks',
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
 * 在庫の正規化
 *
 * @param array $queries
 * @param array $options
 *
 * @return array
 */
function normalize_order_stocks($queries, $options = [])
{
    // 数
    if (isset($queries['quantity'])) {
        $queries['quantity'] = mb_convert_kana($queries['quantity'], 'n', MAIN_INTERNAL_ENCODING);
    }

    // 原価
    if (isset($queries['cost_price'])) {
        $queries['cost_price'] = mb_convert_kana($queries['cost_price'], 'n', MAIN_INTERNAL_ENCODING);
    }

    return $queries;
}

/**
 * 在庫の検証
 *
 * @param array $queries
 * @param array $options
 *
 * @return array
 */
function validate_order_stocks($queries, $options = [])
{
    $options = [
        'duplicate' => isset($options['duplicate']) ? $options['duplicate'] : true,
    ];

    $messages = [];

    // コード
    if (isset($queries['code'])) {
        if (!validator_required($queries['code'])) {
            $messages['code'] = 'コードが入力されていません。';
        } elseif (!validator_alpha_dash($queries['code'])) {
            $messages['code'] = 'コードは半角英数字で入力してください。';
        } elseif (!validator_between($queries['code'], 2, 80)) {
            $messages['code'] = 'コードは2文字以上80文字以内で入力してください。';
        } elseif ($options['duplicate'] === true) {
            if (empty($queries['id'])) {
                $order_stocks = db_select([
                    'select' => 'id',
                    'from'   => DATABASE_PREFIX . 'order_stocks',
                    'where'  => [
                        'deleted IS NULL AND code = :code',
                        [
                            'code' => $queries['code'],
                        ],
                    ],
                ]);
            } else {
                $order_stocks = db_select([
                    'select' => 'id',
                    'from'   => DATABASE_PREFIX . 'order_stocks',
                    'where'  => [
                        'id != :id AND deleted IS NULL AND code = :code',
                        [
                            'id'   => $queries['id'],
                            'code' => $queries['code'],
                        ],
                    ],
                ]);
            }
            if (!empty($order_stocks)) {
                $messages['code'] = '入力されたコードはすでに使用されています。';
            }
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

    // 内容
    if (isset($queries['text'])) {
        if (!validator_required($queries['text'])) {
        } elseif (!validator_max_length($queries['text'], 5000)) {
            $messages['text'] = '内容は5000文字以内で入力してください。';
        }
    }

    // 種類
    if (isset($queries['kind'])) {
        if (!validator_required($queries['kind'])) {
            $messages['kind'] = '種類が入力されていません。';
        } elseif (!validator_list($queries['kind'], $GLOBALS['plugin']['order']['option']['order_stock']['kind'])) {
            $messages['kind'] = '種類の値が不正です。';
        }
    }

    // ダウンロード案内
    if (isset($queries['download'])) {
        if (!validator_required($queries['download'])) {
        } elseif (!validator_max_length($queries['download'], 5000)) {
            $messages['download'] = 'ダウンロード案内は5000文字以内で入力してください。';
        }
    }

    // 数
    if (isset($queries['quantity'])) {
        if (!validator_required($queries['quantity'])) {
        } elseif (!validator_numeric($queries['quantity'])) {
            $messages['quantity'] = '数は半角数字で入力してください。';
        } elseif (!validator_max_length($queries['quantity'], 10)) {
            $messages['quantity'] = '数は10桁以内で入力してください。';
        }
    }

    // 原価
    if (isset($queries['cost_price'])) {
        if (!validator_required($queries['cost_price'])) {
        } elseif (!validator_numeric($queries['cost_price'])) {
            $messages['cost_price'] = '原価は半角数字で入力してください。';
        } elseif (!validator_max_length($queries['cost_price'], 10)) {
            $messages['cost_price'] = '原価は10桁以内で入力してください。';
        }
    }

    // 店舗用メモ
    if (isset($queries['memo'])) {
        if (!validator_required($queries['memo'])) {
        } elseif (!validator_max_length($queries['memo'], 5000)) {
            $messages['memo'] = '店舗用メモは5000文字以内で入力してください。';
        }
    }

    return $messages;
}

/**
 * 在庫の初期値
 *
 * @return array
 */
function default_order_stocks()
{
    return [
        'id'         => null,
        'created'    => localdate('Y-m-d H:i:s'),
        'modified'   => localdate('Y-m-d H:i:s'),
        'deleted'    => null,
        'code'       => '',
        'name'       => '',
        'text'       => null,
        'kind'       => '',
        'download'   => null,
        'quantity'   => null,
        'cost_price' => null,
        'memo'       => null,
    ];
}
