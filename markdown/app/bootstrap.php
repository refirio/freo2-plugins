<?php

// エントリーとページを「マークダウン」に対応
$GLOBALS['setting_contents']['entry']['entry_text_type']['kind'][$GLOBALS['plugin']['markdown']['code']] = 'マークダウン';
$GLOBALS['setting_contents']['page']['page_text_type']['kind'][$GLOBALS['plugin']['markdown']['code']] = 'マークダウン';
$GLOBALS['config']['option']['entry']['text_type'][$GLOBALS['plugin']['markdown']['code']] = 'マークダウン';

/**
 * Markdownテキストをこのファイルの記法に従ってHTMLに変換する。
 *
 * @param string $text 変換対象のMarkdownテキスト
 * @return string 変換後のHTML文字列
 */
function plugin_markdown_convert($text)
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = explode("\n", $text);

    // 脚注定義( [^id]: 内容 )・参照リンク定義( [label]: url "title" )を事前に抜き出しておく
    $footnoteDefs = [];
    $linkDefs = [];
    $filtered = [];
    foreach ($lines as $l) {
        $t = trim($l);
        if (preg_match('/^\[\^([^\]]+)\]:\s*(.*)$/u', $t, $mm)) {
            $footnoteDefs[$mm[1]] = $mm[2];
            continue;
        }
        if (preg_match('/^\[([^\]]+)\]:\s*(\S+)(?:\s+"([^"]*)")?\s*$/u', $t, $mm2)) {
            $linkDefs[$mm2[1]] = ['url' => $mm2[2], 'title' => $mm2[3] ?? ''];
            continue;
        }
        $filtered[] = $l;
    }
    $lines = array_values($filtered);
    $n = count($lines);
    plugin_markdown_footnotes('reset', $footnoteDefs);
    plugin_markdown_linkrefs('reset', $linkDefs);

    $out = [];
    $i = 0;

    while ($i < $n) {
        $line = $lines[$i];
        $trimmed = trim($line);

        // 空行
        if ($trimmed === '') {
            $i++;
            continue;
        }

        // 水平線 (---, ***, ___)
        if (preg_match('/^(?:-{3,}|\*{3,}|_{3,})$/', $trimmed)) {
            $out[] = '<hr>';
            $i++;
            continue;
        }

        // インデントによるコードブロック(半角スペース4つ以上 or タブ)
        if (preg_match('/^(?: {4}|\t)/', $line)) {
            $buf = [];
            while ($i < $n && (trim($lines[$i]) === '' || preg_match('/^(?: {4}|\t)/', $lines[$i]))) {
                $buf[] = (trim($lines[$i]) === '') ? '' : preg_replace('/^(?: {4}|\t)/', '', $lines[$i]);
                $i++;
            }
            while ($buf && end($buf) === '') {
                array_pop($buf);
            }
            $out[] = '<pre><code>' . htmlspecialchars(implode("\n", $buf), ENT_QUOTES, 'UTF-8') . '</code></pre>';
            continue;
        }

        // フェンスコードブロック(```、```言語、```言語:ファイル名)
        if (preg_match('/^```\s*(\S*)\s*$/', $trimmed, $fm)) {
            $spec = $fm[1];
            $buf = [];
            $i++;
            while ($i < $n && !preg_match('/^```\s*$/', trim($lines[$i]))) {
                $buf[] = $lines[$i];
                $i++;
            }
            if ($i < $n) {
                $i++;
            }
            $out[] = plugin_markdown_code_block_html($spec, implode("\n", $buf));
            continue;
        }

        // HTMLコメント <!-- ... --> (複数行可)
        if (preg_match('/^<!--/', $trimmed)) {
            $buf = [$line];
            while ($i + 1 < $n && strpos(end($buf), '-->') === false) {
                $i++;
                $buf[] = $lines[$i];
            }
            $i++;
            $out[] = implode("\n", $buf);
            continue;
        }

        // ブロックHTML(<details>/<div>など、複数行の生HTMLブロック)
        if (preg_match('/^<(' . plugin_markdown_block_tag_pattern() . ')(?:[\s>]|$)/i', $trimmed, $tm)) {
            $tag = strtolower($tm[1]);
            $buf = [$line];
            while ($i + 1 < $n && !preg_match('#</' . $tag . '>#i', end($buf))) {
                $i++;
                $buf[] = $lines[$i];
            }
            $i++;
            $out[] = implode("\n", $buf);
            continue;
        }

        // 生HTML(タグだけで構成された1行。<a><img></a> のように複数のタグが並ぶ行も含む。山括弧オートリンクは除く)
        if (!plugin_markdown_is_autolink_bracket($trimmed) && plugin_markdown_is_html_line($trimmed)) {
            $out[] = $trimmed;
            $i++;
            continue;
        }

        // 見出し(# 記法)
        if (preg_match('/^(#{1,6})\s*(.+)$/u', $line, $m)) {
            $level = strlen($m[1]);
            $out[] = "<h{$level}>" . plugin_markdown_inline(trim($m[2])) . "</h{$level}>";
            $i++;
            continue;
        }

        // 引用(継続行は > を省略可)
        if (preg_match('/^>\s?(.*)$/', $line)) {
            $buf = [];
            while ($i < $n) {
                if (preg_match('/^>\s?(.*)$/', $lines[$i], $m)) {
                    $buf[] = $m[1];
                    $i++;
                } elseif (trim($lines[$i]) !== '' && !plugin_markdown_is_block_start($lines, $i, $n)) {
                    $buf[] = $lines[$i];
                    $i++;
                } else {
                    break;
                }
            }
            $out[] = '<blockquote><p>' . plugin_markdown_inline(implode("\n", $buf)) . '</p></blockquote>';
            continue;
        }

        // テーブル
        if (plugin_markdown_is_table_start($lines, $i, $n)) {
            $headerCells = plugin_markdown_table_cells($lines[$i]);
            $aligns = array_map('plugin_markdown_table_align', plugin_markdown_table_cells($lines[$i + 1]));
            $i += 2;
            $rows = [];
            while ($i < $n && strpos(trim($lines[$i]), '|') !== false && trim($lines[$i]) !== '') {
                $rows[] = plugin_markdown_table_cells($lines[$i]);
                $i++;
            }
            $out[] = plugin_markdown_table_html($headerCells, $aligns, $rows);
            continue;
        }

        // 箇条書き(順不同、ネスト・チェックボックス対応)
        if (preg_match('/^[*\-]\s+(.+)$/', $line)) {
            [$listHtml, $i] = plugin_markdown_parse_list($lines, $i, $n, 'ul');
            $out[] = $listHtml;
            continue;
        }

        // 箇条書き(順序あり、ネスト対応。"1." "1)" の両方に対応)
        if (preg_match('/^\d+[.)]\s+(.+)$/', $line)) {
            [$listHtml, $i] = plugin_markdown_parse_list($lines, $i, $n, 'ol');
            $out[] = $listHtml;
            continue;
        }

        // 段落(途中に = / - のみの行があれば代替記法の見出しにする)
        $buf = [];
        while ($i < $n && trim($lines[$i]) !== '') {
            if ($buf && preg_match('/^(=+|-+)$/', trim($lines[$i]))) {
                $underline = trim($lines[$i]);
                $level = ($underline[0] === '=') ? 1 : 2;
                $out[] = "<h{$level}>" . plugin_markdown_inline(implode("\n", $buf)) . "</h{$level}>";
                $i++;
                continue 2;
            }
            if (plugin_markdown_is_block_start($lines, $i, $n)) {
                break;
            }
            $buf[] = $lines[$i];
            $i++;
        }
        if ($buf) {
            $out[] = '<p>' . plugin_markdown_inline(implode("\n", $buf)) . '</p>';
        }
    }

    // 脚注一覧
    $used = plugin_markdown_footnotes('used');
    if ($used) {
        $defs = plugin_markdown_footnotes('defs');
        $items = [];
        foreach ($used as $id) {
            $body = isset($defs[$id]) ? plugin_markdown_inline($defs[$id]) : '';
            $safeId = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
            $items[] = '<li id="fn-' . $safeId . '">' . $body . ' <a href="#fnref-' . $safeId . '">&#8617;</a></li>';
        }
        $out[] = '<hr>';
        $out[] = '<ol class="footnotes">' . implode('', $items) . '</ol>';
    }

    return implode("\n", $out);
}

