<?php

import('plugins/order/app/services/order_record.php');
import('app/services/mail.php');

//ワンタイムトークン
if (!token('check')) {
    error('不正なアクセスです。');
}

// 投稿データを確認
if (empty($_SESSION['item']) || empty($_SESSION['post'])) {
    // リダイレクト
    redirect('/');
}

// 支払方法を取得
$order_payments = model('select_order_payments', [
    'where' => [
        'id = :id AND enabled = 1',
        [
            'id' => $_SESSION['post']['order_record']['payment_id'],
        ],
    ],
]);
if (empty($order_payments)) {
    error('支払方法が見つかりません。');
}
$order_payment = $order_payments[0];

// 配送方法を取得
$order_delivery = null;
if ($_SESSION['post']['order_record']['provide'] === 'delivery') {
    $order_deliveries = model('select_order_deliveries', [
        'where' => [
            'id = :id AND enabled = 1',
            [
                'id' => $_SESSION['post']['order_record']['delivery_id'],
            ],
        ],
    ]);
    if (empty($order_deliveries)) {
        error('配送方法が見つかりません。');
    }
    $order_delivery = $order_deliveries[0];
}

// 規格を取得
$order_specs = model('select_order_specs', [
    'where' => 'order_specs.id IN(' . implode(',', array_map('db_escape', array_column($_SESSION['item'], 'order_spec_id'))) . ') AND order_specs.enabled = 1',
], [
    'associate' => true,
]);
$order_spec_sets = [];
foreach ($order_specs as $order_spec) {
    $order_spec_sets[$order_spec['id']] = $order_spec;
}

// 商品合計金額と数量合計を集計
$subtotal       = 0;
$quantity_total = 0;
foreach ($_SESSION['item'] as $item) {
    $subtotal       += $order_spec_sets[$item['order_spec_id']]['selling_price'] * $item['quantity'];
    $quantity_total += $item['quantity'];
}

// 送料を計算
$delivery_cost = 0;
if ($order_delivery !== null) {
    if ($order_delivery['calculate'] === 'product') {
        $delivery_cost = $order_delivery['cost'] * $quantity_total;
    } else {
        $delivery_cost = $order_delivery['cost'];
    }
    if ($order_delivery['threshold'] !== null && $subtotal >= $order_delivery['threshold']) {
        // TODO: 都道府県ごとの上乗せ送料（surcharge）は未対応
        $delivery_cost = $order_delivery['discounted'];
    }
}

// トランザクションを開始
db_transaction();

// 注文記録を登録
$resource = service_order_record_insert([
    'values' => [
        'provide'       => $_SESSION['post']['order_record']['provide'],
        'payment_id'    => $_SESSION['post']['order_record']['payment_id'],
        'payment_fee'   => $order_payment['fee'],
        'delivery_id'   => $_SESSION['post']['order_record']['delivery_id'],
        'delivery_cost' => $delivery_cost,
        'discount'      => 0,       // 仮の値
        'status'        => 'order', // 仮の値
        'user_id'       => !empty($_SESSION['auth']['user']['id']) ? $_SESSION['auth']['user']['id'] : null,
        'email'         => $_SESSION['post']['order_record']['email'],
        'name_01'       => $_SESSION['post']['order_record']['name_01'],
        'name_02'       => $_SESSION['post']['order_record']['name_02'],
        'kana_01'       => $_SESSION['post']['order_record']['kana_01'],
        'kana_02'       => $_SESSION['post']['order_record']['kana_02'],
        'zipcode'       => $_SESSION['post']['order_record']['zipcode'],
        'prefecture'    => $_SESSION['post']['order_record']['prefecture'],
        'address_01'    => $_SESSION['post']['order_record']['address_01'],
        'address_02'    => $_SESSION['post']['order_record']['address_02'],
        'telephone'     => $_SESSION['post']['order_record']['telephone'],
        'message'       => $_SESSION['post']['order_record']['message'],
    ],
], [
    'items' => $_SESSION['item'],
]);
if (!$resource) {
    error('データを登録できません。');
}

// トランザクションを終了
db_commit();

// メールで使う表示用データを作成
$_view['subtotal']       = $subtotal;
$_view['delivery_cost']  = $delivery_cost;
$_view['payment_fee']    = $order_payment['fee'];
$_view['total']          = $subtotal + $delivery_cost + $order_payment['fee'];
$_view['order_payment']  = $order_payment;
$_view['order_delivery'] = $order_delivery;
$_view['items']          = [];
foreach ($_SESSION['item'] as $item) {
    $order_spec = $order_spec_sets[$item['order_spec_id']];

    $_view['items'][] = [
        'title'     => $order_spec['entry_title'],
        'spec_name' => $order_spec['name'],
        'price'     => $order_spec['selling_price'],
        'quantity'  => $item['quantity'],
        'subtotal'  => $order_spec['selling_price'] * $item['quantity'],
    ];
}

// メールを送信（管理者用）
$to      = $GLOBALS['setting']['mail_to'];
$subject = $GLOBALS['plugin']['order']['setting']['mail_order_subject_admin'];
$message = view('../../plugins/order/app/views/mail/order/send_admin.php', true);
$headers = [
    'From' => $GLOBALS['setting']['mail_from'],
];

if (service_mail_send($to, $subject, $message, $headers) === false) {
    error('メールを送信できません。');
}

// メールを送信（自動返信）
$to      = $_SESSION['post']['order_record']['email'];
$subject = $GLOBALS['plugin']['order']['setting']['mail_order_subject'];
$message = view('../../plugins/order/app/views/mail/order/send_user.php', true);
$headers = [
    'From' => $GLOBALS['setting']['mail_from'],
];

if (service_mail_send($to, $subject, $message, $headers) === false) {
    error('メールを送信できません。');
}

$provide = $_SESSION['post']['order_record']['provide'];

// 投稿セッションを初期化
unset($_SESSION['post']);
unset($_SESSION['item']);
$_SESSION['cart'][$provide] = []; // 処理対象のみカラにする。キーごと消すと、カートの追加・変更・削除が foreach で未定義のキーを読むため

// リダイレクト
redirect('/order/complete');
