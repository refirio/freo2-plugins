<?php

// 投稿データを確認
if (empty($_SESSION['post']['contact'])) {
    // リダイレクト
    redirect('/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ワンタイムトークン
    if (!token('check')) {
        error('不正なアクセスです。');
    }

    // お問い合わせ内容を整理
    $message = "■会社名\n"
             . $_SESSION['post']['contact']['company'] . "\n"
             . "\n"
             . "■電話番号\n"
             . $_SESSION['post']['contact']['tel'] . "\n"
             . "\n"
             . "■お問い合わせ内容\n"
             . $_SESSION['post']['contact']['message'];

    $_SESSION['post']['contact']['message'] = $message;

    unset($_SESSION['post']['contact']['company']);
    unset($_SESSION['post']['contact']['tel']);

    // フォワード
    forward('plugins/contact/app/controllers/contact/post.php');
} else {
    $_view['contact'] = $_SESSION['post']['contact'];
}

// タイトル
$_view['title'] = $GLOBALS['string']['heading_contact'];
