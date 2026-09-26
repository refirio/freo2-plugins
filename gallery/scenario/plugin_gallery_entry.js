/*
 * gallery プラグインのシナリオ
 *
 * プラグインを有効化 → 管理画面で作品を登録 → ダッシュボード・公開側の一覧と詳細で確認 → パスワード認証で公開に変えて認証 → 削除 → プラグインを無効に戻す、を1本で通す。
 *
 * 前提: テスト用データベース側で gallery プラグインがインストールされ（型 gallery がある）、無効になっていること。
 *       （このシナリオが有効にして最後に戻す）
 */

/* テスト用の作品（test オブジェクトはページごとに作り直されるため、値は定数で持つ） */
var scenarioGallery = {
    code:     'scenario-gallery',
    title:    'シナリオの作品',
    text:     '<p>シナリオから登録した作品の説明です。</p>',
    textBody: 'シナリオから登録した作品の説明です。',
    password: 'scenario1234'
};

/* 認証前に表示される内容（設定 restricted_password_title / restricted_password_text の初期値） */
var restrictedTitle = '要認証: ';
var restrictedText  = 'パスワード認証により公開されます。';

/* プラグイン詳細の操作（exec）のフォームを送信する */
var submitPluginExec = function(exec) {
    test.click('form:has(input[name="exec"][value="' + exec + '"]) button[type="submit"]', 'プラグイン詳細に ' + exec + ' のフォームがありません。');
};

/* 一覧から、コードで対象の行を取得する */
var codeRow = function(code) {
    return $('table tbody tr').filter(function() {
        return $(this).find('td code').text().trim() === code;
    });
};

/* 公開側の詳細の枠（id はコードから作られる） */
var detailBox = function() {
    return $('#gallery-' + scenarioGallery.code);
};

/* 本文を入力してからコールバックを実行する（WYSIWYGエディタは非同期に作られるので待つ） */
var fillText = function(html, callback) {
    var textarea = $('form.register textarea[name="text"]');

    if (textarea.length === 0) {
        return test.abort('本文の入力欄が見つかりません。プラグインの設定で本文の入力項目が「なし」になっている可能性があります。');
    }
    if (!textarea.hasClass('editor')) {
        if (html !== null) {
            textarea.val(html);
        }

        return callback();
    }

    return test.wait(function() {
        return window.text != null && typeof window.text.setData === 'function';
    }, function() {
        if (html !== null) {
            window.text.setData(html);
        }

        callback();
    }, 'WYSIWYGエディタ（CKEditor）が初期化されませんでした。CDNから読み込めているか確認してください。');
};

