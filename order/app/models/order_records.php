<?php

import('libs/modules/validator.php');

/**
 * 注文記録の取得
 *
 * @param array $queries
 * @param array $options
 *
 * @return array
 */
function select_order_records($queries, $options = [])
{
    $queries = db_placeholder($queries);

    // 注文記録を取得
    $queries['from'] = DATABASE_PREFIX . 'order_records';

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
 * 注文記録の登録
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function insert_order_records($queries, $options = [])
{
    $queries = db_placeholder($queries);
    $options = [
        'items' => isset($options['items']) ? $options['items'] : [],
    ];

    // 初期値を取得
    $defaults = model('default_order_records');

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
    $queries['insert_into'] = DATABASE_PREFIX . 'order_records';

    $resource = db_insert($queries);
    if (!$resource) {
        return $resource;
    }

    // IDを取得
    $record_id = db_last_insert_id();

    if (isset($options['items'])) {
        // 関連するフィールドを更新
        model('set_item_order_records', $record_id, $options['items']);
    }

    return $resource;
}

/**
 * 注文記録の編集
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function update_order_records($queries, $options = [])
{
    $queries = db_placeholder($queries);

    // 初期値を取得
    $defaults = model('default_order_records');

    if (isset($queries['set']['modified'])) {
        if ($queries['set']['modified'] === false) {
            unset($queries['set']['modified']);
        }
    } else {
        $queries['set']['modified'] = $defaults['modified'];
    }

    // データを編集
    $queries['update'] = DATABASE_PREFIX . 'order_records';

    $resource = db_update($queries);
    if (!$resource) {
        return $resource;
    }

    if (isset($options['items'])) {
        // 関連する項目を更新
        model('set_item_order_records', $options['id'], $options['items']);
    }

    return $resource;
}

/**
 * 注文記録の削除
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function delete_order_records($queries, $options = [])
{
    $queries = db_placeholder($queries);
    $options = [
        'softdelete' => isset($options['softdelete']) ? $options['softdelete'] : true,
        'associate'  => isset($options['associate'])  ? $options['associate']  : false,
    ];

    // 削除するデータのIDを取得
    $order_records = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'order_records AS order_records',
        'where'  => isset($queries['where']) ? $queries['where'] : '',
        'limit'  => isset($queries['limit']) ? $queries['limit'] : '',
    ]);

    $ids = [];
    foreach ($order_records as $order_record) {
        $ids[] = intval($order_record['id']);
    }

    if ($options['associate'] === true && !empty($ids)) {
        // 関連するデータを削除
        $resource = model('delete_order_record_items', [
            'where' => 'record_id IN(' . implode(',', $ids) . ')',
        ]);
        if (!$resource) {
            return $resource;
        }
    }

    if ($options['softdelete'] === true) {
        // データを編集
        $resource = db_update([
            'update' => DATABASE_PREFIX . 'order_records AS order_records',
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
            'delete_from' => DATABASE_PREFIX . 'order_records',
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
 * 注文記録の正規化
 *
 * @param array $queries
 * @param array $options
 *
 * @return array
 */
function normalize_order_records($queries, $options = [])
{
    // 配送方法
    if (isset($queries['delivery_id']) && $queries['delivery_id'] === '') {
        $queries['delivery_id'] = null;
    }

    // 手数料
    if (isset($queries['payment_fee'])) {
        $queries['payment_fee'] = mb_convert_kana($queries['payment_fee'], 'n', MAIN_INTERNAL_ENCODING);
    }

    // 送料
    if (isset($queries['delivery_cost'])) {
        $queries['delivery_cost'] = mb_convert_kana($queries['delivery_cost'], 'n', MAIN_INTERNAL_ENCODING);
    }

    // 値引き額
    if (isset($queries['discount'])) {
        $queries['discount'] = mb_convert_kana($queries['discount'], 'n', MAIN_INTERNAL_ENCODING);
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
 * 注文記録の検証
 *
 * @param array $queries
 * @param array $options
 *
 * @return array
 */
function validate_order_records($queries, $options = [])
{
    $messages = [];

    // 提供方法
    if (isset($queries['provide'])) {
        if (!validator_required($queries['provide'])) {
            $messages['provide'] = '提供方法が入力されていません。';
        } elseif (!validator_list($queries['provide'], $GLOBALS['plugin']['order']['option']['order_spec']['provide'])) {
            $messages['provide'] = '提供方法の値が不正です。';
        }
    }

    // 支払方法
    if (isset($queries['payment_id'])) {
        if (!validator_required($queries['payment_id'])) {
            $messages['payment_id'] = '支払方法が入力されていません。';
        }
    }

    // 手数料
    if (isset($queries['payment_fee'])) {
        if (!validator_required($queries['payment_fee'])) {
            $messages['payment_fee'] = '手数料が入力されていません。';
        } elseif (!validator_numeric($queries['payment_fee'])) {
            $messages['payment_fee'] = '手数料は半角数字で入力してください。';
        } elseif (!validator_max_length($queries['payment_fee'], 10)) {
            $messages['payment_fee'] = '手数料は10桁以内で入力してください。';
        }
    }

    // 配送方法（正規化で未選択が NULL になるので、キーの有無で確認し、NULL も未入力として扱う）
    if (array_key_exists('delivery_id', $queries) && ($queries['provide'] ?? null) === 'delivery') {
        if ($queries['delivery_id'] === null || !validator_required($queries['delivery_id'])) {
            $messages['delivery_id'] = '配送方法が入力されていません。';
        }
    }

    // 送料
    if (isset($queries['delivery_cost'])) {
        if (!validator_required($queries['delivery_cost'])) {
            $messages['delivery_cost'] = '送料が入力されていません。';
        } elseif (!validator_numeric($queries['delivery_cost'])) {
            $messages['delivery_cost'] = '送料は半角数字で入力してください。';
        } elseif (!validator_max_length($queries['delivery_cost'], 10)) {
            $messages['delivery_cost'] = '送料は10桁以内で入力してください。';
        }
    }

    // 値引き額
    if (isset($queries['discount'])) {
        if (!validator_required($queries['discount'])) {
            $messages['discount'] = '値引き額が入力されていません。';
        } elseif (!validator_numeric($queries['discount'])) {
            $messages['discount'] = '値引き額は半角数字で入力してください。';
        } elseif (!validator_max_length($queries['discount'], 10)) {
            $messages['discount'] = '値引き額は10桁以内で入力してください。';
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
        } elseif (!validator_list($queries['status'], $GLOBALS['plugin']['order']['option']['order_record']['status'])) {
            $messages['status'] = '状況の値が不正です。';
        }
    }

    // メールアドレス
    if (isset($queries['email']) && ($queries['provide'] ?? null) !== 'direct') {
        if (!validator_required($queries['email'])) {
        } elseif (!validator_email($queries['email'])) {
            $messages['email'] = 'メールアドレスの入力内容が正しくありません。';
        } elseif (!validator_max_length($queries['email'], 80)) {
            $messages['email'] = 'メールアドレスは80文字以内で入力してください。';
        }
    }

    // 名前 姓
    if (isset($queries['name_01']) && ($queries['provide'] ?? null) === 'delivery') {
        if (!validator_required($queries['name_01'])) {
            $messages['name_01'] = '名前 姓が入力されていません。';
        } elseif (!validator_max_length($queries['name_01'], 20)) {
            $messages['name_01'] = '名前 姓は20文字以内で入力してください。';
        }
    }

    // 名前 名
    if (isset($queries['name_02']) && ($queries['provide'] ?? null) === 'delivery') {
        if (!validator_required($queries['name_02'])) {
            $messages['name_02'] = '名前 名が入力されていません。';
        } elseif (!validator_max_length($queries['name_02'], 20)) {
            $messages['name_02'] = '名前 名は20文字以内で入力してください。';
        }
    }

    // カナ 姓
    if (isset($queries['kana_01']) && ($queries['provide'] ?? null) === 'delivery') {
        if (!validator_required($queries['kana_01'])) {
        } elseif (!validator_katakana($queries['kana_01'])) {
            $messages['kana_01'] = 'カナ 姓は全角カタカナで入力してください。';
        } elseif (!validator_max_length($queries['kana_01'], 20)) {
            $messages['kana_01'] = 'カナ 姓は20文字以内で入力してください。';
        }
    }

    // カナ 名
    if (isset($queries['kana_02']) && ($queries['provide'] ?? null) === 'delivery') {
        if (!validator_required($queries['kana_02'])) {
        } elseif (!validator_katakana($queries['kana_02'])) {
            $messages['kana_02'] = 'カナ 名は全角カタカナで入力してください。';
        } elseif (!validator_max_length($queries['kana_02'], 20)) {
            $messages['kana_02'] = 'カナ 名は20文字以内で入力してください。';
        }
    }

    // 郵便番号
    if (isset($queries['zipcode']) && ($queries['provide'] ?? null) === 'delivery') {
        if (!validator_required($queries['zipcode'])) {
            $messages['zipcode'] = '郵便番号が入力されていません。';
        } elseif (!validator_max_length($queries['zipcode'], 8)) {
            $messages['zipcode'] = '郵便番号は8文字以内で入力してください。';   // ハイフンありで8文字とするか、ハイフンなしで7文字とするか
        }
    }

    // 都道府県
    if (isset($queries['prefecture']) && ($queries['provide'] ?? null) === 'delivery') {
        if (!validator_required($queries['prefecture'])) {
            $messages['prefecture'] = '都道府県が入力されていません。';
        }
        //if (!validator_list($queries['prefecture'], $GLOBALS['config']['option']['entry']['prefecture'])) {
        //    $messages['prefecture'] = '都道府県の値が不正です。';
        //}
    }

    // 住所 1
    if (isset($queries['address_01']) && ($queries['provide'] ?? null) === 'delivery') {
        if (!validator_required($queries['address_01'])) {
            $messages['address_01'] = '住所 1が入力されていません。';
        } elseif (!validator_max_length($queries['address_01'], 100)) {
            $messages['address_01'] = '住所 1は100文字以内で入力してください。';
        }
    }

    // 住所 2
    if (isset($queries['address_02']) && ($queries['provide'] ?? null) === 'delivery') {
        if (!validator_required($queries['address_02'])) {
        } elseif (!validator_max_length($queries['address_02'], 100)) {
            $messages['address_02'] = '住所 2は100文字以内で入力してください。';
        }
    }

    // 電話番号
    if (isset($queries['telephone']) && ($queries['provide'] ?? null) === 'delivery') {
        if (!validator_required($queries['telephone'])) {
            $messages['telephone'] = '電話番号が入力されていません。';
        } elseif (!validator_max_length($queries['telephone'], 11)) {
            $messages['telephone'] = '電話番号は11文字以内で入力してください。';   // 日本の番号限定でいいと思うが、桁数やハイフンの有無は扱いを決めておきたい
        }
    }

    // お問い合わせ内容
    if (isset($queries['message'])) {
        if (!validator_required($queries['message'])) {
        } elseif (!validator_max_length($queries['message'], 1000)) {
            $messages['message'] = 'お問い合わせ内容は1000文字以内で入力してください。';
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
 * 注文記録の表示用データ作成
 *
 * @param array $data
 *
 * @return array
 */
function view_order_records($data)
{
    return $data;
}

/**
 * 関連するフィールドを更新
 *
 * @param int   $record_id
 * @param array $items
 *
 * @return void
 */
function set_item_order_records($record_id, $items)
{
    // 古いデータを削除
    $resource = model('delete_order_record_items', [
        'where' => [
            'record_id = :record_id',
            [
                'record_id' => $record_id,
            ],
        ],
    ]);
    if (!$resource) {
        error('データを削除できません。');
    }

    if (empty($items)) {
        return;
    }

    // 規格の販売価格を取得
    $spec_ids = array_filter(array_column($items, 'order_spec_id'));
    $order_spec_sets = [];
    if (!empty($spec_ids)) {
        $order_specs = model('select_order_specs', [
            'where' => 'id IN(' . implode(',', array_map('db_escape', $spec_ids)) . ')',
        ]);
        foreach ($order_specs as $order_spec) {
            $order_spec_sets[$order_spec['id']] = $order_spec;
        }
    }

    // 新しいデータを登録
    foreach ($items as $item) {
        if (empty($item['order_spec_id'])) {
            // 直接入力の項目（データベースに無い商品）
            $resource = model('insert_order_record_items', [
                'values' => [
                    'record_id'     => $record_id,
                    'spec_id'       => null,
                    'name'          => $item['name'],
                    'selling_price' => $item['selling_price'],
                    'cost_price'    => null,
                    'quantity'      => $item['quantity'],
                ],
            ]);
            if (!$resource) {
                error('データを登録できません。');
            }
            continue;
        }

        // 在庫を確認して減らす・原価を集計
        $order_products = model('select_order_products', [
            'where' => [
                'spec_id = :spec_id',
                [
                    'spec_id' => $item['order_spec_id'],
                ],
            ],
        ], [
            'associate' => true,
        ]);

        $cost_price = null;
        foreach ($order_products as $order_product) {
            $consumed = $order_product['quantity'] * $item['quantity'];

            if ($GLOBALS['plugin']['order']['setting']['use_stock']) {
                if ($order_product['order_stock_quantity'] !== null && $order_product['order_stock_quantity'] < $consumed) {
                    error('在庫が不足しています（' . $order_product['order_stock_name'] . '）。');
                }

                $resource = model('update_order_stocks', [
                    'set'   => [
                        'quantity' => ['GREATEST(CAST(quantity AS SIGNED) - ' . intval($consumed) . ', 0)'],
                    ],
                    'where' => [
                        'id = :id AND quantity IS NOT NULL',
                        [
                            'id' => $order_product['stock_id'],
                        ],
                    ],
                ]);
                if (!$resource) {
                    error('在庫を更新できません。');
                }
            }

            // 原価を集計（規格1点あたり）
            $cost_price = ($cost_price ?? 0) + ($order_product['order_stock_cost_price'] ?? 0) * $order_product['quantity'];
        }

        $resource = model('insert_order_record_items', [
            'values' => [
                'record_id'     => $record_id,
                'spec_id'       => $item['order_spec_id'],
                'selling_price' => $order_spec_sets[$item['order_spec_id']]['selling_price'],
                'cost_price'    => $cost_price,
                'quantity'      => $item['quantity'],
            ],
        ]);
        if (!$resource) {
            error('データを登録できません。');
        }
    }
}

/**
 * 注文記録の初期値
 *
 * @return array
 */
function default_order_records()
{
    return [
        'id'            => null,
        'created'       => localdate('Y-m-d H:i:s'),
        'modified'      => localdate('Y-m-d H:i:s'),
        'deleted'       => null,
        'provide'       => '',
        'payment_id'    => 0,
        'payment_fee'   => 0,
        'delivery_id'   => null,
        'delivery_cost' => 0,
        'discount'      => 0,
        'shipping_date' => null,
        'status'        => '',
        'user_id'       => null,
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
        'message'       => null,
        'memo'          => null,
    ];
}
