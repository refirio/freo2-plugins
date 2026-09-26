/*
 * order プラグインのシナリオ
 *
 * 設定を有効化 → 前提データを作る → カート → 注文 → 発送 → 後始末 → 設定を戻す、を1本で通す。
 *
 * 前提: テスト用データベース側で order プラグインがインストールされ、有効になっていること。
 *       （このシナリオはインストールしない。テスト用データベースへの入れ方は CLAUDE.md を参照）
 *       在庫管理（use_stock）と配送管理（use_shipping）は無効であること（このシナリオが有効にして最後に戻す）。
 */

/* テストデータ */
var scenarioPaymentName  = 'シナリオ支払';
var scenarioDeliveryName = 'シナリオ配送';
var scenarioStockCode    = 'scenario-stock';
var scenarioStockName    = 'シナリオ在庫';
var scenarioCatalogCode  = 'scenario-catalog';
var scenarioCatalogTitle = 'シナリオ商品';
var scenarioSpecCode     = 'scenario-spec';
var scenarioSpecName     = 'シナリオ規格';
var scenarioOrderName    = 'シナリオ 太郎';
var scenarioOrderEmail   = 'scenario-order@example.com';

/* プラグインの設定フォーム（exec=setting のフォーム）を取得する */
var settingForm = function() {
    return $('input[name="exec"][value="setting"]').closest('form');
};

/* 一覧から、コードで対象の行を取得する（在庫・規格・商品） */
var codeRow = function(code) {
    return $('table tbody tr').filter(function() {
        return $(this).find('td code').first().text().trim() === code;
    });
};

/* 一覧から、1列目の名前で対象の行を取得する（支払方法・配送方法） */
var nameRow = function(name) {
    return $('table tbody tr').filter(function() {
        return $(this).find('td').first().text().trim() === name;
    });
};

/* 一覧から、行に含まれる文字列で対象の行を取得する（注文・製品） */
var textRow = function(value) {
    return $('table tbody tr').filter(function() {
        return $(this).text().indexOf(value) !== -1;
    });
};

/* 発送管理の「発送状況」の1行目を取得する */
var shippingStatusRow = function() {
    return $('div.card-header:contains("発送状況")').closest('div.card').find('table tbody tr').first();
};

