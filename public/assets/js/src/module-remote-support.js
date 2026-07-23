/* global $, globalTranslate, navigator, document */

/**
 * Remote support session page.
 * @module moduleRemoteSupport
 */
const moduleRemoteSupport = {
    API_URL: '/pbxcore/api/v3/module-remote-support/session',
    POLL_INTERVAL_MS: 1000,
    KNOWN_STATES: ['off', 'starting', 'active', 'stopping', 'error'],
    pollTimer: null,
    current: null,
    $views: null,
    $live: null,
    $code: null,
    $countdown: null,
    $error: null,
    $phone: null,
    $telegram: null,
    $contactFallback: null,

    /**
     * Initialize cached elements, handlers, and initial state loading.
     * @returns {void}
     */
    initialize() {
        moduleRemoteSupport.$views = $('[data-state-view]');
        moduleRemoteSupport.$live = $('#remote-support-live');
        moduleRemoteSupport.$code = $('#remote-support-code');
        moduleRemoteSupport.$countdown = $('#remote-support-countdown');
        moduleRemoteSupport.$error = $('#remote-support-error');
        moduleRemoteSupport.$phone = $('#remote-support-phone');
        moduleRemoteSupport.$telegram = $('#remote-support-telegram');
        moduleRemoteSupport.$contactFallback = $('#remote-support-contact-fallback');

        $('#remote-support-start, #remote-support-retry').on('click', () => {
            moduleRemoteSupport.mutate('start');
        });
        $('#remote-support-stop').on('click', () => {
            moduleRemoteSupport.mutate('stop');
        });
        $('#remote-support-copy').on('click', () => {
            moduleRemoteSupport.copyCurrentCode();
        });

        moduleRemoteSupport.loadStatus();
    },

    /**
     * Load the authenticated server state.
     * @returns {void}
     */
    loadStatus() {
        moduleRemoteSupport.request('GET', moduleRemoteSupport.API_URL);
    },

    /**
     * Request a state mutation.
     * @param {string} action - Allowlisted API action.
     * @returns {void}
     */
    mutate(action) {
        if (!['start', 'stop'].includes(action)) {
            return;
        }

        moduleRemoteSupport.disableActions(true);
        moduleRemoteSupport.request('POST', `${moduleRemoteSupport.API_URL}:${action}`);
    },

    /**
     * Perform a same-origin JSON request.
     * @param {string} method - HTTP method.
     * @param {string} url - Fixed module API URL.
     * @returns {void}
     */
    request(method, url) {
        $.ajax({
            url,
            method,
            dataType: 'json',
            contentType: 'application/json',
        }).done((response) => {
            if (response && response.result === true && response.data) {
                moduleRemoteSupport.render(response.data);
                return;
            }
            moduleRemoteSupport.renderSafeError('rest_error_internal');
        }).fail(() => {
            moduleRemoteSupport.renderSafeError('rest_error_network');
        }).always(() => {
            moduleRemoteSupport.disableActions(false);
        });
    },

    /**
     * Render a normalized server state.
     * @param {Object} data - Normalized session response.
     * @returns {void}
     */
    render(data) {
        const state = moduleRemoteSupport.KNOWN_STATES.includes(data.state)
            ? data.state
            : 'error';
        moduleRemoteSupport.current = {
            state,
            code: typeof data.code === 'string' ? data.code : '',
            expiresAt: Number.isInteger(data.expiresAt) ? data.expiresAt : 0,
            errorCode: typeof data.errorCode === 'string' ? data.errorCode : '',
        };

        moduleRemoteSupport.$views.prop('hidden', true);
        $(`[data-state-view="${state}"]`).prop('hidden', false);
        moduleRemoteSupport.$live.text(moduleRemoteSupport.stateLabel(state));
        moduleRemoteSupport.$code.text(moduleRemoteSupport.current.code);
        moduleRemoteSupport.$error.text(
            moduleRemoteSupport.errorLabel(moduleRemoteSupport.current.errorCode)
        );
        moduleRemoteSupport.renderContacts(Array.isArray(data.contacts) ? data.contacts : []);
        moduleRemoteSupport.updateCountdown();
        moduleRemoteSupport.schedulePolling(state);
    },

    /**
     * Render only validated contact types and schemes.
     * @param {Array<Object>} contacts - Validated server contacts.
     * @returns {void}
     */
    renderContacts(contacts) {
        moduleRemoteSupport.$phone.prop('hidden', true);
        moduleRemoteSupport.$telegram.prop('hidden', true);
        let visible = false;

        contacts.forEach((contact) => {
            if (
                contact.type === 'phone'
                && typeof contact.uri === 'string'
                && contact.uri.startsWith('tel:')
            ) {
                moduleRemoteSupport.$phone
                    .attr('href', contact.uri)
                    .find('span')
                    .text(typeof contact.label === 'string' ? contact.label : '');
                moduleRemoteSupport.$phone.prop('hidden', false);
                visible = true;
            } else if (
                contact.type === 'telegram'
                && typeof contact.uri === 'string'
                && contact.uri.startsWith('https://')
            ) {
                moduleRemoteSupport.$telegram
                    .attr('href', contact.uri)
                    .find('span')
                    .text(typeof contact.label === 'string' ? contact.label : '');
                moduleRemoteSupport.$telegram.prop('hidden', false);
                visible = true;
            }
        });

        moduleRemoteSupport.$contactFallback.prop('hidden', visible);
    },

    /**
     * Poll only while the server owns an in-flight or active session.
     * @param {string} state - Current allowlisted state.
     * @returns {void}
     */
    schedulePolling(state) {
        if (moduleRemoteSupport.pollTimer !== null) {
            window.clearTimeout(moduleRemoteSupport.pollTimer);
            moduleRemoteSupport.pollTimer = null;
        }
        if (['starting', 'active', 'stopping'].includes(state)) {
            moduleRemoteSupport.pollTimer = window.setTimeout(
                () => moduleRemoteSupport.loadStatus(),
                moduleRemoteSupport.POLL_INTERVAL_MS
            );
        }
    },

    /**
     * Render the remaining fixed session duration.
     * @returns {void}
     */
    updateCountdown() {
        if (!moduleRemoteSupport.current || moduleRemoteSupport.current.state !== 'active') {
            moduleRemoteSupport.$countdown.text('');
            return;
        }

        const remaining = Math.max(
            0,
            moduleRemoteSupport.current.expiresAt - Math.floor(Date.now() / 1000)
        );
        const hours = Math.floor(remaining / 3600);
        const minutes = Math.floor((remaining % 3600) / 60);
        const seconds = remaining % 60;
        const time = [hours, minutes, seconds]
            .map((part) => String(part).padStart(2, '0'))
            .join(':');
        moduleRemoteSupport.$countdown.text(
            globalTranslate.module_remote_support_Countdown.replace('%time%', time)
        );
    },

    /**
     * Copy the current code without placing it in a URL or log.
     * @returns {void}
     */
    copyCurrentCode() {
        if (!moduleRemoteSupport.current || moduleRemoteSupport.current.code === '') {
            return;
        }

        const value = moduleRemoteSupport.current.code;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(() => {
                moduleRemoteSupport.$live.text(globalTranslate.module_remote_support_Copied);
            }).catch(() => {
                moduleRemoteSupport.copyWithFallback(value);
            });
            return;
        }
        moduleRemoteSupport.copyWithFallback(value);
    },

    /**
     * Copy through a temporary, non-visible textarea.
     * @param {string} value - Session code.
     * @returns {void}
     */
    copyWithFallback(value) {
        const $temporary = $('<textarea>')
            .val(value)
            .attr('readonly', true)
            .css({position: 'fixed', left: '-9999px'});
        $('body').append($temporary);
        $temporary.trigger('select');
        document.execCommand('copy');
        $temporary.remove();
        moduleRemoteSupport.$live.text(globalTranslate.module_remote_support_Copied);
    },

    /**
     * Render a safe client-side error state.
     * @param {string} safeCode - Translation key returned by local logic.
     * @returns {void}
     */
    renderSafeError(safeCode) {
        moduleRemoteSupport.render({
            state: 'error',
            errorCode: safeCode,
            contacts: [],
        });
    },

    /**
     * Resolve a localized state label.
     * @param {string} state - Allowlisted state.
     * @returns {string} Localized state label.
     */
    stateLabel(state) {
        const labels = {
            off: globalTranslate.module_remote_support_StateOff,
            starting: globalTranslate.module_remote_support_StateStarting,
            active: globalTranslate.module_remote_support_StateActive,
            stopping: globalTranslate.module_remote_support_StateStopping,
            error: globalTranslate.module_remote_support_StateError,
        };

        return labels[state];
    },

    /**
     * Resolve only allowlisted safe error codes.
     * @param {string} safeCode - Server-safe error code.
     * @returns {string} Localized message.
     */
    errorLabel(safeCode) {
        const errors = {
            preflight_failed: globalTranslate.module_remote_support_ErrorPreflight,
            runtime_failed: globalTranslate.module_remote_support_ErrorRuntime,
            allocation_failed: globalTranslate.module_remote_support_ErrorAllocation,
            key_install_failed: globalTranslate.module_remote_support_ErrorKeyInstall,
            tunnel_start_failed: globalTranslate.module_remote_support_ErrorTunnelStart,
            tunnel_not_established: globalTranslate.module_remote_support_ErrorTunnelStart,
            tunnel_disconnected: globalTranslate.module_remote_support_ErrorDisconnected,
            cleanup_failed: globalTranslate.module_remote_support_ErrorCleanup,
            rest_error_network: globalTranslate.module_remote_support_ErrorNetwork,
            rest_error_internal: globalTranslate.module_remote_support_ErrorInternal,
        };

        return errors[safeCode] || globalTranslate.module_remote_support_ErrorInternal;
    },

    /**
     * Toggle mutation controls during a request.
     * @param {boolean} disabled - Whether actions are disabled.
     * @returns {void}
     */
    disableActions(disabled) {
        $('#remote-support-start, #remote-support-stop, #remote-support-retry')
            .toggleClass('loading disabled', disabled)
            .prop('disabled', disabled);
    },
};

$(document).ready(() => {
    moduleRemoteSupport.initialize();
});
