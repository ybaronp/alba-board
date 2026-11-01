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
    function bindCopy(buttonId, fieldId) {
        var button = document.getElementById(buttonId);
        var label = button.textContent;
        var reset;
        button.addEventListener('click', async function () {
            var field = document.getElementById(fieldId);
            var status = document.getElementById('alba-copy-status');
            clearTimeout(reset);
            try {
                await navigator.clipboard.writeText(field.value);
                button.textContent = button.dataset.copied;
            } catch (error) {
                field.closest('details').open = true;
                field.focus();
                field.select();
                button.textContent = button.dataset.fallback;
            }
            status.textContent = button.textContent;
            reset = setTimeout(function () { button.textContent = label; status.textContent = ''; }, 2500);
        });
    }
    bindCopy('alba-copy-mcp-url', 'alba-mcp-url');
    bindCopy('alba-copy-mcp-prompt', 'alba-mcp-setup-prompt');
}());
