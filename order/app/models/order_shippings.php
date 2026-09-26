<?php

import('libs/modules/validator.php');

/**
 * 発送記録の取得
 *
 * @param array $queries
 * @param array $options
 *
 * @return array
 */
function select_order_shippings($queries, $options = [])
{
    $queries = db_placeholder($queries);

    // 発送記録を取得
    $queries['from'] = DATABASE_PREFIX . 'order_shippings';

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
 * 発送記録の登録
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function insert_order_shippings($queries, $options = [])
{
    $queries = db_placeholder($queries);
    $options = [
        'items' => isset($options['items']) ? $options['items'] : [],
    ];

    // 初期値を取得
    $defaults = model('default_order_shippings');

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
    $queries['insert_into'] = DATABASE_PREFIX . 'order_shippings';

    $resource = db_insert($queries);
    if (!$resource) {
        return $resource;
    }

    // IDを取得
    $shipping_id = db_last_insert_id();

    if (isset($options['items'])) {
        // 関連する明細を更新
        model('set_item_order_shippings', $shipping_id, $queries['values']['record_id'], $options['items']);
    }

    return $resource;
}

/**
 * 発送記録の編集
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function update_order_shippings($queries, $options = [])
{
    $queries = db_placeholder($queries);

    // 初期値を取得
    $defaults = model('default_order_shippings');

    if (isset($queries['set']['modified'])) {
        if ($queries['set']['modified'] === false) {
            unset($queries['set']['modified']);
        }
    } else {
        $queries['set']['modified'] = $defaults['modified'];
    }

    // データを編集
    $queries['update'] = DATABASE_PREFIX . 'order_shippings';

    $resource = db_update($queries);
    if (!$resource) {
        return $resource;
    }

    if (isset($options['items'])) {
        // 関連する明細を更新
        model('set_item_order_shippings', $options['id'], $options['record_id'], $options['items']);
    }

    return $resource;
}

/**
 * 発送記録の削除
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function delete_order_shippings($queries, $options = [])
{
    $queries = db_placeholder($queries);
    $options = [
        'softdelete' => isset($options['softdelete']) ? $options['softdelete'] : true,
        'associate'  => isset($options['associate'])  ? $options['associate']  : false,
    ];

    // 削除するデータのIDを取得
    $order_shippings = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'order_shippings AS order_shippings',
        'where'  => isset($queries['where']) ? $queries['where'] : '',
        'limit'  => isset($queries['limit']) ? $queries['limit'] : '',
    ]);

    $ids = [];
    foreach ($order_shippings as $order_shipping) {
        $ids[] = intval($order_shipping['id']);
    }

    if ($options['associate'] === true && !empty($ids)) {
        // 関連するデータを削除
        $resource = model('delete_order_shipping_items', [
            'where' => 'shipping_id IN(' . implode(',', $ids) . ')',
        ]);
        if (!$resource) {
            return $resource;
        }
    }

    if ($options['softdelete'] === true) {
        // データを編集
        $resource = db_update([
            'update' => DATABASE_PREFIX . 'order_shippings AS order_shippings',
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
            'delete_from' => DATABASE_PREFIX . 'order_shippings',
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
 * 発送記録の正規化
 *
 * @param array $queries
 * @param array $options
 *
 * @return array
 */
function normalize_order_shippings($queries, $options = [])
{
    // 配送方法
    if (isset($queries['delivery_id']) && $queries['delivery_id'] === '') {
        $queries['delivery_id'] = null;
    }

    // 送料
    if (isset($queries['delivery_cost'])) {
        $queries['delivery_cost'] = $queries['delivery_cost'] === '' ? null : mb_convert_kana($queries['delivery_cost'], 'n', MAIN_INTERNAL_ENCODING);
    }

    // 配送日
    if (isset($queries['shipping_date'])) {
        $queries['shipping_date'] = mb_convert_kana($queries['shipping_date'], 'a', MAIN_INTERNAL_ENCODING);
    }

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
 * 発送記録の検証
 *
 * @param array $queries
 * @param array $options
 *
 * @return array
 */
function validate_order_shippings($queries, $options = [])
{
    $messages = [];

    // 送料
    if (isset($queries['delivery_cost'])) {
        if (!validator_required($queries['delivery_cost'])) {
        } elseif (!validator_numeric($queries['delivery_cost'])) {
            $messages['delivery_cost'] = '送料は半角数字で入力してください。';
        } elseif (!validator_max_length($queries['delivery_cost'], 10)) {
            $messages['delivery_cost'] = '送料は10桁以内で入力してください。';
        }
    }

    // 配送日
    if (isset($queries['shipping_date'])) {
        if (!validator_required($queries['shipping_date'])) {
        } elseif (!validator_date($queries['shipping_date'])) {
            $messages['shipping_date'] = '配送日の値が不正です。';
        }
    }

    // 状況
    if (isset($queries['status'])) {
        if (!validator_required($queries['status'])) {
            $messages['status'] = '状況が入力されていません。';
        } elseif (!validator_list($queries['status'], $GLOBALS['plugin']['order']['option']['order_shippings']['status'])) {
            $messages['status'] = '状況の値が不正です。';
        }
    }

    // メールアドレス
    if (isset($queries['email'])) {
        if (!validator_required($queries['email'])) {
        } elseif (!validator_email($queries['email'])) {
            $messages['email'] = 'メールアドレスの入力内容が正しくありません。';
        } elseif (!validator_max_length($queries['email'], 80)) {
            $messages['email'] = 'メールアドレスは80文字以内で入力してください。';
        }
    }

    // 名前 姓
    if (isset($queries['name_01'])) {
        if (!validator_required($queries['name_01'])) {
        } elseif (!validator_max_length($queries['name_01'], 20)) {
            $messages['name_01'] = '名前 姓は20文字以内で入力してください。';
        }
    }

    // 名前 名
    if (isset($queries['name_02'])) {
        if (!validator_required($queries['name_02'])) {
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
        } elseif (!validator_max_length($queries['zipcode'], 8)) {
            $messages['zipcode'] = '郵便番号は8文字以内で入力してください。';
        }
    }

    // 住所 1
    if (isset($queries['address_01'])) {
        if (!validator_required($queries['address_01'])) {
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
        } elseif (!validator_max_length($queries['telephone'], 11)) {
            $messages['telephone'] = '電話番号は11文字以内で入力してください。';
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
 * 発送可能数の計算から除外する発送記録ID（配送失敗・返送）を取得
 *
 * @param string|null $where 追加の絞り込み条件（省略可）
 *
 * @return array
 */
function select_unsuccessful_order_shippings($where = null)
{
    $condition = 'status IN(' . implode(',', array_map('db_escape', ['failed', 'returned'])) . ')';
    if ($where) {
        $condition .= ' AND (' . $where . ')';
    }

    $order_shippings = model('select_order_shippings', [
        'select' => 'id',
        'where'  => $condition,
    ]);

    return array_map('intval', array_column($order_shippings, 'id'));
}

/**
 * 関連する明細を更新
 *
 * @param int   $shipping_id
 * @param int   $record_id
 * @param array $items
 *
 * @return void
 */
function set_item_order_shippings($shipping_id, $record_id, $items)
{
    // 古いデータを削除
    $resource = model('delete_order_shipping_items', [
        'where' => [
            'shipping_id = :shipping_id',
            [
                'shipping_id' => $shipping_id,
            ],
        ],
    ]);
    if (!$resource) {
        error('データを削除できません。');
    }

    if (empty($items)) {
        return;
    }

    // 対象の注文明細を取得（他の注文の明細を指定できないようにrecord_idで絞り込む）
    $order_record_items = model('select_order_record_items', [
        'where' => [
            'id IN(' . implode(',', array_map('db_escape', array_column($items, 'record_item_id'))) . ') AND record_id = :record_id',
            [
                'record_id' => $record_id,
            ],
        ],
    ]);
    $order_record_item_sets = [];
    foreach ($order_record_items as $order_record_item) {
        $order_record_item_sets[$order_record_item['id']] = $order_record_item;
    }

    // 新しいデータを登録
    foreach ($items as $item) {
        if (!isset($order_record_item_sets[$item['record_item_id']])) {
            error('注文明細が見つかりません。');
        }

        // イレギュラーな配送（過剰発送など）に対応するため、超過チェックは行わず登録する
        // （超過の有無は/admin/shippingの「発送状況」で警告表示する）
        $resource = model('insert_order_shipping_items', [
            'values' => [
                'shipping_id'    => $shipping_id,
                'record_item_id' => $item['record_item_id'],
                'quantity'       => $item['quantity'],
            ],
        ]);
        if (!$resource) {
            error('データを登録できません。');
        }
    }
}

/**
 * 発送記録の初期値
 *
 * @return array
 */
function default_order_shippings()
{
    return [
        'id'            => null,
        'created'       => localdate('Y-m-d H:i:s'),
        'modified'      => localdate('Y-m-d H:i:s'),
        'deleted'       => null,
        'record_id'     => 0,
        'delivery_id'   => null,
        'delivery_cost' => null,
        'shipping_date' => null,
        'status'        => '',
        'email'         => null,
        'name_01'       => null,
        'name_02'       => null,
        'kana_01'       => null,
        'kana_02'       => null,
        'zipcode'       => null,
        'prefecture'    => null,
        'address_01'    => null,
        'address_02'    => null,
        'telephone'     => null,
        'memo'          => null,
    ];
}
