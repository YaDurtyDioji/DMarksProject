<?php
/**
 * Create or edit a generated HTML page.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/page-render.php';
mcg_require_auth();

$pages = mcg_read_data('pages', []);
if (!is_array($pages)) {
    $pages = [];
}
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$existing = null;
foreach ($pages as $item) {
    if ((int) $item['id'] === $id) {
        $existing = $item;
        break;
    }
}

$styles = mcg_style_names();
$errors = [];
$page = $existing ?: [
    'id' => 0,
    'title' => '',
    'slug' => '',
    'style' => 'Style1',
    'description' => '',
    'lead' => '',
    'status' => 'draft',
    'blocks' => [['type' => 'text', 'text' => '', 'image' => '', 'alt' => '']],
];

function mcg_block_card($index, array $block): string
{
    $type = isset($block['type']) ? (string) $block['type'] : 'text';
    $text = isset($block['text']) ? (string) $block['text'] : '';
    $image = isset($block['image']) ? (string) $block['image'] : '';
    $alt = isset($block['alt']) ? (string) $block['alt'] : '';
    $hasImage = $image !== '' && strpos($image, 'admin/uploads/') === 0;
    $types = [
        'text' => 'Paragraph',
        'heading' => 'Heading',
        'image' => 'Image',
        'quote' => 'Quote',
        'list' => 'List',
    ];
    $options = '';
    foreach ($types as $value => $label) {
        $options .= '<option value="' . mcg_e($value) . '"' . ($type === $value ? ' selected' : '') . '>' . mcg_e($label) . '</option>';
    }
    $textPlaceholder = $type === 'heading' ? 'Heading text' : ($type === 'list' ? "One item per line\nSecond item" : 'Write the content…');
    $thumb = $hasImage
        ? '<img class="block-thumb" src="' . mcg_e(mcg_media_url($image)) . '" alt="">'
        : '<img class="block-thumb" alt="" hidden>';
    return '
        <div class="block-card" data-type="' . mcg_e($type) . '">
            <div class="block-head">
                <span class="block-handle" aria-hidden="true">⋮⋮</span>
                <select class="block-type" name="block_type[]" aria-label="Block type">' . $options . '</select>
                <div class="block-actions">
                    <button class="icon-button" type="button" data-move-up aria-label="Move block up">' . mcg_icon('up') . '</button>
                    <button class="icon-button" type="button" data-move-down aria-label="Move block down">' . mcg_icon('down') . '</button>
                    <button class="icon-button" type="button" data-remove-block aria-label="Remove block">' . mcg_icon('x') . '</button>
                </div>
            </div>
            <div class="block-body">
                <div class="block-text-field"' . ($type === 'image' ? ' hidden' : '') . '>
                    <textarea name="block_text[]" placeholder="' . mcg_e($textPlaceholder) . '">' . mcg_e($text) . '</textarea>
                </div>
                <div class="block-image-field"' . ($type !== 'image' ? ' hidden' : '') . '>
                    <div class="block-image-row">
                        ' . $thumb . '
                        <div>
                            <input class="block-file" type="file" name="block_image[]" accept="image/jpeg,image/png,image/webp">
                            <p class="block-hint">JPEG, PNG or WebP · re-encoded to strip metadata and payloads · max 8 MB</p>
                        </div>
                    </div>
                    <input type="text" name="block_alt[]" value="' . mcg_e($alt) . '" placeholder="Image description / caption (optional)">
                    <input type="hidden" name="block_image_existing[]" value="' . mcg_e($image) . '">
                </div>
            </div>
        </div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mcg_require_post();
    mcg_check_csrf();

    $title = mcg_clean_text($_POST['title'] ?? '', 120);
    $slugInput = mcg_clean_text($_POST['slug'] ?? '', 100);
    $style = isset($_POST['style']) ? (string) $_POST['style'] : 'Style1';
    $status = isset($_POST['status']) && $_POST['status'] === 'published' ? 'published' : 'draft';
    $description = mcg_clean_text($_POST['description'] ?? '', 300);
    $lead = mcg_clean_text($_POST['lead'] ?? '', 300);
    $slug = mcg_slug($slugInput !== '' ? $slugInput : $title);
    $slug = mcg_substr($slug, 80);
    $slug = trim($slug, '-');

    if ($title === '') {
        $errors['title'] = 'Give the page a title.';
    }
    if (!isset($styles[$style])) {
        $errors['style'] = 'Choose a valid design concept.';
        $style = 'Style1';
    }
    if (!preg_match('/^[a-z0-9-]{2,80}$/', $slug)) {
        $errors['slug'] = 'Use a slug of 2–80 lowercase letters, numbers or hyphens.';
    }

    $types = isset($_POST['block_type']) && is_array($_POST['block_type']) ? $_POST['block_type'] : [];
    $texts = isset($_POST['block_text']) && is_array($_POST['block_text']) ? $_POST['block_text'] : [];
    $alts = isset($_POST['block_alt']) && is_array($_POST['block_alt']) ? $_POST['block_alt'] : [];
    $existingImages = isset($_POST['block_image_existing']) && is_array($_POST['block_image_existing']) ? $_POST['block_image_existing'] : [];
    $uploaded = isset($_FILES['block_image']) && is_array($_FILES['block_image']) ? $_FILES['block_image'] : null;

    $blocks = [];
    $allowedTypes = ['text', 'heading', 'image', 'quote', 'list'];
    foreach ($types as $index => $typeValue) {
        if (count($blocks) >= 40) {
            break;
        }
        $type = in_array($typeValue, $allowedTypes, true) ? (string) $typeValue : 'text';
        $text = mcg_clean_multiline($texts[$index] ?? '', 8000);
        $alt = mcg_clean_text($alts[$index] ?? '', 180);
        $image = '';

        if ($type === 'image') {
            $hasUpload = $uploaded !== null
                && isset($uploaded['error'][$index])
                && is_numeric($uploaded['error'][$index])
                && (int) $uploaded['error'][$index] !== UPLOAD_ERR_NO_FILE;

            if ($hasUpload) {
                $file = [
                    'name' => $uploaded['name'][$index] ?? '',
                    'type' => $uploaded['type'][$index] ?? '',
                    'tmp_name' => $uploaded['tmp_name'][$index] ?? '',
                    'error' => $uploaded['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $uploaded['size'][$index] ?? 0,
                ];
                $result = mcg_handle_image_upload($file, 'page');
                if (!$result['ok']) {
                    $errors['blocks'] = $result['error'];
                } else {
                    $image = $result['path'];
                }
            } else {
                $candidate = isset($existingImages[$index]) ? str_replace('\\', '/', (string) $existingImages[$index]) : '';
                if ($candidate !== '' && strpos($candidate, 'admin/uploads/') === 0 && is_file(MCG_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate))) {
                    $image = $candidate;
                }
            }
            if ($image === '') {
                continue;
            }
            $blocks[] = ['type' => 'image', 'text' => '', 'image' => $image, 'alt' => $alt];
        } else {
            if ($text === '') {
                continue;
            }
            $blocks[] = ['type' => $type, 'text' => $text, 'image' => '', 'alt' => ''];
        }
    }

    $file = $style . '/' . $slug . '.html';
    $oldFile = $existing && !empty($existing['file']) ? (string) $existing['file'] : '';
    if (mcg_style_file_exists($style, $slug, $oldFile)) {
        $errors['slug'] = 'A page already exists at ' . $file . '. Choose another slug.';
    }
    foreach ($pages as $other) {
        if ((int) $other['id'] !== $id && (string) ($other['file'] ?? '') === $file) {
            $errors['slug'] = 'Another managed page already uses that file.';
        }
    }

    $page = [
        'id' => $id,
        'title' => $title,
        'slug' => $slug,
        'style' => $style,
        'description' => $description,
        'lead' => $lead,
        'status' => $status,
        'blocks' => $blocks,
        'created' => $existing['created'] ?? mcg_now(),
        'updated' => mcg_now(),
        'file' => $file,
    ];

    if (!$errors) {
        if ($status === 'published') {
            $published = mcg_publish_page($page);
            if (!$published['ok']) {
                $errors['general'] = $published['error'];
            }
        } else {
            /* Drafts are not public. Remove a previously generated file if it exists. */
            if ($oldFile !== '' && $oldFile !== $file) {
                mcg_delete_page_file(['file' => $oldFile]);
            } elseif ($oldFile !== '') {
                mcg_delete_page_file(['file' => $oldFile]);
            }
        }

        if (!$errors) {
            if ($existing) {
                foreach ($pages as $index => $item) {
                    if ((int) $item['id'] === $id) {
                        $pages[$index] = $page;
                        break;
                    }
                }
            } else {
                $page['id'] = mcg_next_id($pages);
                $pages[] = $page;
            }
            mcg_write_data('pages', $pages);
            if ($status === 'published' && $oldFile !== '' && $oldFile !== $file) {
                mcg_delete_page_file(['file' => $oldFile]);
            }
            mcg_flash('success', $status === 'published' ? 'Page published to ' . $file . '.' : 'Draft saved.');
            mcg_redirect('page-edit.php?id=' . (int) $page['id']);
        }
    }
}