/* 発送管理の「発送履歴」から、送料で対象の行を取得する */
var shippingHistoryRow = function(cost) {
    return $('div.card-header:contains("発送履歴")').closest('div.card').find('table tbody tr').filter(function() {
        return $(this).text().indexOf(cost) !== -1;
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

test.scenario = [
    // 初期ページ: ログインページへ移動する
    function() {
        test.assertExists('a:contains("ログイン")', '初期ページにログインへのリンクがありません。');
        test.click('a:contains("ログイン")');
    },
    // ログインページ: ログインする
    function() {
        var form = $('form:eq(0)');

        test.assert(form.find('input[name="username"]').length === 1, 'ログインフォームが表示されていません。');

        form.find('input[name="username"]').val('admin');
        form.find('input[name="password"]').val('abcd1234');
        test.click('form:eq(0) button[type="submit"]');
    },
    // 管理画面: order プラグインが有効なことを確認して、プラグイン詳細へ移動する
    function() {
        test.assertText('body', '管理者さん', 'ログインできていません。');
        test.assertExists('a:contains("商品管理")', 'order プラグインのメニューがありません。プラグインがインストール・有効化されているか確認してください。');
        test.assertExists('a:contains("注文管理")', 'order プラグインのメニューがありません。プラグインがインストール・有効化されているか確認してください。');

        test.visit('/admin/plugin_view?code=order');
    },
    // プラグイン詳細: 在庫管理と配送管理を有効にする
    function() {
        var form = settingForm();

        if (!test.assert(form.length === 1, 'プラグインの設定フォームが表示されていません。')) {
            return;
        }

        test.assert(form.find('input[name="setting[use_stock]"]').prop('checked') === false, '在庫管理が既に有効です。前回のテストが中断した可能性があります。');
        test.assert(form.find('input[name="setting[use_shipping]"]').prop('checked') === false, '配送管理が既に有効です。前回のテストが中断した可能性があります。');

        form.find('input[name="setting[use_stock]"]').prop('checked', true);
        form.find('input[name="setting[use_shipping]"]').prop('checked', true);
        test.click(form.find('button[type="submit"]'), 'プラグインの設定の登録ボタンが見つかりません。');
    },
    // プラグイン一覧: 設定を更新できたことを確認して、支払方法管理へ移動する
    function() {
        test.assertText('div.alert-success', 'プラグインの設定を更新しました。', 'プラグインの設定を更新できていません。');

        test.visit('/admin/payment');
    },
    // 支払方法一覧: 前回のデータが残っていないことを確認して、登録ページへ移動する
    function() {
        test.assert(nameRow(scenarioPaymentName).length === 0, '支払方法 ' + scenarioPaymentName + ' が残っています。前回のテストが中断した可能性があります。');
        test.click('a:contains("支払方法登録")');
    },
    // 支払方法登録: 入力して送信する
    function() {
        var form = $('form.register');

        test.assert(form.find('input[name="name"]').length === 1, '支払方法の登録フォームが表示されていません。');

        form.find('input[name="name"]').val(scenarioPaymentName);
        form.find('input[name="fee"]').val('0');
        form.find('select[name="enabled"]').val('1');
        test.click('form.register button[type="submit"]');
    },
    // 支払方法一覧: 登録できたことを確認して、配送方法管理へ移動する
    function() {
        test.assertText('div.alert-success', '支払方法を登録しました。', '支払方法を登録できていません。');
        test.assert(nameRow(scenarioPaymentName).length === 1, '登録した支払方法が一覧にありません。');

        test.visit('/admin/delivery');
    },
    // 配送方法一覧: 前回のデータが残っていないことを確認して、登録ページへ移動する
    function() {
        test.assert(nameRow(scenarioDeliveryName).length === 0, '配送方法 ' + scenarioDeliveryName + ' が残っています。前回のテストが中断した可能性があります。');
        test.click('a:contains("配送方法登録")');
    },
    // 配送方法登録: 入力して送信する
    function() {
        var form = $('form.register');

        test.assert(form.find('input[name="name"]').length === 1, '配送方法の登録フォームが表示されていません。');

        form.find('input[name="name"]').val(scenarioDeliveryName);
        form.find('select[name="calculate"]').val('order');
        form.find('input[name="cost"]').val('500');
        form.find('select[name="enabled"]').val('1');
        test.click('form.register button[type="submit"]');
    },
    // 配送方法一覧: 登録できたことを確認して、在庫管理へ移動する
    function() {
        test.assertText('div.alert-success', '配送方法を登録しました。', '配送方法を登録できていません。');
        test.assert(nameRow(scenarioDeliveryName).length === 1, '登録した配送方法が一覧にありません。');

        test.visit('/admin/stock');
    },
    // 在庫一覧: 前回のデータが残っていないことを確認して、登録ページへ移動する
    function() {
        test.assert(codeRow(scenarioStockCode).length === 0, '在庫 ' + scenarioStockCode + ' が残っています。前回のテストが中断した可能性があります。');
        test.click('a:contains("在庫登録")');
    },
    // 在庫登録: 入力して送信する
    function() {
        var form = $('form.register');

        test.assert(form.find('input[name="code"]').length === 1, '在庫の登録フォームが表示されていません。');

        form.find('input[name="code"]').val(scenarioStockCode);
        form.find('input[name="name"]').val(scenarioStockName);
        form.find('select[name="kind"]').val('analog');
        form.find('input[name="quantity"]').val('100');
        form.find('input[name="cost_price"]').val('300');
        test.click('form.register button[type="submit"]');
    },
    // 在庫一覧: 登録できたことを確認して、商品管理へ移動する
    function() {
        test.assertText('div.alert-success', '在庫を登録しました。', '在庫を登録できていません。');
        test.assert(codeRow(scenarioStockCode).length === 1, '登録した在庫が一覧にありません。');

        test.visit('/admin/catalog');
    },
    // 商品一覧: 前回のデータが残っていないことを確認して、登録ページへ移動する
    function() {
        test.assert(codeRow(scenarioCatalogCode).length === 0, '商品 ' + scenarioCatalogCode + ' が残っています。前回のテストが中断した可能性があります。');
        test.click('a:contains("商品登録")');
    },
    // 商品登録: 入力して送信する
    function() {
        var form = $('form.register');

        if (!test.assert(form.find('input[name="code"]').length === 1, '商品の登録フォームが表示されていません。')) {
            return;
        }

        form.find('select[name="public"]').val('all');
        form.find('input[name="code"]').val(scenarioCatalogCode);
        form.find('input[name="title"]').val(scenarioCatalogTitle);

        waitEditor(function() {
            test.click('form.register button[type="submit"]');
        });
    },
    // 商品一覧: 登録できたことを確認して、規格管理へ移動する
    function() {
        test.assertText('div.alert-success', '商品を登録しました。', '商品を登録できていません。');

        var row = codeRow(scenarioCatalogCode);

        if (!test.assert(row.length === 1, '登録した商品が一覧にありません。')) {
            return;
        }

        test.click(row.find('a:contains("規格")'), '一覧の ' + scenarioCatalogCode + ' の規格へのリンクが見つかりません。');
    },
    // 規格一覧: 前回のデータが残っていないことを確認して、登録ページへ移動する
    function() {
        test.assert(codeRow(scenarioSpecCode).length === 0, '規格 ' + scenarioSpecCode + ' が残っています。前回のテストが中断した可能性があります。');
        test.click('a:contains("規格登録")');
    },
    // 規格登録: 入力して送信する
    function() {
        var form = $('form.register');

        test.assert(form.find('input[name="code"]').length === 1, '規格の登録フォームが表示されていません。');
        test.assert(form.find('input[name="entry_id"]').val() !== '', '規格の登録フォームに商品のIDが渡されていません。');

        form.find('input[name="code"]').val(scenarioSpecCode);
        form.find('input[name="name"]').val(scenarioSpecName);
        form.find('select[name="provide"]').val('delivery');
        form.find('input[name="selling_price"]').val('1000');
        form.find('select[name="enabled"]').val('1');
        test.click('form.register button[type="submit"]');
    },
    // 規格一覧: 登録できたことを確認して、製品管理へ移動する
    function() {
        test.assertText('div.alert-success', '規格を登録しました。', '規格を登録できていません。');

        var row = codeRow(scenarioSpecCode);

        if (!test.assert(row.length === 1, '登録した規格が一覧にありません。')) {
            return;
        }

        test.click(row.find('a:contains("製品")'), '一覧の ' + scenarioSpecCode + ' の製品へのリンクが見つかりません。');
    },
    // 製品一覧: 前回のデータが残っていないことを確認して、登録ページへ移動する
    function() {
        test.assert(codeRow(scenarioStockCode).length === 0, '製品 ' + scenarioStockCode + ' が残っています。前回のテストが中断した可能性があります。');
        test.click('a:contains("製品登録")');
    },
    // 製品登録: 在庫を選んで送信する
    function() {
        var form = $('form.register');

        test.assert(form.find('select[name="stock_id"]').length === 1, '製品の登録フォームが表示されていません。');
        test.assert(form.find('input[name="spec_id"]').val() !== '', '製品の登録フォームに規格のIDが渡されていません。');

        var option = form.find('select[name="stock_id"] option').filter(function() {
            return $(this).text().indexOf(scenarioStockCode) !== -1;
        });

        if (!test.assert(option.length === 1, '登録した在庫が選択肢にありません。')) {
            return;
        }

        form.find('select[name="stock_id"]').val(option.val());
        form.find('input[name="quantity"]').val('1');
        test.click('form.register button[type="submit"]');
    },
    // 製品一覧: 登録できたことを確認して、ログアウトする
    function() {
        test.assertText('div.alert-success', '製品を登録しました。', '製品を登録できていません。');
        test.assert(codeRow(scenarioStockCode).length === 1, '登録した製品が一覧にありません。');

        test.click('a:contains("ログアウト")');
    },
    // ログインページ: 公開側の商品一覧へ移動する
    function() {
        test.assertNotExists('a:contains("ログアウト")', 'ログアウトできていません。');

        test.visit('/catalog/');
    },
    // 商品一覧（公開側）: 登録した商品へ移動する
    function() {
        var link = $('a:contains("' + scenarioCatalogTitle + '")');

        if (!test.assert(link.length >= 1, '公開側の商品一覧に ' + scenarioCatalogTitle + ' がありません。')) {
            return;
        }

        test.click(link.first(), '公開側の商品一覧の ' + scenarioCatalogTitle + ' へのリンクが見つかりません。');
    },
    // 商品詳細（公開側）: カートに追加する
    function() {
        test.assertText('main', scenarioCatalogTitle, '公開側の商品詳細が表示されていません。');
        test.assertNoText('main', '在庫切れ', '在庫があるのに在庫切れと表示されています。');
        test.assertExists('form[action$="/cart/add"] input[name="order_spec_id"]', 'カートに追加するフォームに規格が渡されていません。');

        test.click('form[action$="/cart/add"] button[type="submit"]');
    },
    // カート: 追加できたことを確認して、数を2に変更する
    function() {
        test.assertText('div.alert-success', 'カートに商品を追加しました。', 'カートに追加できていません。');

        var row = textRow(scenarioSpecName);

        if (!test.assert(row.length === 1, 'カートに ' + scenarioSpecName + ' がありません。')) {
            return;
        }

        test.assert(row.text().indexOf(scenarioCatalogTitle) !== -1, 'カートに商品名が表示されていません。');
        test.assert(row.find('input[name="quantity"]').val() === '1', 'カートの数が1ではありません。');

        row.find('input[name="quantity"]').val('2');
        test.click(row.find('button[type="submit"]:contains("変更")'), 'カートの変更ボタンが見つかりません。');
    },
    // カート: 数を変更できたことを確認して、注文手続きへ進む
    function() {
        test.assertText('div.alert-success', 'カートの数を変更しました。', 'カートの数を変更できていません。');
        test.assert(textRow(scenarioSpecName).find('input[name="quantity"]').val() === '2', 'カートの数が2になっていません。');

        test.click('a:contains("注文手続きへ進む")');
    },
    // 注文（公開側）: 未入力で送信して、入力エラーを確認する
    function() {
        var form = $('form.register');

        if (!test.assert(form.find('select[name="payment_id"]').length === 1, '注文フォームが表示されていません。')) {
            return;
        }

        // 提供方法が配送なので、宛先の入力欄が表示される
        test.assertValue('form.register input[name="provide"]', 'delivery', '提供方法が配送になっていません。');
        test.assertExists('form.register input[name="name_01"]', '配送なのに宛先の入力欄がありません。');
        test.assertExists('form.register input[name="address_01"]', '配送なのに住所の入力欄がありません。');
        test.assertExists('form.register select[name="delivery_id"]', '配送なのに配送方法の選択欄がありません。');

        $('div.warning').remove();
        test.click('form.register button[type="submit"]');

        test.wait(function() {
            return $('div.warning').length > 0;
        }, function() {
            // 提供方法が配送のときだけ必須になる項目（配送方法・名前・住所・電話番号）でエラーが出る
            test.assertText('form.register', '支払方法が入力されていません。', '支払方法の入力エラーが表示されていません。');
            test.assertText('form.register', 'メールアドレスが入力されていません。', 'メールアドレスの入力エラーが表示されていません（公開側の注文では必須）。');
            test.assertText('form.register', '配送方法が入力されていません。', '配送方法の入力エラーが表示されていません。');
            test.assertText('form.register', '名前 姓が入力されていません。', '名前 姓の入力エラーが表示されていません。');
            test.assertText('form.register', '郵便番号が入力されていません。', '郵便番号の入力エラーが表示されていません。');
            test.assertText('form.register', '住所 1が入力されていません。', '住所 1の入力エラーが表示されていません。');
            test.assertText('form.register', '電話番号が入力されていません。', '電話番号の入力エラーが表示されていません。');

            test.reload();
        }, '注文フォームの入力エラーが表示されません。');
    },
    // 注文（公開側）: 入力して送信する
    function() {
        var form = $('form.register');

        if (!test.assert(form.find('select[name="payment_id"]').length === 1, '注文フォームが表示されていません。')) {
            return;
        }

        var paymentOption = form.find('select[name="payment_id"] option').filter(function() {
            return $(this).text().trim() === scenarioPaymentName;
        });
        var deliveryOption = form.find('select[name="delivery_id"] option').filter(function() {
            return $(this).text().trim() === scenarioDeliveryName;
        });

        if (!test.assert(paymentOption.length === 1, '登録した支払方法が選択肢にありません。')) {
            return;
        }
        if (!test.assert(deliveryOption.length === 1, '登録した配送方法が選択肢にありません。')) {
            return;
        }

        form.find('select[name="payment_id"]').val(paymentOption.val());
        form.find('select[name="delivery_id"]').val(deliveryOption.val());
        form.find('input[name="email"]').val(scenarioOrderEmail);
        form.find('input[name="name_01"]').val('シナリオ');
        form.find('input[name="name_02"]').val('太郎');
        form.find('input[name="kana_01"]').val('シナリオ');
        form.find('input[name="kana_02"]').val('タロウ');
        form.find('input[name="zipcode"]').val('5300001');
        form.find('input[name="prefecture"]').val('大阪府');
        form.find('input[name="address_01"]').val('大阪市北区1-2-3');
        form.find('input[name="telephone"]').val('0612345678');
        form.find('textarea[name="message"]').val('シナリオテストの注文です。');
        test.click('form.register button[type="submit"]');
    },
    // 注文確認（公開側）: 内容を確認して注文する
    function() {
        test.assertText('main', scenarioSpecName, '注文確認に規格名が表示されていません。');
        test.assertText('main', scenarioCatalogTitle, '注文確認に商品名が表示されていません。');
        test.assertText('main', scenarioPaymentName, '注文確認に支払方法が表示されていません。');
        test.assertText('main', scenarioDeliveryName, '注文確認に配送方法が表示されていません。');
        test.assertText('main', scenarioOrderEmail, '注文確認にメールアドレスが表示されていません。');
        test.assertText('main', '大阪市北区1-2-3', '注文確認に住所が表示されていません。');

        test.click('form[action$="/order/preview"] button[type="submit"]');
    },
    // 注文完了（公開側）: 完了を確認して、カートを確認する
    function() {
        test.assertText('main', '注文が完了しました。', '注文を完了できていません。');

        test.visit('/cart/');
    },
    // カート: 注文したものがカートから消えていることを確認して、ログインページへ移動する
    function() {
        test.assertNoText('main', scenarioSpecName, '注文したのにカートに商品が残っています。');

        test.click('a:contains("ログイン")');
    },
    // ログインページ: ログインする
    function() {
        var form = $('form:eq(0)');

        test.assert(form.find('input[name="username"]').length === 1, 'ログインフォームが表示されていません。');

        form.find('input[name="username"]').val('admin');
        form.find('input[name="password"]').val('abcd1234');
        test.click('form:eq(0) button[type="submit"]');
    },
    // 管理画面: 注文管理へ移動する
    function() {
        test.assertText('body', '管理者さん', 'ログインできていません。');

        test.visit('/admin/order');
    },
    // 注文一覧: 注文を確認して、発送管理へ移動する
    function() {
        var row = textRow(scenarioOrderName);

        if (!test.assert(row.length === 1, '注文一覧に ' + scenarioOrderName + ' の注文がありません。')) {
            return;
        }

        test.assert(row.text().indexOf('新規受付') !== -1, '注文の状況が新規受付ではありません。');
        // 商品 1,000円 × 2 + 送料 500円 + 手数料 0円
        test.assert(row.text().indexOf('2,500円') !== -1, '注文の合計金額が 2,500円 ではありません。');

        test.click(row.find('a:contains("発送")'), '注文一覧の発送へのリンクが見つかりません。配送管理（use_shipping）が有効か確認してください。');
    },
    // 発送管理: 発送前の状態を確認して、発送登録へ移動する
    function() {
        var row = shippingStatusRow();

        if (!test.assert(row.length === 1, '発送状況が表示されていません。')) {
            return;
        }

        test.assert(row.find('td').eq(2).text().trim() === '2', '発送状況の注文数が2ではありません。');
        test.assert(row.find('td').eq(3).text().trim() === '0', '発送状況の発送数が0ではありません。');
        test.assert(row.find('td').eq(4).text().trim() === '2', '発送状況の残りが2ではありません。');
        test.assertNoText('main', '発送数が注文数を超えている商品があります。', '発送前なのに超過の警告が表示されています。');

        test.click('a:contains("発送登録")');
    },
    // 発送登録: 2つのうち1つを発送する
    function() {
        var form = $('form.register');

        if (!test.assert(form.find('input[name="quantity[]"]').length === 1, '発送の登録フォームが表示されていません。')) {
            return;
        }

        var row = form.find('table tbody tr').first();

        test.assert(row.find('td').eq(2).text().trim() === '2', '発送登録の注文数が2ではありません。');
        test.assert(row.find('td').eq(3).text().trim() === '2', '発送登録の発送可能数が2ではありません。');

        var deliveryOption = form.find('select[name="delivery_id"] option').filter(function() {
            return $(this).text().trim() === scenarioDeliveryName;
        });

        if (!test.assert(deliveryOption.length === 1, '登録した配送方法が選択肢にありません。')) {
            return;
        }

        form.find('input[name="quantity[]"]').val('1');
        form.find('select[name="status"]').val('completed');
        form.find('select[name="delivery_id"]').val(deliveryOption.val());
        form.find('input[name="delivery_cost"]').val('500');
        test.click('form.register button[type="submit"]');
    },
    // 発送管理: 1つ発送できたことを確認して、もう一度発送登録へ移動する
    function() {
        test.assertText('div.alert-success', '発送を登録しました。', '発送を登録できていません。');

        var row = shippingStatusRow();

        test.assert(row.find('td').eq(3).text().trim() === '1', '発送状況の発送数が1ではありません。');
        test.assert(row.find('td').eq(4).text().trim() === '1', '発送状況の残りが1ではありません。');
        test.assert(shippingHistoryRow('500円').length === 1, '発送履歴に登録した発送がありません。');
        test.assert(shippingHistoryRow('500円').text().indexOf('発送済み') !== -1, '発送履歴の状況が発送済みではありません。');
        test.assertNoText('main', '発送数が注文数を超えている商品があります。', '超過していないのに警告が表示されています。');

        test.click('a:contains("発送登録")');
    },
    // 発送登録: 残り1つのところに5つ発送する（数量超過は弾かれない）
    function() {
        var form = $('form.register');

        if (!test.assert(form.find('input[name="quantity[]"]').length === 1, '発送の登録フォームが表示されていません。')) {
            return;
        }

        test.assert(form.find('table tbody tr').first().find('td').eq(3).text().trim() === '1', '発送登録の発送可能数が1ではありません。');

        var deliveryOption = form.find('select[name="delivery_id"] option').filter(function() {
            return $(this).text().trim() === scenarioDeliveryName;
        });

        if (!test.assert(deliveryOption.length === 1, '登録した配送方法が選択肢にありません。')) {
            return;
        }

        form.find('input[name="quantity[]"]').val('5');
        form.find('select[name="status"]').val('completed');
        form.find('select[name="delivery_id"]').val(deliveryOption.val());
        form.find('input[name="delivery_cost"]').val('300');
        test.click('form.register button[type="submit"]');
    },
    // 発送管理: 数量超過が警告として表示されることを確認して、超過分の発送を編集する
    function() {
        test.assertText('div.alert-success', '発送を登録しました。', '数量を超過した発送を登録できていません。');

        var row = shippingStatusRow();

        test.assert(row.find('td').eq(3).text().trim() === '6', '発送状況の発送数が6ではありません。');
        test.assert(row.find('td').eq(4).text().trim() === '-4', '発送状況の残りが-4ではありません。');
        test.assertText('main', '発送数が注文数を超えている商品があります。', '数量を超過したのに警告が表示されていません。');
        test.assertText('main', '実際の送料が、注文時点の送料を超過しています。', '送料を超過したのに警告が表示されていません。');

        test.click(shippingHistoryRow('300円').find('a:contains("編集")'), '発送履歴の送料300円の編集リンクが見つかりません。');
    },
    // 発送編集: 対象を確認して削除する
    function() {
        test.assertValue('form.register input[name="delivery_cost"]', '300', '編集対象の発送が違います。');

        submitDeleteForm();
    },
    // 発送管理: 削除できたことを確認して、残りの発送を編集する
    function() {
        test.assertText('div.alert-success', '発送を削除しました。', '発送を削除できていません。');

        var row = shippingStatusRow();

        test.assert(row.find('td').eq(3).text().trim() === '1', '発送状況の発送数が1に戻っていません。');
        test.assert(row.find('td').eq(4).text().trim() === '1', '発送状況の残りが1に戻っていません。');
        test.assertNoText('main', '発送数が注文数を超えている商品があります。', '超過を解消したのに警告が表示されています。');
        test.assertNoText('main', '実際の送料が、注文時点の送料を超過しています。', '超過を解消したのに送料の警告が表示されています。');
        test.assert(shippingHistoryRow('300円').length === 0, '削除した発送が発送履歴に残っています。');

        test.click(shippingHistoryRow('500円').find('a:contains("編集")'), '発送履歴の送料500円の編集リンクが見つかりません。');
    },
    // 発送編集: 対象を確認して削除する
    function() {
        test.assertValue('form.register input[name="delivery_cost"]', '500', '編集対象の発送が違います。');

        submitDeleteForm();
    },
    // 発送管理: すべて削除できたことを確認して、注文管理へ移動する
    function() {
        test.assertText('div.alert-success', '発送を削除しました。', '発送を削除できていません。');

        var row = shippingStatusRow();

        test.assert(row.find('td').eq(3).text().trim() === '0', '発送状況の発送数が0に戻っていません。');
        test.assert(row.find('td').eq(4).text().trim() === '2', '発送状況の残りが2に戻っていません。');

        test.click('a:contains("注文管理")');
    },
    // 注文一覧: 注文を編集する
    function() {
        var row = textRow(scenarioOrderName);

        if (!test.assert(row.length === 1, '注文一覧に ' + scenarioOrderName + ' の注文がありません。')) {
            return;
        }

        test.click(row.find('a:contains("編集")'), '注文一覧の編集リンクが見つかりません。');
    },
    // 注文編集: 対象を確認して削除する
    function() {
        test.assertValue('form.register input[name="email"]', scenarioOrderEmail, '編集対象の注文が違います。');

        submitDeleteForm();
    },
    // 注文一覧: 削除できたことを確認して、商品管理へ移動する
    function() {
        test.assertText('div.alert-success', '注文を削除しました。', '注文を削除できていません。');
        test.assert(textRow(scenarioOrderName).length === 0, '削除した注文が一覧に残っています。');

        test.visit('/admin/catalog');
    },
    // 商品一覧: 規格管理へ移動する
    function() {
        var row = codeRow(scenarioCatalogCode);

        if (!test.assert(row.length === 1, '商品一覧に ' + scenarioCatalogCode + ' がありません。')) {
            return;
        }

        test.click(row.find('a:contains("規格")'), '一覧の ' + scenarioCatalogCode + ' の規格へのリンクが見つかりません。');
    },
    // 規格一覧: 製品管理へ移動する
    function() {
        var row = codeRow(scenarioSpecCode);

        if (!test.assert(row.length === 1, '規格一覧に ' + scenarioSpecCode + ' がありません。')) {
            return;
        }

        test.click(row.find('a:contains("製品")'), '一覧の ' + scenarioSpecCode + ' の製品へのリンクが見つかりません。');
    },
    // 製品一覧: 製品を編集する
    function() {
        var row = codeRow(scenarioStockCode);

        if (!test.assert(row.length === 1, '製品一覧に ' + scenarioStockCode + ' がありません。')) {
            return;
        }

        test.click(row.find('a:contains("編集")'), '一覧の ' + scenarioStockCode + ' の編集リンクが見つかりません。');
    },
    // 製品編集: 対象を確認して削除する
    function() {
        test.assertValue('form.register input[name="quantity"]', '1', '編集対象の製品が違います。');

        submitDeleteForm();
    },
    // 製品一覧: 削除できたことを確認して、規格管理へ移動する
    function() {
        test.assertText('div.alert-success', '製品を削除しました。', '製品を削除できていません。');
        test.assert(codeRow(scenarioStockCode).length === 0, '削除した製品が一覧に残っています。');

        test.click('a:contains("規格管理")');
    },
    // 規格一覧: 規格を編集する
    function() {
        var row = codeRow(scenarioSpecCode);

        if (!test.assert(row.length === 1, '規格一覧に ' + scenarioSpecCode + ' がありません。')) {
            return;
        }

        test.click(row.find('a:contains("編集")'), '一覧の ' + scenarioSpecCode + ' の編集リンクが見つかりません。');
    },
    // 規格編集: 対象を確認して削除する
    function() {
        test.assertValue('form.register input[name="code"]', scenarioSpecCode, '編集対象の規格が違います。');

        submitDeleteForm();
    },
    // 規格一覧: 削除できたことを確認して、商品管理へ移動する
    function() {
        test.assertText('div.alert-success', '規格を削除しました。', '規格を削除できていません。');
        test.assert(codeRow(scenarioSpecCode).length === 0, '削除した規格が一覧に残っています。');

        test.click('a:contains("商品管理")');
    },
    // 商品一覧: 商品を編集する
    function() {
        var row = codeRow(scenarioCatalogCode);

        if (!test.assert(row.length === 1, '商品一覧に ' + scenarioCatalogCode + ' がありません。')) {
            return;
        }

        test.click(row.find('a:contains("編集")'), '一覧の ' + scenarioCatalogCode + ' の編集リンクが見つかりません。');
    },
    // 商品編集: 対象を確認して削除する
    function() {
        test.assertValue('form.register input[name="code"]', scenarioCatalogCode, '編集対象の商品が違います。');

        submitDeleteForm();
    },
    // 商品一覧: 削除できたことを確認して、在庫管理へ移動する
    function() {
        test.assertText('div.alert-success', '商品を削除しました。', '商品を削除できていません。');
        test.assert(codeRow(scenarioCatalogCode).length === 0, '削除した商品が一覧に残っています。');

        test.visit('/admin/stock');
    },
    // 在庫一覧: 注文で在庫が減っていることを確認して、在庫を編集する
    function() {
        var row = codeRow(scenarioStockCode);

        if (!test.assert(row.length === 1, '在庫一覧に ' + scenarioStockCode + ' がありません。')) {
            return;
        }

        // 100個の在庫から、製品1個 × 注文2点 が引かれる
        test.assert(row.find('td').eq(4).text().trim() === '98', '注文で在庫が減っていません。在庫管理（use_stock）が有効か確認してください。');

        test.click(row.find('a:contains("編集")'), '一覧の ' + scenarioStockCode + ' の編集リンクが見つかりません。');
    },
    // 在庫編集: 対象を確認して削除する
    function() {
        test.assertValue('form.register input[name="code"]', scenarioStockCode, '編集対象の在庫が違います。');

        submitDeleteForm();
    },
    // 在庫一覧: 削除できたことを確認して、配送方法管理へ移動する
    function() {
        test.assertText('div.alert-success', '在庫を削除しました。', '在庫を削除できていません。');
        test.assert(codeRow(scenarioStockCode).length === 0, '削除した在庫が一覧に残っています。');

        test.visit('/admin/delivery');
    },
    // 配送方法一覧: 配送方法を編集する
    function() {
        var row = nameRow(scenarioDeliveryName);

        if (!test.assert(row.length === 1, '配送方法一覧に ' + scenarioDeliveryName + ' がありません。')) {
            return;
        }

        test.click(row.find('a:contains("編集")'), '一覧の ' + scenarioDeliveryName + ' の編集リンクが見つかりません。');
    },
    // 配送方法編集: 対象を確認して削除する
    function() {
        test.assertValue('form.register input[name="name"]', scenarioDeliveryName, '編集対象の配送方法が違います。');

        submitDeleteForm();
    },
    // 配送方法一覧: 削除できたことを確認して、支払方法管理へ移動する
    function() {
        test.assertText('div.alert-success', '配送方法を削除しました。', '配送方法を削除できていません。');
        test.assert(nameRow(scenarioDeliveryName).length === 0, '削除した配送方法が一覧に残っています。');

        test.visit('/admin/payment');
    },
    // 支払方法一覧: 支払方法を編集する
    function() {
        var row = nameRow(scenarioPaymentName);

        if (!test.assert(row.length === 1, '支払方法一覧に ' + scenarioPaymentName + ' がありません。')) {
            return;
        }

        test.click(row.find('a:contains("編集")'), '一覧の ' + scenarioPaymentName + ' の編集リンクが見つかりません。');
    },
    // 支払方法編集: 対象を確認して削除する
    function() {
        test.assertValue('form.register input[name="name"]', scenarioPaymentName, '編集対象の支払方法が違います。');

        submitDeleteForm();
    },
    // 支払方法一覧: 削除できたことを確認して、プラグイン詳細へ移動する
    function() {
        test.assertText('div.alert-success', '支払方法を削除しました。', '支払方法を削除できていません。');
        test.assert(nameRow(scenarioPaymentName).length === 0, '削除した支払方法が一覧に残っています。');

        test.visit('/admin/plugin_view?code=order');
    },
    // プラグイン詳細: 在庫管理と配送管理を無効に戻す
    function() {
        var form = settingForm();

        if (!test.assert(form.length === 1, 'プラグインの設定フォームが表示されていません。')) {
            return;
        }

        form.find('input[name="setting[use_stock]"]').prop('checked', false);
        form.find('input[name="setting[use_shipping]"]').prop('checked', false);
        test.click(form.find('button[type="submit"]'), 'プラグインの設定の登録ボタンが見つかりません。');
    },
    // プラグイン一覧: 設定を更新できたことを確認して、プラグイン詳細へ移動する
    function() {
        test.assertText('div.alert-success', 'プラグインの設定を更新しました。', 'プラグインの設定を更新できていません。');

        test.visit('/admin/plugin_view?code=order');
    },
    // プラグイン詳細: 設定が元に戻っていることを確認して、ログアウトする
    function() {
        var form = settingForm();

        if (!test.assert(form.length === 1, 'プラグインの設定フォームが表示されていません。')) {
            return;
        }

        test.assert(form.find('input[name="setting[use_stock]"]').prop('checked') === false, '在庫管理が無効に戻っていません。');
        test.assert(form.find('input[name="setting[use_shipping]"]').prop('checked') === false, '配送管理が無効に戻っていません。');

        test.click('a:contains("ログアウト")');
    }
];
