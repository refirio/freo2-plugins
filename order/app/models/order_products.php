<?php

import('libs/modules/validator.php');

/**
 * 製品の取得
 *
 * @param array $queries
 * @param array $options
 *
 * @return array
 */
function select_order_products($queries, $options = [])
{
    $queries = db_placeholder($queries);
    $options = [
        'associate' => isset($options['associate']) ? $options['associate'] : false,
    ];

    if ($options['associate'] === true) {
        // 関連するデータを取得
        if (!isset($queries['select'])) {
            $queries['select'] = 'DISTINCT order_products.*, '
                               . 'order_stocks.code AS order_stock_code, '
                               . 'order_stocks.name AS order_stock_name, '
                               . 'order_stocks.text AS order_stock_text, '
                               . 'order_stocks.kind AS order_stock_kind, '
                               . 'order_stocks.download AS order_stock_download, '
                               . 'order_stocks.quantity AS order_stock_quantity, '
                               . 'order_stocks.cost_price AS order_stock_cost_price, '
                               . 'order_stocks.memo AS order_stock_memo';
        }

        $queries['from'] = DATABASE_PREFIX . 'order_products AS order_products '
                         . 'LEFT JOIN ' . DATABASE_PREFIX . 'order_stocks AS order_stocks ON order_products.stock_id = order_stocks.id';

        // 削除済みデータは取得しない
        if (!isset($queries['where'])) {
            $queries['where'] = 'TRUE';
        }
        $queries['where'] = 'order_products.deleted IS NULL AND (' . $queries['where'] . ')';
    } else {
        // 製品を取得
        $queries['from'] = DATABASE_PREFIX . 'order_products';

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
 * 製品の登録
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function insert_order_products($queries, $options = [])
{
    $queries = db_placeholder($queries);

    // 初期値を取得
    $defaults = model('default_order_products');

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
    $queries['insert_into'] = DATABASE_PREFIX . 'order_products';

    $resource = db_insert($queries);
    if (!$resource) {
        return $resource;
    }

    return $resource;
}

/**
 * 製品の編集
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function update_order_products($queries, $options = [])
{
    $queries = db_placeholder($queries);

    // 初期値を取得
    $defaults = model('default_order_products');

    if (isset($queries['set']['modified'])) {
        if ($queries['set']['modified'] === false) {
            unset($queries['set']['modified']);
        }
    } else {
        $queries['set']['modified'] = $defaults['modified'];
    }

    // データを編集
    $queries['update'] = DATABASE_PREFIX . 'order_products';

    $resource = db_update($queries);
    if (!$resource) {
        return $resource;
    }

    return $resource;
}

/**
 * 製品の削除
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function delete_order_products($queries, $options = [])
{
    $queries = db_placeholder($queries);
    $options = [
        'softdelete' => isset($options['softdelete']) ? $options['softdelete'] : true,
    ];

    if ($options['softdelete'] === true) {
        // データを編集
        $resource = db_update([
            'update' => DATABASE_PREFIX . 'order_products AS order_products',
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
            'delete_from' => DATABASE_PREFIX . 'order_products',
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
 * 製品の正規化
 *
 * @param array $queries
 * @param array $options
 *
 * @return array
 */
function normalize_order_products($queries, $options = [])
{
    // 数
    if (isset($queries['quantity'])) {
        $queries['quantity'] = mb_convert_kana($queries['quantity'], 'n', MAIN_INTERNAL_ENCODING);
    }

    // 並び順
    if (isset($queries['sort'])) {
        $queries['sort'] = mb_convert_kana($queries['sort'], 'n', MAIN_INTERNAL_ENCODING);
    } else {
        if (!$queries['id']) {
            $order_products = db_select([
                'select' => 'MAX(sort) AS sort',
                'from'   => DATABASE_PREFIX . 'order_products',
                'where'  => 'deleted IS NULL',
            ]);
            $queries['sort'] = $order_products[0]['sort'] + 1;
        }
    }

    return $queries;
}

/**
 * 製品の検証
 *
 * @param array $queries
 * @param array $options
 *
 * @return array
 */
function validate_order_products($queries, $options = [])
{
    $options = [
        'duplicate' => isset($options['duplicate']) ? $options['duplicate'] : true,
    ];

    $messages = [];

    // 規格
    if (isset($queries['spec_id'])) {
        if (!validator_required($queries['spec_id'])) {
            $messages['spec_id'] = '規格が入力されていません。';
        }
    }

    // 在庫
    if (isset($queries['stock_id'])) {
        if (!validator_required($queries['stock_id'])) {
            $messages['stock_id'] = '在庫が入力されていません。';
        }
    }

    // 数
    if (isset($queries['quantity'])) {
        if (!validator_required($queries['quantity'])) {
            $messages['quantity'] = '数が入力されていません。';
        } elseif (!validator_numeric($queries['quantity'])) {
            $messages['quantity'] = '数は半角数字で入力してください。';
        } elseif (!validator_max_length($queries['quantity'], 10)) {
            $messages['quantity'] = '数は10桁以内で入力してください。';
        }
    }

    // 店舗用メモ
    if (isset($queries['memo'])) {
        if (!validator_required($queries['memo'])) {
        } elseif (!validator_max_length($queries['memo'], 5000)) {
            $messages['memo'] = '店舗用メモは5000文字以内で入力してください。';
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
 * 製品の初期値
 *
 * @return array
 */
function default_order_products()
{
    return [
        'id'       => null,
        'created'  => localdate('Y-m-d H:i:s'),
        'modified' => localdate('Y-m-d H:i:s'),
        'deleted'  => null,
        'spec_id'  => 0,
        'stock_id' => 0,
        'quantity' => 0,
        'memo'     => null,
        'sort'     => 0,
    ];
}