$pageId = isset($page['id']) ? (int) $page['id'] : 0;
$isPublished = ($page['status'] ?? 'draft') === 'published';
$blocks = isset($page['blocks']) && is_array($page['blocks']) ? $page['blocks'] : [];
if (!$blocks) {
    $blocks = [['type' => 'text', 'text' => '', 'image' => '', 'alt' => '']];
}
$file = isset($page['file']) && $page['file'] !== '' ? (string) $page['file'] : mcg_page_file_path($page);

mcg_admin_header($existing ? 'Edit page' : 'New page', 'pages', 'Build the content, choose a concept and publish a real HTML file.');
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Pages</p>
        <h2><?= $existing ? 'Edit page' : 'Create a page' ?></h2>
        <p><?= $existing ? 'Update the content and republish.' : 'Add text and images, then publish into one of the three concepts.' ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="pages.php"><?= mcg_icon('arrow-right') ?> Back to pages</a>
        <?php if ($pageId > 0): ?>
            <a class="btn btn-secondary" href="preview.php?id=<?= $pageId ?>" target="_blank" rel="noopener"><?= mcg_icon('eye') ?> Preview</a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($errors['general'])): ?>
    <div class="notice notice-danger" style="margin-bottom: 16px;"><?= mcg_e($errors['general']) ?></div>
