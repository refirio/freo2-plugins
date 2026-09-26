<?php

/*******************************************************************************

 設定ファイル

*******************************************************************************/

/* プラグインコード */
$GLOBALS['plugin']['order']['code'] = 'order';

/* プラグイン名 */
$GLOBALS['plugin']['order']['name'] = 'オーダー';

/* プラグイン概要 */
$GLOBALS['plugin']['order']['description'] = '商品を登録し、注文を受け付けて管理します。';

/* プラグイン詳細 */
$GLOBALS['plugin']['order']['detail'] = <<< EOM
管理画面から商品を登録し、注文を受け付けます。
EOM;

/* プラグインバージョン */
$GLOBALS['plugin']['order']['version'] = '0.0.0';

/* プラグイン更新日 */
$GLOBALS['plugin']['order']['updated'] = '2026-08-29';

/* プラグイン製作者 */
$GLOBALS['plugin']['order']['author'] = 'refirio';

/* プラグインURL */
$GLOBALS['plugin']['order']['link'] = 'https://refirio.org/';

/* プラグイン設定項目 */
$GLOBALS['plugin']['order']['setting_define'] = [
    'use_approve' => [
        'name'        => '商品の承認',
        'explanation' => '公開には管理者の承認が必要になります。',
        'type'        => 'boolean',
        'required'    => false,
    ],
    'use_pictures' => [
        'name'        => '写真の入力',
        'explanation' => null,
        'type'        => 'boolean',
        'required'    => false,
    ],
    'use_thumbnail' => [
        'name'        => 'サムネイルの入力',
        'explanation' => null,
        'type'        => 'boolean',
        'required'    => false,
    ],
    'default_code' => [
        'name'        => 'コードの初期値',
        'explanation' => 'YmdHisが年月日時分秒になります。',
        'type'        => 'text',
        'required'    => false,
    ],
    'text_type' => [
        'name'        => '本文の入力項目',
        'explanation' => null,
        'type'        => 'select',
        'required'    => false,
        'kind'        => [
            'none'     => 'なし',
            'textarea' => '複数行入力',
            'html'     => 'HTML直接入力',
            'wysiwyg'  => 'WYSIWYGエディタ',
        ],
    ],
    'use_stock' => [
        'name'        => '在庫管理',
        'explanation' => '在庫数を管理します。',
        'type'        => 'boolean',
        'required'    => false,
    ],
    'use_shipping' => [
        'name'        => '配送管理',
        'explanation' => '配送情報を管理します。',
        'type'        => 'boolean',
        'required'    => false,
    ],
    'use_direct' => [
        'name'        => '対面販売用画面',
        'explanation' => '対面販売用の画面を使用するかどうかを指定します。',
        'type'        => 'boolean',
        'required'    => false,
    ],
    'text_index' => [
        'name'        => '文言 商品',
        'explanation' => null,
        'type'        => 'textarea',
        'required'    => false,
    ],
    'text_detail' => [
        'name'        => '文言 商品詳細',
        'explanation' => null,
        'type'        => 'textarea',
        'required'    => false,
    ],
    'heading_address' => [
        'name'        => '見出し 住所管理',
        'explanation' => null,
        'type'        => 'text',
        'required'    => false,
    ],
    'heading_cart' => [
        'name'        => '見出し カート',
        'explanation' => null,
        'type'        => 'text',
        'required'    => false,
    ],
    'text_cart' => [
        'name'        => '文言 カート',
        'explanation' => null,
        'type'        => 'textarea',
        'required'    => false,
    ],
    'button_cart' => [
        'name'        => 'ボタン カート表示',
        'explanation' => null,
        'type'        => 'text',
        'required'    => false,
    ],
    'button_cart_add' => [
        'name'        => 'ボタン カート追加',
        'explanation' => null,
        'type'        => 'text',
        'required'    => false,
    ],
    'button_cart_order' => [
        'name'        => 'ボタン 注文手続き',
        'explanation' => null,
        'type'        => 'text',
        'required'    => false,
    ],
    'heading_order' => [
        'name'        => '見出し 注文',
        'explanation' => null,
        'type'        => 'text',
        'required'    => false,
    ],
    'text_order' => [
        'name'        => '文言 注文',
        'explanation' => null,
        'type'        => 'textarea',
        'required'    => false,
    ],
    'text_order_preview' => [
        'name'        => '文言 注文確認',
        'explanation' => null,
        'type'        => 'textarea',
        'required'    => false,
    ],
    'text_order_complete' => [
        'name'        => '文言 注文完了',
        'explanation' => null,
        'type'        => 'textarea',
        'required'    => false,
    ],
    'button_order' => [
        'name'        => 'ボタン 注文',
        'explanation' => null,
        'type'        => 'text',
        'required'    => false,
    ],
    'button_order_preview' => [
        'name'        => 'ボタン 注文確認',
        'explanation' => null,
        'type'        => 'text',
        'required'    => false,
    ],
    'mail_order_subject' => [
        'name'        => 'メール件名 注文完了',
        'explanation' => null,
        'type'        => 'text',
        'required'    => false,
    ],
    'mail_order_subject_admin' => [
        'name'        => 'メール件名 注文完了（管理者用）',
        'explanation' => null,
        'type'        => 'text',
        'required'    => false,
    ],
    'number_limit_history' => [
        'name'        => '注文履歴の表示件数',
        'explanation' => null,
        'type'        => 'number',
        'required'    => true,
    ],
    'number_width_history' => [
        'name'        => '注文履歴のページャー幅',
        'explanation' => null,
        'type'        => 'number',
        'required'    => true,
    ],
    'number_limit_admin_order' => [
        'name'        => '注文の表示件数（管理ページ）',
        'explanation' => null,
        'type'        => 'number',
        'required'    => true,
    ],
    'number_width_admin_order' => [
        'name'        => '注文のページャー幅（管理ページ）',
        'explanation' => null,
        'type'        => 'number',
        'required'    => true,
    ],
];

