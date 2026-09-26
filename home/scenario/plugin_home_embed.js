/*
 * home プラグインのシナリオ
 *
 * プラグインを有効化 → 設定「トップページ」に埋め込みの指定を書く → エントリー・ページを登録 → トップページに埋め込まれることを確認
 * → プラグインを無効に戻す → 設定を元に戻す → 後始末、を1本で通す。
 *
 * 前提: テスト用データベース側で home プラグインがインストールされ、無効になっていること。
 *       設定「トップページ」（text_home_index）が空であること（このシナリオが書き換えて最後に戻す）。
 */

/* テスト用のエントリー・ページ（指定の書式が \w だけを受け付けるので、コードにハイフンを使わない） */
var scenarioEntry = {
    code:     'scenario_home_entry',
    title:    'シナリオの埋め込みエントリー',
    text:     '<p>価格は $100 です。</p>',
    textBody: '価格は $100 です。'
};
var scenarioHidden = {
    code:  'scenario_home_hidden',
    title: 'シナリオの非公開エントリー'
};
var scenarioPage = {
    code:     'scenario_home_page',
    title:    'シナリオの埋め込みページ',
    text:     '<p>置換の記号 \\1 と $2 もそのまま出ます。</p>',
    textBody: '置換の記号 \\1 と $2 もそのまま出ます。'
};

/* 設定「トップページ」に書く内容（エントリー・非公開のエントリー・ページ・新着の一覧） */
var homeText = [
    '<entry:code=' + scenarioEntry.code + '>',
    '<entry:code=' + scenarioHidden.code + '>',
    '<page:code=' + scenarioPage.code + '>',
    '<entries:limit=5>'
].join('\n');

/* プラグイン詳細の操作（exec）のフォームを送信する */
var submitPluginExec = function(exec) {
    test.click('form:has(input[name="exec"][value="' + exec + '"]) button[type="submit"]', 'プラグイン詳細に ' + exec + ' のフォームがありません。');
};

/* 一覧から、コードで対象の行を取得する（エントリー・ページ） */
var codeRow = function(code) {
    return $('table tbody tr').filter(function() {
        return $(this).find('td code').text().trim() === code;
    });
};

/* 本文を入力してからコールバックを実行する（WYSIWYGエディタは非同期に作られるので待つ） */
var fillText = function(html, callback) {
    var textarea = $('form.register textarea[name="text"]');

    if (textarea.length === 0) {
        return test.abort('本文の入力欄が見つかりません。本文形式が「なし」になっている可能性があります。');
    }
    if (!textarea.hasClass('editor')) {
        textarea.val(html);

        return callback();
    }

    return test.wait(function() {
        return window.text != null && typeof window.text.setData === 'function';
    }, function() {
        window.text.setData(html);

        callback();
    }, 'WYSIWYGエディタ（CKEditor）が初期化されませんでした。CDNから読み込めているか確認してください。');
};

/* エントリー・ページの登録フォームに入力して送信する */
var submitEntry = function(entry, publicValue) {
    var form = $('form.register');

    test.assert(form.find('input[name="code"]').length === 1, '登録フォームが表示されていません。');

    form.find('select[name="public"]').val(publicValue).trigger('change');
    form.find('input[name="code"]').val(entry.code);
    form.find('input[name="title"]').val(entry.title);

    fillText(entry.text || '', function() {
        test.click('form.register button[type="submit"]');
    });
};

