/*
 * order プラグインのシナリオ（提供方法の違い）
 *
 * 在庫管理・対面販売を有効化 → 3つの提供方法の規格を持つ商品を作る
 * → 公開側: 対面販売の規格が出ないこと、在庫切れの規格を選べないこと、ダウンロード商品を注文できること（宛先を聞かない）
 * → 管理画面: ダウンロード案内が表示されること、対面販売の注文を登録できること（在庫が減る）
 * → 商品詳細のパスワード認証 → 後始末 → 設定を戻す、を1本で通す。
 *
 * 前提: テスト用データベース側で order プラグインがインストールされ、有効になっていること。
 *       在庫管理（use_stock）と対面販売（use_direct）は無効であること（このシナリオが有効にして最後に戻す）。
 */

/* テストデータ */
var scenarioPaymentName = 'シナリオ提供支払';
var scenarioEmail       = 'scenario-provide@example.com';
var scenarioCatalog     = { code: 'scenario-provide', title: 'シナリオ提供方法', password: 'scenario1234' };
var scenarioDownloadUrl = 'https://example.com/scenario-download';
var scenarioStocks = {
    download: { code: 'scenario-dl-stock',     name: 'シナリオDL在庫',   kind: 'digital', quantity: '' },
    zero:     { code: 'scenario-zero-stock',   name: 'シナリオ品切れ在庫', kind: 'analog',  quantity: '0' },
    direct:   { code: 'scenario-direct-stock', name: 'シナリオ店頭在庫',  kind: 'analog',  quantity: '10' }
};
var scenarioSpecs = {
    download: { code: 'scenario-spec-dl',     name: 'シナリオDL版',   provide: 'download', price: '800' },
    zero:     { code: 'scenario-spec-zero',   name: 'シナリオ品切れ版', provide: 'delivery', price: '1200' },
    direct:   { code: 'scenario-spec-direct', name: 'シナリオ店頭版',  provide: 'direct',   price: '1500' }
};
var scenarioKeys = ['download', 'zero', 'direct'];

/* プラグインの設定フォーム（exec=setting のフォーム）を取得する */
var settingForm = function() {
    return $('input[name="exec"][value="setting"]').closest('form');
};

/* 一覧から、コードで対象の行を取得する（在庫・規格・商品・製品） */
var codeRow = function(code) {
    return $('table tbody tr').filter(function() {
        return $(this).find('td code').first().text().trim() === code;
    });
};

/* 一覧から、1列目の名前で対象の行を取得する（支払方法） */
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

/* 在庫を登録する（登録フォームで実行） */
var registerStock = function(key) {
    var stock = scenarioStocks[key];
    var form = $('form.register');

    if (!test.assert(form.find('input[name="code"]').length === 1, '在庫の登録フォームが表示されていません。')) {
        return;
    }

    form.find('input[name="code"]').val(stock.code);
    form.find('input[name="name"]').val(stock.name);
    form.find('select[name="kind"]').val(stock.kind).trigger('change');
    form.find('input[name="quantity"]').val(stock.quantity);
    form.find('input[name="cost_price"]').val('100');

    if (stock.kind === 'digital') {
        // デジタルコンテンツを選んだときだけ「ダウンロード案内」の欄が出る（plugins/order/js/admin.js）
        test.assert($('.for-digital').is(':visible'), 'デジタルコンテンツを選んでも「ダウンロード案内」の欄が表示されません。');
        form.find('textarea[name="download"]').val(scenarioDownloadUrl);
    }

    test.click('form.register button[type="submit"]');
};

/* 規格を登録する（登録フォームで実行） */
var registerSpec = function(key) {
    var spec = scenarioSpecs[key];
    var form = $('form.register');

    if (!test.assert(form.find('input[name="code"]').length === 1, '規格の登録フォームが表示されていません。')) {
        return;
    }

    form.find('input[name="code"]').val(spec.code);
    form.find('input[name="name"]').val(spec.name);
    form.find('select[name="provide"]').val(spec.provide);
    form.find('input[name="selling_price"]').val(spec.price);
    form.find('select[name="enabled"]').val('1');
    test.click('form.register button[type="submit"]');
};

