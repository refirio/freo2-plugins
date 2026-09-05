<?php if (!empty($_view['home_entry'])) :  ?>
<div id="page-<?php t($_view['home_entry']['code']) ?>">
    <h2 class="h3 mt-4 mb-3"><?php h($_view['home_entry']['title']) ?></h2>

    <?php if (!empty($_view['home_entry']['pictures']) && !empty($_view['home_entry']['thumbnail'])) : ?>
    <div class="images">
        <div class="image my-3"><a href="<?php t(MAIN_FILE) ?>/file/page/<?php t($_view['home_entry']['code']) ?>"><img src="<?php t($GLOBALS['config']['storage_url'] . '/' . $GLOBALS['config']['file_target']['entry'] . $_view['home_entry']['id'] . '/' . $_view['home_entry']['thumbnail']) ?>" alt="" class="img-fluid"></a></div>
    </div>
    <?php elseif (!empty($_view['home_entry']['pictures']) || !empty($_view['home_entry']['thumbnail'])) : ?>
    <div class="images">
        <?php if (!empty($_view['home_entry']['pictures'])) : ?>
        <div class="image my-3">
            <?php foreach ($_view['home_entry']['pictures'] as $picture) : ?>
            <img src="<?php t($GLOBALS['config']['storage_url'] . '/' . $GLOBALS['config']['file_target']['entry'] . $_view['home_entry']['id'] . '/' . $picture) ?>" alt="" class="img-fluid">
            <?php endforeach ?>
        </div>
        <?php elseif (!empty($_view['home_entry']['thumbnail'])) : ?>
        <div class="image my-3">
            <img src="<?php t($GLOBALS['config']['storage_url'] . '/' . $GLOBALS['config']['file_target']['entry'] . $_view['home_entry']['id'] . '/' . $_view['home_entry']['thumbnail']) ?>" alt="" class="img-fluid">
        </div>
        <?php endif ?>
    </div>
    <?php endif ?>

    <?php if (!empty($_view['home_entry']['text'])) : ?>
    <div class="text">
        <?php e($_view['home_entry']['text']) ?>
    </div>
    <?php endif ?>
</div>
<?php endif ?>