/* プラグイン設定初期値 */
$GLOBALS['plugin']['order']['setting_default'] = [
    'use_approve'              => false,
    'use_pictures'             => true,
    'use_thumbnail'            => true,
    'default_code'             => null,
    'text_type'                => 'wysiwyg',
    'use_stock'                => false,
    'use_shipping'             => false,
    'use_direct'               => false,
    'text_index'               => '',
    'text_detail'              => '',
    'heading_address'          => '住所管理',
    'heading_cart'             => 'カート',
    'text_cart'                => '<p>カートの内容は以下のとおりです。</p>',
    'button_cart'              => 'カートを表示',
    'button_cart_add'          => 'カートへ追加',
    'button_cart_order'        => '注文手続きへ進む',
    'heading_order'            => '注文',
    'text_order'               => '<p>カートの内容を注文します。</p>',
    'text_order_preview'       => '<p>以下の内容で注文します。</p>',
    'text_order_complete'      => '<p>注文が完了しました。</p>',
    'button_order'             => '注文',
    'button_order_preview'     => '確認',
    'mail_order_subject'       => 'ご注文ありがとうございます',
    'mail_order_subject_admin' => 'ご注文がありました',
    'number_limit_history'     => 10,
    'number_width_history'     => 5,
    'number_limit_admin_order' => 10,
    'number_width_admin_order' => 5,
];

/* オプション項目 */
$GLOBALS['plugin']['order']['option'] = [
    'order_stock' => [
        // 種類
        'kind' => [
            'analog'  => 'アナログコンテンツ',
            'digital' => 'デジタルコンテンツ',
        ],
    ],
    'order_payment' => [
        // 有効
        'enabled' => [
            1 => '有効',
            0 => '無効',
        ],
    ],
    'order_record' => [
        // 状況
        'status' => [
            'order'     => '新規受付',
            'pending'   => '入金待ち',
            'paid'      => '入金確認済み',
            'shipping'  => '発送対応中',
            'shipped'   => '発送完了',
            'provided'  => 'ダウンロード提供済み',
            'completed' => '対応完了',
            'returned'  => '返品・返金',
            'cancelled' => 'キャンセル',
            'hold'      => '保留',
        ],
    ],
    'order_shippings' => [
        // 状況
        'status' => [
            'preparing' => '発送準備中',
            'completed' => '発送済み',
            'failed'    => '配送失敗',
            'returned'  => '返送',
        ],
    ],
    'order_delivery' => [
        // 有効
        'enabled' => [
            1 => '有効',
            0 => '無効',
        ],
        // 送料計算
        'calculate' => [
            'order'   => '1注文ごと',
            'product' => '1商品ごと',
        ],
    ],
    'order_spec' => [
        // 有効
        'enabled' => [
            1 => '有効',
            0 => '無効',
        ],
        // 提供方法
        'provide' => [
            'delivery' => '配送',
            'direct'   => '対面',
            'download' => 'ダウンロード',
        ],
        // 配送日目安
        'delivery_days' => [
            1   => '即日発送',
            2   => '翌日発送',
            3   => '数日程度で発送',
            7   => '1週間程度で発送',
            14  => '2週間程度で発送',
            21  => '3週間程度で発送',
            30  => '1ヶ月程度で発送',
            999 => '未定',
        ],
    ],
];