/* 編集画面で対象を確認して削除する */
var deleteEntry = function(code) {
    var form = $('form.delete');

    test.assertValue('form.register input[name="code"]', code, '削除対象が ' + code + ' ではありません。');
    test.assert(form.length === 1, '削除フォームが表示されていません。');

    form.off('submit');
    test.click('form.delete button[type="submit"]');
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
    // ログインできたことを確認して、home プラグインの詳細に移動
    function() {
        test.assertText('body', '管理者さん', 'ログインできていません。');
        test.visit('/admin/plugin_view?code=home');
    },
    // インストール済みで無効なことを確認して、有効化する
    function() {
        test.assertNoText('dd', '未インストール', 'home プラグインがインストールされていません。テスト用データベースにインストールしてください。');
        test.assertText('dd', '無効', 'home プラグインが既に有効です。前回のテストが中断した可能性があります。');

        submitPluginExec('enable');
    },
    // 有効化できたことを確認して、文言の設定に移動
    function() {
        test.assertText('div.alert-success', 'プラグインを有効化しました。', 'home プラグインを有効化できていません。');

        test.visit('/admin/setting?target=text');
    },
    // 設定「トップページ」が空なことを確認して、埋め込みの指定を書く
    function() {
        var textarea = $('form.register textarea[name="text_home_index"]');

        test.assert(textarea.length === 1, '設定に「トップページ」の入力欄がありません。');
        test.assert(textarea.val() === '', '設定「トップページ」が空ではありません。前回のテストが中断した可能性があります。');

        textarea.val(homeText);
        test.click('form.register button[type="submit"]');
    },
    // 設定できたことを確認して、エントリー管理に移動
    function() {
        test.assertText('div.alert-success', '設定を登録しました。', '設定を登録できていません。');
        // プラグインは表示用の値だけを書き換えるので、管理画面には指定がそのまま出ること（ここで埋め込んだ HTML が出ると、保存し直したときに指定が消える）
        test.assertValue('form.register textarea[name="text_home_index"]', homeText, '設定画面の「トップページ」に、埋め込みの指定がそのまま表示されていません。');

        test.visit('/admin/entry');
    },
    // 前回のデータが残っていないことを確認して、エントリー登録に移動
    function() {
        test.assert(codeRow(scenarioEntry.code).length === 0, 'エントリー ' + scenarioEntry.code + ' が残っています。前回のテストが中断した可能性があります。');
        test.assert(codeRow(scenarioHidden.code).length === 0, 'エントリー ' + scenarioHidden.code + ' が残っています。前回のテストが中断した可能性があります。');

        test.visit('/admin/entry_form');
    },
    // 公開のエントリーを登録
    function() {
        submitEntry(scenarioEntry, 'all');
    },
    // 登録できたことを確認して、エントリー登録に移動
    function() {
        test.assertText('div.alert-success', 'エントリーを登録しました。', 'エントリー ' + scenarioEntry.code + ' を登録できていません。');
        test.assert(codeRow(scenarioEntry.code).length === 1, '登録したエントリー ' + scenarioEntry.code + ' が一覧にありません。');

        test.visit('/admin/entry_form');
    },
    // 非公開のエントリーを登録
    function() {
        submitEntry(scenarioHidden, 'none');
    },
    // 登録できたことを確認して、ページ管理に移動
    function() {
        test.assertText('div.alert-success', 'エントリーを登録しました。', 'エントリー ' + scenarioHidden.code + ' を登録できていません。');
        test.assert(codeRow(scenarioHidden.code).length === 1, '登録したエントリー ' + scenarioHidden.code + ' が一覧にありません。');

        test.visit('/admin/page');
    },
    // 前回のデータが残っていないことを確認して、ページ登録に移動
    function() {
        test.assert(codeRow(scenarioPage.code).length === 0, 'ページ ' + scenarioPage.code + ' が残っています。前回のテストが中断した可能性があります。');

        test.visit('/admin/page_form');
    },
    // 公開のページを登録
    function() {
        submitEntry(scenarioPage, 'all');
    },
    // 登録できたことを確認して、トップページに移動
    function() {
        test.assertText('div.alert-success', 'ページを登録しました。', 'ページを登録できていません。');
        test.assert(codeRow(scenarioPage.code).length === 1, '登録したページが一覧にありません。');

        test.visit('/');
    },
    // 指定した場所にエントリー・ページ・新着の一覧が埋め込まれ、非公開のエントリーは出ないことを確認
    function() {
        var home = $('#home');
        var entry = home.find('#entry-' + scenarioEntry.code);
        var page = home.find('#page-' + scenarioPage.code);
        var headline = home.find('ul.headline');

        test.assert(home.length === 1, 'トップページに設定「トップページ」の内容が表示されていません。');

        test.assertText(entry.find('h2'), scenarioEntry.title, 'エントリーが埋め込まれていません。');
        test.assertText(entry, scenarioEntry.textBody, 'エントリーの本文が崩れています（$ などが置換の記号として扱われている可能性があります）。');
        test.assertExists(entry.find('a[href$="/entry/detail/' + scenarioEntry.code + '"]'), '埋め込んだエントリーに詳細へのリンクがありません。');

        test.assertText(page.find('h2'), scenarioPage.title, 'ページが埋め込まれていません。');
        test.assertText(page.find('div.text'), scenarioPage.textBody, 'ページの本文が崩れています（\\1 や $ などが置換の記号として扱われている可能性があります）。');

        test.assert(headline.length === 1, '新着の一覧が埋め込まれていません。');
        test.assertText(headline, scenarioEntry.title, '新着の一覧に公開のエントリーがありません。');

        test.assertNotExists('#entry-' + scenarioHidden.code, '非公開のエントリーが埋め込まれています。');
        test.assertNoText(home, scenarioHidden.title, '非公開のエントリーのタイトルがトップページに出ています。');
        test.assert(home.html().indexOf('code=') === -1, '埋め込みの指定が置き換わらずに残っています。');

        test.visit('/admin/plugin_view?code=home');
    },
    // プラグインを無効に戻す
    function() {
        submitPluginExec('disable');
    },
    // 無効にできたことを確認して、トップページに移動
    function() {
        test.assertText('div.alert-success', 'プラグインを無効化しました。', 'home プラグインを無効に戻せていません。');

        test.visit('/');
    },
    // 無効にすると埋め込まれなくなることを確認して、文言の設定に移動
    function() {
        test.assertNotExists('#entry-' + scenarioEntry.code, 'プラグインを無効にしてもエントリーが埋め込まれています。');
        test.assertNotExists('#page-' + scenarioPage.code, 'プラグインを無効にしてもページが埋め込まれています。');

        test.visit('/admin/setting?target=text');
    },
    // 設定「トップページ」を元の空に戻す
    function() {
        var textarea = $('form.register textarea[name="text_home_index"]');

        test.assertValue('form.register textarea[name="text_home_index"]', homeText, '設定「トップページ」が書き換わっています。');

        textarea.val('');
        test.click('form.register button[type="submit"]');
    },
    // 設定を戻せたことを確認して、エントリー管理に移動
    function() {
        test.assertText('div.alert-success', '設定を登録しました。', '設定を登録できていません。');
        test.assertValue('form.register textarea[name="text_home_index"]', '', '設定「トップページ」を空に戻せていません。');

        test.visit('/admin/entry');
    },
    // 公開のエントリーの編集ページに移動
    function() {
        test.click(codeRow(scenarioEntry.code).find('a'), '一覧の ' + scenarioEntry.code + ' の編集リンクが見つかりません。');
    },
    // 公開のエントリーを削除
    function() {
        deleteEntry(scenarioEntry.code);
    },
    // 削除できたことを確認して、非公開のエントリーの編集ページに移動
    function() {
        test.assertText('div.alert-success', 'エントリーを削除しました。', 'エントリー ' + scenarioEntry.code + ' を削除できていません。');

        test.click(codeRow(scenarioHidden.code).find('a'), '一覧の ' + scenarioHidden.code + ' の編集リンクが見つかりません。');
    },
    // 非公開のエントリーを削除
    function() {
        deleteEntry(scenarioHidden.code);
    },
    // 削除できたことを確認して、ページ管理に移動
    function() {
        test.assertText('div.alert-success', 'エントリーを削除しました。', 'エントリー ' + scenarioHidden.code + ' を削除できていません。');
        test.assert(codeRow(scenarioEntry.code).length === 0 && codeRow(scenarioHidden.code).length === 0, '削除したエントリーが一覧に残っています。');

        test.visit('/admin/page');
    },
    // ページの編集ページに移動
    function() {
        test.click(codeRow(scenarioPage.code).find('a'), '一覧の ' + scenarioPage.code + ' の編集リンクが見つかりません。');
    },
    // ページを削除
    function() {
        deleteEntry(scenarioPage.code);
    },
    // 削除できたことを確認して、ログアウト
    function() {
        test.assertText('div.alert-success', 'ページを削除しました。', 'ページを削除できていません。');
        test.assert(codeRow(scenarioPage.code).length === 0, '削除したページが一覧に残っています。');

        test.visit('/auth/logout');
    },
    // ログアウトできたことを確認して初期ページに戻る
    function() {
        test.assertExists('input[name="username"]', 'ログアウトできていません。');
        test.click('a:contains("ホームページへ戻る")');
    }
];
