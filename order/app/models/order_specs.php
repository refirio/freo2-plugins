<?php

import('libs/modules/validator.php');

/**
 * 規格の取得
 *
 * @param array $queries
 * @param array $options
 *
 * @return array
 */
function select_order_specs($queries, $options = [])
{
    $queries = db_placeholder($queries);
    $options = [
        'associate' => isset($options['associate']) ? $options['associate'] : false,
    ];

    if ($options['associate'] === true) {
        // 関連するデータを取得
        if (!isset($queries['select'])) {
            $queries['select'] = 'DISTINCT order_specs.*, '
                               . 'entries.code AS entry_code, '
                               . 'entries.title AS entry_title, '
                               . 'types.code AS type_code';
        }

        $queries['from'] = DATABASE_PREFIX . 'order_specs AS order_specs '
                         . 'LEFT JOIN ' . DATABASE_PREFIX . 'entries AS entries ON order_specs.entry_id = entries.id '
                         . 'LEFT JOIN ' . DATABASE_PREFIX . 'types AS types ON entries.type_id = types.id';

        // 削除済みデータは取得しない
        if (!isset($queries['where'])) {
            $queries['where'] = 'TRUE';
        }
        $queries['where'] = 'order_specs.deleted IS NULL AND (' . $queries['where'] . ')';
    } else {
        // 規格を取得
        $queries['from'] = DATABASE_PREFIX . 'order_specs';

        // 削除済みデータは取得しない
        if (!isset($queries['where'])) {
            $queries['where'] = 'TRUE';
        }
        $queries['where'] = 'deleted IS NULL AND (' . $queries['where'] . ')';
    }

    // データを取得
    $results = db_select($queries);

    return $results;
}

/**
 * 規格の登録
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function insert_order_specs($queries, $options = [])
{
    $queries = db_placeholder($queries);

    // 初期値を取得
    $defaults = model('default_order_specs');

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
    $queries['insert_into'] = DATABASE_PREFIX . 'order_specs';

    $resource = db_insert($queries);
    if (!$resource) {
        return $resource;
    }

    return $resource;
}

/**
 * 規格の編集
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function update_order_specs($queries, $options = [])
{
    $queries = db_placeholder($queries);

    // 初期値を取得
    $defaults = model('default_order_specs');

    if (isset($queries['set']['modified'])) {
        if ($queries['set']['modified'] === false) {
            unset($queries['set']['modified']);
        }
    } else {
        $queries['set']['modified'] = $defaults['modified'];
    }

    // データを編集
    $queries['update'] = DATABASE_PREFIX . 'order_specs';

    $resource = db_update($queries);
    if (!$resource) {
        return $resource;
    }

    return $resource;
}

/**
 * 規格の削除
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function delete_order_specs($queries, $options = [])
{
    $queries = db_placeholder($queries);
    $options = [
        'softdelete' => isset($options['softdelete']) ? $options['softdelete'] : true,
        'associate'  => isset($options['associate'])  ? $options['associate']  : false,
    ];

    // 削除するデータのIDを取得
    $order_specs = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'order_specs AS order_specs',
        'where'  => isset($queries['where']) ? $queries['where'] : '',
        'limit'  => isset($queries['limit']) ? $queries['limit'] : '',
    ]);

    $ids = [];
    foreach ($order_specs as $order_spec) {
        $ids[] = intval($order_spec['id']);
    }

    if ($options['associate'] === true) {
        // 関連するデータを削除
        $resource = model('delete_order_products', [
            'where' => 'spec_id IN(' . implode(',', array_map('db_escape', $ids)) . ')',
        ]);
        if (!$resource) {
            return $resource;
        }
    }

    if ($options['softdelete'] === true) {
        // データを編集
        $resource = db_update([
            'update' => DATABASE_PREFIX . 'order_specs AS order_specs',
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
            'delete_from' => DATABASE_PREFIX . 'order_specs',
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
 * 規格の正規化
 *
 * @param array $queries
 * @param array $options
 *
 * @return array
 */
function normalize_order_specs($queries, $options = [])
{
    // 販売価格
    if (isset($queries['selling_price'])) {
        $queries['selling_price'] = mb_convert_kana($queries['selling_price'], 'n', MAIN_INTERNAL_ENCODING);
    }

    // 通常価格
    if (isset($queries['regular_price'])) {
        $queries['regular_price'] = mb_convert_kana($queries['regular_price'], 'n', MAIN_INTERNAL_ENCODING);
    }

    // 送料
    if (isset($queries['shipping_cost'])) {
        $queries['shipping_cost'] = mb_convert_kana($queries['shipping_cost'], 'n', MAIN_INTERNAL_ENCODING);
    }

    // 配送日目安
    if (isset($queries['delivery_days'])) {
        $queries['delivery_days'] = mb_convert_kana($queries['delivery_days'], 'n', MAIN_INTERNAL_ENCODING);
    }

    // 販売制限数
    if (isset($queries['sales_limit'])) {
        $queries['sales_limit'] = mb_convert_kana($queries['sales_limit'], 'n', MAIN_INTERNAL_ENCODING);
    }

    // 並び順
    if (isset($queries['sort'])) {
        $queries['sort'] = mb_convert_kana($queries['sort'], 'n', MAIN_INTERNAL_ENCODING);
    } else {
        if (!$queries['id']) {
            $order_specs = db_select([
                'select' => 'MAX(sort) AS sort',
                'from'   => DATABASE_PREFIX . 'order_specs',
                'where'  => 'deleted IS NULL',
            ]);
            $queries['sort'] = $order_specs[0]['sort'] + 1;
        }
    }

    return $queries;
}

