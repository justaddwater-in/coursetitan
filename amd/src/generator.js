// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * CourseTitan generator page: posts the form and renders the progress stream.
 *
 * @module     local_coursetitan/generator
 * @copyright  2026 JustAddWater <https://justaddwater.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/str'], function(Str) {
    'use strict';

    var ICON = {
        pending: '○',
        running: '<span class="spinner-border spinner-border-sm text-primary" role="status"></span>',
        done: '<span class="text-success">✓</span>',
        failed: '<span class="text-danger">✕</span>'
    };

    var STEPS = ['outline', 'content', 'build'];

    /**
     * Page controller.
     *
     * @param {HTMLElement} root
     * @param {Object} config
     */
    function Generator(root, config) {
        this.root = root;
        this.config = config;
        this.form = root.querySelector('[data-region="form"]');
        this.button = root.querySelector('[data-action="generate"]');
        this.timer = null;
        this.finished = false;
        this.strings = {};
        this.form.addEventListener('submit', this.submit.bind(this));
    }

    /**
     * Find an element by data-region inside an optional parent.
     *
     * @param {string} name
     * @param {HTMLElement} [parent]
     * @returns {HTMLElement}
     */
    Generator.prototype.region = function(name, parent) {
        return (parent || this.root).querySelector('[data-region="' + name + '"]');
    };

    /**
     * Show or hide an element.
     *
     * @param {HTMLElement} el
     * @param {boolean} visible
     */
    Generator.prototype.toggle = function(el, visible) {
        el.classList.toggle('d-none', !visible);
    };

    /**
     * Set a step's state, and optionally its detail text.
     *
     * @param {string} step
     * @param {string} state pending|running|done|failed
     * @param {string} [detail]
     */
    Generator.prototype.setStep = function(step, state, detail) {
        var li = this.root.querySelector('[data-step="' + step + '"]');
        if (!li) {
            return;
        }
        this.region('icon', li).innerHTML = ICON[state];
        li.dataset.state = state;
        if (detail !== undefined) {
            this.region('detail', li).textContent = detail;
        }
    };

    /**
     * Load the strings the stream handler needs.
     *
     * @returns {Promise}
     */
    Generator.prototype.loadStrings = function() {
        var keys = ['js_nosource', 'js_connectionlost', 'js_elapsed', 'js_tokens', 'js_summary', 'js_skipped'];
        var self = this;
        return Str.get_strings(keys.map(function(k) {
            return {key: k, component: 'local_coursetitan'};
        })).then(function(values) {
            keys.forEach(function(k, i) {
                self.strings[k] = values[i];
            });
            return null;
        });
    };

    /**
     * Fill {name} placeholders.
     *
     * @param {string} key
     * @param {Object} values
     * @returns {string}
     */
    Generator.prototype.str = function(key, values) {
        return (this.strings[key] || key).replace(/\{(\w+)\}/g, function(m, name) {
            return values && values[name] !== undefined ? values[name] : m;
        });
    };

    /**
     * Form submit handler.
     *
     * @param {Event} e
     */
    Generator.prototype.submit = function(e) {
        e.preventDefault();
        var self = this;
        var data = new FormData(this.form);
        var files = this.form.querySelector('input[type="file"]').files;
        var formError = this.region('formerror');

        this.loadStrings().then(function() {
            if (!String(data.get('text') || '').trim() && files.length === 0) {
                formError.textContent = self.strings.js_nosource;
                self.toggle(formError, true);
                return null;
            }
            self.toggle(formError, false);
            data.append('sesskey', self.config.sesskey);
            self.start(data);
            return null;
        }).catch(function(err) {
            self.fail(String(err));
        });
    };

    /**
     * Reset the UI and open the stream.
     *
     * @param {FormData} data
     */
    Generator.prototype.start = function(data) {
        var self = this;
        this.finished = false;
        this.button.disabled = true;
        this.toggle(this.region('progress'), true);
        this.toggle(this.region('result'), false);
        this.toggle(this.region('error'), false);
        this.toggle(this.region('bar'), false);
        this.region('current').textContent = '';
        STEPS.forEach(function(s) {
            self.setStep(s, 'pending', '');
        });

        var started = Date.now();
        var elapsed = this.region('elapsed');
        clearInterval(this.timer);
        this.timer = setInterval(function() {
            var s = Math.round((Date.now() - started) / 1000);
            elapsed.textContent = self.str('js_elapsed', {time: Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2)});
        }, 1000);

        fetch(this.config.streamUrl, {method: 'POST', body: data, credentials: 'same-origin'})
            .then(function(response) {
                if (!response.ok || !response.body) {
                    throw new Error('HTTP ' + response.status);
                }
                return self.read(response.body.getReader());
            })
            .then(function() {
                if (!self.finished) {
                    self.fail(self.strings.js_connectionlost);
                }
                return null;
            })
            .catch(function(err) {
                self.fail(String(err && err.message ? err.message : err));
            });
    };

    /**
     * Read Server-Sent Events from the response body.
     *
     * @param {ReadableStreamDefaultReader} reader
     * @returns {Promise}
     */
    Generator.prototype.read = function(reader) {
        var self = this;
        var decoder = new TextDecoder();
        var pending = '';

        var next = function() {
            return reader.read().then(function(chunk) {
                if (chunk.done) {
                    return null;
                }
                pending += decoder.decode(chunk.value, {stream: true});
                var events = pending.split(/\r?\n\r?\n/);
                pending = events.pop();
                events.forEach(function(raw) {
                    var payload = raw.split(/\r?\n/)
                        .filter(function(line) {
                            return line.indexOf('data:') === 0;
                        })
                        .map(function(line) {
                            return line.slice(5).trim();
                        })
                        .join('');
                    if (payload) {
                        try {
                            self.handle(JSON.parse(payload));
                        } catch (err) {
                            window.console.warn('CourseTitan: bad event', payload, err);
                        }
                    }
                });
                return next();
            });
        };
        return next();
    };

    /**
     * Apply one event to the UI.
     *
     * @param {Object} ev
     */
    Generator.prototype.handle = function(ev) {
        switch (ev.event) {
            case 'stage_start':
                this.setStep(ev.stage, 'running');
                if (ev.stage === 'content' && ev.total) {
                    this.progress(0, ev.total);
                }
                break;
            case 'stage_progress':
                this.progress(ev.done, ev.total);
                this.region('current').textContent = ev.label || '';
                break;
            case 'stage_done':
                if (ev.stage === 'content') {
                    this.toggle(this.region('bar'), false);
                    this.region('current').textContent = '';
                }
                this.setStep(ev.stage, 'done', this.describe(ev));
                break;
            case 'done':
                this.complete(ev);
                break;
            case 'error':
                this.fail(ev.message);
                break;
        }
    };

    /**
     * Update the activity progress bar.
     *
     * @param {number} done
     * @param {number} total
     */
    Generator.prototype.progress = function(done, total) {
        var bar = this.region('bar');
        this.toggle(bar, true);
        bar.firstElementChild.style.width = Math.round(100 * done / Math.max(1, total)) + '%';
        this.setStep('content', 'running', done + ' / ' + total);
    };

    /**
     * Detail text for a finished step.
     *
     * @param {Object} ev
     * @returns {string}
     */
    Generator.prototype.describe = function(ev) {
        var parts = [];
        if (ev.detail) {
            parts.push(ev.detail);
        }
        parts.push((ev.elapsed_ms / 1000).toFixed(1) + 's');
        return parts.join(' · ');
    };

    /**
     * Show the finished course.
     *
     * @param {Object} ev
     */
    Generator.prototype.complete = function(ev) {
        this.finished = true;
        clearInterval(this.timer);
        this.button.disabled = false;

        this.region('title').textContent = ev.title;
        this.region('open').href = ev.url;
        var usage = ev.usage || {};
        this.region('summary').textContent = this.str('js_summary', {
            model: ev.model,
            tokens: this.str('js_tokens', {in: usage.in || 0, out: usage.out || 0, cached: usage.cache || 0})
        });

        var list = this.region('warnings');
        list.innerHTML = '';
        (ev.warnings || []).forEach(function(w) {
            var li = document.createElement('li');
            li.textContent = w;
            list.appendChild(li);
        });
        this.toggle(list, list.children.length > 0);
        this.toggle(this.region('result'), true);
        this.region('result').scrollIntoView({behavior: 'smooth', block: 'nearest'});
    };

    /**
     * Show an error and mark the running step as failed.
     *
     * @param {string} message
     */
    Generator.prototype.fail = function(message) {
        var self = this;
        this.finished = true;
        clearInterval(this.timer);
        this.button.disabled = false;
        STEPS.forEach(function(s) {
            var li = self.root.querySelector('[data-step="' + s + '"]');
            if (li && li.dataset.state === 'running') {
                self.setStep(s, 'failed');
            }
        });
        var box = this.region('error');
        box.textContent = message;
        this.toggle(box, true);
    };

    return {
        /**
         * Initialise the page.
         *
         * @param {Object} config
         * @param {string} config.streamUrl
         * @param {string} config.sesskey
         */
        init: function(config) {
            var root = document.querySelector('[data-region="coursetitan"]');
            if (root) {
                new Generator(root, config);
            }
        }
    };
});
