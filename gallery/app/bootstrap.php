<?php

// メニューを追加
$menu_contents = [];
foreach ($GLOBALS['menu_contents']['admin']['contents'] as $key => $value) {
    if ($key === 'menu') {
        $menu_contents['gallery'] = [
            'name'   => 'ギャラリー管理',
            'link'   => '/admin/gallery',
            'active' => '/^gallery(_|$)/',
            'icon'   => '#symbol-list-ul',
            'show'   => true,
        ];
    }
    $menu_contents[$key] = $value;
}
$GLOBALS['menu_contents']['admin']['contents'] = $menu_contents;

/**
 * HTML出力で出力するページのURLを取得
 *
 * 一覧と、「全体に公開」の作品の詳細（画像があれば、画像のページも）
 *
 * @return array
 */
function plugin_gallery_export_urls()
{
    $urls = ['/gallery/'];

    $entries = service_export_select_published('gallery', [
        'order_by' => 'entries.id',
    ]);
    foreach ($entries as $entry) {
        $urls[] = '/gallery/detail/' . $entry['code'];

        if (!empty($entry['pictures'])) {
            $urls[] = '/file/gallery/' . $entry['code'];
        }
    }

    return $urls;
}
