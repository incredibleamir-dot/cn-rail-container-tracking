<?php $pageTitle = 'Admin - Users'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h4 class="fw-bold mb-0"><i class="fas fa-user-shield me-2 text-primary"></i>User Management</h4><small class="text-muted"><?php echo $totalUsers; ?> total users, <?php echo $activeUsers; ?> active</small></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal" style="border-radius:10px;"><i class="fas fa-user-plus me-1"></i>Add User</button>
</div>

<div class="card-modern p-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>ID</th><th>Name</th><th>PIN</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?php echo $u['id']; ?></td>
                    <td class="fw-bold"><?php echo htmlspecialchars($u['name']); ?></td>
                    <td class="font-mono"><span class="pin-display" id="pin-<?php echo $u['id']; ?>">****</span><button class="btn btn-sm btn-link p-0 ms-1" onclick="togglePin(<?php echo $u['id']; ?>,'<?php echo htmlspecialchars($u['pin']); ?>')" title="Show/Hide PIN"><i class="fas fa-eye"></i></button></td>
                    <td><span class="badge <?php echo $u['role'] === 'admin' ? 'bg-danger' : 'bg-secondary'; ?>"><?php echo $u['role']; ?></span></td>
                    <td><span class="badge <?php echo $u['is_active'] ? 'bg-success' : 'bg-warning text-dark'; ?>"><?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                    <td class="small text-muted"><?php echo $u['created_at']; ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-primary" onclick="openEditUserModal(<?php echo $u['id']; ?>,'<?php echo htmlspecialchars(addslashes($u['name'])); ?>','<?php echo $u['role']; ?>')" title="Edit"><i class="fas fa-edit"></i></button>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" action="/admin/toggle" class="d-inline" id="toggleForm-<?php echo $u['id']; ?>"><input type="hidden" name="user_id" value="<?php echo $u['id']; ?>"><button type="button" class="btn btn-sm btn-outline-<?php echo $u['is_active'] ? 'warning' : 'success'; ?>" title="<?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?>" onclick="confirmAction('<?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?> this user?', function() { document.getElementById('toggleForm-<?php echo $u['id']; ?>').submit(); })"><i class="fas fa-<?php echo $u['is_active'] ? 'ban' : 'check'; ?>"></i></button></form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4"><a href="/admin/settings" class="btn btn-outline-primary"><i class="fas fa-cog me-1"></i>API Settings</a></div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white"><h6 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New User</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="/admin/create">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label fw-bold">Name</label><input type="text" name="name" class="form-control" required placeholder="Full name"></div>
                    <div class="mb-3"><label class="form-label fw-bold">PIN</label><div class="input-group"><input type="text" name="pin" id="newPin" class="form-control font-monospace" placeholder="Alphanumeric, min 4 chars" maxlength="32"><button type="button" class="btn btn-outline-secondary" onclick="generateNewPin()" title="Auto-generate"><i class="fas fa-random"></i></button></div><div class="form-check mt-2"><input type="checkbox" name="auto_generate" id="autoGenPin" class="form-check-input" onchange="if(this.checked) document.getElementById('newPin').value='';"><label class="form-check-label small" for="autoGenPin">Auto-generate PIN on submit</label></div></div>
                    <div class="mb-3"><label class="form-label fw-bold">Role</label><select name="role" class="form-select"><option value="user">User</option><option value="admin">Admin</option></select></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Create User</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white"><h6 class="modal-title"><i class="fas fa-edit me-2"></i>Edit User</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="/admin/edit">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="mb-3"><label class="form-label fw-bold">Name</label><input type="text" name="name" id="edit_user_name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label fw-bold">New PIN (leave blank to keep current)</label><input type="text" name="pin" class="form-control font-monospace" maxlength="32" placeholder="Leave blank to keep current"></div>
                    <div class="mb-3"><label class="form-label fw-bold">Role</label><select name="role" id="edit_user_role" class="form-select"><option value="user">User</option><option value="admin">Admin</option></select></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button></div>
            </form>
        </div>
    </div>
</div>

<script>
function togglePin(id, pin) { var el = document.getElementById('pin-' + id); if (el.innerText === '****') { el.innerText = pin; el.classList.add('text-danger'); } else { el.innerText = '****'; el.classList.remove('text-danger'); } }
function generateNewPin() { var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; var pin = ''; for (var i = 0; i < 8; i++) pin += chars.charAt(Math.floor(Math.random() * chars.length)); document.getElementById('newPin').value = pin; document.getElementById('autoGenPin').checked = false; }
function openEditUserModal(id, name, role) { document.getElementById('edit_user_id').value = id; document.getElementById('edit_user_name').value = name; document.getElementById('edit_user_role').value = role; new bootstrap.Modal(document.getElementById('editUserModal')).show(); }
</script>
