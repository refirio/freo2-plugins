<?php import('app/views/header.php') ?>

    <div id="gallery-<?php h($_view['entry']['code']) ?>">
        <h2 class="h3 mt-4 mb-3"><?php h($_view['entry']['title']) ?></h2>
        <?php e($GLOBALS['plugin']['gallery']['setting']['text_detail']) ?>

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
                <a href="<?php t(MAIN_FILE) ?>/file/gallery/<?php t($_view['entry']['code']) ?>"><img src="<?php t(app_file_url('entry', $_view['entry']['id'], $_view['entry']['pictures'][0])) ?>" alt="" class="img-fluid rounded mx-auto d-block"></a>
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
    </div>

    <?php import('app/views/comment.php') ?>

    <?php import('app/views/comment_form.php') ?>

    <?php e($_view['widget_sets']['public_page']) ?>

<?php import('app/views/footer.php') ?>
