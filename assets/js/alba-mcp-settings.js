(function () {
    'use strict';
    var picker = document.getElementById('alba-mcp-boards');
    if (!picker) return;
    var rows = Array.from(picker.querySelectorAll('.alba-ai-board'));
    var search = document.getElementById('alba-board-search');
    var selectedOnly = document.getElementById('alba-board-selected-only');
    var selectedCount = document.getElementById('alba-board-selected-count');
    var shownCount = document.getElementById('alba-board-shown-count');
    var empty = document.getElementById('alba-board-no-results');
    document.getElementById('alba-board-total-count').textContent = rows.length;
    function refresh() {
        var query = search.value.trim().toLocaleLowerCase();
        var selected = 0, shown = 0;
        rows.forEach(function (row) {
            var checked = row.querySelector('input').checked;
            if (checked) selected++;
            row.hidden = !row.textContent.toLocaleLowerCase().includes(query) || (selectedOnly.checked && !checked);
            if (!row.hidden) shown++;
        });
        selectedCount.textContent = selected;
        shownCount.textContent = shown;
        empty.hidden = shown !== 0;
    }
    search.addEventListener('input', refresh);
    picker.addEventListener('change', refresh);
    picker.querySelectorAll('[data-alba-board-selection]').forEach(function (button) {
        button.addEventListener('click', function () {
            var action = button.dataset.albaBoardSelection;
            rows.forEach(function (row) {
                if (action === 'matching' && row.hidden) return;
                row.querySelector('input').checked = action !== 'none';
            });
            refresh();
        });
    });
    // No selection is saved until the WordPress settings form is submitted.
    refresh();
    document.getElementById('alba-copy-mcp-url').addEventListener('click', async function () {
        var field = document.getElementById('alba-mcp-url');
        var status = document.getElementById('alba-copy-status');
        try {
            await navigator.clipboard.writeText(field.value);
            status.textContent = status.dataset.copied;
        } catch (error) {
            field.focus();
            field.select();
            status.textContent = status.dataset.fallback;
        }
    });
}());
