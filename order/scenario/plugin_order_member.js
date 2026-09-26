/*
 * order プラグインのシナリオ（会員での注文）
 *
 * 前提データ（支払方法・配送方法・商品・規格）と会員を作る
 * → 会員でログインして住所を登録（入力エラーも確認）→ 登録した住所を選んで注文 → 注文履歴・注文詳細で確認
 * → 住所を削除 → 管理画面で注文が会員にひも付いていることを確認 → 後始末、を1本で通す。
 *
 * 前提: テスト用データベース側で order プラグインがインストールされ、有効になっていること。
 *       プラグインの設定は変えない（在庫管理が無効なので、在庫と製品は作らない。規格一覧に「製品」のリンクも出ない）。
 */

/* テストデータ */
var scenarioPaymentName  = 'シナリオ会員支払';
var scenarioDeliveryName = 'シナリオ会員配送';
var scenarioCatalog      = { code: 'scenario-member', title: 'シナリオ会員商品' };
var scenarioSpec         = { code: 'scenario-spec-member', name: 'シナリオ会員版' };

/* テスト用の会員（会員向けの画面を使うので、権限は最小の「ゲスト」にする） */
var scenarioMember = {
    username:  'scenario-buyer',
    password:  'scenario5678',
    name:      'シナリオ購入者',
    email:     'scenario-buyer@example.com',
    authority: 'ゲスト'
};

/* 会員が登録する住所 */
var scenarioAddress = {
    name_01:    '会員',
    name_02:    '花子',
    kana_01:    'カイイン',
    kana_02:    'ハナコ',
    zipcode:    '1000001',
    prefecture: '東京都',
    address_01: '千代田区1-1',
    address_02: '',
    telephone:  '0311112222'
};
var scenarioAddressName = scenarioAddress.name_01 + ' ' + scenarioAddress.name_02;

/* 一覧から、コードで対象の行を取得する（在庫・規格・商品・製品） */
var codeRow = function(code) {
    return $('table tbody tr').filter(function() {
        return $(this).find('td code').first().text().trim() === code;
    });
};

/* 一覧から、1列目の名前で対象の行を取得する（支払方法・配送方法・住所） */
var nameRow = function(name) {
    return $('table tbody tr').filter(function() {
        return $(this).find('td').first().text().trim() === name;
    });
};

/* 一覧から、行に含まれる文字列で対象の行を取得する（注文） */
var textRow = function(value) {
    return $('table tbody tr').filter(function() {
        return $(this).text().indexOf(value) !== -1;
    });
};

/* ユーザー一覧から、ユーザー名で対象の行を取得する（メールアドレスの列も code なので先頭の td だけを見る） */
var userRow = function(username) {
    return $('table tbody tr').filter(function() {
        return $(this).find('td').first().find('code').text().trim() === username;
    });
};

/* 本文のエディタ（CKEditor）の準備を待ってから続ける */
var waitEditor = function(callback) {
    if ($('textarea.editor').length === 0) {
        callback();

        return;
    }

    test.wait(function() {
        return typeof window.text !== 'undefined' && window.text !== null;
    }, callback, '本文のエディタが準備できません。');
};

/* 削除フォームを送信する（common.js の確認ダイアログを外す） */
var submitDeleteForm = function() {
    var form = $('form.delete');

    if (!test.assert(form.length === 1, '削除フォームが表示されていません。')) {
        return;
    }

    form.off('submit');
    test.click('form.delete button[type="submit"]');
};

/* セレクトボックスから、表示名に文字列を含む選択肢を選ぶ */
var selectByText = function(select, text, message) {
    var option = select.find('option').filter(function() {
        return $(this).text().indexOf(text) !== -1;
    });

    if (!test.assert(option.length === 1, message)) {
        return false;
    }

    select.val(option.val());

    return true;
};

/* ログインフォームから送信する */
var login = function(username, password) {
    var form = $('form:eq(0)');

    test.assert(form.find('input[name="username"]').length === 1, 'ログインフォームが表示されていません。');

    form.find('input[name="username"]').val(username);
    form.find('input[name="password"]').val(password);
    test.click('form:eq(0) button[type="submit"]');
};

/* 住所の登録フォームに入力する */
var fillAddress = function(values) {
    var form = $('form.register');

    for (var name in values) {
        form.find('input[name="' + name + '"]').val(values[name]);
    }
};