/**
 * 指定行がいずれかのブロック要素の開始行かどうかを判定する。
 * 段落の継続判定・引用の遅延継続判定に使用する。
 *
 * @param string[] $lines 行に分割したMarkdownテキスト
 * @param int $i 判定対象の行インデックス
 * @param int $n $lines の行数
 * @return bool ブロック要素の開始行であれば true
 */
function plugin_markdown_is_block_start($lines, $i, $n)
{
    $line = $lines[$i];
    $trimmed = trim($line);

    if ($trimmed === '') {
        return true;
    }
    if (preg_match('/^(?:-{3,}|\*{3,}|_{3,})$/', $trimmed)) {
        return true;
    }
    if (preg_match('/^```/', $trimmed)) {
        return true;
    }
    if (preg_match('/^<!--/', $trimmed)) {
        return true;
    }
    if (preg_match('/^<(' . plugin_markdown_block_tag_pattern() . ')(?:[\s>]|$)/i', $trimmed)) {
        return true;
    }
    if (!plugin_markdown_is_autolink_bracket($trimmed) && plugin_markdown_is_html_line($trimmed)) {
        return true;
    }
    if (preg_match('/^(#{1,6})\s*(.+)$/u', $line)) {
        return true;
    }
    if (preg_match('/^>\s?/', $line)) {
        return true;
    }
    if (plugin_markdown_is_table_start($lines, $i, $n)) {
        return true;
    }
    if (preg_match('/^[*\-]\s+(.+)$/', $line)) {
        return true;
    }
    if (preg_match('/^\d+[.)]\s+(.+)$/', $line)) {
        return true;
    }
    return false;
}

