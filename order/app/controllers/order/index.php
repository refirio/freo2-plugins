<?php

import('libs/modules/recaptcha.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ワンタイムトークン
    if (!token('check')) {
        error('不正なアクセスです。');
    }

    // reCAPTCHA
    if ($GLOBALS['config']['recaptcha_enable'] == true && empty($_SESSION['recaptcha'])) {
        $result = recaptcha_verify($GLOBALS['config']['recaptcha_secret_key']);
        if ($result) {
            $_SESSION['recaptcha'] = true;
        } else {
            error('reCAPTCHAでの認証に失敗しました。');
        }
    }

    // 入力データを整理
    $post = [
        'order_record' => model('normalize_order_records', [
            'provide'       => isset($_POST['provide'])       ? $_POST['provide']       : '',
            'payment_id'    => isset($_POST['payment_id'])    ? $_POST['payment_id']    : '',
          //'payment_fee'   => isset($_POST['payment_fee'])   ? $_POST['payment_fee']   : '',
            'delivery_id'   => isset($_POST['delivery_id'])   ? $_POST['delivery_id']   : '',
          //'delivery_cost' => isset($_POST['delivery_cost']) ? $_POST['delivery_cost'] : '',
          //'discount'      => isset($_POST['discount'])      ? $_POST['discount']      : '',
          //'shipping_date' => isset($_POST['shipping_date']) ? $_POST['shipping_date'] : '',
          //'status'        => isset($_POST['status'])        ? $_POST['status']        : '',
            'user_id'       => isset($_POST['user_id'])       ? $_POST['user_id']       : '',
            'email'         => isset($_POST['email'])         ? $_POST['email']         : '',
            'name_01'       => isset($_POST['name_01'])       ? $_POST['name_01']       : '',
            'name_02'       => isset($_POST['name_02'])       ? $_POST['name_02']       : '',
            'kana_01'       => isset($_POST['kana_01'])       ? $_POST['kana_01']       : '',
            'kana_02'       => isset($_POST['kana_02'])       ? $_POST['kana_02']       : '',
            'zipcode'       => isset($_POST['zipcode'])       ? $_POST['zipcode']       : '',
            'prefecture'    => isset($_POST['prefecture'])    ? $_POST['prefecture']    : '',
            'address_01'    => isset($_POST['address_01'])    ? $_POST['address_01']    : '',
            'address_02'    => isset($_POST['address_02'])    ? $_POST['address_02']    : '',
            'telephone'     => isset($_POST['telephone'])     ? $_POST['telephone']     : '',
            'text'          => isset($_POST['text'])          ? $_POST['text']          : '',
            'message'       => isset($_POST['message'])       ? $_POST['message']       : '',
        ]),
    ];

    // 入力データを検証＆登録
    $warnings = model('validate_order_records', $post['order_record']);

    // メールアドレス（注文完了メールを送るため、公開側の注文では必須。管理画面からの登録では任意）
    if (!isset($warnings['email']) && !validator_required($post['order_record']['email'])) {
        $warnings['email'] = 'メールアドレスが入力されていません。';
    }

    if (isset($_POST['_type']) && $_POST['_type'] === 'json') {
        if (empty($warnings)) {
            ok();
        } else {
            warning($warnings);
        }
    } else {
        if (empty($warnings)) {
            $_SESSION['post']['order_record'] = $post['order_record'];

            // リダイレクト
            redirect('/order/preview');
        } else {
            $_view['order_record'] = $post['order_record'];

            $_view['warnings'] = $warnings;
        }
    }
} elseif (isset($_GET['referer']) && $_GET['referer'] === 'preview') {
    // 入力データを復元
    $_view['order_record'] = $_SESSION['post']['order_record'];
} else {
    // 初期データを取得
    $_view['order_record'] = model('default_order_records');

    // ログイン中のユーザーのメールアドレスを初期値にする
    if (!empty($_SESSION['auth']['user']['id'])) {
        $users = model('select_users', [
            'where' => [
                'id = :id AND enabled = 1',
                [
                    'id' => $_SESSION['auth']['user']['id'],
                ],
            ],
        ]);
        if (!empty($users)) {
            $_view['order_record']['email'] = $users[0]['email'];
        }
    }

    // カートの内容を取得
    $provide = null;
    if (isset($_GET['provide']) && !empty($_SESSION['cart'][$_GET['provide']])) {
        $provide = $_GET['provide'];
    } elseif (!empty($_SESSION['cart']['delivery'])) {
        $provide = 'delivery';
    } elseif (!empty($_SESSION['cart']['download'])) {
        $provide = 'download';
    }

    if ($provide) {
        $_SESSION['item'] = $_SESSION['cart'][$provide];
    } else {
        $_SESSION['item'] = [];
    }

    $_view['order_record']['provide'] = $provide;
}

// 支払方法を取得
$_view['order_payments'] = model('select_order_payments', [
    'where'    => 'enabled = 1',
    'order_by' => 'id',
]);

// 配送方法を取得
$_view['order_deliveries'] = model('select_order_deliveries', [
    'where'    => 'enabled = 1',
    'order_by' => 'id',
]);

// 登録済みの住所を取得
$_view['order_addresses'] = [];
if (!empty($_SESSION['auth']['user']['id'])) {
    $_view['order_addresses'] = model('select_order_addresses', [
        'where' => [
            'user_id = :user_id',
            [
                'user_id' => $_SESSION['auth']['user']['id'],
            ],
        ],
        'order_by' => 'id',
    ]);
}

// 注文記録の表示用データ作成
$_view['order_record'] = model('view_order_records', $_view['order_record'] ?? []);

if (empty($_SESSION['item'])) {
    // リダイレクト
    redirect('/catalog/');
} else {
    // 規格を取得
    $order_specs = model('select_order_specs', [
        'where' => 'id IN(' . implode(',', array_map('db_escape', array_column($_SESSION['item'], 'order_spec_id'))) . ') AND enabled = 1',
    ]);
    $_view['order_spec_sets'] = [];
    foreach ($order_specs as $order_spec) {
        $_view['order_spec_sets'][$order_spec['id']] = $order_spec;
    }

    // エントリーを取得
    $entries = service_entry_select_published('catalog', [
        'where' => 'entries.id IN(' . implode(',', array_map('db_escape', array_column($_view['order_spec_sets'], 'entry_id'))) . ')',
    ]);
    $_view['entry_sets'] = [];
    foreach ($entries as $entry) {
        $_view['entry_sets'][$entry['id']] = $entry;
    }
}

// タイトル
$_view['title'] = $GLOBALS['plugin']['order']['setting']['heading_order'];
