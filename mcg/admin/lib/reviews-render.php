<?php
/**
 * Publish approved client reviews as a public Reviews page in each concept.
 */

declare(strict_types=1);

require_once __DIR__ . '/page-render.php';

function mcg_render_reviews_page(string $style, array $reviews): string
{
    $cards = '';
    foreach ($reviews as $review) {
        if (empty($review['approved'])) {
            continue;
        }
        $rating = max(1, min(5, (int) ($review['rating'] ?? 5)));
        $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
        $name = (string) ($review['name'] ?? 'Client');
        $initial = strtoupper(mcg_substr($name, 1));
        $cards .= '
                    <figure class="review-card">
                        <span class="review-mark" aria-hidden="true">“</span>
                        <span class="review-stars" aria-label="' . $rating . ' out of 5">' . $stars . '</span>
                        <blockquote>' . nl2br(mcg_e((string) ($review['quote'] ?? '')), false) . '</blockquote>
                        <figcaption>
                            <span class="review-avatar" aria-hidden="true">' . mcg_e($initial) . '</span>
                            <span><strong>' . mcg_e($name) . '</strong><span>' . mcg_e((string) ($review['role'] ?? '')) . '</span></span>
                        </figcaption>
                    </figure>';
    }
    if ($cards === '') {
        $cards = '<p class="reviews-empty">Patient stories will appear here as they are approved.</p>';
    }

    $main = '
        <section class="page-hero">
            <div class="girih page-girih" aria-hidden="true"></div>
            <div class="wrap page-hero-inner">
                <nav class="breadcrumb" aria-label="Breadcrumb"><a href="index.html">Home</a><span aria-hidden="true">/</span><span aria-current="page">Reviews</span></nav>
                <div class="page-hero-main">
                    <div class="page-hero-copy">
                        <p class="eyebrow">Patient stories</p>
                        <h1>In their words</h1>
                        <p class="page-hero-lede">The reason we do this work — in the words of the people we are privileged to treat.</p>
                    </div>
                    <div class="page-hero-side" aria-hidden="true">
                        <span class="page-no">14</span>
                        <span class="page-star"><svg class="ico"><use href="#i-star"></use></svg></span>
                        <span class="page-label">MCG · Parktown North</span>
                    </div>
                </div>
                <div class="page-hero-meta"><span>Patient stories</span><span>' . count($reviews) . ' approved</span><span>MCG Biokinetics</span></div>
            </div>
        </section>
        <section class="section page-body">
            <div class="wrap">
                <div class="reviews-grid">' . $cards . '
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
        </section>';

    $extraHead = '<style>
        .reviews-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.2rem}
        .review-card{position:relative;margin:0;padding:2.1rem 1.7rem 1.7rem;border:1px solid var(--pg-line);background:var(--pg-card-bg);color:var(--pg-ink)}
        .review-mark{font-family:var(--font-serif);font-size:3.8rem;line-height:.6;color:var(--pg-accent);opacity:.45;display:block;margin-bottom:.6rem}
        .review-stars{display:block;margin-bottom:1rem;color:#dc9a2e;letter-spacing:.12em;font-size:.85rem}
        .review-card blockquote{margin:0 0 1.6rem;font-family:var(--font-serif);font-size:1.28rem;line-height:1.55;color:var(--pg-ink)}
        .review-card figcaption{display:flex;align-items:center;gap:.8rem;padding-top:1.1rem;border-top:1px solid var(--pg-line);font-size:.62rem;letter-spacing:.13em;text-transform:uppercase}
        .review-card figcaption strong{display:block;color:var(--pg-accent);font-size:.65rem}
        .review-card figcaption span span{display:block;margin-top:.15rem;color:var(--pg-muted);font-size:.6rem;text-transform:none;letter-spacing:.04em}
        .review-avatar{display:grid;place-items:center;width:38px;height:38px;border-radius:50%;background:var(--pg-accent);color:var(--pg-accent-ink);font-family:var(--font-serif);font-size:1rem;flex:none}
        .reviews-empty{grid-column:1/-1;color:var(--pg-muted);padding:2rem 0}
        @media(max-width:900px){.reviews-grid{grid-template-columns:1fr}}
    </style>';

    return mcg_style_shell($style, 'Reviews — MCG Biokinetics', 'Patient stories from MCG Biokinetics.', $main, false, $extraHead);
}

function mcg_publish_reviews(): array
{
    $all = mcg_read_data('reviews', []);
    $approved = [];
    foreach ($all as $review) {
        if (!empty($review['approved'])) {
            $approved[] = $review;
        }
    }
    usort($approved, function ($a, $b) {
        return ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0));
    });

    $errors = [];
    foreach (array_keys(mcg_style_names()) as $style) {
        if (!is_dir(MCG_ROOT . DIRECTORY_SEPARATOR . $style)) {
            continue;
        }
        $result = mcg_write_site_file($style . '/Reviews.html', mcg_render_reviews_page($style, $approved));
        if (!$result['ok']) {
            $errors[] = $style . ': ' . $result['error'];
        }
    }
    return $errors;
}