/**
 * ブロックレベルの生HTMLとして複数行パススルーする対象タグ名を、preg_match用の交互パターン文字列で返す。
 *
 * @return string 例: "details|div|section|..."
 */
function plugin_markdown_block_tag_pattern()
{
    return 'details|div|section|article|header|footer|aside|nav|figure|form';
}

/**
 * 行全体がHTMLタグだけで構成されているか( <img ...> / <a ...><img ...></a> など、タグの間に文字を含まない)を判定する。
 * 開始タグで始まる行だけを対象とする。タグ名の直後が空白・/・> のものだけをタグとみなすため、
 * <https://...> や <user@example.com> などの山括弧オートリンクはタグとして扱わない。
 *
 * @param string $trimmed 前後の空白を除去した行
 * @return bool タグだけで構成されていれば true
 */
function plugin_markdown_is_html_line($trimmed)
{
    return (bool) preg_match('/^<[a-zA-Z][a-zA-Z0-9\-]*(?:\s[^>]*)?\/?>(?:\s*<\/?[a-zA-Z][a-zA-Z0-9\-]*(?:\s[^>]*)?\/?>)*$/', $trimmed);
}

/**
 * 行全体が山括弧オートリンク( <https://...> / <mailto:...> / <user@example.com> )かどうかを判定する。
 * 生HTMLタグの1行パススルー処理からこの形式を除外するために使用する。
 *
 * @param string $trimmed 前後の空白を除去した行
 * @return bool 山括弧オートリンクであれば true
 */
function plugin_markdown_is_autolink_bracket($trimmed)
{
    if (preg_match('/^<(?:https?:|mailto:)[^ <>]+>$/', $trimmed)) {
        return true;
    }
    if (preg_match('/^<[^ <>@]+@[^ <>]+\.[^ <>]+>$/', $trimmed)) {
        return true;
    }
    return false;
}

/**
 * 脚注の定義・使用状況を静的変数で保持する簡易ストア。
 * 変換の開始時に 'reset' で $footnoteDefs をセットしてから使用すること。
 *
 * @param string $action 'reset'|'use'|'defs'|'used'
 * @param mixed $arg 'reset' のときは脚注定義の連想配列([id => 内容])、'use' のときは参照する脚注ID
 * @return mixed 'use' は採番された脚注番号(int)、'defs' は定義の連想配列、'used' は使用された脚注IDの配列、'reset' は null
 */
function plugin_markdown_footnotes($action, $arg = null)
{
    static $defs = [];
    static $used = [];

    switch ($action) {
        case 'reset':
            $defs = is_array($arg) ? $arg : [];
            $used = [];
            return null;
        case 'use':
            if (!in_array($arg, $used, true)) {
                $used[] = $arg;
            }
            return array_search($arg, $used, true) + 1;
        case 'defs':
            return $defs;
        case 'used':
            return $used;
    }
    return null;
}

