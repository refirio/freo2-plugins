<?php if (!empty($_view['home_entries'])) : ?>
<div id="entry">
    <h2 class="h3 mt-4 mb-3">エントリー</h2>
    <ul class="headline">
        <?php foreach ($_view['home_entries'] as $entry) : ?>
        <li>
            <time datetime="<?php h(localdate('Y-m-d', $entry['datetime'])) ?>" class="datetime"><?php h(localdate('Y.m.d', $entry['datetime'])) ?></time>
            <a href="<?php t(MAIN_FILE) ?>/entry/detail/<?php h($entry['code']) ?>" class="px-2"><?php h($entry['title']) ?></a>
            <span class="text"><?php h(truncate(strip_tags($entry['text'] ?? ''), 100)) ?></span>
        </li>
        <?php endforeach ?>
    </ul>
</div>
<?php endif ?>
