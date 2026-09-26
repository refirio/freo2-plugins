/*
 * contact プラグインのシナリオ
 *
 * プラグインを有効化 → 公開側のお問い合わせ（会社名・電話番号が足されたフォーム）→ 自動返信メール → 管理画面で確認・削除 → プラグインを無効に戻す、を1本で通す。
 *
 * 前提: テスト用データベース側で contact プラグインがインストールされ、無効になっていること。
 *       （有効のままだと本体の contact.js が電話番号の必須エラーで通らないため、このシナリオが有効にして最後に戻す）
 */

/* テスト用のお問い合わせ（test オブジェクトはページごとに作り直されるため、値は定数で持つ） */
var scenarioContact = {
    name:    'シナリオ花子',
    email:   'scenario-plugin-contact@example.com',
    company: 'シナリオ株式会社',
    tel:     '03-0000-0000',
    subject: 'シナリオのお問い合わせ（プラグイン）',
    message: 'プラグインのフォームから送信したお問い合わせの内容です。'
};

/* お問い合わせ内容の先頭に差し込まれる見出し（preview.php） */
var messageHeadings = ['■会社名', '■電話番号', '■お問い合わせ内容'];

/* プラグイン詳細の操作（exec）のフォームを送信する */
var submitPluginExec = function(exec) {
    test.click('form:has(input[name="exec"][value="' + exec + '"]) button[type="submit"]', 'プラグイン詳細に ' + exec + ' のフォームがありません。');
};

/* 入力欄の直後に表示された入力エラーを取得する */
var fieldWarning = function(name) {
    return $('form.register [name="' + name + '"]').parent().find('div.warning');
};

/* 入力して送信し、入力エラーが表示されるまで待つ */
var submitAndWait = function(values, callback) {
    var form = $('form.register');

    for (var name in values) {
        form.find('[name="' + name + '"]').val(values[name]);
    }

    $('div.warning').remove();
    test.click('form.register button[type="submit"]');
    test.wait(function() {
        return $('form.register div.warning').length > 0;
    }, callback, '入力エラーが表示されませんでした。');
};

/* お問い合わせフォームに正しい内容を入力して送信する */
var submitContact = function() {
    var form = $('form.register');

    for (var name in scenarioContact) {
        form.find('[name="' + name + '"]').val(scenarioContact[name]);
    }

    test.click('form.register button[type="submit"]');
};

/* お問い合わせ一覧から、件名で対象の行を取得する */
var contactRow = function(subject) {
    return $('table tbody tr').filter(function() {
        return $(this).text().indexOf(subject) !== -1;
    });
};

/* ログインフォームから admin でログインする */
var login = function() {
    var form = $('form:eq(0)');

    test.assert(form.find('input[name="username"]').length === 1, 'ログインフォームが表示されていません。');

    form.find('input[name="username"]').val('admin');
    form.find('input[name="password"]').val('abcd1234');
    test.click('form:eq(0) button[type="submit"]');
};

