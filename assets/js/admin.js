function toggleAllStaf(source) {
    const checkboxes = document.querySelectorAll('.staf-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
}

function toggleAllLembur(source) {
    const checkboxes = document.querySelectorAll('.lembur-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
}

function updateCheckAllState() {
    const checkAll = document.getElementById('checkAllStaf');
    const checkboxes = document.querySelectorAll('.staf-checkbox');
    const checkedCount = document.querySelectorAll('.staf-checkbox:checked').length;
    
    checkAll.checked = checkedCount === checkboxes.length;
    checkAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
}
