<?php
/**
 * Gallery data + public Gallery.html rendering.
 */

declare(strict_types=1);

require_once __DIR__ . '/page-render.php';

function mcg_gallery_data(): array
{
    $data = mcg_read_data('gallery', ['categories' => [], 'photos' => []]);
    $categories = isset($data['categories']) && is_array($data['categories']) ? $data['categories'] : [];
    $photos = isset($data['photos']) && is_array($data['photos']) ? $data['photos'] : [];

    usort($categories, function ($a, $b) {
        return ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0));
    });
    usort($photos, function ($a, $b) use ($categories) {
        $order = [];
        foreach ($categories as $category) {
            $order[(int) $category['id']] = (int) ($category['order'] ?? 0);
        }
        $catA = $order[(int) ($a['category_id'] ?? 0)] ?? 9999;
        $catB = $order[(int) ($b['category_id'] ?? 0)] ?? 9999;
        if ($catA === $catB) {
            return ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0));
        }
        return $catA <=> $catB;
    });

    return ['categories' => $categories, 'photos' => $photos];
}

function mcg_gallery_category_name(array $categories, $id): string
{
    foreach ($categories as $category) {
        if ((int) $category['id'] === (int) $id) {
            return (string) $category['name'];
        }
    }
    return 'Uncategorised';
}

