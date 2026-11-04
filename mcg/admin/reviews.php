<?php
/**
 * Client reviews: add, edit, approve, order and delete.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/reviews-render.php';
mcg_require_auth();

function mcg_reviews_publish(): void
{
    $errors = mcg_publish_reviews();
    if ($errors) {
        mcg_flash('error', 'Reviews saved, but a public Reviews page could not be written: ' . implode(' · ', $errors));
    }
}

$reviews = mcg_read_data('reviews', []);
if (!is_array($reviews)) {
    $reviews = [];
}
$editing = null;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
foreach ($reviews as $review) {
    if ((int) $review['id'] === $editId) {
        $editing = $review;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mcg_require_post();
    mcg_check_csrf();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'save') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name = mcg_clean_text($_POST['name'] ?? '', 120);
        $role = mcg_clean_text($_POST['role'] ?? '', 120);
        $quote = mcg_clean_multiline($_POST['quote'] ?? '', 1400);
        $rating = isset($_POST['rating']) ? (int) $_POST['rating'] : 5;
        $rating = max(1, min(5, $rating));
        $approved = isset($_POST['approved']) ? 1 : 0;
        $order = isset($_POST['order']) ? (int) $_POST['order'] : 0;

        if ($name === '' || $quote === '') {
            mcg_flash('error', 'A review needs a client name and the review text.');
        } elseif ($id > 0) {
            foreach ($reviews as $index => $review) {
                if ((int) $review['id'] === $id) {
                    $reviews[$index]['name'] = $name;
                    $reviews[$index]['role'] = $role;
                    $reviews[$index]['quote'] = $quote;
                    $reviews[$index]['rating'] = $rating;
                    $reviews[$index]['approved'] = (bool) $approved;
                    $reviews[$index]['order'] = $order;
                    $reviews[$index]['updated'] = mcg_now();
                    break;
                }
            }
            mcg_write_data('reviews', $reviews);
            mcg_reviews_publish();
            mcg_flash('success', 'Review updated.');
        } else {
            $maxOrder = 0;
            foreach ($reviews as $review) {
                $maxOrder = max($maxOrder, (int) ($review['order'] ?? 0));
            }
            $reviews[] = [
                'id' => mcg_next_id($reviews),
                'name' => $name,
                'role' => $role,
                'quote' => $quote,
                'rating' => $rating,
                'approved' => (bool) $approved,
                'order' => $order > 0 ? $order : $maxOrder + 10,
                'created' => mcg_now(),
            ];
            mcg_write_data('reviews', $reviews);
            mcg_reviews_publish();
            mcg_flash('success', 'Review added.');
        }
        mcg_redirect('reviews.php');
    }

    if ($action === 'publish') {
        $errors = mcg_publish_reviews();
        mcg_flash($errors ? 'error' : 'success', $errors ? 'Could not publish: ' . implode(' · ', $errors) : 'Reviews pages republished.');
        mcg_redirect('reviews.php');
    }

    if ($action === 'delete') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $remaining = [];
        foreach ($reviews as $review) {
            if ((int) $review['id'] !== $id) {
                $remaining[] = $review;
            }
        }
        mcg_write_data('reviews', $remaining);
        mcg_reviews_publish();
        mcg_flash('success', 'Review deleted.');
        mcg_redirect('reviews.php');
    }

    if ($action === 'toggle') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        foreach ($reviews as $index => $review) {
            if ((int) $review['id'] === $id) {
                $reviews[$index]['approved'] = empty($review['approved']);
                break;
            }
        }
        mcg_write_data('reviews', $reviews);
            mcg_reviews_publish();
        mcg_flash('success', 'Review visibility updated.');
        mcg_redirect('reviews.php');
    }
}

usort($reviews, function ($a, $b) {
    $order = ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0));
    if ($order !== 0) {
        return $order;
    }
    return strcmp((string) ($b['created'] ?? ''), (string) ($a['created'] ?? ''));
});
$approvedCount = 0;
foreach ($reviews as $review) {
    if (!empty($review['approved'])) {
        $approvedCount++;
    }
}
$form = $editing ?: ['id' => 0, 'name' => '', 'role' => '', 'quote' => '', 'rating' => 5, 'approved' => true, 'order' => 0];
$maxOrder = 0;
foreach ($reviews as $review) {
    $maxOrder = max($maxOrder, (int) ($review['order'] ?? 0));
}

mcg_admin_header('Reviews', 'reviews', 'Collect patient testimonials, approve them and control their order.');
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Testimonials</p>
        <h2>Client reviews</h2>
        <p><?= count($reviews) ?> reviews · <?= $approvedCount ?> approved. Approved reviews are exposed through a read-only JSON endpoint for the website.</p>
    </div>
    <div class="page-actions">
        <form method="post" action="reviews.php">
            <?= mcg_csrf_field() ?>
            <input type="hidden" name="action" value="publish">
            <button class="btn btn-primary" type="submit"><?= mcg_icon('upload') ?> Publish reviews</button>
        </form>
        <a class="btn btn-secondary" href="api/reviews.php" target="_blank" rel="noopener"><?= mcg_icon('eye') ?> Public JSON</a>
    </div>
</div>

<section class="notice" style="margin-bottom: 18px;">
    <p><strong>Published pages.</strong> Saving a review refreshes
        <a href="../Style1/Reviews.html" target="_blank" rel="noopener">Style1/Reviews.html</a>,
        <a href="../Style2/Reviews.html" target="_blank" rel="noopener">Style2/Reviews.html</a> and
        <a href="../Style3/Reviews.html" target="_blank" rel="noopener">Style3/Reviews.html</a>.
    </p>
</section>

<div class="split-2">
    <section class="panel">
        <div class="panel-head">
            <div>
                <h3>All reviews</h3>
                <p>Ordered by the display order value.</p>
            </div>
        </div>
        <div class="panel-body flush">
            <?php if (!$reviews): ?>
                <div class="table-empty">
                    <?= mcg_icon('reviews') ?>
                    <p>No reviews yet. Add the first testimonial on the right.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Review</th>
                                <th>Rating</th>
                                <th>Order</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $review): ?>
                                <tr>
                                    <td>
                                        <div class="cell-title">
                                            <strong><?= mcg_e($review['name'] ?? '') ?></strong>
                                            <span><?= mcg_e($review['role'] ?? '') ?></span>
                                        </div>
                                    </td>
                                    <td><span class="message-snippet"><?= mcg_e($review['quote'] ?? '') ?></span></td>
                                    <td><?= mcg_stars((int) ($review['rating'] ?? 5)) ?></td>
                                    <td><?= (int) ($review['order'] ?? 0) ?></td>
                                    <td><?= mcg_status_pill(!empty($review['approved']) ? 'published' : 'draft') ?></td>
                                    <td>
                                        <div class="row-actions">
                                            <a class="btn btn-secondary btn-sm" href="reviews.php?edit=<?= (int) $review['id'] ?>"><?= mcg_icon('edit') ?> Edit</a>
                                            <form method="post" action="reviews.php">
                                                <?= mcg_csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
                                                <button class="btn btn-secondary btn-sm" type="submit"><?= mcg_icon(empty($review['approved']) ? 'check' : 'x') ?> <?= empty($review['approved']) ? 'Approve' : 'Hide' ?></button>
                                            </form>
                                            <form method="post" action="reviews.php" data-confirm="Delete this review permanently?">
                                                <?= mcg_csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
                                                <button class="btn btn-danger btn-sm" type="submit"><?= mcg_icon('trash') ?></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h3><?= $editing ? 'Edit review' : 'Add a review' ?></h3>
                <p><?= $editing ? 'Update the testimonial below.' : 'Paste a client testimonial and choose whether it is public.' ?></p>
            </div>
            <?php if ($editing): ?>
                <a class="btn btn-secondary btn-sm" href="reviews.php">Cancel</a>
            <?php endif; ?>
        </div>
        <div class="panel-body">
            <form method="post" action="reviews.php">
                <?= mcg_csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
                <div class="field">
                    <label for="name">Client name / label</label>
                    <input type="text" id="name" name="name" value="<?= mcg_e($form['name']) ?>" maxlength="120" required placeholder="e.g. Patient">
                </div>
                <div class="field">
                    <label for="role">Context</label>
                    <input type="text" id="role" name="role" value="<?= mcg_e($form['role']) ?>" maxlength="120" placeholder="e.g. Of a young patient">
                </div>
                <div class="field">
                    <label for="quote">Review text</label>
                    <textarea id="quote" name="quote" maxlength="1400" required style="min-height: 150px;"><?= mcg_e($form['quote']) ?></textarea>
                </div>
                <div class="range-row">
                    <div class="field">
                        <label for="rating">Rating</label>
                        <select id="rating" name="rating">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?= $i ?>" <?= (int) $form['rating'] === $i ? 'selected' : '' ?>><?= $i ?> star<?= $i === 1 ? '' : 's' ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="order">Display order</label>
                        <input type="number" id="order" name="order" value="<?= (int) ($form['order'] ?: $maxOrder + 10) ?>" step="10">
                    </div>
                </div>
                <label class="check" style="margin: 4px 0 14px;">
                    <input type="checkbox" name="approved" value="1" <?= !empty($form['approved']) ? 'checked' : '' ?>>
                    Approved for public display
                </label>
                <button class="btn btn-primary btn-block" type="submit"><?= mcg_icon('check') ?> <?= $editing ? 'Update review' : 'Add review' ?></button>
            </form>
        </div>
    </section>
</div>

<section class="notice" style="margin-top: 18px;">
    <p><strong>Website integration.</strong> Approved reviews are available at <span class="mono">/admin/api/reviews.php</span> as JSON. They can be fetched by the static pages or by a future reviews section.</p>
</section>

<?php mcg_admin_footer(); ?>
