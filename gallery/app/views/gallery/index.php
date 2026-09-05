<?php import('app/views/header.php') ?>

    <div id="plugin-gallery">
        <h2 class="h3 mt-4 mb-3">Gallery</h2>
        <?php e($GLOBALS['plugin']['gallery']['setting']['text_index']) ?>
        <?php foreach ($_view['categories'] as $category) : ?>
        <?php if (isset($category['name'])) : ?><h3 class="h4 mb-4"><?php h($category['name']) ?></h3><?php endif ?>
        <?php if (!empty($_view['entry_sets'][$category['id']])) : ?>
        <div class="row">
            <?php foreach ($_view['entry_sets'][$category['id']] as $entry) : ?>
            <div class="col-md-3">
                <div class="card mb-4 shadow-sm">
                    <?php if (!empty($entry['thumbnail'])) : ?>
                    <a href="<?php t(MAIN_FILE) ?>/gallery/detail/<?php h($entry['code']) ?>">
                        <img class="card-img-top" src="<?php t($GLOBALS['config']['storage_url'] . '/' . $GLOBALS['config']['file_target']['entry'] . $entry['id'] . '/' . $entry['thumbnail']) ?>" alt="<?php h($entry['title']) ?>">
                    </a>
                    <?php endif ?>
                    <div class="card-body">
                        <p class="card-text"><a href="<?php t(MAIN_FILE) ?>/gallery/detail/<?php h($entry['code']) ?>"><?php h($entry['title']) ?></a></p>
                    </div>
                </div>
            </div>
            <?php endforeach ?>
        </div>
        <?php elseif (isset($category['name'])) : ?>
        <div class="row">
            <p>登録されていません。</p>
        </div>
        <?php endif ?>
        <?php endforeach ?>
    </div>

<?php import('app/views/footer.php') ?>
