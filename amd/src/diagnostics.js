// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Provider diagnostics page interactions.
 *
 * @module     local_coursetitan/diagnostics
 * @copyright  2026 JustAddWater <https://justaddwater.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    /**
     * Initialise provider test forms.
     */
    var init = function() {
        document.querySelectorAll('[data-region="provider-test-form"]').forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (form.dataset.submitting === '1') {
                    event.preventDefault();
                    return;
                }

                var button = form.querySelector('[data-action="provider-test"]');
                if (!button) {
                    return;
                }

                form.dataset.submitting = '1';
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.textContent = button.dataset.testingLabel;

                var spinner = document.createElement('span');
                spinner.className = 'spinner-border spinner-border-sm mr-2';
                spinner.setAttribute('role', 'status');
                spinner.setAttribute('aria-hidden', 'true');
                button.insertBefore(spinner, button.firstChild);
            });
        });
    };

    return {
        init: init
    };
});
