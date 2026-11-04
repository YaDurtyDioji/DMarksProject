<?php
/**
 * Page manager: list, preview and delete generated pages.
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mcg_require_post();
    mcg_check_csrf();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($action === 'delete' && $id > 0) {
        $remaining = [];
        $deleted = null;
        foreach ($pages as $page) {
            if ((int) $page['id'] === $id) {
                $deleted = $page;
            } else {
                $remaining[] = $page;
            }
        }
        if ($deleted) {
            mcg_delete_page_file($deleted);
            mcg_write_data('pages', $remaining);
            mcg_flash('success', 'Page “' . ($deleted['title'] ?? 'Untitled') . '” was deleted.');
        } else {
            mcg_flash('error', 'That page could not be found.');
        }
        mcg_redirect('pages.php');
    }
}

usort($pages, function ($a, $b) {
    return strcmp((string) ($b['updated'] ?? ''), (string) ($a['updated'] ?? ''));
});
$styles = mcg_style_names();

mcg_admin_header('Pages', 'pages', 'Create new HTML pages, edit their content and publish them into a concept folder.');
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Content</p>
        <h2>Pages</h2>
        <p>Each page is written as a real <span class="mono">.html</span> file inside the selected design concept, reusing that concept’s header, footer and styles.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="page-edit.php"><?= mcg_icon('plus') ?> New page</a>
    </div>
</div>

<section class="panel">
    <div class="panel-head">
        <div>
            <h3>All generated pages</h3>
            <p><?= count($pages) ?> page<?= count($pages) === 1 ? '' : 's' ?> managed by the portal.</p>
        </div>
    </div>
    <div class="panel-body flush">
        <?php if (!$pages): ?>
            <div class="table-empty">
                <?= mcg_icon('pages') ?>
                <p>No pages created yet.</p>
                <a class="btn btn-primary btn-sm" href="page-edit.php"><?= mcg_icon('plus') ?> Create the first page</a>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Page</th>
                            <th>Concept</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pages as $page): ?>
                            <tr>
                                <td>
                                    <div class="cell-title">
                                        <strong><?= mcg_e($page['title'] ?? 'Untitled') ?></strong>
                                        <span class="mono"><?= mcg_e($page['file'] ?? '') ?></span>
                                    </div>
                                </td>
                                <td><span class="style-chip"><?= mcg_e($styles[$page['style'] ?? 'Style1'] ?? 'Style 1') ?></span></td>
                                <td><?= mcg_status_pill((string) ($page['status'] ?? 'draft')) ?></td>
                                <td class="small muted"><?= mcg_e(mcg_format_date($page['updated'] ?? '')) ?></td>
                                <td>
                                    <div class="row-actions">
                                        <?php if (!empty($page['file']) && ($page['status'] ?? '') === 'published'): ?>
                                            <a class="btn btn-secondary btn-sm" href="../<?= mcg_e($page['file']) ?>" target="_blank" rel="noopener"><?= mcg_icon('external') ?> View</a>
                                        <?php endif; ?>
                                        <a class="btn btn-secondary btn-sm" href="preview.php?id=<?= (int) $page['id'] ?>" target="_blank" rel="noopener"><?= mcg_icon('eye') ?> Preview</a>
                                        <a class="btn btn-secondary btn-sm" href="page-edit.php?id=<?= (int) $page['id'] ?>"><?= mcg_icon('edit') ?> Edit</a>
                                        <form method="post" action="pages.php" data-confirm="Delete this page and its generated HTML file?">
                                            <?= mcg_csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $page['id'] ?>">
                                            <button class="btn btn-danger btn-sm" type="submit"><?= mcg_icon('trash') ?> Delete</button>
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

<section class="notice" style="margin-top: 18px;">
    <p><strong>Where do files go?</strong> A page published to <span class="mono">Atelier</span> is saved as <span class="mono">Style1/your-slug.html</span>; Emerald uses <span class="mono">Style2/</span> and Kinetic uses <span class="mono">Style3/</span>. Existing core pages cannot be overwritten.</p>
</section>

<?php mcg_admin_footer(); ?>
