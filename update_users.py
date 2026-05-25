#!/usr/bin/env python3
import codecs

filepath = r'c:\xampp\htdocs\Citeflow_Manager\public\users.php'

with codecs.open(filepath, 'r', 'utf-8') as f:
    content = f.read()

# Add Designation column to table row with designation and image fields in edit form
# First, update the edit form to include role onchange handler
content = content.replace(
    '''<select class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="role">
                                            <option value="employee" <?php echo $u['role'] === 'employee' ? 'selected' : ''; ?>>Employee</option>
                                            <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        </select>''',
    '''<select class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="role" onchange="toggleDesignationEdit(this)">
                                            <option value="employee" <?php echo $u['role'] === 'employee' ? 'selected' : ''; ?>>Employee</option>
                                            <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        </select>'''
)

# Add Designation and Image upload fields to edit form
content = content.replace(
    '''<select class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="is_active">
                                            <option value="1" <?php echo (int)$u['is_active'] === 1 ? 'selected' : ''; ?>>Active</option>
                                            <option value="0" <?php echo (int)$u['is_active'] !== 1 ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                        <div class="rounded-lg border border-slate-300 bg-slate-50 px-2 py-2 text-xs text-slate-600">''',
    '''<select class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="is_active">
                                            <option value="1" <?php echo (int)$u['is_active'] === 1 ? 'selected' : ''; ?>>Active</option>
                                            <option value="0" <?php echo (int)$u['is_active'] !== 1 ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                        <select class="designation-field-edit w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="designation" <?php echo $u['role'] === 'admin' ? 'style="display:none;"' : ''; ?>>
                                            <option value="">Select Designation</option>
                                            <option value="VA" <?php echo $u['designation'] === 'VA' ? 'selected' : ''; ?>>VA</option>
                                            <option value="SEO" <?php echo $u['designation'] === 'SEO' ? 'selected' : ''; ?>>SEO</option>
                                            <option value="Design" <?php echo $u['designation'] === 'Design' ? 'selected' : ''; ?>>Design</option>
                                            <option value="DevOps" <?php echo $u['designation'] === 'DevOps' ? 'selected' : ''; ?>>DevOps</option>
                                            <option value="PPC" <?php echo $u['designation'] === 'PPC' ? 'selected' : ''; ?>>PPC</option>
                                            <option value="Social" <?php echo $u['designation'] === 'Social' ? 'selected' : ''; ?>>Social</option>
                                            <option value="Account Manager" <?php echo $u['designation'] === 'Account Manager' ? 'selected' : ''; ?>>Account Manager</option>
                                            <option value="Project Manager" <?php echo $u['designation'] === 'Project Manager' ? 'selected' : ''; ?>>Project Manager</option>
                                        </select>
                                        <input class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" placeholder="Upload image">
                                        <div class="rounded-lg border border-slate-300 bg-slate-50 px-2 py-2 text-xs text-slate-600">'''
)

# Add Designation column to table row
content = content.replace(
    '''<td class="px-5 py-4 text-sm text-slate-600"><?php echo e($u['role']); ?></td>
                        <td class="px-5 py-4 text-sm text-slate-600"><?php echo (int)$u['is_active'] === 1 ? 'Active' : 'Inactive'; ?></td>''',
    '''<td class="px-5 py-4 text-sm text-slate-600"><?php echo e($u['role']); ?></td>
                        <td class="px-5 py-4 text-sm text-slate-600"><?php echo e((string)($u['designation'] ?? '-')); ?></td>
                        <td class="px-5 py-4 text-sm text-slate-600"><?php echo (int)$u['is_active'] === 1 ? 'Active' : 'Inactive'; ?></td>'''
)

# Update colspan
content = content.replace('colspan="6">No users found.</td>', 'colspan="7">No users found.</td>')

# Add script for designation toggle
content = content.replace(
    '''<?php if (!$users): ?>
                    <tr><td class="px-5 py-6 text-sm text-slate-500" colspan="7">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php render_footer(); ?>''',
    '''<?php if (!$users): ?>
                    <tr><td class="px-5 py-6 text-sm text-slate-500" colspan="7">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<script>
function toggleDesignation(select) {
    const fields = document.querySelectorAll('.designation-field');
    fields.forEach(field => {
        field.style.display = select.value === 'employee' ? '' : 'none';
    });
}
function toggleDesignationEdit(select) {
    const parent = select.closest('form');
    if (!parent) return;
    const field = parent.querySelector('.designation-field-edit');
    if (field) {
        field.style.display = select.value === 'employee' ? '' : 'none';
    }
}
document.addEventListener('DOMContentLoaded', function() {
    const createForm = document.querySelector('form input[name="action"][value="create_user"]')?.closest('form') || document.querySelector('form[enctype="multipart/form-data"]');
    if (createForm) {
        const roleSelect = createForm.querySelector('select[name="role"]');
        if (roleSelect) toggleDesignation(roleSelect);
    }
});
</script>
<?php render_footer(); ?>'''
)

with codecs.open(filepath, 'w', 'utf-8') as f:
    f.write(content)

print('Successfully updated users.php')
