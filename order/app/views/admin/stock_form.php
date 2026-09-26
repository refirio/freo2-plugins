<?php import('app/views/admin/header.php') ?>

    <main class="col-md-9 ms-sm-auto col-lg-10 mb-2 px-md-4">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 mb-2">
            <h2 class="h3">
                <svg class="bi flex-shrink-0 me-1 mb-1" width="24" height="24"><use xlink:href="#symbol-pencil-square"/></svg>
                コンテンツ
            </h2>
            <nav style="--bs-breadcrumb-divider: '>';">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php t(MAIN_FILE) ?>/admin/">ホーム</a></li>
                    <li class="breadcrumb-item"><a href="<?php t(MAIN_FILE) ?>/admin/catalog">商品管理</a></li>
                    <li class="breadcrumb-item"><a href="<?php t(MAIN_FILE) ?>/admin/stock">在庫管理</a></li>
                    <li class="breadcrumb-item active"><?php h($_view['title']) ?></li>
                </ol>
            </nav>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header heading"><?php h($_view['title']) ?></div>
            <div class="card-body">
                <?php if (isset($_view['warnings'])) : ?>
                <div class="alert alert-danger">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24"><use xlink:href="#symbol-exclamation-triangle-fill"/></svg>
                    <?php foreach ($_view['warnings'] as $warning) : ?>
                    <?php h($warning) ?>
                    <?php endforeach ?>
                </div>
                <?php endif ?>

                <form action="<?php t(MAIN_FILE) ?>/admin/stock_form<?php $_view['order_stock']['id'] ? t('?id=' . $_view['order_stock']['id']) : '' ?>" method="post" class="register validate">
                    <input type="hidden" name="_token" value="<?php t($_view['token']) ?>" class="token">
                    <input type="hidden" name="id" value="<?php t($_view['order_stock']['id']) ?>">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header">
                            登録
                        </div>
                        <div class="card-body">
                            <div class="form-group mb-2">
                                <label class="fw-bold">在庫管理コード <span class="badge text-light bg-secondary" data-toggle="tooltip" title="在庫最小単位の管理コード。">？</span> <span class="badge bg-danger">必須</span></label>
                                <input type="text" name="code" size="30" value="<?php t($_view['order_stock']['code']) ?>" class="form-control">
                            </div>
                            <div class="form-group mb-2">
                                <label class="fw-bold">名前 <span class="badge bg-danger">必須</span></label>
                                <input type="text" name="name" size="30" value="<?php t($_view['order_stock']['name']) ?>" class="form-control">
                            </div>
                            <div class="form-group mb-2">
                                <label class="fw-bold">内容 <span class="badge text-light bg-secondary" data-toggle="tooltip" title="色やサイズなど在庫を区別できる内容を入力。">？</span></label>
                                <textarea name="text" rows="5" cols="50" class="form-control"><?php t($_view['order_stock']['text']) ?></textarea>
                            </div>
                            <div class="form-group mb-2">
                                <label class="fw-bold">種類 <span class="badge bg-danger">必須</span></label>
                                <select name="kind" class="form-select" style="width: 200px;">
                                    <option value=""></option>
                                    <?php foreach ($GLOBALS['plugin']['order']['option']['order_stock']['kind'] as $key => $value) : ?>
                                    <option value="<?php t($key) ?>"<?php $key == $_view['order_stock']['kind'] ? e(' selected="selected"') : '' ?>><?php t($value) ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="form-group mb-2 for-digital">
                                <label class="fw-bold">ダウンロード案内 <span class="badge text-light bg-secondary" data-toggle="tooltip" title="デジタルコンテンツのダウンロード方法。">？</span></label>
                                <textarea name="download" rows="10" cols="50" class="form-control"><?php t($_view['order_stock']['download']) ?></textarea>
                            </div>
                            <div class="form-group mb-2">
                                <label class="fw-bold">数 <span class="badge text-light bg-secondary" data-toggle="tooltip" title="在庫の数。在庫数を管理しない場合は空欄にする。">？</span></label>
                                <input type="text" name="quantity" size="30" value="<?php t($_view['order_stock']['quantity']) ?>" class="form-control" style="width: 200px;">
                            </div>
                            <div class="form-group mb-2">
                                <label class="fw-bold">原価</label>
                                <input type="text" name="cost_price" size="30" value="<?php t($_view['order_stock']['cost_price']) ?>" class="form-control" style="width: 200px;">
                            </div>
                            <div class="form-group mb-2">
                                <label class="fw-bold">メモ <span class="badge text-light bg-secondary" data-toggle="tooltip" title="公開されないテキスト。">？</span></label>
                                <textarea name="memo" rows="10" cols="50" class="form-control"><?php t($_view['order_stock']['memo']) ?></textarea>
                            </div>
                            <div class="form-group mt-4">
                                <button type="submit" class="btn btn-primary px-4">登録</button>
                            </div>
                        </div>
                    </div>
                </form>

                <?php if (!empty($_GET['id'])) : ?>
                <form action="<?php t(MAIN_FILE) ?>/admin/stock_delete" method="post" class="delete">
                    <input type="hidden" name="_token" value="<?php t($_view['token']) ?>" class="token">
                    <input type="hidden" name="id" value="<?php t($_view['order_stock']['id']) ?>">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header">
                            削除
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <button type="submit" class="btn btn-danger px-4">削除</button>
                            </div>
                        </div>
                    </div>
                </form>
                <?php endif ?>
            </div>
        </div>
        <?php e($_view['widget_sets']['admin_page']) ?>
    </main>

<?php

$_view['script'] = ($_view['script'] ?? '') . '<script src="' . t($GLOBALS['config']['http_path'], true) . t(loader_file('plugins/order/js/admin.js'), true) . '"></script>' . "\n";

?>
<?php import('app/views/admin/footer.php') ?>
