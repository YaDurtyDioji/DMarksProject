<?php
/**
 * Gallery manager: categories, uploads, captions and viewing order.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/gallery-render.php';
mcg_require_auth();

$gallery = mcg_read_data('gallery', ['categories' => [], 'photos' => []]);
$categories = isset($gallery['categories']) && is_array($gallery['categories']) ? $gallery['categories'] : [];
$photos = isset($gallery['photos']) && is_array($gallery['photos']) ? $gallery['photos'] : [];

function mcg_gallery_save_all(array $categories, array $photos): void
{
    mcg_write_data('gallery', ['categories' => array_values($categories), 'photos' => array_values($photos)]);
}

function mcg_gallery_publish_feedback(): void
{
    $errors = mcg_publish_gallery();
    if ($errors) {
        mcg_flash('error', 'Gallery saved, but a public page could not be written: ' . implode(' · ', $errors));
    } else {
        mcg_flash('success', 'Gallery saved and the three public Gallery pages were refreshed.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mcg_require_post();
    mcg_check_csrf();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    $changed = false;

    if ($action === 'add_category') {
        $name = mcg_clean_text($_POST['name'] ?? '', 80);
        if ($name === '') {
            mcg_flash('error', 'Give the category a name.');
        } else {
            $maxOrder = 0;
            foreach ($categories as $category) {
                $maxOrder = max($maxOrder, (int) ($category['order'] ?? 0));
            }
            $categories[] = [
                'id' => mcg_next_id($categories),
                'name' => $name,
                'order' => $maxOrder + 10,
            ];
            $changed = true;
        }
    } elseif ($action === 'rename_category') {
        $categoryId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name = mcg_clean_text($_POST['name'] ?? '', 80);
        $categoryOrder = isset($_POST['order']) ? (int) $_POST['order'] : 0;
        foreach ($categories as $index => $category) {
            if ((int) $category['id'] === $categoryId && $name !== '') {
                $categories[$index]['name'] = $name;
                $categories[$index]['order'] = $categoryOrder;
                $changed = true;
            }
        }
    } elseif ($action === 'delete_category') {
        $categoryId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $remaining = [];
        foreach ($categories as $category) {
            if ((int) $category['id'] !== $categoryId) {
                $remaining[] = $category;
            }
        }
        if (count($remaining) !== count($categories)) {
            $categories = $remaining;
            foreach ($photos as $index => $photo) {
                if ((int) ($photo['category_id'] ?? 0) === $categoryId) {
                    $photos[$index]['category_id'] = 0;
                }
            }
            $changed = true;
        }
    } elseif ($action === 'upload_photos') {
        $categoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
        $files = isset($_FILES['photos']) && is_array($_FILES['photos']) ? $_FILES['photos'] : null;
        $maxOrder = 0;
        foreach ($photos as $photo) {
            $maxOrder = max($maxOrder, (int) ($photo['order'] ?? 0));
        }
        $uploadedCount = 0;
        if ($files && isset($files['name']) && is_array($files['name'])) {
            $count = min(12, count($files['name']));
            for ($i = 0; $i < $count; $i++) {
                $error = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                if ((int) $error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $file = [
                    'name' => $files['name'][$i] ?? '',
                    'type' => $files['type'][$i] ?? '',
                    'tmp_name' => $files['tmp_name'][$i] ?? '',
                    'error' => $error,
                    'size' => $files['size'][$i] ?? 0,
                ];
                $result = mcg_handle_image_upload($file, 'gallery');
                if (!$result['ok']) {
                    mcg_flash('error', ($file['name'] ?: 'Image') . ': ' . $result['error']);
                    continue;
                }
                $baseCaption = pathinfo((string) $file['name'], PATHINFO_FILENAME);
                $baseCaption = str_replace(['-', '_'], ' ', $baseCaption);
                $baseCaption = mcg_clean_text($baseCaption, 120);
                $maxOrder += 10;
                $photos[] = [
                    'id' => mcg_next_id($photos),
                    'path' => $result['path'],
                    'alt' => ($baseCaption !== '' ? $baseCaption : 'MCG Biokinetics') . ' at MCG Biokinetics',
                    'caption' => $baseCaption !== '' ? $baseCaption : 'MCG Biokinetics',
                    'category_id' => $categoryId,
                    'order' => $maxOrder,
                    'created' => mcg_now(),
                ];
                $uploadedCount++;
            }
        }
        if ($uploadedCount > 0) {
            $changed = true;
            mcg_flash('success', $uploadedCount . ' photo(s) uploaded, optimised and re-encoded.');
        } elseif (!$files) {
            mcg_flash('error', 'Choose at least one image to upload.');
        }
    } elseif ($action === 'save_photo') {
        $photoId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $caption = mcg_clean_text($_POST['caption'] ?? '', 160);
        $alt = mcg_clean_text($_POST['alt'] ?? '', 220);
        $categoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
        $order = isset($_POST['order']) ? (int) $_POST['order'] : 0;
        foreach ($photos as $index => $photo) {
            if ((int) $photo['id'] === $photoId) {
                $photos[$index]['caption'] = $caption;
                $photos[$index]['alt'] = $alt;
                $photos[$index]['category_id'] = $categoryId;
                $photos[$index]['order'] = $order;
                $changed = true;
                break;
            }
        }
    } elseif ($action === 'move_up' || $action === 'move_down') {
        $photoId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $target = null;
        foreach ($photos as $index => $photo) {
            if ((int) $photo['id'] === $photoId) {
                $target = $index;
                break;
            }
        }
        if ($target !== null) {
            $categoryId = (int) ($photos[$target]['category_id'] ?? 0);
            $group = [];
            foreach ($photos as $index => $photo) {
                if ((int) ($photo['category_id'] ?? 0) === $categoryId) {
                    $group[] = $index;
                }
            }
            usort($group, function ($a, $b) use ($photos) {
                return ((int) ($photos[$a]['order'] ?? 0)) <=> ((int) ($photos[$b]['order'] ?? 0));
            });
            $position = array_search($target, $group, true);
            $swapWith = null;
            if ($action === 'move_up' && $position !== false && $position > 0) {
                $swapWith = $group[$position - 1];
            } elseif ($action === 'move_down' && $position !== false && $position < count($group) - 1) {
                $swapWith = $group[$position + 1];
            }
            if ($swapWith !== null) {
                $temp = $photos[$target];
                $photos[$target] = $photos[$swapWith];
                $photos[$swapWith] = $temp;
                $counter = 10;
                foreach ($group as $index) {
                    $photos[$index]['order'] = $counter;
                    $counter += 10;
                }
                $changed = true;
            }
        }
    } elseif ($action === 'delete_photo') {
        $photoId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $remaining = [];
        foreach ($photos as $photo) {
            if ((int) $photo['id'] === $photoId) {
                if (!empty($photo['path'])) {
                    mcg_delete_media((string) $photo['path']);
                }
            } else {
                $remaining[] = $photo;
            }
        }
        if (count($remaining) !== count($photos)) {
            $photos = $remaining;
            $changed = true;
        }
    } elseif ($action === 'publish') {
        $errors = mcg_publish_gallery();
        if ($errors) {
            mcg_flash('error', 'Could not publish: ' . implode(' · ', $errors));
        } else {
            mcg_flash('success', 'All three Gallery pages were republished.');
        }
    }

    if ($changed) {
        mcg_gallery_save_all($categories, $photos);
        mcg_gallery_publish_feedback();
    }
    mcg_redirect('gallery.php');
}

$data = mcg_gallery_data();
$categories = $data['categories'];
$photos = $data['photos'];
$counts = [];
foreach ($photos as $photo) {
    $cat = (int) ($photo['category_id'] ?? 0);
    $counts[$cat] = ($counts[$cat] ?? 0) + 1;
}
$maxCategoryOrder = 0;
foreach ($categories as $category) {
    $maxCategoryOrder = max($maxCategoryOrder, (int) ($category['order'] ?? 0));
}

mcg_admin_header('Gallery', 'gallery', 'Upload, categorise, caption and reorder the photographs used across the public Gallery pages.');
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Photography</p>
        <h2>Gallery</h2>
        <p>Photos are re-encoded on upload to strip metadata and payloads. Saving here republishes <span class="mono">Gallery.html</span> in all three concepts.</p>
    </div>
    <div class="page-actions">
        <form method="post" action="gallery.php">
            <?= mcg_csrf_field() ?>
            <input type="hidden" name="action" value="publish">
            <button class="btn btn-secondary" type="submit"><?= mcg_icon('upload') ?> Republish gallery</button>
        </form>
    </div>
</div>

<div class="grid-3" style="margin-bottom: 18px;">
    <?php foreach (mcg_style_names() as $style => $label): ?>
        <a class="quick-card" href="../<?= mcg_e($style) ?>/Gallery.html" target="_blank" rel="noopener">
            <?= mcg_icon('gallery') ?>
            <span><?= mcg_e($label) ?></span>
            <?= mcg_icon('external', 'icon arrow') ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="split-2">
    <section class="panel">
        <div class="panel-head">
            <div>
                <h3>Photographs</h3>
                <p><?= count($photos) ?> photo(s). Lower order values appear first within a category.</p>
            </div>
        </div>
        <div class="panel-body">
            <?php if (!$photos): ?>
                <div class="table-empty">
                    <?= mcg_icon('gallery') ?>
                    <p>No photographs yet. Upload the first images on the right.</p>
                </div>
            <?php else: ?>
                <div class="photo-grid">
                    <?php foreach ($photos as $photo): ?>
                        <form class="photo-card" method="post" action="gallery.php">
                            <?= mcg_csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $photo['id'] ?>">
                            <img src="<?= mcg_e(mcg_media_url((string) ($photo['path'] ?? ''))) ?>" alt="" loading="lazy">
                            <div class="photo-card-body">
                                <div class="field">
                                    <label>Caption</label>
                                    <input type="text" name="caption" value="<?= mcg_e($photo['caption'] ?? '') ?>" maxlength="160">
                                </div>
                                <div class="field">
                                    <label>Alt text</label>
                                    <input type="text" name="alt" value="<?= mcg_e($photo['alt'] ?? '') ?>" maxlength="220">
                                </div>
                                <div class="range-row">
                                    <div class="field">
                                        <label>Category</label>
                                        <select name="category_id">
                                            <option value="0">Uncategorised</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= (int) $category['id'] ?>" <?= (int) ($photo['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= mcg_e($category['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label>Order</label>
                                        <input type="number" name="order" value="<?= (int) ($photo['order'] ?? 0) ?>" step="10">
                                    </div>
                                </div>
                                <div class="button-row" style="justify-content: space-between;">
                                    <button class="btn btn-primary btn-sm" type="submit" name="action" value="save_photo"><?= mcg_icon('check') ?> Save</button>
                                    <div class="button-row">
                                        <button class="icon-button" type="submit" name="action" value="move_up" aria-label="Move up" title="Move up"><?= mcg_icon('up') ?></button>
                                        <button class="icon-button" type="submit" name="action" value="move_down" aria-label="Move down" title="Move down"><?= mcg_icon('down') ?></button>
                                        <button class="btn btn-danger btn-sm" type="submit" name="action" value="delete_photo" data-confirm="Delete this photograph from the gallery?" title="Delete"><?= mcg_icon('trash') ?></button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="stack">
        <section class="panel">
            <div class="panel-head">
                <div>
                    <h3>Upload photos</h3>
                    <p>JPEG, PNG or WebP · max 8 MB each · up to 12 at a time.</p>
                </div>
            </div>
            <div class="panel-body">
                <form method="post" action="gallery.php" enctype="multipart/form-data">
                    <?= mcg_csrf_field() ?>
                    <input type="hidden" name="action" value="upload_photos">
                    <div class="upload-box">
                        <?= mcg_icon('image') ?>
                        <strong>Choose photographs</strong>
                        <p>Images are decoded, resized to a 2400px maximum, re-encoded as JPEG and stored in <span class="mono">admin/uploads</span>.</p>
                        <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
                    </div>
                    <div class="field" style="margin-top: 12px;">
                        <label for="uploadCategory">Category</label>
                        <select id="uploadCategory" name="category_id">
                            <option value="0">Uncategorised</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>"><?= mcg_e($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary btn-block" type="submit"><?= mcg_icon('upload') ?> Upload photographs</button>
                </form>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h3>Categories</h3>
                    <p>Categories become filters on the public Gallery pages.</p>
                </div>
            </div>
            <div class="panel-body">
                <form method="post" action="gallery.php" class="button-row" style="margin-bottom: 14px;">
                    <?= mcg_csrf_field() ?>
                    <input type="hidden" name="action" value="add_category">
                    <input class="input" type="text" name="name" placeholder="New category" maxlength="80" required style="flex: 1;">
                    <button class="btn btn-primary" type="submit"><?= mcg_icon('plus') ?> Add</button>
                </form>
                <div class="category-list">
                    <?php foreach ($categories as $category): ?>
                        <div class="category-row">
                            <form method="post" action="gallery.php">
                                <?= mcg_csrf_field() ?>
                                <input type="hidden" name="action" value="rename_category">
                                <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                <input type="text" name="name" value="<?= mcg_e($category['name']) ?>" maxlength="80" aria-label="Category name">
                                <input class="category-order" type="number" name="order" value="<?= (int) ($category['order'] ?? 0) ?>" step="10" aria-label="Category order" title="Category order">
                                <button class="icon-button" type="submit" title="Save name and order"><?= mcg_icon('check') ?></button>
                            </form>
                            <span class="category-count"><?= (int) ($counts[(int) $category['id']] ?? 0) ?> photos</span>
                            <form method="post" action="gallery.php" data-confirm="Delete this category? Its photos will become uncategorised.">
                                <?= mcg_csrf_field() ?>
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                <button class="icon-button" type="submit" title="Delete category"><?= mcg_icon('trash') ?></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$categories): ?>
                        <p class="small muted">No categories yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="notice">
            <p><strong>Virus safety.</strong> Only genuine JPEG/PNG/WebP files pass validation. Every upload is re-encoded through GD, so executable payloads, scripts and metadata are not written to the server. Executable extensions are denied in <span class="mono">admin/uploads/.htaccess</span>.</p>
        </section>
    </div>
</div>

<?php mcg_admin_footer(); ?>
