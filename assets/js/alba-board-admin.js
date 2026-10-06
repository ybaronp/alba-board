// assets/js/alba-board-admin.js
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.alba-board-copy-shortcode').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            var shortcode = this.getAttribute('data-shortcode');
            
            // Clipboard API
            if (navigator && navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(shortcode);
            } else {
                var temp = document.createElement('textarea');
                temp.value = shortcode;
                document.body.appendChild(temp);
                temp.select();
                try { document.execCommand('copy'); } catch(err){}
                document.body.removeChild(temp);
            }
            
            // UX feedback: Preserve the original icon, if present, and replace only the content
            var originalHTML = this.innerHTML;
            this.innerHTML = '<span class="dashicons dashicons-yes-alt" style="font-size:16px; width:16px; height:16px; margin-top:1px; color:#10b981;"></span> <span>Copied!</span>';
            
            var _btn = this;
            setTimeout(function() {
                _btn.innerHTML = originalHTML;
            }, 1500);
        });
    });
});