/* 送信して、入力エラーが表示されるまで待つ */
var submitAndWait = function(callback) {
    $('div.warning').remove();
    test.click('form.register button[type="submit"]');
    test.wait(function() {
        return $('form.register div.warning').length > 0;
    }, callback, '入力エラーが表示されませんでした。');
};

/* 編集ページへ移動して削除し、一覧で確認するステップ（編集 → 削除 → 確認して次へ） */
var deleteSteps = function(label, gotoEdit, assertTarget, message, gotoNext) {
    return [
        function() {
            gotoEdit();
        },
        function() {
            assertTarget();
            submitDeleteForm();
        },
        function() {
            test.assertText('div.alert-success', message, label + 'を削除できていません。');
            gotoNext();
        }
    ];
};

test.scenario = [
    // 初期ページ: ログインページへ移動する
    function() {
        test.assertExists('a:contains("ログイン")', '初期ページにログインへのリンクがありません。');
        test.click('a:contains("ログイン")');
    },
    // ログインページ: 管理者でログインする
    function() {
        login('admin', 'abcd1234');
    },
    // 管理画面: order プラグインが有効なことを確認して、支払方法管理へ移動する
    function() {
        test.assertText('body', '管理者さん', 'ログインできていません。');
        test.assertExists('a:contains("商品管理")', 'order プラグインのメニューがありません。プラグインがインストール・有効化されているか確認してください。');

        test.visit('/admin/payment');
    },
    // 支払方法一覧: 前回のデータが残っていないことを確認して、登録ページへ移動する
    function() {
        test.assert(nameRow(scenarioPaymentName).length === 0, '支払方法 ' + scenarioPaymentName + ' が残っています。前回のテストが中断した可能性があります。');
        test.click('a:contains("支払方法登録")');
    },
    // 支払方法登録
    function() {
        var form = $('form.register');

        form.find('input[name="name"]').val(scenarioPaymentName);
        form.find('input[name="fee"]').val('0');
        form.find('select[name="enabled"]').val('1');
        test.click('form.register button[type="submit"]');
    },
    // 支払方法一覧: 登録できたことを確認して、配送方法管理へ移動する
    function() {
        test.assertText('div.alert-success', '支払方法を登録しました。', '支払方法を登録できていません。');

        test.visit('/admin/delivery');
    },
    // 配送方法一覧: 前回のデータが残っていないことを確認して、登録ページへ移動する
    function() {
        test.assert(nameRow(scenarioDeliveryName).length === 0, '配送方法 ' + scenarioDeliveryName + ' が残っています。前回のテストが中断した可能性があります。');
        test.click('a:contains("配送方法登録")');
    },
    // 配送方法登録（注文ごとに500円）
    function() {
        var form = $('form.register');

        form.find('input[name="name"]').val(scenarioDeliveryName);
        form.find('select[name="calculate"]').val('order');
        form.find('input[name="cost"]').val('500');
        form.find('select[name="enabled"]').val('1');
        test.click('form.register button[type="submit"]');
    },
    // 配送方法一覧: 登録できたことを確認して、商品管理へ移動する
    function() {
        test.assertText('div.alert-success', '配送方法を登録しました。', '配送方法を登録できていません。');

        test.visit('/admin/catalog');
    },
    // 商品一覧: 前回のデータが残っていないことを確認して、登録ページへ移動する
    function() {
        test.assert(codeRow(scenarioCatalog.code).length === 0, '商品 ' + scenarioCatalog.code + ' が残っています。前回のテストが中断した可能性があります。');
        test.click('a:contains("商品登録")');
    },
    // 商品登録
    function() {
        var form = $('form.register');

        form.find('select[name="public"]').val('all');
        form.find('input[name="code"]').val(scenarioCatalog.code);
        form.find('input[name="title"]').val(scenarioCatalog.title);

        waitEditor(function() {
            test.click('form.register button[type="submit"]');
        });
    },
    // 商品一覧: 登録できたことを確認して、規格管理へ移動する
    function() {
        test.assertText('div.alert-success', '商品を登録しました。', '商品を登録できていません。');

        test.click(codeRow(scenarioCatalog.code).find('a:contains("規格")'), '一覧の ' + scenarioCatalog.code + ' の規格へのリンクが見つかりません。');
    },
    // 規格一覧: 登録ページへ移動する
    function() {
        test.click('a:contains("規格登録")');
    },
    // 規格登録（配送・2,000円）
    function() {
        var form = $('form.register');

        form.find('input[name="code"]').val(scenarioSpec.code);
        form.find('input[name="name"]').val(scenarioSpec.name);
        form.find('select[name="provide"]').val('delivery');
        form.find('input[name="selling_price"]').val('2000');
        form.find('select[name="enabled"]').val('1');
        test.click('form.register button[type="submit"]');
    },
    // 規格一覧: 登録できたことを確認して、ユーザー管理へ移動する
    function() {
        test.assertText('div.alert-success', '規格を登録しました。', '規格を登録できていません。');
        test.assert(codeRow(scenarioSpec.code).length === 1, '登録した規格が一覧にありません。');

        test.click('a:contains("ユーザー管理")');
    },
    // ユーザー一覧: 前回のデータが残っていないことを確認して、登録ページへ移動する
    function() {
        test.assert(userRow(scenarioMember.username).length === 0, 'ユーザー ' + scenarioMember.username + ' が残っています。前回のテストが中断した可能性があります。');
        test.click('a:contains("ユーザー登録")');
    },
    // ユーザー登録: ゲストの会員を登録する
    function() {
        var form = $('form.register');
        var authority = form.find('select[name="authority_id"] option').filter(function() {
            return $(this).text() === scenarioMember.authority;
        });

        test.assert(authority.length === 1, '権限「' + scenarioMember.authority + '」がありません。');

        form.find('input[name="username"]').val(scenarioMember.username);
        form.find('input[name="password"]').val(scenarioMember.password);
        form.find('input[name="password_confirm"]').val(scenarioMember.password);
        form.find('input[name="name"]').val(scenarioMember.name);
        form.find('input[name="email"]').val(scenarioMember.email);
        form.find('select[name="authority_id"]').val(authority.val());
        form.find('select[name="enabled"]').val('1');
        test.click('form.register button[type="submit"]');
    },
    // ユーザー一覧: 登録できたことを確認して、ログアウトする
    function() {
        test.assertText('div.alert-success', 'ユーザーを登録しました。', 'ユーザーを登録できていません。');

        test.visit('/auth/logout');
    },
    // ログインページ: 会員でログインする
    function() {
        login(scenarioMember.username, scenarioMember.password);
    },
    // 会員ページ: 注文履歴・住所管理のメニューがあることを確認して、住所管理へ移動する
    function() {
        test.assertExists('a:contains("注文履歴")', '会員ページに「注文履歴」のメニューがありません。');
        test.assertExists('a:contains("住所管理")', '会員ページに「住所管理」のメニューがありません。');

        test.click('a:contains("住所管理")');
    },
    // 住所一覧: まだ住所が無いことを確認して、登録ページへ移動する
    function() {
        test.assert($('table tbody tr').length === 0, '登録したばかりの会員に住所があります。');

        test.click('a:contains("住所登録")');
    },
    // 住所登録: 未入力で送信すると、必須の6項目が入力エラーになることを確認する
    function() {
        test.assert($('form.register').length === 1, '住所の登録フォームが表示されていません。');

        submitAndWait(function() {
            test.assertText('form.register', '名前 姓が入力されていません。', '名前 姓の入力エラーが表示されていません。');
            test.assertText('form.register', '郵便番号が入力されていません。', '郵便番号の入力エラーが表示されていません。');
            test.assertText('form.register', '電話番号が入力されていません。', '電話番号の入力エラーが表示されていません。');
            // 必須は 名前 姓・名前 名・郵便番号・都道府県・住所 1・電話番号（カナと住所 2 は任意）
            test.assert($('form.register div.warning').length === 6, '入力エラーの数が6つではありません。（' + $('form.register div.warning').length + '）');

            test.reload();
        });
    },
    // 住所登録: カナをひらがなで入力すると、カナだけが入力エラーになることを確認する
    function() {
        var values = $.extend({}, scenarioAddress, { kana_01: 'かいいん' });

        fillAddress(values);
        submitAndWait(function() {
            test.assertText('form.register', 'カタカナ', 'カナの形式の入力エラーが表示されていません。');
            test.assert($('form.register div.warning').length === 1, 'カナ以外にも入力エラーが表示されています。（' + $('form.register div.warning').text() + '）');

            test.reload();
        });
    },
    // 住所登録: 正しく入力して登録する
    function() {
        fillAddress(scenarioAddress);
        test.click('form.register button[type="submit"]');
    },
    // 住所一覧: 登録できたことを確認して、商品詳細へ移動する
    function() {
        test.assertText('div.alert-success', '住所を登録しました。', '住所を登録できていません。');
        test.assert(nameRow(scenarioAddressName).length === 1, '登録した住所が一覧にありません。');

        test.visit('/catalog/detail/' + scenarioCatalog.code);
    },
    // 商品詳細（公開側）: カートに追加する
    function() {
        test.assertExists('form[action$="/cart/add"] input[name="order_spec_id"]', 'カートに追加するフォームに規格が渡されていません。');

        test.click('form[action$="/cart/add"] button[type="submit"]');
    },
    // カート: 注文手続きへ進む
    function() {
        test.assertText('div.alert-success', 'カートに商品を追加しました。', 'カートに追加できていません。');

        test.click('a:contains("注文手続きへ進む")');
    },
    // 注文（公開側）: メールアドレスが会員のものになっていることを確認し、登録した住所を選んで送信する
    function() {
        var form = $('form.register');
        var select = $('#address_select');

        test.assertValue('form.register input[name="email"]', scenarioMember.email, 'メールアドレスの初期値が会員のメールアドレスになっていません。');

        if (!test.assert(select.length === 1, '注文フォームに「登録した住所から選ぶ」がありません。')) {
            return;
        }
        if (!selectByText(select, scenarioAddressName, '登録した住所が選択肢にありません。')) {
            return;
        }

        // 選ぶと order.js が宛先の欄を埋める
        select.trigger('change');
        test.assertValue('form.register input[name="name_01"]', scenarioAddress.name_01, '住所を選んでも名前 姓が入りません。');
        test.assertValue('form.register input[name="kana_02"]', scenarioAddress.kana_02, '住所を選んでもカナ 名が入りません。');
        test.assertValue('form.register input[name="address_01"]', scenarioAddress.address_01, '住所を選んでも住所 1 が入りません。');
        test.assertValue('form.register input[name="telephone"]', scenarioAddress.telephone, '住所を選んでも電話番号が入りません。');

        if (!selectByText(form.find('select[name="payment_id"]'), scenarioPaymentName, '登録した支払方法が選択肢にありません。')) {
            return;
        }
        if (!selectByText(form.find('select[name="delivery_id"]'), scenarioDeliveryName, '登録した配送方法が選択肢にありません。')) {
            return;
        }

        test.click('form.register button[type="submit"]');
    },
    // 注文確認（公開側）: 選んだ住所が入っていることを確認して注文する
    function() {
        test.assertText('main', scenarioAddress.address_01, '注文確認に住所が表示されていません。');
        test.assertText('main', scenarioMember.email, '注文確認にメールアドレスが表示されていません。');

        test.click('form[action$="/order/preview"] button[type="submit"]');
    },
    // 注文完了（公開側）: 完了を確認して、注文履歴へ移動する
    function() {
        test.assertText('main', '注文が完了しました。', '注文を完了できていません。');

        test.visit('/auth/history');
    },
    // 注文履歴: 注文が1件あることを確認して、注文詳細へ移動する
    function() {
        var rows = $('table tbody tr');

        if (!test.assert(rows.length === 1, '注文履歴に注文が1件ありません。（' + rows.length + '件）')) {
            return;
        }

        // 商品 2,000円 + 送料 500円 + 手数料 0円
        test.assert(rows.text().indexOf('2,500円') !== -1, '注文履歴の合計金額が 2,500円 ではありません。');
        test.assert(rows.text().indexOf(scenarioDeliveryName) !== -1, '注文履歴に配送方法が表示されていません。');

        test.click(rows.find('a:contains("表示")'), '注文履歴の表示リンクが見つかりません。');
    },
    // 注文詳細: お届け先・明細・合計金額を確認して、住所管理へ移動する
    function() {
        test.assertText('main', scenarioAddressName, '注文詳細にお届け先の名前がありません。');
        test.assertText('main', scenarioAddress.address_01, '注文詳細にお届け先の住所がありません。');
        test.assertText('main table', scenarioSpec.name, '注文詳細の明細に規格名がありません。');
        test.assertText('main table', scenarioCatalog.title, '注文詳細の明細に商品名がありません。');
        test.assertText('main dd.fw-bold', '2,500円', '注文詳細の合計金額が 2,500円 ではありません。');

        test.visit('/auth/address');
    },
    // 住所一覧: 住所の編集ページへ移動する
    function() {
        test.click(nameRow(scenarioAddressName).find('a:contains("編集")'), '住所一覧の編集リンクが見つかりません。');
    },
    // 住所編集: 登録した内容が復元されていることを確認して削除する
    function() {
        test.assertValue('form.register input[name="name_01"]', scenarioAddress.name_01, '編集画面に名前 姓が復元されていません。');
        test.assertValue('form.register input[name="zipcode"]', scenarioAddress.zipcode, '編集画面に郵便番号が復元されていません。');

        submitDeleteForm();
    },
    // 住所一覧: 削除できたことを確認して、ログアウトする
    function() {
        test.assertText('div.alert-success', '住所を削除しました。', '住所を削除できていません。');
        test.assert(nameRow(scenarioAddressName).length === 0, '削除した住所が一覧に残っています。');

        test.visit('/auth/logout');
    },
    // ログインページ: 管理者でログインする
    function() {
        login('admin', 'abcd1234');
    },
    // 管理画面: 注文管理へ移動する
    function() {
        test.assertText('body', '管理者さん', 'ログインできていません。');

        test.visit('/admin/order');
    },
    // 注文一覧: 会員の注文で、お名前がユーザー編集へのリンクになっていることを確認して、編集ページへ移動する
    function() {
        var row = textRow(scenarioAddressName);

        if (!test.assert(row.length === 1, '注文一覧に ' + scenarioAddressName + ' の注文がありません。')) {
            return;
        }

        test.assert(row.find('a[href*="/admin/user_form?id="]').length === 1, '会員の注文なのに、お名前がユーザーへのリンクになっていません（user_id が入っていない可能性があります）。');

        test.click(row.find('a:contains("編集")'), '注文一覧の編集リンクが見つかりません。');
    }
].concat(
    deleteSteps('注文', function() {}, function() {
        test.assertValue('form.register input[name="email"]', scenarioMember.email, '編集対象の注文が違います。');
    }, '注文を削除しました。', function() {
        test.assert(textRow(scenarioAddressName).length === 0, '削除した注文が一覧に残っています。');
        test.visit('/admin/user');
    }).slice(1),
    deleteSteps('会員', function() {
        test.click(userRow(scenarioMember.username).find('a:contains("編集")'), 'ユーザー一覧の ' + scenarioMember.username + ' の編集リンクが見つかりません。');
    }, function() {
        test.assertValue('form.register input[name="username"]', scenarioMember.username, '編集対象のユーザーが違います。');
    }, 'ユーザーを削除しました。', function() {
        test.visit('/admin/catalog');
    }),
    [
        // 商品一覧: 規格管理へ移動する
        function() {
            test.click(codeRow(scenarioCatalog.code).find('a:contains("規格")'), '一覧の ' + scenarioCatalog.code + ' の規格へのリンクが見つかりません。');
        }
    ],
    deleteSteps('規格', function() {
        test.click(codeRow(scenarioSpec.code).find('a:contains("編集")'), '規格一覧の ' + scenarioSpec.code + ' の編集リンクが見つかりません。');
    }, function() {
        test.assertValue('form.register input[name="code"]', scenarioSpec.code, '編集対象の規格が違います。');
    }, '規格を削除しました。', function() {
        test.click('a:contains("商品管理")');
    }),
    deleteSteps('商品', function() {
        test.click(codeRow(scenarioCatalog.code).find('a:contains("編集")'), '一覧の ' + scenarioCatalog.code + ' の編集リンクが見つかりません。');
    }, function() {
        test.assertValue('form.register input[name="code"]', scenarioCatalog.code, '編集対象の商品が違います。');
    }, '商品を削除しました。', function() {
        test.visit('/admin/delivery');
    }),
    deleteSteps('配送方法', function() {
        test.click(nameRow(scenarioDeliveryName).find('a:contains("編集")'), '配送方法一覧の編集リンクが見つかりません。');
    }, function() {
        test.assertValue('form.register input[name="name"]', scenarioDeliveryName, '編集対象の配送方法が違います。');
    }, '配送方法を削除しました。', function() {
        test.visit('/admin/payment');
    }),
    deleteSteps('支払方法', function() {
        test.click(nameRow(scenarioPaymentName).find('a:contains("編集")'), '支払方法一覧の編集リンクが見つかりません。');
    }, function() {
        test.assertValue('form.register input[name="name"]', scenarioPaymentName, '編集対象の支払方法が違います。');
    }, '支払方法を削除しました。', function() {
        test.assert(nameRow(scenarioPaymentName).length === 0, '削除した支払方法が一覧に残っています。');
        test.click('a:contains("ログアウト")');
    })
);