/* 製品を登録する（登録フォームで実行。規格と同じキーの在庫をひも付ける） */
var registerProduct = function(key) {
    var form = $('form.register');

    if (!test.assert(form.find('select[name="stock_id"]').length === 1, '製品の登録フォームが表示されていません。')) {
        return;
    }
    if (!selectByText(form.find('select[name="stock_id"]'), scenarioStocks[key].code, '在庫 ' + scenarioStocks[key].code + ' が製品の選択肢にありません。')) {
        return;
    }

    form.find('input[name="quantity"]').val('1');
    test.click('form.register button[type="submit"]');
};

/* 規格一覧から、指定の規格の製品一覧へ移動するステップ */
var gotoProductsStep = function(key) {
    return function() {
        test.click(codeRow(scenarioSpecs[key].code).find('a:contains("製品")'), '規格一覧の ' + scenarioSpecs[key].code + ' の製品へのリンクが見つかりません。');
    };
};

/* 規格を順に登録するステップ（登録 → 一覧で確認して次の登録へ） */
var specSteps = [];
scenarioKeys.forEach(function(key, index) {
    specSteps.push(function() {
        registerSpec(key);
    });
    specSteps.push(function() {
        test.assertText('div.alert-success', '規格を登録しました。', '規格 ' + scenarioSpecs[key].code + ' を登録できていません。');
        test.assert(codeRow(scenarioSpecs[key].code).length === 1, '登録した規格 ' + scenarioSpecs[key].code + ' が一覧にありません。');

        if (index < scenarioKeys.length - 1) {
            test.click('a:contains("規格登録")');
        } else {
            gotoProductsStep(scenarioKeys[0])();
        }
    });
});

/* 製品を順に登録するステップ（製品一覧 → 登録 → 規格一覧に戻って次の規格の製品へ） */
var productSteps = [];
scenarioKeys.forEach(function(key, index) {
    productSteps.push(function() {
        test.click('a:contains("製品登録")');
    });
    productSteps.push(function() {
        registerProduct(key);
    });
    productSteps.push(function() {
        test.assertText('div.alert-success', '製品を登録しました。', '規格 ' + scenarioSpecs[key].code + ' の製品を登録できていません。');
        test.assert(codeRow(scenarioStocks[key].code).length === 1, '登録した製品が一覧にありません。');

        if (index < scenarioKeys.length - 1) {
            test.click('a:contains("規格管理")');
        } else {
            test.click('a:contains("ログアウト")');
        }
    });
    if (index < scenarioKeys.length - 1) {
        productSteps.push(gotoProductsStep(scenarioKeys[index + 1]));
    }
});

/* 規格を順に削除するステップ（関連する製品も消える） */
var specDeleteSteps = [];
scenarioKeys.forEach(function(key, index) {
    specDeleteSteps.push(function() {
        test.click(codeRow(scenarioSpecs[key].code).find('a:contains("編集")'), '規格一覧の ' + scenarioSpecs[key].code + ' の編集リンクが見つかりません。');
    });
    specDeleteSteps.push(function() {
        test.assertValue('form.register input[name="code"]', scenarioSpecs[key].code, '編集対象の規格が違います。');

        submitDeleteForm();
    });
    specDeleteSteps.push(function() {
        test.assertText('div.alert-success', '規格を削除しました。', '規格 ' + scenarioSpecs[key].code + ' を削除できていません。');
        test.assert(codeRow(scenarioSpecs[key].code).length === 0, '削除した規格が一覧に残っています。');

        if (index === scenarioKeys.length - 1) {
            test.click('a:contains("商品管理")');
        }
    });
});
// 最後以外の「削除できたことを確認」ステップは、次の規格の編集へ進む処理と1つにまとめる
specDeleteSteps = specDeleteSteps.reduce(function(steps, step, index) {
    if (index % 3 === 0 && index > 0) {
        var previous = steps.pop();

        steps.push(function() {
            previous();
            step();
        });
    } else {
        steps.push(step);
    }

    return steps;
}, []);

