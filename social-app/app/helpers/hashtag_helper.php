<?php

declare(strict_types=1);

/**
 * Tách hashtag khỏi nội dung khi đăng/sửa bài: plain text vào posts.content, tag lưu qua post_hashtags.
 *
 * @return array{plain: string, tags: list<string>}
 */
function parse_post_content_hashtags(string $text): array
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = trim($text);
    if ($text === '') {
        return ['plain' => '', 'tags' => []];
    }

    $tags = [];
    if (preg_match_all('/#(\w+)/u', $text, $m)) {
        $tags = array_values(array_unique($m[1]));
    }

    $lines = explode("\n", $text);
    $out = [];
    foreach ($lines as $line) {
        $line = trim((string) preg_replace('/#(\w+)/u', '', $line));
        $line = trim((string) preg_replace('/[ \t]+/u', ' ', $line));
        $out[] = $line;
    }
    $plain = trim(implode("\n", $out));
    $plain = trim((string) preg_replace("/\n{3,}/u", "\n\n", $plain));

    return ['plain' => $plain, 'tags' => $tags];
}

/**
 * Ghép nội dung plain trong DB với hashtag để hiển thị trong ô sửa (giống lúc user sửa bài).
 *
 * @param array<int, string> $hashtags
 */
function compose_post_content_for_editor(string $plainContent, array $hashtags): string
{
    $plainContent = trim($plainContent);
    $tags = [];
    foreach ($hashtags as $tag) {
        $t = trim((string) $tag);
        if ($t === '') {
            continue;
        }
        $tags[] = '#' . ltrim($t, '#');
    }
    if (empty($tags)) {
        return $plainContent;
    }

    return trim($plainContent . "\n" . implode(' ', $tags));
}
