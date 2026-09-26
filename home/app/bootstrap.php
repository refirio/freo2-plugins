<?php

$text_home_index = $GLOBALS['setting']['text_home_index'];

// 指定があれば処理を行なう
if ($text_home_index && preg_match_all('/\<\w+\:\w+\=\w+\>/', $text_home_index, $matches) !== false) {
    $marks = $matches[0];

    // それぞれの指定内容を確認する
    foreach ($marks as $mark) {
        // 指定内容の形式を確認する
        if (preg_match('/^\<(\w+)\:(\w+)\=(\w+)\>$/', $mark, $matches)) {
            // 正しい指定ならデータベースから記事を取得する
            $type   = $matches[1];
            $option = $matches[2];
            $value  = $matches[3];

            import('app/services/entry.php');

            $_view['home_entry']   = null;
            $_view['home_entries'] = null;
            if (($type === 'entry' || $type === 'page') && $option === 'code' && $value) {
                $entries = service_entry_select_published($type, [
                    'where' => [
                        'entries.code = :code',
                        [
                            'code' => $value,
                        ],
                    ],
                ]);
                if (!empty($entries)) {
                    $_view['home_entry'] = $entries[0];
                }
            } elseif ($type === 'entries' && $option === 'limit' && is_numeric($value)) {
                $_view['home_entries'] = service_entry_select_published('entry', [
                    'order_by' => 'entries.datetime DESC, entries.id',
                    'limit'    => $value,
                ]);
            } else {
                continue;
            }

            // 記事をビューに割り当てて結果を取得する
            $contents = view('../../plugins/home/app/views/home/' . $type . '.php', true);

            // 指定箇所に結果を挿入する
            $text_home_index = str_replace($mark, $contents, $text_home_index);
        }
    }
}

$GLOBALS['setting']['text_home_index'] = $text_home_index;