/**
 * 規格の検証
 *
 * @param array $queries
 * @param array $options
 *
 * @return array
 */
function validate_order_specs($queries, $options = [])
{
    $options = [
        'duplicate' => isset($options['duplicate']) ? $options['duplicate'] : true,
    ];

    $messages = [];

    // 商品
    if (isset($queries['entry_id'])) {
        if (!validator_required($queries['entry_id'])) {
            $messages['entry_id'] = '商品が入力されていません。';
        }
    }

    // 規格管理コード
    if (isset($queries['code'])) {
        if (!validator_required($queries['code'])) {
            $messages['code'] = '規格管理コードが入力されていません。';
        } elseif (!validator_alpha_dash($queries['code'])) {
            $messages['code'] = '規格管理コードは半角英数字で入力してください。';
        } elseif (!validator_between($queries['code'], 2, 80)) {
            $messages['code'] = '規格管理コードは2文字以上80文字以内で入力してください。';
        } elseif ($options['duplicate'] === true) {
            if (empty($queries['id'])) {
                $order_specs = db_select([
                    'select' => 'id',
                    'from'   => DATABASE_PREFIX . 'order_specs',
                    'where'  => [
                        'deleted IS NULL AND code = :code',
                        [
                            'code' => $queries['code'],
                        ],
                    ],
                ]);
            } else {
                $order_specs = db_select([
                    'select' => 'id',
                    'from'   => DATABASE_PREFIX . 'order_specs',
                    'where'  => [
                        'id != :id AND deleted IS NULL AND code = :code',
                        [
                            'id'   => $queries['id'],
                            'code' => $queries['code'],
                        ],
                    ],
                ]);
            }
            if (!empty($order_specs)) {
                $messages['code'] = '入力された規格管理コードはすでに使用されています。';
            }
        }
    }

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

    // 提供方法
    if (isset($queries['provide'])) {
        if (!validator_required($queries['provide'])) {
            $messages['provide'] = '提供方法が入力されていません。';
        } elseif (!validator_list($queries['provide'], $GLOBALS['plugin']['order']['option']['order_spec']['provide'])) {
            $messages['provide'] = '提供方法の値が不正です。';
        }
    }

    // 販売価格
    if (isset($queries['selling_price'])) {
        if (!validator_required($queries['selling_price'])) {
            $messages['selling_price'] = '販売価格が入力されていません。';
        } elseif (!validator_numeric($queries['selling_price'])) {
            $messages['selling_price'] = '販売価格は半角数字で入力してください。';
        } elseif (!validator_max_length($queries['selling_price'], 10)) {
            $messages['selling_price'] = '販売価格は10桁以内で入力してください。';
        }
    }

    // 通常価格
    if (isset($queries['regular_price'])) {
        if (!validator_required($queries['regular_price'])) {
        } elseif (!validator_numeric($queries['regular_price'])) {
            $messages['regular_price'] = '通常価格は半角数字で入力してください。';
        } elseif (!validator_max_length($queries['regular_price'], 10)) {
            $messages['regular_price'] = '通常価格は10桁以内で入力してください。';
        }
    }

    // 送料
    if (isset($queries['shipping_cost'])) {
        if (!validator_required($queries['shipping_cost'])) {
        } elseif (!validator_numeric($queries['shipping_cost'])) {
            $messages['shipping_cost'] = '送料は半角数字で入力してください。';
        } elseif (!validator_max_length($queries['shipping_cost'], 10)) {
            $messages['shipping_cost'] = '送料は10桁以内で入力してください。';
        }
    }

    // 配送日目安
    if (isset($queries['delivery_days'])) {
        if (!validator_required($queries['delivery_days'])) {
        } elseif (!validator_numeric($queries['delivery_days'])) {
            $messages['delivery_days'] = '配送日目安は半角数字で入力してください。';
        } elseif (!validator_max_length($queries['delivery_days'], 10)) {
            $messages['delivery_days'] = '配送日目安は10桁以内で入力してください。';
        }
    }

    // 販売制限数
    if (isset($queries['sales_limit'])) {
        if (!validator_required($queries['sales_limit'])) {
        } elseif (!validator_numeric($queries['sales_limit'])) {
            $messages['sales_limit'] = '販売制限数は半角数字で入力してください。';
        } elseif (!validator_max_length($queries['sales_limit'], 10)) {
            $messages['sales_limit'] = '販売制限数は10桁以内で入力してください。';
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
 * 規格の初期値
 *
 * @return array
 */
function default_order_specs()
{
    return [
        'id'            => null,
        'created'       => localdate('Y-m-d H:i:s'),
        'modified'      => localdate('Y-m-d H:i:s'),
        'deleted'       => null,
        'entry_id'      => 0,
        'code'          => '',
        'enabled'       => 1,
        'name'          => '',
        'provide'       => '',
        'selling_price' => 0,
        'regular_price' => null,
        'shipping_cost' => null,
        'delivery_days' => null,
        'sales_limit'   => null,
        'memo'          => null,
        'sort'          => 0,
    ];
}
