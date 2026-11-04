<?php
/**
 * Builds standalone HTML pages in one of the three concept folders, reusing
 * that concept's header, footer and stylesheet.
 */

declare(strict_types=1);

function mcg_style_names(): array
{
    return [
        'Style1' => 'Atelier — light editorial',
        'Style2' => 'Emerald — dark cinematic',
        'Style3' => 'Kinetic — bright modern',
    ];
}

function mcg_localise_concept_links(string $fragment): string
{
    return preg_replace('#href="\.\./(?!Style[123]/)#', 'href="', $fragment);
}

function mcg_style_shell(string $style, string $title, string $description, string $mainHtml, bool $preview = false, string $extraHead = '', string $extraJs = ''): string
{
    $styles = mcg_style_names();
    if (!isset($styles[$style])) {
        $style = 'Style1';
    }
    $indexPath = MCG_ROOT . DIRECTORY_SEPARATOR . $style . DIRECTORY_SEPARATOR . 'index.html';
    if (!is_file($indexPath)) {
        return '<!doctype html><html><head><meta charset="utf-8"><title>' . mcg_e($title) . '</title></head><body><main>' . $mainHtml . '</main></body></html>';
    }
    $source = (string) file_get_contents($indexPath);

    $head = '';
    if (preg_match('#<head>(.*?)</head>#si', $source, $match)) {
        $head = $match[1];
    }
    $sprite = '';
    if (preg_match('#<svg class="svg-sprite".*?</svg>#si', $source, $match)) {
        $sprite = $match[0];
    }
    $header = '';
    if (preg_match('#<header class="site-header".*?</header>#si', $source, $match)) {
        $header = mcg_localise_concept_links($match[0]);
    }
    $footer = '';
    if (preg_match('#<footer class="site-footer".*?</footer>#si', $source, $match)) {
        $footer = mcg_localise_concept_links($match[0]);
    }
    $js = '';
    if (preg_match_all('#<script>(.*?)</script>#si', $source, $matches)) {
        $js = trim((string) end($matches[1]));
    }

    $head = preg_replace('#<title>.*?</title>#si', '<title>' . mcg_e($title) . '</title>', $head, 1);
    $head = preg_replace(
        '#<meta name="description"[^>]*>#si',
        '<meta name="description" content="' . mcg_e(mcg_substr($description, 180)) . '">',
        $head,
        1
    );
    if (stripos($head, 'name="description"') === false) {
        $head = '<meta name="description" content="' . mcg_e(mcg_substr($description, 180)) . '">' . $head;
    }

    $prepend = '';
    if ($preview) {
        $prepend .= '<base href="../' . mcg_e($style) . '/">';
        $prepend .= '<meta name="robots" content="noindex,nofollow">';
    }
    $head = $prepend . $head . $extraHead;

    $bodyClass = $preview ? ' class="generated-preview"' : '';
    return '<!doctype html>' . "\n" . '<html lang="en">' . "\n" . '<head>' . "\n" . $head . "\n" . '</head>' . "\n"
        . '<body' . $bodyClass . '>' . "\n"
        . '<a class="skip-link" href="#main">Skip to content</a>' . "\n"
        . $sprite . "\n"
        . $header . "\n"
        . '<main id="main">' . $mainHtml . '</main>' . "\n"
        . $footer . "\n"
        . '<script>' . $js . '</script>' . "\n"
        . ($extraJs !== '' ? '<script>' . $extraJs . '</script>' . "\n" : '')
        . '</body>' . "\n" . '</html>';
}

function mcg_paragraphs(string $text): string
{
    $parts = preg_split('/\n\s*\n/', trim($text));
    $out = '';
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }
        $out .= '<p>' . nl2br(mcg_e($part), false) . '</p>';
    }
    return $out;
}

function mcg_render_page_blocks(array $blocks): string
{
    $out = '';
    foreach ($blocks as $block) {
        $type = isset($block['type']) ? (string) $block['type'] : 'text';
        $text = isset($block['text']) ? (string) $block['text'] : '';
        $image = isset($block['image']) ? (string) $block['image'] : '';
        $alt = isset($block['alt']) ? (string) $block['alt'] : '';

        if ($type === 'heading') {
            $text = trim($text);
            if ($text !== '') {
                $out .= '<h2>' . mcg_e($text) . '</h2>';
            }
        } elseif ($type === 'quote') {
            $text = trim($text);
            if ($text !== '') {
                $out .= '<blockquote class="page-quote">' . mcg_paragraphs($text) . '</blockquote>';
            }
        } elseif ($type === 'list') {
            $lines = preg_split('/\r?\n/', $text);
            $items = '';
            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line !== '') {
                    $items .= '<li>' . mcg_e($line) . '</li>';
                }
            }
            if ($items !== '') {
                $out .= '<ul>' . $items . '</ul>';
            }
        } elseif ($type === 'image') {
            if ($image !== '' && strpos($image, '..') === false && strpos($image, 'admin/uploads/') === 0) {
                $full = MCG_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $image);
                if (is_file($full)) {
                    $out .= '<figure class="page-inline-image"><img src="' . mcg_e(mcg_media_url($image)) . '" alt="'
                        . mcg_e($alt !== '' ? $alt : 'MCG Biokinetics') . '" loading="lazy">';
                    if ($alt !== '') {
                        $out .= '<figcaption>' . mcg_e($alt) . '</figcaption>';
                    }
                    $out .= '</figure>';
                }
            }
        } else {
            $text = trim($text);
            if ($text !== '') {
                $out .= mcg_paragraphs($text);
            }
        }
    }
    return $out;
}