/**
 * 参照リンク定義( [label]: url "title" )を静的変数で保持する簡易ストア。
 * 変換の開始時に 'reset' で定義の連想配列をセットしてから使用すること。
 *
 * @param string $action 'reset'|'get'
 * @param mixed $arg 'reset' のときは定義の連想配列([label => ['url'=>..,'title'=>..]])、'get' のときは参照するラベル
 * @return mixed 'get' は定義の連想配列(未定義なら null)、'reset' は null
 */
function plugin_markdown_linkrefs($action, $arg = null)
{
    static $defs = [];

    switch ($action) {
        case 'reset':
            $defs = is_array($arg) ? $arg : [];
            return null;
        case 'get':
            return $defs[$arg] ?? null;
    }
    return null;
}

/**
 * 指定行がテーブルの開始行(ヘッダー行+区切り行)かどうかを判定する。
 *
 * @param string[] $lines 行に分割したMarkdownテキスト
 * @param int $i 判定対象の行インデックス(ヘッダー行)
 * @param int $n $lines の行数
 * @return bool テーブルの開始行であれば true
 */
function plugin_markdown_is_table_start($lines, $i, $n)
{
    if ($i + 1 >= $n) {
        return false;
    }
    if (strpos($lines[$i], '|') === false) {
        return false;
    }
    return plugin_markdown_is_table_delimiter($lines[$i + 1]);
}

/**
 * 行がテーブルの区切り行( |:---|:---:|---:| など )かどうかを判定する。
 *
 * @param string $line 判定対象の行
 * @return bool 区切り行であれば true
 */
function plugin_markdown_is_table_delimiter($line)
{
    $line = trim($line);
    if ($line === '') {
        return false;
    }
    return (bool) preg_match('/^\|?\s*:?-+:?\s*(\|\s*:?-+:?\s*)*\|?$/', $line);
}

/**
 * テーブルの1行( |セル1|セル2|... )をセルの配列に分解する。
 *
 * @param string $line テーブルの1行
 * @return string[] 前後の空白を除去したセルの配列
 */
function plugin_markdown_table_cells($line)
{
    $line = trim($line);
    $line = preg_replace('/^\|/', '', $line);
    $line = preg_replace('/\|$/', '', $line);
    return array_map('trim', explode('|', $line));
}

/**
 * テーブル区切り行の1セル(例: :---, ---:, :---:)から寄せ方向を判定する。
 *
 * @param string $cell 区切り行の1セル
 * @return string 'left'|'center'|'right'|''(指定なし)
 */
function plugin_markdown_table_align($cell)
{
    $cell = trim($cell);
    $left = substr($cell, 0, 1) === ':';
    $right = substr($cell, -1) === ':';
    if ($left && $right) {
        return 'center';
    }
    if ($right) {
        return 'right';
    }
    if ($left) {
        return 'left';
    }
    return '';
}

/**
 * ヘッダー・寄せ方向・本体行から <table> のHTMLを組み立てる。
 *
 * @param string[] $header ヘッダー行のセル配列
 * @param string[] $aligns 列ごとの寄せ方向('left'|'center'|'right'|'')の配列
 * @param string[][] $rows 本体行(セル配列)の配列
 * @return string <table>...</table> のHTML文字列
 */