test.scenario = [
    // 初期ページからログインページに移動
    function() {
        test.assertExists('a:contains("ログイン")', '初期ページにログインへのリンクがありません。');
        test.click('a:contains("ログイン")');
    },
    // 管理者用ページにログイン
    function() {
        var form = $('form:eq(0)');

        test.assert(form.find('input[name="username"]').length === 1, 'ログインフォームが表示されていません。');

        form.find('input[name="username"]').val('admin');
        form.find('input[name="password"]').val('abcd1234');
        test.click('form:eq(0) button[type="submit"]');
    },
    // ログインできたことを確認して、gallery プラグインの詳細に移動
    function() {
        test.assertText('body', '管理者さん', 'ログインできていません。');
        test.assertNotExists('a:contains("ギャラリー管理")', 'gallery プラグインが既に有効です（メニューがあります）。前回のテストが中断した可能性があります。');

        test.visit('/admin/plugin_view?code=gallery');
    },
    // インストール済みで無効なことを確認して、有効化する
    function() {
        test.assertNoText('dd', '未インストール', 'gallery プラグインがインストールされていません。テスト用データベースにインストールしてください。');
        test.assertText('dd', '無効', 'gallery プラグインが既に有効です。前回のテストが中断した可能性があります。');

        submitPluginExec('enable');
    },
    // 有効化できたことを確認して、ギャラリー管理に移動
    function() {
        test.assertText('div.alert-success', 'プラグインを有効化しました。', 'gallery プラグインを有効化できていません。');

        test.click('a:contains("ギャラリー管理")', '有効化しても管理画面のメニューに「ギャラリー管理」が出ていません。');
    },
    // 前回のデータが残っていないことを確認して、登録ページに移動
    function() {
        test.assert(codeRow(scenarioGallery.code).length === 0, '作品 ' + scenarioGallery.code + ' が残っています。前回のテストが中断した可能性があります。');

        test.click('a:contains("ギャラリー登録")');
    },
    // 作品を登録
    function() {
        var form = $('form.register');

        test.assert(form.find('input[name="code"]').length === 1, 'ギャラリーの登録フォームが表示されていません。');
        test.assert(form.find('input[name="type_id"]').val() !== '', '型 gallery の ID がフォームに入っていません。');

        form.find('select[name="public"]').val('all').trigger('change');
        form.find('input[name="code"]').val(scenarioGallery.code);
        form.find('input[name="title"]').val(scenarioGallery.title);

        fillText(scenarioGallery.text, function() {
            test.click('form.register button[type="submit"]');
        });
    },
    // 登録できたことを確認して、ダッシュボードに移動
    function() {
        var row = codeRow(scenarioGallery.code);

        test.assertText('div.alert-success', 'ギャラリーを登録しました。', '作品を登録できていません。');
        test.assert(row.length === 1, '登録した作品が一覧にありません。');
        test.assert(row.find('span.badge').last().text().trim() === '公開', '登録した作品の公開が「公開」になっていません。');

        test.visit('/admin/');
    },
    // ダッシュボードにギャラリー数のボックスが差し込まれていることを確認して、公開側の一覧に移動
    function() {
        test.wait(function() {
            return $('div.card-header:contains("ギャラリー数")').length === 1;
        }, function() {
            var count = $('div.card-header:contains("ギャラリー数")').closest('div.card').find('a').text().trim();

            test.assert(count === '1', 'ダッシュボードのギャラリー数が 1 になっていません。（' + count + '）');

            test.visit('/gallery/');
        }, 'ダッシュボードに「ギャラリー数」のボックスが表示されていません。');
    },
    // 公開側の一覧に作品があることを確認して、詳細に移動
    function() {
        test.assertExists('#plugin-gallery', 'ギャラリーの一覧が表示されていません。');

        test.click('#plugin-gallery a:contains("' + scenarioGallery.title + '")', '公開側の一覧に登録した作品がありません。');
    },
    // 公開側の詳細にタイトルと本文が表示されることを確認して、ギャラリー管理に移動
    function() {
        test.assert(location.pathname.indexOf('/gallery/detail/' + scenarioGallery.code) !== -1, '一覧のリンクが詳細（/gallery/detail/）を指していません。');
        test.assertText(detailBox().find('h2'), scenarioGallery.title, '公開側の詳細にタイトルが表示されていません。');
        test.assertText(detailBox().find('div.text'), scenarioGallery.textBody, '公開側の詳細に本文が表示されていません。');

        test.visit('/admin/gallery');
    },
    // 作品の編集ページに移動
    function() {
        test.click(codeRow(scenarioGallery.code).find('a:contains("編集")'), '一覧の ' + scenarioGallery.code + ' の編集リンクが見つかりません。');
    },
    // 公開を「パスワード認証で公開」に変更
    function() {
        var form = $('form.register');

        test.assertValue('form.register input[name="code"]', scenarioGallery.code, '編集対象が ' + scenarioGallery.code + ' ではありません。');
        test.assertValue('form.register input[name="title"]', scenarioGallery.title, '編集画面にタイトルが復元されていません。');

        form.find('select[name="public"]').val('password').trigger('change');
        form.find('input[name="password"]').val(scenarioGallery.password);

        fillText(null, function() {
            test.click('form.register button[type="submit"]');
        });
    },
    // 変更できたことを確認して、公開側の詳細に移動
    function() {
        test.assertText('div.alert-success', 'ギャラリーを登録しました。', '作品を編集できていません。');
        test.assert(codeRow(scenarioGallery.code).find('span.badge').last().text().trim() === 'パスワード認証で公開', '作品の公開が「パスワード認証で公開」になっていません。');

        test.visit('/gallery/detail/' + scenarioGallery.code);
    },
    // 認証前は本文が伏せられていることを確認して、正しいパスワードで認証する
    function() {
        var form = detailBox().find('form');

        test.assertText(detailBox().find('h2'), restrictedTitle + scenarioGallery.title, '認証前のタイトルに「' + restrictedTitle + '」が付いていません。');
        test.assertText(detailBox().find('div.text'), restrictedText, '認証前の本文が差し替えられていません。');
        test.assertNoText(detailBox(), scenarioGallery.textBody, '認証前なのに本来の本文が表示されています。');
        test.assert(form.length === 1 && /\/gallery\/detail\/scenario-gallery$/.test(form.attr('action')), 'パスワードフォームの送信先が /gallery/detail/<コード> になっていません。');

        form.find('input[name="password"]').val(scenarioGallery.password);
        test.click(detailBox().find('form button[type="submit"]'), 'パスワードフォームに認証ボタンがありません。');
    },
    // 認証後は詳細に戻り、本文が表示されることを確認して、ギャラリー管理に移動
    function() {
        test.assert(location.pathname.indexOf('/gallery/detail/' + scenarioGallery.code) !== -1, '認証後に詳細へ戻っていません。（' + location.pathname + '）');
        test.assertNoText(detailBox().find('h2'), '要認証', '認証後のタイトルに「要認証」が残っています。');
        test.assertText(detailBox().find('div.text'), scenarioGallery.textBody, '認証しても本来の本文が表示されません。');
        test.assertNotExists('#gallery-' + scenarioGallery.code + ' form input[name="password"]', '認証後もパスワードの入力フォームが表示されています。');

        test.visit('/admin/gallery');
    },
    // 作品の編集ページに移動
    function() {
        test.click(codeRow(scenarioGallery.code).find('a:contains("編集")'), '一覧の ' + scenarioGallery.code + ' の編集リンクが見つかりません。');
    },
    // 作品を削除
    function() {
        var form = $('form.delete');

        test.assertValue('form.register input[name="code"]', scenarioGallery.code, '削除対象が ' + scenarioGallery.code + ' ではありません。');
        test.assert(form.length === 1, '削除フォームが表示されていません。');

        form.off('submit');
        test.click('form.delete button[type="submit"]');
    },
    // 削除できたことを確認して、公開側の一覧に移動
    function() {
        test.assertText('div.alert-success', 'ギャラリーを削除しました。', '作品を削除できていません。');
        test.assert(codeRow(scenarioGallery.code).length === 0, '削除した作品が一覧に残っています。');

        test.visit('/gallery/');
    },
    // 公開側の一覧からも消えていることを確認して、gallery プラグインの詳細に移動
    function() {
        test.assertNotExists('#plugin-gallery a:contains("' + scenarioGallery.title + '")', '削除した作品が公開側の一覧に残っています。');

        test.visit('/admin/plugin_view?code=gallery');
    },
    // プラグインを無効に戻す
    function() {
        submitPluginExec('disable');
    },
    // 無効にできたことを確認して、ログアウト
    function() {
        test.assertText('div.alert-success', 'プラグインを無効化しました。', 'gallery プラグインを無効に戻せていません。');
        test.assertNotExists('a:contains("ギャラリー管理")', 'プラグインを無効にしても「ギャラリー管理」のメニューが残っています。');

        test.visit('/auth/logout');
    },
    // ログアウトできたことを確認して初期ページに戻る
    function() {
        test.assertExists('input[name="username"]', 'ログアウトできていません。');
        test.click('a:contains("ホームページへ戻る")');
    }
];
