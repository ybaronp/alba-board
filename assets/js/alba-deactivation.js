// assets/js/alba-deactivation.js

document.addEventListener('DOMContentLoaded', function() {
    // Tomamos los datos dinámicos que nos pasará PHP
    var pluginSlug = albaDeactivationData.pluginSlug;
    var sendingText = albaDeactivationData.sendingText;
    
    // Selectores
    var deactivateLink = document.querySelector('tr[data-plugin="' + pluginSlug + '"] .deactivate a');
    var overlay = document.getElementById('alba-feedback-overlay');
    var skipBtn = document.getElementById('alba-skip-deactivate');
    var submitBtn = document.getElementById('alba-submit-feedback');
    var detailsBox = document.getElementById('alba-feedback-details');
    var radios = document.querySelectorAll('input[name="alba_reason"]');
    var deactivationUrl = '';

    if (deactivateLink && overlay) {
        deactivateLink.addEventListener('click', function(e) {
            e.preventDefault();
            deactivationUrl = this.href; // Guarda la URL de desactivación de WP
            overlay.style.display = 'flex'; // Muestra el modal
        });
    }

    // Muestra la caja de texto si cambian de opción
    radios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            detailsBox.style.display = 'block';
        });
    });

    // Botón "Saltar y Desactivar"
    if (skipBtn) {
        skipBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (deactivationUrl) window.location.href = deactivationUrl;
        });
    }

    // Botón "Enviar y Desactivar"
    if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            e.preventDefault();
            submitBtn.disabled = true;
            submitBtn.textContent = sendingText;

            var selectedReason = document.querySelector('input[name="alba_reason"]:checked');
            var reasonValue = selectedReason ? selectedReason.value : 'Other';
            var details = detailsBox.value;

            // Envía a la API
            fetch('https://albaboard.com/wp-json/alba/v1/feedback', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    reason: reasonValue,
                    details: details
                })
            }).then(function() {
                if (deactivationUrl) window.location.href = deactivationUrl;
            }).catch(function() {
                if (deactivationUrl) window.location.href = deactivationUrl;
            });
        });
    }
});