function mcg_render_gallery_page(string $style, array $categories, array $photos): string
{
    $intro = mcg_setting('gallery_intro', 'A look at the space, the sessions and the movement that happens here.');
    $categoryNames = [];
    foreach ($categories as $category) {
        $categoryNames[(int) $category['id']] = (string) $category['name'];
    }

    $filters = '<button type="button" class="is-active" data-filter="all">All <span>' . count($photos) . '</span></button>';
    $usedCategories = [];
    foreach ($photos as $photo) {
        $usedCategories[(int) ($photo['category_id'] ?? 0)] = true;
    }
    foreach ($categories as $category) {
        $id = (int) $category['id'];
        if (empty($usedCategories[$id])) {
            continue;
        }
        $count = 0;
        foreach ($photos as $photo) {
            if ((int) ($photo['category_id'] ?? 0) === $id) {
                $count++;
            }
        }
        $filters .= '<button type="button" data-filter="' . $id . '">' . mcg_e($category['name']) . ' <span>' . $count . '</span></button>';
    }

    $items = '';
    foreach ($photos as $photo) {
        $path = (string) ($photo['path'] ?? '');
        if ($path === '') {
            continue;
        }
        $caption = trim((string) ($photo['caption'] ?? ''));
        if ($caption === '') {
            $caption = trim((string) ($photo['alt'] ?? ''));
        }
        if ($caption === '') {
            $caption = 'MCG Biokinetics';
        }
        $categoryId = (int) ($photo['category_id'] ?? 0);
        $categoryName = $categoryNames[$categoryId] ?? 'Uncategorised';
        $url = mcg_media_url($path);
        $items .= '
                    <a class="gallery-item" href="' . mcg_e($url) . '" data-category="' . $categoryId . '" data-caption="' . mcg_e($caption) . '">
                        <img src="' . mcg_e($url) . '" alt="' . mcg_e($photo['alt'] ?? $caption) . '" loading="lazy">
                        <span class="gallery-cap"><span>' . mcg_e($caption) . '</span><span>' . mcg_e($categoryName) . '</span></span>
                    </a>';
    }
    if ($items === '') {
        $items = '<p class="gallery-empty">Photographs will appear here as soon as they are added in the admin portal.</p>';
    }

    $main = '
        <section class="page-hero">
            <div class="girih page-girih" aria-hidden="true"></div>
            <div class="wrap page-hero-inner">
                <nav class="breadcrumb" aria-label="Breadcrumb"><a href="index.html">Home</a><span aria-hidden="true">/</span><span aria-current="page">Gallery</span></nav>
                <div class="page-hero-main">
                    <div class="page-hero-copy">
                        <p class="eyebrow">Inside the practice</p>
                        <h1>Gallery</h1>
                        <p class="page-hero-lede">' . mcg_e($intro) . '</p>
                    </div>
                    <div class="page-hero-side" aria-hidden="true">
                        <span class="page-no">13</span>
                        <span class="page-star"><svg class="ico"><use href="#i-star"></use></svg></span>
                        <span class="page-label">MCG · Parktown North</span>
                    </div>
                </div>
                <div class="page-hero-meta"><span>Inside the practice</span><span>' . count($photos) . ' photographs</span><span>' . count($categories) . ' categories</span></div>
            </div>
        </section>
        <section class="section page-body">
            <div class="wrap">
                <div class="gallery-filters" role="group" aria-label="Filter gallery by category">' . $filters . '</div>
                <div class="gallery-grid" id="galleryGrid">' . $items . '
                </div>
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
        </section>
        <dialog class="lightbox" id="galleryLightbox" aria-label="Gallery image">
            <button class="lightbox-close" type="button" aria-label="Close image">×</button>
            <figure><img src="" alt=""><figcaption></figcaption></figure>
        </dialog>';

    $extraHead = '<style>
        .gallery-filters{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:2.4rem}
        .gallery-filters button{appearance:none;border:1px solid var(--pg-line);border-radius:999px;background:transparent;color:var(--pg-muted);padding:.6rem 1rem;font-family:var(--font-sans);font-size:.58rem;font-weight:600;letter-spacing:.16em;text-transform:uppercase;cursor:pointer;transition:background .25s,color .25s,border-color .25s}
        .gallery-filters button span{opacity:.6;margin-left:4px}
        .gallery-filters button.is-active{background:var(--pg-accent);border-color:var(--pg-accent);color:var(--pg-accent-ink)}
        .gallery-item.is-hidden{display:none}
        .gallery-empty{grid-column:1/-1;padding:3rem 0;color:var(--pg-muted)}
    </style>';

    $extraJs = '
        (function () {
            var grid = document.getElementById("galleryGrid");
            var filters = document.querySelectorAll(".gallery-filters button");
            if (grid && filters.length) {
                filters.forEach(function (button) {
                    button.addEventListener("click", function () {
                        var filter = button.getAttribute("data-filter");
                        filters.forEach(function (other) { other.classList.remove("is-active"); });
                        button.classList.add("is-active");
                        grid.querySelectorAll(".gallery-item").forEach(function (item) {
                            var show = filter === "all" || item.getAttribute("data-category") === filter;
                            item.classList.toggle("is-hidden", !show);
                        });
                    });
                });
            }
            var dialog = document.getElementById("galleryLightbox");
            if (!dialog || !dialog.showModal) return;
            var image = dialog.querySelector("img");
            var caption = dialog.querySelector("figcaption");
            grid.querySelectorAll(".gallery-item").forEach(function (item) {
                item.addEventListener("click", function (event) {
                    event.preventDefault();
                    image.src = item.getAttribute("href");
                    image.alt = item.querySelector("img").alt;
                    caption.textContent = item.getAttribute("data-caption");
                    dialog.showModal();
                });
            });
            dialog.querySelector(".lightbox-close").addEventListener("click", function () { dialog.close(); });
            dialog.addEventListener("click", function (event) { if (event.target === dialog) dialog.close(); });
        }());
    ';

    return mcg_style_shell($style, 'Gallery — MCG Biokinetics', $intro, $main, false, $extraHead, $extraJs);
}

function mcg_publish_gallery(): array
{
    $data = mcg_gallery_data();
    $results = [];
    foreach (array_keys(mcg_style_names()) as $style) {
        if (!is_dir(MCG_ROOT . DIRECTORY_SEPARATOR . $style)) {
            continue;
        }
        $html = mcg_render_gallery_page($style, $data['categories'], $data['photos']);
        $result = mcg_write_site_file($style . '/Gallery.html', $html);
        if (!$result['ok']) {
            $results[] = $style . ': ' . $result['error'];
        }
    }
    return $results;
}
