<?php import('app/views/header.php') ?>

    <div id="catalog-<?php h($_view['entry']['code']) ?>">
        <h2 class="h3 mt-4 mb-3"><?php h($_view['entry']['title']) ?></h2>
        <?php e($GLOBALS['plugin']['order']['setting']['text_detail']) ?>

        <?php if (!empty($_view['entry']['category_sets'])) : ?>
        <ul class="category">
            <?php foreach ($_view['entry']['category_sets'] as $category_sets) : ?>
            <li><?php h($category_sets['category_name']) ?></li>
            <?php endforeach ?>
        </ul>
        <?php endif ?>

        <?php if (!empty($_view['entry']['pictures'])) : ?>
        <div class="images">
            <div class="image my-3">
                <a href="<?php t(MAIN_FILE) ?>/file/catalog/<?php t($_view['entry']['code']) ?>"><img src="<?php t($GLOBALS['config']['storage_url'] . '/' . $GLOBALS['config']['file_target']['entry'] . $_view['entry']['id'] . '/' . $_view['entry']['pictures'][0]) ?>" alt="" class="img-fluid rounded mx-auto d-block"></a>
            </div>
        </div>
        <?php endif ?>

        <?php if (!empty($_view['entry']['text'])) : ?>
        <div class="text">
            <?php e($_view['entry']['text']) ?>
        </div>
        <?php endif ?>

        <?php import('app/views/field.php') ?>

        <?php import('app/views/password_form.php') ?>

        <?php
        $single_spec              = count($_view['order_specs']) === 1 ? $_view['order_specs'][0] : null;
        $single_spec_out_of_stock = $single_spec && !empty($_view['order_spec_out_of_stocks'][$single_spec['id']]);
        ?>
        <?php if ($_view['entry']['public'] !== 'password' || !empty($_SESSION['entry_passwords'][$_view['entry']['id']])) : ?>
        <form action="<?php t(MAIN_FILE) ?>/cart/add" method="post">
            <input type="hidden" name="_token" value="<?php t($_view['token']) ?>" class="token">
            <input type="hidden" name="quantity" value="1">
            <!--<pre><?php
                print_r($_view['order_specs']);
            ?></pre>-->
            <?php if ($single_spec) : ?>
            <input type="hidden" name="order_spec_id" value="<?php t($single_spec['id']) ?>">
            <?php if ($single_spec_out_of_stock) : ?>
            <p>在庫切れ</p>
            <?php endif ?>
            <?php else : ?>
            <?php foreach ($_view['order_specs'] as $order_spec) : ?>
            <label><input type="radio" name="order_spec_id" value="<?php t($order_spec['id']) ?>" class="form-check-input"<?php !empty($_view['order_spec_out_of_stocks'][$order_spec['id']]) ? e(' disabled="disabled"') : '' ?>> <?php t($order_spec['name']) ?><?php if (!empty($_view['order_spec_out_of_stocks'][$order_spec['id']])) : ?>（在庫切れ）<?php endif ?></label><br>
            <?php endforeach ?>
            <?php endif ?>
            <div class="form-group mt-4">
                <button type="submit" class="btn btn-primary px-4"<?php $single_spec_out_of_stock ? e(' disabled="disabled"') : '' ?>><?php h($GLOBALS['plugin']['order']['setting']['button_cart_add']) ?></button>
            </div>
        </form>
        <?php endif ?>
    </div>

    <?php import('app/views/comment.php') ?>

    <?php import('app/views/comment_form.php') ?>

    <?php e($_view['widget_sets']['public_page']) ?>

<?php import('app/views/footer.php') ?>
