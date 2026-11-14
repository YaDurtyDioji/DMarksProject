<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/Auth.php';
require_super();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if (($_POST['act'] ?? '') === 'create') {
            Auth::createUser($_POST['username'], $_POST['email'], $_POST['password'], $_POST['role'] ?? 'admin');
            flash('User created');
        } elseif (($_POST['act'] ?? '') === 'delete') {
            Auth::deleteUser((int)$_POST['id']); flash('User deleted');
        } elseif (($_POST['act'] ?? '') === 'role') {
            Auth::changeRole((int)$_POST['id'], $_POST['role']); flash('Role changed');
        } elseif (($_POST['act'] ?? '') === 'pwd') {
            Auth::changePassword((int)$_POST['id'], $_POST['password']); flash('Password reset');
        }
    } catch (Exception $e) { flash('Error: '.$e->getMessage()); }
    header('Location: users.php'); exit;
}
render_header('Users');
?>
<h1>User Management</h1>
<details class="panel"><summary>+ Add User</summary>
<form method="post" class="grid">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="act" value="create">
  <input name="username" placeholder="Username" required>
  <input name="email" type="email" placeholder="Email" required>
  <input name="password" type="password" placeholder="Password" required>
  <select name="role"><option value="admin">Admin</option><option value="super_admin">Super Admin</option></select>
  <button>Create</button>
</form></details>
<table class="data">
<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Created</th><th>Actions</th></tr>
<?php foreach (Auth::users() as $u): ?>
<tr>
<td><?= (int)$u['id'] ?></td>
<td><?= h($u['username']) ?></td>
<td><?= h($u['email']) ?></td>
<td>
  <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="role"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
  <select name="role" onchange="this.form.submit()"><option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option><option value="super_admin" <?= $u['role']==='super_admin'?'selected':'' ?>>Super</option></select></form>
</td>
<td><?= h($u['created_at']) ?></td>
<td>
  <form method="post" style="display:inline" onsubmit="return confirm('Delete?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn-danger">Del</button></form>
  <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="pwd"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input name="password" type="password" placeholder="New pwd" style="width:90px"><button class="btn-small">Set</button></form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php render_footer(); ?>