/* 在庫を順に削除するステップ */
var stockDeleteSteps = [];
scenarioKeys.forEach(function(key, index) {
    stockDeleteSteps.push(function() {
        test.click(codeRow(scenarioStocks[key].code).find('a:contains("編集")'), '在庫一覧の ' + scenarioStocks[key].code + ' の編集リンクが見つかりません。');
    });
    stockDeleteSteps.push(function() {
        test.assertValue('form.register input[name="code"]', scenarioStocks[key].code, '編集対象の在庫が違います。');

        submitDeleteForm();
    });
    stockDeleteSteps.push(function() {
        test.assertText('div.alert-success', '在庫を削除しました。', '在庫 ' + scenarioStocks[key].code + ' を削除できていません。');
        test.assert(codeRow(scenarioStocks[key].code).length === 0, '削除した在庫が一覧に残っています。');

        if (index === scenarioKeys.length - 1) {
            test.visit('/admin/payment');
        }
    });
});
stockDeleteSteps = stockDeleteSteps.reduce(function(steps, step, index) {
    if (index % 3 === 0 && index > 0) {
        var previous = steps.pop();

        steps.push(function() {
            previous();
            step();
        });
    } else {
        steps.push(step);
    }

    return steps;
}, []);

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

        test.visit('/admin/plugin_view?code=order');
    },
    // プラグイン詳細: 在庫管理と対面販売を有効にする
    function() {
        var form = settingForm();

        if (!test.assert(form.length === 1, 'プラグインの設定フォームが表示されていません。')) {
            return;
        }

        test.assert(form.find('input[name="setting[use_stock]"]').prop('checked') === false, '在庫管理が既に有効です。前回のテストが中断した可能性があります。');
        test.assert(form.find('input[name="setting[use_direct]"]').prop('checked') === false, '対面販売が既に有効です。前回のテストが中断した可能性があります。');

        form.find('input[name="setting[use_stock]"]').prop('checked', true);
        form.find('input[name="setting[use_direct]"]').prop('checked', true);
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
    // 支払方法登録: 手数料100円で登録する
    function() {
        var form = $('form.register');

        test.assert(form.find('input[name="name"]').length === 1, '支払方法の登録フォームが表示されていません。');

        form.find('input[name="name"]').val(scenarioPaymentName);
        form.find('input[name="fee"]').val('100');
        form.find('select[name="enabled"]').val('1');
        test.click('form.register button[type="submit"]');
    },
    // 支払方法一覧: 登録できたことを確認して、在庫管理へ移動する
    function() {
        test.assertText('div.alert-success', '支払方法を登録しました。', '支払方法を登録できていません。');

        test.visit('/admin/stock');
    },
    // 在庫一覧: 前回のデータが残っていないことを確認して、登録ページへ移動する
    function() {
        scenarioKeys.forEach(function(key) {
            test.assert(codeRow(scenarioStocks[key].code).length === 0, '在庫 ' + scenarioStocks[key].code + ' が残っています。前回のテストが中断した可能性があります。');
        });

        test.click('a:contains("在庫登録")');
    },
    // 在庫登録: ダウンロード用の在庫（デジタルコンテンツ、数は管理しない）
    function() {
        registerStock('download');
    },
    // 在庫一覧: 登録できたことを確認して、次の在庫の登録ページへ移動する
    function() {
        test.assertText('div.alert-success', '在庫を登録しました。', '在庫 ' + scenarioStocks.download.code + ' を登録できていません。');
        test.click('a:contains("在庫登録")');
    },
    // 在庫登録: 在庫が0の在庫
    function() {
        registerStock('zero');
    },
    // 在庫一覧: 登録できたことを確認して、次の在庫の登録ページへ移動する
    function() {
        test.assertText('div.alert-success', '在庫を登録しました。', '在庫 ' + scenarioStocks.zero.code + ' を登録できていません。');
        test.click('a:contains("在庫登録")');
    },
    // 在庫登録: 対面販売用の在庫（10個）
    function() {
        registerStock('direct');
    },
    // 在庫一覧: 3つとも登録できたことを確認して、商品管理へ移動する
    function() {
        test.assertText('div.alert-success', '在庫を登録しました。', '在庫 ' + scenarioStocks.direct.code + ' を登録できていません。');
        scenarioKeys.forEach(function(key) {
            test.assert(codeRow(scenarioStocks[key].code).length === 1, '登録した在庫 ' + scenarioStocks[key].code + ' が一覧にありません。');
        });

        test.visit('/admin/catalog');
    },
    // 商品一覧: 前回のデータが残っていないことを確認して、登録ページへ移動する
    function() {
        test.assert(codeRow(scenarioCatalog.code).length === 0, '商品 ' + scenarioCatalog.code + ' が残っています。前回のテストが中断した可能性があります。');
        test.click('a:contains("商品登録")');
    },
    // 商品登録: 入力して送信する
    function() {
        var form = $('form.register');

        if (!test.assert(form.find('input[name="code"]').length === 1, '商品の登録フォームが表示されていません。')) {
            return;
        }

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
    // 規格一覧: 前回のデータが残っていないことを確認して、登録ページへ移動する
    function() {
        scenarioKeys.forEach(function(key) {
            test.assert(codeRow(scenarioSpecs[key].code).length === 0, '規格 ' + scenarioSpecs[key].code + ' が残っています。前回のテストが中断した可能性があります。');
        });

        test.click('a:contains("規格登録")');
    }
].concat(specSteps, productSteps, [
    // ログインページ（ログアウト後）: 公開側の商品詳細へ移動する
    function() {
        test.assertNotExists('a:contains("ログアウト")', 'ログアウトできていません。');

        test.visit('/catalog/detail/' + scenarioCatalog.code);
    },
    // 商品詳細（公開側）: 対面販売の規格が出ず、在庫切れの規格は選べないことを確認して、ダウンロード版をカートに入れる
    function() {
        var form = $('form[action$="/cart/add"]');
        var radio = function(key) {
            return form.find('label').filter(function() {
                return $(this).text().indexOf(scenarioSpecs[key].name) !== -1;
            });
        };

        test.assertText('main', scenarioCatalog.title, '公開側の商品詳細が表示されていません。');
        test.assert(form.find('input[name="order_spec_id"]').length === 2, '規格の選択肢が2つ（ダウンロード版・在庫切れ版）になっていません。');
        test.assert(radio('direct').length === 0, '対面販売の規格が公開側に表示されています。');
        test.assert(radio('zero').text().indexOf('在庫切れ') !== -1, '在庫が0の規格に「在庫切れ」と表示されていません。');
        test.assert(radio('zero').find('input').prop('disabled') === true, '在庫切れの規格を選べてしまいます。');
        test.assert(radio('download').text().indexOf('在庫切れ') === -1, '数を管理しないダウンロード版が在庫切れになっています。');
        test.assert(radio('download').find('input').prop('disabled') === false, 'ダウンロード版を選べません。');

        // 後でパスワード認証の前にカートへ直接送る確認に使うので、規格のIDを控えておく（test.data はページをまたいで残らない）
        sessionStorage.setItem('scenarioDownloadSpecId', radio('download').find('input').val());

        radio('download').find('input').prop('checked', true);
        test.click(form.find('button[type="submit"]'), 'カートに追加するボタンが見つかりません。');
    },
    // カート: 追加できたことを確認して、注文手続きへ進む
    function() {
        test.assertText('div.alert-success', 'カートに商品を追加しました。', 'カートに追加できていません。');
        test.assert(textRow(scenarioSpecs.download.name).length === 1, 'カートにダウンロード版がありません。');

        test.click('a:contains("注文手続きへ進む")');
    },
    // 注文（公開側）: 宛先を聞かないことを確認し、未入力で送信して入力エラーを確認する
    function() {
        var form = $('form.register');

        if (!test.assert(form.find('select[name="payment_id"]').length === 1, '注文フォームが表示されていません。')) {
            return;
        }

        test.assertValue('form.register input[name="provide"]', 'download', '提供方法がダウンロードになっていません。');
        test.assertNotExists('form.register select[name="delivery_id"]', 'ダウンロードなのに配送方法の選択欄があります。');
        test.assertNotExists('form.register input[name="name_01"]', 'ダウンロードなのに宛先の入力欄があります。');
        test.assertNotExists('form.register input[name="address_01"]', 'ダウンロードなのに住所の入力欄があります。');

        $('div.warning').remove();
        test.click('form.register button[type="submit"]');

        test.wait(function() {
            return $('div.warning').length > 0;
        }, function() {
            // 宛先・配送方法は必須にならず、支払方法とメールアドレス（公開側の注文では必須）だけがエラーになる
            test.assertText('form.register', '支払方法が入力されていません。', '支払方法の入力エラーが表示されていません。');
            test.assertText('form.register', 'メールアドレスが入力されていません。', 'メールアドレスの入力エラーが表示されていません。');
            test.assert($('form.register div.warning').length === 2, '支払方法・メールアドレス以外にも入力エラーが表示されています。（' + $('form.register div.warning').text() + '）');

            test.reload();
        }, '注文フォームの入力エラーが表示されません。');
    },
    // 注文（公開側）: 支払方法とメールアドレスを入力して送信する
    function() {
        var form = $('form.register');

        if (!selectByText(form.find('select[name="payment_id"]'), scenarioPaymentName, '登録した支払方法が選択肢にありません。')) {
            return;
        }

        form.find('input[name="email"]').val(scenarioEmail);
        test.click('form.register button[type="submit"]');
    },
    // 注文確認（公開側）: 宛先が出ないことを確認して注文する
    function() {
        test.assertText('main', scenarioSpecs.download.name, '注文確認に規格名が表示されていません。');
        test.assertText('main', scenarioEmail, '注文確認にメールアドレスが表示されていません。');
        test.assertNotExists('main dt:contains("住所 1")', 'ダウンロードなのに注文確認に住所が表示されています。');

        test.click('form[action$="/order/preview"] button[type="submit"]');
    },
    // 注文完了（公開側）: 完了を確認して、記録された自動返信メールを開く
    function() {
        test.assertText('main', '注文が完了しました。', '注文を完了できていません。');

        test.visit('/tool/test/mail?date=' + test.date + '&filename=' + encodeURIComponent(test.time + '_' + scenarioEmail));
    },
    // 自動返信メール: 注文内容と金額が入り、お届け先が入らないことを確認して、ログインページへ移動する
    function() {
        test.assertText('body', 'to: ' + scenarioEmail, '自動返信メールが記録されていません。（mail_log が無効になっている可能性があります）');
        test.assertText('body', scenarioSpecs.download.name, '自動返信メールに規格名がありません。');
        // 商品 800円 + 手数料 100円（ダウンロードなので送料は0円）
        test.assertText('body', '合計金額：900円', '自動返信メールの合計金額が 900円 ではありません。');
        test.assertNoText('body', '【お届け先】', 'ダウンロードなのに自動返信メールにお届け先があります。');

        test.visit('/auth/');
    },
    // ログインページ: ログインする
    function() {
        var form = $('form:eq(0)');

        form.find('input[name="username"]').val('admin');
        form.find('input[name="password"]').val('abcd1234');
        test.click('form:eq(0) button[type="submit"]');
    },
    // 管理画面: 注文管理へ移動する
    function() {
        test.assertText('body', '管理者さん', 'ログインできていません。');

        test.visit('/admin/order');
    },
    // 注文一覧: ダウンロードの注文を確認して、編集ページへ移動する
    function() {
        // 名前を聞かないので、お名前の列にはメールアドレスが出る
        var row = textRow(scenarioEmail);

        if (!test.assert(row.length === 1, '注文一覧に ' + scenarioEmail + ' の注文がありません。')) {
            return;
        }

        test.assert(row.text().indexOf('ダウンロード') !== -1, '注文の提供方法がダウンロードではありません。');
        test.assert(row.text().indexOf('900円') !== -1, '注文の合計金額が 900円 ではありません。');

        test.click(row.find('a:contains("編集")'), '注文一覧の編集リンクが見つかりません。');
    },
    // 注文編集: 在庫に登録したダウンロード案内が表示されることを確認して、注文管理へ戻る
    function() {
        var card = $('div.card-header:contains("ダウンロード案内")').closest('div.card');

        test.assert(card.length === 1, 'ダウンロードの注文なのに「ダウンロード案内」が表示されていません。');
        test.assertText(card.find('pre'), scenarioDownloadUrl, 'ダウンロード案内に在庫の内容が表示されていません。');
        test.assertText(card.find('pre'), scenarioStocks.download.name, 'ダウンロード案内に在庫名が表示されていません。');

        test.click('a:contains("注文管理")');
    },
    // 注文一覧: 対面販売の注文登録へ移動する
    function() {
        test.click('a:contains("注文登録（対面）")', '対面販売を有効にしても「注文登録（対面）」のボタンがありません。');
    },
    // 注文登録（対面）: 対面販売の規格だけが選べることを確認して、2つ登録する
    function() {
        var form = $('form.register');
        var select = form.find('select[name="spec_id[]"]').first();

        if (!test.assert(select.length === 1, '注文登録の商品の選択欄がありません。')) {
            return;
        }

        test.assertValue('form.register select[name="provide"]', 'direct', '提供方法が対面になっていません。');
        test.assert(select.find('option:contains("' + scenarioSpecs.download.name + '")').length === 0, '対面販売の注文登録に、ダウンロードの規格が出ています。');
        test.assert(!$('.for-email').is(':visible'), '対面販売なのにメールアドレスの欄が表示されています。');
        test.assert(!$('.for-delivery').first().is(':visible'), '対面販売なのに宛先の欄が表示されています。');

        if (!selectByText(select, scenarioSpecs.direct.name, '対面販売の規格が選択肢にありません。')) {
            return;
        }
        if (!selectByText(form.find('select[name="payment_id"]'), scenarioPaymentName, '登録した支払方法が選択肢にありません。')) {
            return;
        }

        form.find('input[name="quantity[]"]').first().val('2');
        form.find('textarea[name="memo"]').val('シナリオの対面販売です。');
        test.click('form.register button[type="submit"]');
    },
    // 注文一覧: 登録できたことを確認して、対面販売の注文の編集ページへ移動する
    function() {
        test.assertText('div.alert-success', '注文を登録しました。', '対面販売の注文を登録できていません。');

        var row = textRow('対面');

        if (!test.assert(row.length === 1, '注文一覧に対面販売の注文がありません。')) {
            return;
        }

        // 商品 1,500円 × 2（手数料は管理画面の入力値。初期値は0円）
        test.assert(row.text().indexOf('3,000円') !== -1, '対面販売の注文の合計金額が 3,000円 ではありません。');

        test.click(row.find('a:contains("編集")'), '注文一覧の編集リンクが見つかりません。');
    },
    // 注文編集: 明細を確認して削除する
    function() {
        test.assertValue('form.register textarea[name="memo"]', 'シナリオの対面販売です。', '編集対象の注文が違います。');
        test.assertText('main table', scenarioSpecs.direct.name, '注文明細に対面販売の規格がありません。');

        submitDeleteForm();
    },
    // 注文一覧: 削除できたことを確認して、ダウンロードの注文の編集ページへ移動する
    function() {
        test.assertText('div.alert-success', '注文を削除しました。', '対面販売の注文を削除できていません。');

        test.click(textRow(scenarioEmail).find('a:contains("編集")'), '注文一覧の ' + scenarioEmail + ' の編集リンクが見つかりません。');
    },
    // 注文編集: 対象を確認して削除する
    function() {
        test.assertValue('form.register input[name="email"]', scenarioEmail, '編集対象の注文が違います。');

        submitDeleteForm();
    },
    // 注文一覧: 削除できたことを確認して、在庫管理へ移動する
    function() {
        test.assertText('div.alert-success', '注文を削除しました。', 'ダウンロードの注文を削除できていません。');
        test.assert(textRow(scenarioEmail).length === 0, '削除した注文が一覧に残っています。');

        test.visit('/admin/stock');
    },
    // 在庫一覧: 対面販売で在庫が減り、数を管理しない在庫は空のままなことを確認して、商品管理へ移動する
    function() {
        test.assert(codeRow(scenarioStocks.direct.code).find('td').eq(4).text().trim() === '8', '対面販売の注文で在庫が減っていません（10 → 8）。');
        test.assert(codeRow(scenarioStocks.zero.code).find('td').eq(4).text().trim() === '0', '在庫が0の在庫の数が変わっています。');
        test.assert(codeRow(scenarioStocks.download.code).find('td').eq(4).text().trim() === '', '数を管理しない在庫に数が入っています。');

        test.visit('/admin/catalog');
    },
    // 商品一覧: 商品の編集ページへ移動する
    function() {
        test.click(codeRow(scenarioCatalog.code).find('a:contains("編集")'), '一覧の ' + scenarioCatalog.code + ' の編集リンクが見つかりません。');
    },
    // 商品編集: 公開を「パスワード認証で公開」に変更する
    function() {
        var form = $('form.register');

        test.assertValue('form.register input[name="code"]', scenarioCatalog.code, '編集対象の商品が違います。');

        form.find('select[name="public"]').val('password').trigger('change');
        form.find('input[name="password"]').val(scenarioCatalog.password);

        waitEditor(function() {
            test.click('form.register button[type="submit"]');
        });
    },
    // 商品一覧: 変更できたことを確認して、公開側の商品詳細へ移動する
    function() {
        test.assertText('div.alert-success', '商品を登録しました。', '商品を編集できていません。');

        test.visit('/catalog/detail/' + scenarioCatalog.code);
    },
    // 商品詳細（公開側）: 認証前はカートのフォームが出ないことを確認して、カートの追加に直接送る
    function() {
        var specId = sessionStorage.getItem('scenarioDownloadSpecId');
        var token = $('main input[name="_token"]').first().val();

        test.assertExists('main input[name="exec"][value="password"]', '商品詳細にパスワードの入力フォームがありません。');
        test.assertNotExists('form[action$="/cart/add"]', 'パスワード認証の前なのに、カートに追加するフォームが表示されています。');

        if (!test.assert(specId, '控えておいた規格のIDがありません。')) {
            return;
        }

        // 画面から隠すだけでなく、送信されても受け付けないことを確かめる（フォームを組み立てて送る）
        var form = $('<form method="post"><button type="submit">送信</button></form>').attr('action', $('main form').first().attr('action').replace(/\/catalog\/detail\/.*$/, '/cart/add'));

        form.append($('<input type="hidden" name="_token">').val(token));
        form.append($('<input type="hidden" name="quantity">').val('1'));
        form.append($('<input type="hidden" name="order_spec_id">').val(specId));
        $('main').append(form);

        test.click(form.find('button'), '組み立てたフォームの送信ボタンが見つかりません。');
    },
    // エラー画面: 認証前はカートに追加できないことを確認して、商品詳細に戻る
    function() {
        test.assertText('div.alert-danger', 'パスワード認証をしてからカートに追加してください。', 'パスワード認証の前なのに、カートに追加できてしまいます。');

        test.visit('/catalog/detail/' + scenarioCatalog.code);
    },
    // 商品詳細（公開側）: パスワードで認証する
    function() {
        var form = $('main form').filter(function() {
            return $(this).find('input[name="exec"][value="password"]').length === 1;
        });

        if (!test.assert(form.length === 1, '商品詳細にパスワードの入力フォームがありません。')) {
            return;
        }

        test.assert(/\/catalog\/detail\/scenario-provide$/.test(form.attr('action')), 'パスワードフォームの送信先が /catalog/detail/<コード> になっていません。');

        form.find('input[name="password"]').val(scenarioCatalog.password);
        test.click(form.find('button[type="submit"]'), 'パスワードフォームに認証ボタンがありません。');
    },
    // 商品詳細（公開側）: 認証後は詳細に戻り、カートのフォームが出ることを確認して、規格管理へ移動する
    function() {
        test.assert(location.pathname.indexOf('/catalog/detail/' + scenarioCatalog.code) !== -1, '認証後に商品詳細へ戻っていません。（' + location.pathname + '）');
        test.assertNotExists('main input[name="exec"][value="password"]', '認証後もパスワードの入力フォームが表示されています。');
        test.assertExists('form[action$="/cart/add"]', '認証してもカートに追加するフォームが表示されません。');

        sessionStorage.removeItem('scenarioDownloadSpecId');

        test.visit('/admin/catalog');
    },
    // 商品一覧: 規格管理へ移動する
    function() {
        test.click(codeRow(scenarioCatalog.code).find('a:contains("規格")'), '一覧の ' + scenarioCatalog.code + ' の規格へのリンクが見つかりません。');
    }
], specDeleteSteps, [
    // 商品一覧: 商品の編集ページへ移動する
    function() {
        test.click(codeRow(scenarioCatalog.code).find('a:contains("編集")'), '一覧の ' + scenarioCatalog.code + ' の編集リンクが見つかりません。');
    },
    // 商品編集: 対象を確認して削除する
    function() {
        test.assertValue('form.register input[name="code"]', scenarioCatalog.code, '編集対象の商品が違います。');

        submitDeleteForm();
    },
    // 商品一覧: 削除できたことを確認して、在庫管理へ移動する
    function() {
        test.assertText('div.alert-success', '商品を削除しました。', '商品を削除できていません。');
        test.assert(codeRow(scenarioCatalog.code).length === 0, '削除した商品が一覧に残っています。');

        test.visit('/admin/stock');
    }
], stockDeleteSteps, [
    // 支払方法一覧: 支払方法の編集ページへ移動する
    function() {
        test.click(nameRow(scenarioPaymentName).find('a:contains("編集")'), '一覧の ' + scenarioPaymentName + ' の編集リンクが見つかりません。');
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
    // プラグイン詳細: 在庫管理と対面販売を無効に戻す
    function() {
        var form = settingForm();

        if (!test.assert(form.length === 1, 'プラグインの設定フォームが表示されていません。')) {
            return;
        }

        form.find('input[name="setting[use_stock]"]').prop('checked', false);
        form.find('input[name="setting[use_direct]"]').prop('checked', false);
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

        test.assert(form.find('input[name="setting[use_stock]"]').prop('checked') === false, '在庫管理が無効に戻っていません。');
        test.assert(form.find('input[name="setting[use_direct]"]').prop('checked') === false, '対面販売が無効に戻っていません。');

        test.click('a:contains("ログアウト")');
    }
]);
