// assets/js/alba-board-admin.js
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.alba-board-copy-shortcode').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var shortcode = this.getAttribute('data-shortcode');
            if (navigator && navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(shortcode);
            } else {
                var temp = document.createElement('textarea');
                temp.value = shortcode;
                document.body.appendChild(temp);
                temp.select();
                try { document.execCommand('copy'); } catch(e){}
                document.body.removeChild(temp);
            }
            var oldText = this.textContent;
            this.textContent = 'Copied!';
            var btn = this;
            setTimeout(function() {
                btn.textContent = oldText;
            }, 1200);
        });
    });
});