<?php endif; ?>

<form method="post" action="page-edit.php<?= $pageId ? '?id=' . $pageId : '' ?>" enctype="multipart/form-data">
    <?= mcg_csrf_field() ?>
    <input type="hidden" name="id" value="<?= $pageId ?>">

    <div class="split-2">
        <div class="stack">
            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h3>Page content</h3>
                        <p>The blocks below become the body of the page.</p>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="field">
                        <label for="title">Page title</label>
                        <input type="text" id="title" name="title" value="<?= mcg_e($page['title'] ?? '') ?>" required maxlength="120" placeholder="e.g. Running assessments">
                        <?= mcg_field_error($errors, 'title') ?>
                    </div>

                    <hr>

                    <div class="button-row" style="justify-content: space-between; margin-bottom: 10px;">
                        <span class="field-label">Content blocks</span>
                        <button class="add-block" type="button" data-add-block><?= mcg_icon('plus') ?> Add block</button>
                    </div>
                    <div class="block-list" data-block-editor data-next-index="<?= count($blocks) ?>">
                        <?php foreach ($blocks as $index => $block): ?>
                            <?= mcg_block_card($index, $block) ?>
                        <?php endforeach; ?>
                    </div>
                    <?= mcg_field_error($errors, 'blocks') ?>
                    <p class="field-help" style="margin-top: 10px;">Paragraphs preserve line breaks. Lists render one item per line. Images are re-encoded to JPEG to remove metadata and any embedded payloads.</p>
                </div>
            </section>
        </div>

        <div class="stack">
            <section class="panel">
                <div class="panel-head">
                    <h3>Publishing</h3>
                </div>
                <div class="panel-body">
                    <div class="field">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="draft" <?= ($page['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft — private in admin only</option>
                            <option value="published" <?= ($page['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published — write HTML file</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="style">Design concept</label>
                        <select id="style" name="style">
                            <?php foreach ($styles as $value => $label): ?>
                                <option value="<?= mcg_e($value) ?>" <?= ($page['style'] ?? 'Style1') === $value ? 'selected' : '' ?>><?= mcg_e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= mcg_field_error($errors, 'style') ?>
                    </div>
                    <div class="field">
                        <label for="slug">File slug</label>
                        <input type="text" id="slug" name="slug" value="<?= mcg_e($page['slug'] ?? '') ?>" placeholder="auto-from-title" maxlength="80" pattern="[a-z0-9-]+">
                        <span class="field-help">Output: <span class="mono"><?= mcg_e($file) ?></span></span>
                        <?= mcg_field_error($errors, 'slug') ?>
                    </div>
                    <div class="field">
                        <label for="lead">Hero introduction</label>
                        <textarea id="lead" name="lead" maxlength="300" style="min-height: 82px;" placeholder="One sentence shown under the page title."><?= mcg_e($page['lead'] ?? '') ?></textarea>
                    </div>
                    <div class="field">
                        <label for="description">Meta description</label>
                        <textarea id="description" name="description" maxlength="300" style="min-height: 70px;" placeholder="Search description (optional)."><?= mcg_e($page['description'] ?? '') ?></textarea>
                    </div>
                    <button class="btn btn-primary btn-block" type="submit"><?= mcg_icon('check') ?> <?= $isPublished ? 'Save &amp; republish' : 'Save page' ?></button>
                    <?php if ($isPublished && $file): ?>
                        <a class="btn btn-secondary btn-block" href="../<?= mcg_e($file) ?>" target="_blank" rel="noopener" style="margin-top: 8px;"><?= mcg_icon('external') ?> Open published page</a>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($existing): ?>
                <section class="panel">
                    <div class="panel-head">
                        <h3>Delete</h3>
                    </div>
                    <div class="panel-body">
                        <p class="small muted">Removing the page also removes its generated HTML file. This cannot be undone.</p>
                        <button class="btn btn-danger btn-block" type="submit" form="deletePageForm"><?= mcg_icon('trash') ?> Delete page</button>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php if ($existing): ?>
    <form id="deletePageForm" method="post" action="pages.php" data-confirm="Delete “<?= mcg_e($page['title'] ?? '') ?>” and its HTML file?">
        <?= mcg_csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= $pageId ?>">
    </form>
<?php endif; ?>

<template id="blockTemplate"><?= mcg_block_card('__INDEX__', ['type' => 'text', 'text' => '', 'image' => '', 'alt' => '']) ?></template>

<?php mcg_admin_footer(); ?>
