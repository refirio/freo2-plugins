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
    $post = array(
        'contact' => model('normalize_contacts', [
            'name'    => isset($_POST['name'])    ? $_POST['name']    : '',
            'email'   => isset($_POST['email'])   ? $_POST['email']   : '',
            'subject' => isset($_POST['subject']) ? $_POST['subject'] : '',
            'message' => isset($_POST['message']) ? $_POST['message'] : '',
        ]),
    );

    // 会社名を整理
    $post['contact']['company'] = isset($_POST['company']) ? $_POST['company'] : '';

    // 電話番号を整理
    $post['contact']['tel'] = isset($_POST['tel']) ? $_POST['tel'] : '';

    // 入力データを検証
    $warnings = model('validate_contacts', $post['contact']);

    // 会社名を検証
    if (isset($post['contact']['company'])) {
        if (!validator_required($post['contact']['company'])) {
        } elseif (!validator_max_length($post['contact']['company'], 100)) {
            $warnings['company'] = '会社名は100文字以内で入力してください。';
        }
    }

    // 電話番号を検証
    if (isset($post['contact']['tel'])) {
        if (!validator_required($post['contact']['tel'])) {
            $warnings['tel'] = '電話番号が入力されていません。';
        } elseif (!validator_max_length($post['contact']['tel'], 20)) {
            $warnings['tel'] = '電話番号は20文字以内で入力してください。';
        }
    }

    // 入力データを登録
    if (isset($_POST['_type']) && $_POST['_type'] === 'json') {
        if (empty($warnings)) {
            ok();
        } else {
            warning($warnings);
        }
    } else {
        if (empty($warnings)) {
            $_SESSION['post']['contact'] = $post['contact'];

            // リダイレクト
            redirect('/contact/preview');
        } else {
            $_view['contact'] = $post['contact'];

            $_view['warnings'] = $warnings;
        }
    }
} elseif (isset($_GET['referer']) && $_GET['referer'] === 'preview') {
    // 入力データを復元
    $_view['contact'] = $_SESSION['post']['contact'];
} else {
    // 初期データを取得
    $_view['contact'] = model('default_contacts');

    $_view['contact']['company'] = '';
    $_view['contact']['tel']     = '';

    if (!empty($_SESSION['auth']['user']['id'])) {
        // ユーザーを取得
        $users = model('select_users', [
            'where' => [
                'id = :id AND enabled = 1',
                [
                    'id' => $_SESSION['auth']['user']['id'],
                ],
            ],
        ]);

        $_view['contact']['name']  = $users[0]['name'];
        $_view['contact']['email'] = $users[0]['email'];
    }
}

// お問い合わせの表示用データ作成
$_view['contact'] = model('view_contacts', $_view['contact']);

// タイトル
$_view['title'] = $GLOBALS['string']['heading_contact'];