function mcg_render_content_page(array $page, bool $preview = false): string
{
    $title = isset($page['title']) ? (string) $page['title'] : 'New page';
    $lead = isset($page['lead']) ? (string) $page['lead'] : '';
    $description = isset($page['description']) && $page['description'] !== '' ? (string) $page['description'] : $lead;
    $blocks = isset($page['blocks']) && is_array($page['blocks']) ? $page['blocks'] : [];
    $style = isset($page['style']) ? (string) $page['style'] : 'Style1';
    $updated = isset($page['updated']) ? mcg_format_date($page['updated']) : mcg_format_date(mcg_now());

    $body = mcg_render_page_blocks($blocks);
    if ($body === '') {
        $body = '<p class="muted">This page is being prepared.</p>';
    }

    $main = '
        <section class="page-hero">
            <div class="girih page-girih" aria-hidden="true"></div>
            <div class="wrap page-hero-inner">
                <nav class="breadcrumb" aria-label="Breadcrumb"><a href="index.html">Home</a><span aria-hidden="true">/</span><span aria-current="page">' . mcg_e($title) . '</span></nav>
                <div class="page-hero-main">
                    <div class="page-hero-copy">
                        <p class="eyebrow">MCG Biokinetics</p>
                        <h1>' . mcg_e($title) . '</h1>
                        ' . ($lead !== '' ? '<p class="page-hero-lede">' . mcg_e($lead) . '</p>' : '') . '
                    </div>
                    <div class="page-hero-side" aria-hidden="true">
                        <span class="page-no">+</span>
                        <span class="page-star"><svg class="ico"><use href="#i-star"></use></svg></span>
                        <span class="page-label">MCG · Parktown North</span>
                    </div>
                </div>
                <div class="page-hero-meta"><span>Individualised care</span><span>' . mcg_e($updated) . '</span><span>' . mcg_e($style) . '</span></div>
            </div>
        </section>
        <section class="section page-body generated-page">
            <div class="wrap">
                <article class="prose generated-prose">' . $body . '</article>
            </div>
        </section>
        <section class="page-cta">
            <div class="girih page-cta-girih" aria-hidden="true"></div>
            <div class="wrap page-cta-inner">
                <div class="page-cta-copy">
                    <p class="eyebrow">Start your recovery</p>
                    <h2>Book your assessment.</h2>
                </div>
                <div class="page-cta-actions">
                    <a class="btn btn-accent" href="' . mcg_e(mcg_setting('booking_url', '#')) . '" target="_blank" rel="noopener">Book an appointment <svg class="ico"><use href="#i-arrow"></use></svg></a>
                </div>
            </div>
        </section>';

    $extraHead = '<style>
        .generated-prose{max-width:760px;margin:0 auto}
        .generated-prose > .page-inline-image{margin:2rem 0}
        .generated-prose .page-inline-image img{width:100%;height:auto;border-radius:var(--pg-media-radius);border:1px solid var(--pg-line)}
        .generated-prose .page-inline-image figcaption{margin-top:.7rem;font-size:.62rem;letter-spacing:.14em;text-transform:uppercase;color:var(--pg-muted)}
        .generated-prose .page-quote{margin:2.4rem 0;padding:1.4rem 1.6rem;border-left:2px solid var(--pg-accent);background:var(--pg-soft);font-family:var(--font-serif);font-size:1.35rem;line-height:1.5;color:var(--pg-ink)}
        .generated-prose .page-quote p{margin:0}
    </style>';

    return mcg_style_shell($style, $title . ' — MCG Biokinetics', $description, $main, $preview, $extraHead);
}

function mcg_page_file_path(array $page): string
{
    $style = isset($page['style']) ? (string) $page['style'] : 'Style1';
    $slug = isset($page['slug']) && $page['slug'] !== '' ? (string) $page['slug'] : mcg_slug((string) ($page['title'] ?? 'page'));
    return $style . '/' . $slug . '.html';
}

function mcg_write_site_file(string $relativePath, string $html): array
{
    $relativePath = str_replace('\\', '/', $relativePath);
    if (!preg_match('#^Style[123]/[A-Za-z0-9-]+\.html$#', $relativePath)) {
        return ['ok' => false, 'error' => 'Unsafe generated file path.'];
    }
    $full = MCG_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $dir = dirname($full);
    if (!is_dir($dir) || !is_writable($dir)) {
        return ['ok' => false, 'error' => 'The concept folder is not writable.'];
    }
    $tmp = $full . '.tmp';
    if (@file_put_contents($tmp, $html, LOCK_EX) === false) {
        return ['ok' => false, 'error' => 'Could not write the page file.'];
    }
    if (!@rename($tmp, $full)) {
        @unlink($tmp);
        return ['ok' => false, 'error' => 'Could not replace the page file.'];
    }
    @chmod($full, 0644);
    return ['ok' => true, 'error' => ''];
}

function mcg_publish_page(array $page): array
{
    if (($page['status'] ?? 'draft') !== 'published') {
        return ['ok' => true, 'error' => ''];
    }
    $html = mcg_render_content_page($page, false);
    return mcg_write_site_file(mcg_page_file_path($page), $html);
}

function mcg_delete_page_file(array $page): void
{
    if (empty($page['file'])) {
        $file = mcg_page_file_path($page);
    } else {
        $file = (string) $page['file'];
    }
    $file = str_replace('\\', '/', $file);
    if (!preg_match('#^Style[123]/[A-Za-z0-9-]+\.html$#', $file)) {
        return;
    }
    $full = MCG_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (is_file($full)) {
        @unlink($full);
    }
}

function mcg_style_file_exists(string $style, string $slug, string $ignoreFile = ''): bool
{
    $file = $style . '/' . $slug . '.html';
    if ($ignoreFile !== '' && $file === $ignoreFile) {
        return false;
    }
    return is_file(MCG_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file));
}
