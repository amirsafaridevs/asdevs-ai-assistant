(function () {
    'use strict';

    var config = window.asdevsAiSettings || {};
    var defaultEndpoints = config.defaultEndpoints || {};

    var radios = document.querySelectorAll('.asdevs-provider-radio');
    var modelSelect = document.getElementById('asdevs-model');
    var endpointHint = document.querySelector('.asdevs-endpoint-hint code');

    radios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('.asdevs-provider-card').forEach(function (card) {
                card.classList.remove('active');
            });
            this.closest('.asdevs-provider-card').classList.add('active');

            var models = {};
            try {
                models = JSON.parse(this.dataset.models || '{}');
            } catch (e) {
                models = {};
            }

            if (modelSelect) {
                modelSelect.innerHTML = '';
                Object.entries(models).forEach(function (entry) {
                    var slug = entry[0];
                    var name = entry[1];
                    var opt = document.createElement('option');
                    opt.value = slug;
                    opt.textContent = name;
                    modelSelect.appendChild(opt);
                });
            }

            var provider = this.value;
            if (endpointHint && defaultEndpoints[provider]) {
                endpointHint.textContent = defaultEndpoints[provider];
            }
        });
    });

    var toggleBtn = document.getElementById('asdevs-toggle-key');
    var apiKeyInput = document.getElementById('asdevs-api-key');
    if (toggleBtn && apiKeyInput) {
        toggleBtn.addEventListener('click', function () {
            var isPassword = apiKeyInput.type === 'password';
            apiKeyInput.type = isPassword ? 'text' : 'password';
        });
    }

    var customEndpointToggle = document.getElementById('asdevs-use-custom-endpoint');
    var customEndpointBody = document.getElementById('asdevs-custom-endpoint-body');
    if (customEndpointToggle && customEndpointBody) {
        customEndpointToggle.addEventListener('change', function () {
            customEndpointBody.style.display = this.checked ? '' : 'none';
        });
    }
})();
