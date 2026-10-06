// assets/js/alba-deactivation.js

document.addEventListener('DOMContentLoaded', function() {
    // Read the dynamic data passed by PHP
    var pluginSlug = albaDeactivationData.pluginSlug;
    var sendingText = albaDeactivationData.sendingText;
    
    // Selectors
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
            deactivationUrl = this.href; // Save the WordPress deactivation URL
            overlay.style.display = 'flex'; // Show the modal
        });
    }

    // Show the text box when the selected option changes
    radios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            detailsBox.style.display = 'block';
        });
    });

    // "Skip and Deactivate" button
    if (skipBtn) {
        skipBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (deactivationUrl) window.location.href = deactivationUrl;
        });
    }

    // "Send and Deactivate" button
    if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            e.preventDefault();
            submitBtn.disabled = true;
            submitBtn.textContent = sendingText;

            var selectedReason = document.querySelector('input[name="alba_reason"]:checked');
            var reasonValue = selectedReason ? selectedReason.value : 'Other';
            var details = detailsBox.value;

            // Send the response to the API
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
