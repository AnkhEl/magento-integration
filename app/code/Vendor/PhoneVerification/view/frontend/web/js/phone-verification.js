/**
 * My Account > Phone & WhatsApp widget.
 *
 * - Submits the number, shows the VERIFY code and a wa.me link.
 * - Polls the status endpoint until the code is consumed (verified) or expires.
 * All server-provided text is inserted with .text(), never .html().
 */
define([
    'jquery',
    'domReady!'
], function ($) {
    'use strict';

    return function (config, element) {
        var $root = $(element),
            $message = $root.find('[data-role="message"]'),
            $form = $root.find('[data-role="form"]'),
            $input = $root.find('[data-role="phone-input"]'),
            $submit = $root.find('[data-role="submit"]'),
            $panel = $root.find('[data-role="panel"]'),
            $code = $root.find('[data-role="code"]'),
            $waLink = $root.find('[data-role="wa-link"]'),
            $countdown = $root.find('[data-role="countdown"]'),
            $verified = $root.find('[data-role="verified"]'),
            $verifiedPhone = $root.find('[data-role="verified-phone"]'),
            $remove = $root.find('[data-role="remove"]'),
            msg = config.messages || {},
            pollTimer = null,
            countdownTimer = null,
            expiresAt = 0;

        function showMessage(text, isError) {
            $message
                .text(text)
                .toggleClass('message-error error', !!isError)
                .toggleClass('message-success success', !isError)
                .addClass('message')
                .prop('hidden', false);
        }

        function clearMessage() {
            $message.text('').prop('hidden', true);
        }

        function stopTimers() {
            window.clearInterval(pollTimer);
            window.clearInterval(countdownTimer);
            pollTimer = null;
            countdownTimer = null;
        }

        function formatSeconds(total) {
            var m = Math.floor(total / 60),
                s = total % 60;

            return m + ':' + (s < 10 ? '0' : '') + s;
        }

        function tickCountdown() {
            var left = Math.max(0, Math.round((expiresAt - Date.now()) / 1000));

            $countdown.text(formatSeconds(left));
            if (left === 0) {
                onExpired();
            }
        }

        function onExpired() {
            stopTimers();
            $panel.prop('hidden', true);
            $form.prop('hidden', false);
            showMessage(msg.expired, true);
        }

        function onVerified(phone) {
            stopTimers();
            $panel.prop('hidden', true);
            $form.prop('hidden', true);
            if (phone) {
                $verifiedPhone.text(phone);
            }
            $verified.prop('hidden', false);
            showMessage(msg.verified, false);
        }

        function poll() {
            $.ajax({
                url: config.statusUrl,
                type: 'GET',
                dataType: 'json',
                cache: false
            }).done(function (data) {
                if (!data || !data.success) {
                    return;
                }
                if (data.verified) {
                    onVerified(data.phone);
                } else if (!data.pending) {
                    onExpired();
                }
            });
        }

        function startWaiting(expiresInSeconds) {
            stopTimers();
            expiresAt = Date.now() + expiresInSeconds * 1000;
            $panel.prop('hidden', false);
            tickCountdown();
            countdownTimer = window.setInterval(tickCountdown, 1000);
            pollTimer = window.setInterval(poll, config.pollIntervalMs || 3000);
        }

        function ajaxError(xhr) {
            var text = msg.error;

            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                text = xhr.responseJSON.message;
            }
            showMessage(text, true);
        }

        $form.on('submit', function (event) {
            event.preventDefault();
            clearMessage();

            $submit.prop('disabled', true);
            $('body').trigger('processStart');

            $.ajax({
                url: config.requestUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: config.formKey,
                    phone: $.trim($input.val())
                }
            }).done(function (data) {
                if (!data || !data.success) {
                    ajaxError({ responseJSON: data });

                    return;
                }
                $input.val(data.phone);
                $code.text('VERIFY ' + data.code);
                $waLink.attr('href', data.wa_link);
                $form.prop('hidden', true);
                startWaiting(parseInt(data.expires_in, 10) || 600);
            }).fail(ajaxError).always(function () {
                $submit.prop('disabled', false);
                $('body').trigger('processStop');
            });
        });

        $remove.on('click', function () {
            if (!window.confirm(msg.confirmRemove)) {
                return;
            }
            clearMessage();
            $('body').trigger('processStart');

            $.ajax({
                url: config.removeUrl,
                type: 'POST',
                dataType: 'json',
                data: { form_key: config.formKey }
            }).done(function (data) {
                if (data && data.success) {
                    window.location.reload();
                } else {
                    ajaxError({ responseJSON: data });
                }
            }).fail(ajaxError).always(function () {
                $('body').trigger('processStop');
            });
        });

        // Page reloaded while a code is still pending: the plain code is never stored, so it cannot be
        // shown again. Tell the customer to request a fresh one (the old one is invalidated on request).
        if (config.initialState && config.initialState.pending && !config.initialState.verified) {
            showMessage(msg.pendingReload, false);
        }
    };
});
