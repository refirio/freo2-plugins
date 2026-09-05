<?php if (!empty($_view['home_entry'])) :  ?>
<div id="entry-<?php t($_view['home_entry']['code']) ?>">
    <h2 class="h3 mt-4 mb-3"><?php h($_view['home_entry']['title']) ?></h2>
    <div class="text">
        <?php if (!empty($_view['home_entry']['thumbnail'])) : ?>
        <p class="mt-1"><img src="<?php t($GLOBALS['config']['storage_url'] . '/' . $GLOBALS['config']['file_target']['entry'] . $_view['home_entry']['id'] . '/' . $_view['home_entry']['thumbnail']) ?>" alt="" class="img-fluid"></p>
        <?php endif ?>

        <?php if (!empty($_view['home_entry']['text'])) : ?>
        <p class="mb-1"><?php h(truncate(strip_tags($_view['home_entry']['text'] ?? ''), 100)) ?></p>
        <?php endif ?>
        <p class="mt-1"><a href="<?php t(MAIN_FILE) ?>/entry/detail/<?php h($_view['home_entry']['code']) ?>"><?php h($GLOBALS['string']['text_entry_continue']) ?></a></p>
    </div>
</div>
<?php endif ?>