test.scenario = [
    // 初期ページからログインページに移動
    function() {
        test.assertExists('a:contains("ログイン")', '初期ページにログインへのリンクがありません。');
        test.click('a:contains("ログイン")');
    },
    // 管理者用ページにログイン
    function() {
        login();
    },
    // ログインできたことを確認して、contact プラグインの詳細に移動
    function() {
        test.assertText('body', '管理者さん', 'ログインできていません。');
        test.visit('/admin/plugin_view?code=contact');
    },
    // インストール済みで無効なことを確認して、有効化する
    function() {
        test.assertNoText('dd', '未インストール', 'contact プラグインがインストールされていません。テスト用データベースにインストールしてください。');
        test.assertText('dd', '無効', 'contact プラグインが既に有効です。前回のテストが中断した可能性があります。');

        submitPluginExec('enable');
    },
    // 有効化できたことを確認して、ログアウト
    function() {
        test.assertText('div.alert-success', 'プラグインを有効化しました。', 'contact プラグインを有効化できていません。');

        test.visit('/auth/logout');
    },
    // ログアウトできたことを確認して、お問い合わせページに移動
    function() {
        test.assertExists('input[name="username"]', 'ログアウトできていません。');

        test.visit('/contact/');
    },
    // プラグインの入力項目があることを確認し、未入力で送信すると電話番号が必須になっていることを確認
    function() {
        test.assert($('form.register').length === 1, 'お問い合わせフォームが表示されていません。');
        test.assertExists('form.register input[name="company"]', '会社名の入力欄がありません。プラグインのフォームに差し替わっていません。');
        test.assertExists('form.register input[name="tel"]', '電話番号の入力欄がありません。プラグインのフォームに差し替わっていません。');

        submitAndWait({ name: '', email: '', company: '', tel: '', subject: '', message: '' }, function() {
            test.assertText(fieldWarning('tel'), '電話番号が入力されていません。', '電話番号未入力のエラーが表示されていません。');
            test.assertText(fieldWarning('name'), 'お名前が入力されていません。', 'お名前未入力のエラーが表示されていません（本体の検証が効いていません）。');
            test.assert(fieldWarning('company').length === 0, '会社名は任意なのに入力エラーが表示されています。');

            test.reload();
        });
    },
    // 会社名・電話番号の長さの上限を超えると、その2つだけが入力エラーになることを確認
    function() {
        submitAndWait({
            name:    scenarioContact.name,
            email:   scenarioContact.email,
            company: 'あ'.repeat(101),
            tel:     '0'.repeat(21),
            subject: scenarioContact.subject,
            message: scenarioContact.message
        }, function() {
            test.assertText(fieldWarning('company'), '会社名は100文字以内で入力してください。', '会社名の長さのエラーが表示されていません。');
            test.assertText(fieldWarning('tel'), '電話番号は20文字以内で入力してください。', '電話番号の長さのエラーが表示されていません。');
            test.assert($('form.register div.warning').length === 2, '会社名・電話番号以外にも入力エラーが表示されています。（' + $('form.register div.warning').text() + '）');

            test.reload();
        });
    },
    // 正しい内容を入力して確認画面に進む
    function() {
        submitContact();
    },
    // 確認画面に会社名・電話番号が表示されることを確認して、「修正」で入力画面に戻る
    function() {
        test.assertText('#contact dl', scenarioContact.company, '確認画面に会社名が表示されていません。');
        test.assertText('#contact dl', scenarioContact.tel, '確認画面に電話番号が表示されていません。');
        test.assertText('#contact dl', scenarioContact.message, '確認画面にお問い合わせ内容が表示されていません。');

        test.click('a:contains("修正")', '確認画面に「修正」のリンクがありません。');
    },
    // 会社名・電話番号も復元されていることを確認して、もう一度確認画面に進む
    function() {
        test.assertValue('form.register input[name="company"]', scenarioContact.company, '「修正」で戻ったときに会社名が復元されていません。');
        test.assertValue('form.register input[name="tel"]', scenarioContact.tel, '「修正」で戻ったときに電話番号が復元されていません。');
        test.assertValue('form.register textarea[name="message"]', scenarioContact.message, '「修正」で戻ったときにお問い合わせ内容が復元されていません。');

        submitContact();
    },
    // 確認画面から送信する
    function() {
        test.assertText('#contact dl', scenarioContact.company, '確認画面に会社名が表示されていません。');

        test.click('#contact form button[type="submit"]', '確認画面に送信ボタンがありません。');
    },
    // 送信できたことを確認して、記録された自動返信メールを開く
    function() {
        test.assertText('#contact', 'お問い合わせを送信しました。', 'お問い合わせを送信できていません。');

        test.visit('/tool/test/mail?date=' + test.date + '&filename=' + encodeURIComponent(test.time + '_' + scenarioContact.email));
    },
    // 自動返信メールのお問い合わせ内容に、会社名・電話番号が差し込まれていることを確認
    function() {
        test.assertText('body', 'to: ' + scenarioContact.email, '自動返信メールが記録されていません。（mail_log が無効になっている可能性があります）');

        messageHeadings.forEach(function(heading) {
            test.assertText('body', heading, '自動返信メールに「' + heading + '」がありません。');
        });
        test.assertText('body', scenarioContact.company, '自動返信メールに会社名が含まれていません。');
        test.assertText('body', scenarioContact.tel, '自動返信メールに電話番号が含まれていません。');
        test.assertText('body', scenarioContact.message, '自動返信メールにお問い合わせ内容が含まれていません。');

        test.visit('/auth/');
    },
    // 管理者用ページにログイン
    function() {
        login();
    },
    // お問い合わせ管理ページに移動
    function() {
        test.assertText('body', '管理者さん', 'ログインできていません。');
        test.click('a:contains("お問い合わせ管理")');
    },
    // 送信したお問い合わせが一覧にあることを確認して、表示ページに移動
    function() {
        var row = contactRow(scenarioContact.subject);

        test.assert(row.length === 1, '送信したお問い合わせが一覧にありません。');
        test.click(row.find('a:contains("表示")'), '一覧に「表示」のリンクが見つかりません。');
    },
    // 会社名・電話番号がお問い合わせ内容の中に記録されていることを確認して、お問い合わせ管理ページに戻る
    function() {
        var text = $('main').text();
        var company = text.indexOf(scenarioContact.company);
        var tel = text.indexOf(scenarioContact.tel);
        var message = text.indexOf(scenarioContact.message);

        test.assert(company !== -1, '表示ページに会社名が記録されていません。');
        test.assert(tel !== -1, '表示ページに電話番号が記録されていません。');
        test.assert(company < tel && tel < message, 'お問い合わせ内容の先頭に会社名・電話番号が差し込まれていません。');

        test.click('a:contains("お問い合わせ管理")');
    },
    // お問い合わせ編集ページに移動
    function() {
        test.click(contactRow(scenarioContact.subject).find('a:contains("編集")'), '一覧に「編集」のリンクが見つかりません。');
    },
    // お問い合わせを削除
    function() {
        var form = $('form.delete');

        test.assertValue('form.register input[name="subject"]', scenarioContact.subject, '削除対象が送信したお問い合わせではありません。');
        test.assert(form.length === 1, '削除フォームが表示されていません。');

        form.off('submit');
        test.click('form.delete button[type="submit"]');
    },
    // 削除できたことを確認して、contact プラグインの詳細に移動
    function() {
        test.assertText('div.alert-success', 'お問い合わせを削除しました。', 'お問い合わせを削除できていません。');
        test.assert(contactRow(scenarioContact.subject).length === 0, '削除したお問い合わせが一覧に残っています。');

        test.visit('/admin/plugin_view?code=contact');
    },
    // プラグインを無効に戻す
    function() {
        submitPluginExec('disable');
    },
    // 無効にできたことを確認して、お問い合わせページに移動
    function() {
        test.assertText('div.alert-success', 'プラグインを無効化しました。', 'contact プラグインを無効に戻せていません。');

        test.visit('/contact/');
    },
    // 本体のフォームに戻っていることを確認して、ログアウト
    function() {
        test.assert($('form.register').length === 1, 'お問い合わせフォームが表示されていません。');
        test.assertNotExists('form.register input[name="tel"]', 'プラグインを無効にしても電話番号の入力欄が残っています。');

        test.visit('/auth/logout');
    },
    // ログアウトできたことを確認して初期ページに戻る
    function() {
        test.assertExists('input[name="username"]', 'ログアウトできていません。');
        test.click('a:contains("ホームページへ戻る")');
    }
];