function plugin_markdown_table_html($header, $aligns, $rows)
{
    $html = '<table class="table table-bordered">';
    $html .= '<thead><tr>';
    foreach ($header as $idx => $cell) {
        $style = (!empty($aligns[$idx])) ? ' style="text-align:' . $aligns[$idx] . '"' : '';
        $html .= '<th' . $style . '>' . plugin_markdown_inline($cell) . '</th>';
    }
    $html .= '</tr></thead>';
    $html .= '<tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $idx => $cell) {
            $style = (!empty($aligns[$idx])) ? ' style="text-align:' . $aligns[$idx] . '"' : '';
            $html .= '<td' . $style . '>' . plugin_markdown_inline($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}

/**
 * 箇条書き(順不同/順序あり)を、ネストとチェックボックスに対応しつつHTML化する。
 * ネストしたリストが見つかった場合は自身を再帰呼び出しする。
 *
 * @param string[] $lines 行に分割したMarkdownテキスト
 * @param int $i 解析開始行のインデックス
 * @param int $n $lines の行数
 * @param string $type 'ul'|'ol'
 * @return array{0: string, 1: int} [組み立てたリストのHTML, 解析後の行インデックス]
 */
function plugin_markdown_parse_list($lines, $i, $n, $type)
{
    preg_match('/^(\s*)/', $lines[$i], $im);
    $baseIndent = strlen($im[1]);
    $marker = ($type === 'ul') ? '[*\-]' : '\d+[.)]';
    $items = [];

    while ($i < $n && trim($lines[$i]) !== '') {
        preg_match('/^(\s*)/', $lines[$i], $im2);
        $indent = strlen($im2[1]);
        if ($indent !== $baseIndent || !preg_match('/^\s*' . $marker . '\s+(.+)$/', $lines[$i], $m)) {
            break;
        }

        $content = $m[1];
        $i++;

        $checkbox = '';
        if (preg_match('/^\[( |x|X)\]\s*(.*)$/', $content, $cm)) {
            $checked = strtolower($cm[1]) === 'x';
            $checkbox = '<input type="checkbox" disabled' . ($checked ? ' checked' : '') . '> ';
            $content = $cm[2];
        }

        $itemHtml = $checkbox . plugin_markdown_inline($content);

        // 子リスト(インデントが深い箇条書き)を確認
        if ($i < $n && trim($lines[$i]) !== '') {
            preg_match('/^(\s*)/', $lines[$i], $im3);
            $childIndent = strlen($im3[1]);
            if ($childIndent > $baseIndent && preg_match('/^\s*(?:[*\-]|\d+[.)])\s+(.+)$/', $lines[$i])) {
                $childType = preg_match('/^\s*\d+[.)]/', $lines[$i]) ? 'ol' : 'ul';
                [$childHtml, $i] = plugin_markdown_parse_list($lines, $i, $n, $childType);
                $itemHtml .= $childHtml;
            }
        }

        $items[] = '<li>' . $itemHtml . '</li>';
    }

    return ['<' . $type . '>' . implode('', $items) . '</' . $type . '>', $i];
}

/**
 * フェンスコードブロックの中身をHTML化する(```言語:ファイル名 の指定に対応)。
 *
 * @param string $spec フェンス開始行の言語指定部分(例: "ruby:hello.rb")
 * @param string $content コードブロックの中身
 * @return string <pre><code>...</code></pre> のHTML文字列
 */
function plugin_markdown_code_block_html($spec, $content)
{
    $lang = $spec;
    $filename = '';
    if (strpos($spec, ':') !== false) {
        [$lang, $filename] = explode(':', $spec, 2);
    }
    $lang = strtolower(trim($lang));

    $code = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    $classAttr = $lang !== '' ? ' class="language-' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '"' : '';
    $caption = $filename !== '' ? '<div class="filename">' . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') . '</div>' : '';
    return $caption . '<pre><code' . $classAttr . '>' . $code . '</code></pre>';
}

/**
 * 画像のサイズ指定( =200x200 / =200x / =x200 / =full )からstyle属性を組み立てる。
 *
 * @param string $spec サイズ指定文字列(例: "200x200", "200x", "x200", "full", "")
 * @return string ' style="..."' の形式の文字列、指定なし・不正な場合は空文字
 */
function plugin_markdown_image_style($spec)
{
    if ($spec === '') {
        return '';
    }
    if ($spec === 'full') {
        return ' style="max-width:100%;height:auto;"';
    }
    if (preg_match('/^(\d*)x(\d*)$/', $spec, $m)) {
        $styles = [];
        if ($m[1] !== '') {
            $styles[] = 'width:' . (int) $m[1] . 'px';
        }
        if ($m[2] !== '') {
            $styles[] = 'height:' . (int) $m[2] . 'px';
        }
        if ($styles) {
            return ' style="' . implode(';', $styles) . ';"';
        }
    }
    return '';
}

/**
 * <span style="..."> の値のうち、安全な指定(色・文字サイズのみ)だけを許可するホワイトリスト検証を行う。
 *
 * @param string $style 検証対象のstyle属性値
 * @return string|null 正規化した安全なstyle値、許可されない指定の場合は null
 */
function plugin_markdown_sanitize_style($style)
{
    $style = trim($style);
    if (preg_match('/^color\s*:\s*(#[0-9a-fA-F]{3,8}|[a-zA-Z]{1,20})\s*;?$/', $style, $m)) {
        return 'color:' . $m[1] . ';';
    }
    if (preg_match('/^font-size\s*:\s*(\d{1,3}(?:\.\d+)?(?:%|px|em))\s*;?$/', $style, $m)) {
        return 'font-size:' . $m[1] . ';';
    }
    return null;
}

/**
 * 1行または複数行のMarkdownテキストに対して、インライン記法(強調・リンク・画像・インラインコード・脚注参照・自動リンクなど)をHTMLに変換する。
 * HTMLエスケープもこの関数内で行う。
 *
 * @param string $text 変換対象のテキスト(未エスケープの生Markdown)
 * @return string 変換後のHTML文字列
 */
function plugin_markdown_inline($text)
{
    // 改行ルール: 行末が半角スペース2個以上の場合のみ強制改行(<br>)、それ以外の改行は半角スペース1個として連結する
    $rawLines = explode("\n", $text);
    $lineCount = count($rawLines);
    $joined = [];
    foreach ($rawLines as $idx => $l) {
        $hardBreak = $idx < $lineCount - 1 && preg_match('/ {2,}$/', $l);
        $joined[] = rtrim($l, " \t");
        if ($idx < $lineCount - 1) {
            $joined[] = $hardBreak ? "\x00BR\x00" : ' ';
        }
    }
    $text = implode('', $joined);

    // バックスラッシュエスケープ( \* \_ \( など )を退避
    $escapes = [];
    $text = preg_replace_callback('/\\\\([\\\\`*_{}\[\]()#+\-.!~>])/', function ($m) use (&$escapes) {
        $key = "\x00ESC" . count($escapes) . "\x00";
        $escapes[$key] = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
        return $key;
    }, $text);

    // HTML特殊文字をエスケープ
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    $replacements = [];

    // インラインコード(`code`)を退避
    $text = preg_replace_callback('/`([^`]+?)`/', function ($m) use (&$replacements) {
        $key = "\x00CODE" . count($replacements) . "\x00";
        $replacements[$key] = '<code>' . $m[1] . '</code>';
        return $key;
    }, $text);

    // 脚注参照 [^id]
    $text = preg_replace_callback('/\[\^([^\]]+)\]/', function ($m) use (&$replacements) {
        $id = $m[1];
        $num = plugin_markdown_footnotes('use', $id);
        $safeId = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $key = "\x00FN" . count($replacements) . "\x00";
        $replacements[$key] = '<sup id="fnref-' . $safeId . '"><a href="#fn-' . $safeId . '">' . $num . '</a></sup>';
        return $key;
    }, $text);

    // 画像 ![alt](url) / ![alt](url =WxH)
    $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)(?:\s+=(\d*x\d*|full))?\)/', function ($m) use (&$replacements) {
        $style = plugin_markdown_image_style($m[3] ?? '');
        $key = "\x00IMG" . count($replacements) . "\x00";
        $replacements[$key] = '<img src="' . $m[2] . '" alt="' . $m[1] . '"' . $style . '>';
        return $key;
    }, $text);

    // 参照リンク [text][label] (label省略時は [text] 自体をラベルとする)
    $text = preg_replace_callback('/\[([^\]]+)\]\[([^\]]*)\]/', function ($m) use (&$replacements) {
        $label = $m[2] !== '' ? $m[2] : $m[1];
        $def = plugin_markdown_linkrefs('get', $label);
        if ($def === null) {
            return $m[0];
        }
        $href = htmlspecialchars($def['url'], ENT_QUOTES, 'UTF-8');
        $titleAttr = $def['title'] !== '' ? ' title="' . htmlspecialchars($def['title'], ENT_QUOTES, 'UTF-8') . '"' : '';
        $key = "\x00LINK" . count($replacements) . "\x00";
        $replacements[$key] = '<a href="' . $href . '"' . $titleAttr . '>' . $m[1] . '</a>';
        return $key;
    }, $text);

    // 参照リンク(ショートカット形式) [label] (定義が存在する場合のみ変換する)
    $text = preg_replace_callback('/\[([^\]]+)\]/', function ($m) use (&$replacements) {
        $def = plugin_markdown_linkrefs('get', $m[1]);
        if ($def === null) {
            return $m[0];
        }
        $href = htmlspecialchars($def['url'], ENT_QUOTES, 'UTF-8');
        $titleAttr = $def['title'] !== '' ? ' title="' . htmlspecialchars($def['title'], ENT_QUOTES, 'UTF-8') . '"' : '';
        $key = "\x00LINK" . count($replacements) . "\x00";
        $replacements[$key] = '<a href="' . $href . '"' . $titleAttr . '>' . $m[1] . '</a>';
        return $key;
    }, $text);

    // リンク [text](url) / [text](url "title")(この時点でHTMLエスケープ済みのため " は &quot; になっている)
    $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)(?:\s+&quot;([^&]*)&quot;)?\)/', function ($m) use (&$replacements) {
        $titleAttr = !empty($m[3]) ? ' title="' . $m[3] . '"' : '';
        $key = "\x00LINK" . count($replacements) . "\x00";
        $replacements[$key] = '<a href="' . $m[2] . '"' . $titleAttr . '>' . $m[1] . '</a>';
        return $key;
    }, $text);

    // 山括弧オートリンク <https://...> / <mailto:...> / <user@example.com>
    $text = preg_replace_callback('/&lt;(?:mailto:)([^&\s]+)&gt;/', function ($m) use (&$replacements) {
        $key = "\x00AL" . count($replacements) . "\x00";
        $replacements[$key] = '<a href="mailto:' . $m[1] . '">' . $m[1] . '</a>';
        return $key;
    }, $text);
    $text = preg_replace_callback('/&lt;(https?:[^&\s]+)&gt;/', function ($m) use (&$replacements) {
        $key = "\x00AL" . count($replacements) . "\x00";
        $replacements[$key] = '<a href="' . $m[1] . '">' . $m[1] . '</a>';
        return $key;
    }, $text);
    $text = preg_replace_callback('/&lt;([^&\s@]+@[^&\s]+\.[^&\s]+)&gt;/', function ($m) use (&$replacements) {
        $key = "\x00AL" . count($replacements) . "\x00";
        $replacements[$key] = '<a href="mailto:' . $m[1] . '">' . $m[1] . '</a>';
        return $key;
    }, $text);

    // 打ち消し線 ~~text~~
    $text = preg_replace('/~~([^~]+?)~~/u', '<del>$1</del>', $text);

    // 斜体+太字 ***text*** / ___text___
    $text = preg_replace('/\*\*\*([^*]+?)\*\*\*/u', '<strong><em>$1</em></strong>', $text);
    $text = preg_replace('/___([^_]+?)___/u', '<strong><em>$1</em></strong>', $text);

    // 太字 **text** / __text__
    $text = preg_replace('/\*\*([^*]+?)\*\*/u', '<strong>$1</strong>', $text);
    $text = preg_replace('/__([^_]+?)__/u', '<strong>$1</strong>', $text);

    // 斜体 *text* / _text_
    $text = preg_replace('/\*([^*\n]+?)\*/u', '<em>$1</em>', $text);
    $text = preg_replace('/_([^_\n]+?)_/u', '<em>$1</em>', $text);

    // 下線・ハイライト・文字色/サイズ・span(エスケープ後のタグをホワイトリストで復元)
    $text = preg_replace('/&lt;u&gt;(.*?)&lt;\/u&gt;/su', '<u>$1</u>', $text);
    $text = preg_replace('/&lt;mark&gt;(.*?)&lt;\/mark&gt;/su', '<mark>$1</mark>', $text);
    $text = preg_replace('/&lt;span&gt;(.*?)&lt;\/span&gt;/su', '<span>$1</span>', $text);
    $text = preg_replace_callback('/&lt;span style=&quot;([^&]*)&quot;&gt;(.*?)&lt;\/span&gt;/su', function ($m) {
        $style = plugin_markdown_sanitize_style($m[1]);
        if ($style === null) {
            return $m[0];
        }
        return '<span style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '">' . $m[2] . '</span>';
    }, $text);

    // 裸のURLを自動リンク化
    $text = preg_replace_callback('/(https?:\/\/[^\s<]+)/', function ($m) {
        return '<a href="' . $m[1] . '">' . $m[1] . '</a>';
    }, $text);

    // メールアドレスを自動リンク化
    $text = preg_replace_callback('/([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})/', function ($m) {
        return '<a href="mailto:' . $m[1] . '">' . $m[1] . '</a>';
    }, $text);

    // 退避しておいたリンク・画像・コード等を復元
    $text = strtr($text, $replacements);
    $text = strtr($text, $escapes);

    // 強制改行マーカーを <br> に変換
    $text = str_replace("\x00BR\x00", "<br>\n", $text);

    return $text;
}
