/**
 * Registration dynamic compliance fields widget.
 *
 * Page-level Knockout widget (not a full UI Component) bound to the customer registration
 * page's group selector + field-reveal container. Invoked directly by name via
 * `text/x-magento-init` (see registration/dynamic_fields.phtml), which supports plain
 * `function(config, element)` modules in addition to jQuery widgets.
 *
 * Transition note: the 200ms fade+slide transition below is implemented with a minimal jQuery
 * `.animate()` opacity fallback so this file is self-contained; the "real" transition CSS
 * (transform/opacity keyframes on `.compliance-fields`) belongs in a Luma `_module.less` file,
 * which is out of scope for this pass.
 */
define([
    'jquery',
    'ko',
    'mage/translate'
], function ($, ko, $t) {
    'use strict';

    /**
     * HTML-escape a value for safe interpolation into markup built as strings.
     *
     * @param {*} value
     * @return {String}
     */
    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : String(value)).html();
    }

    /**
     * Escape a value for use inside a double-quoted HTML attribute.
     *
     * @param {*} value
     * @return {String}
     */
    function escapeAttr(value) {
        return escapeHtml(value).replace(/"/g, '&quot;');
    }

    /**
     * Build the inner control markup for a single field, switching on fieldType. Mirrors the
     * server-side rendering in account/resubmit.phtml.
     *
     * @param {Object} field
     * @return {String}
     */
    function buildFieldControlMarkup(field) {
        var name = 'customercompliance_fields[' + field.fieldCode + ']',
            fileName = 'customercompliance_files[' + field.fieldCode + ']',
            id = 'compliance_field_' + field.fieldId,
            requiredAttr = field.isRequired ? ' required aria-required="true"' : '',
            html = '',
            accept = '';

        switch (field.fieldType) {
            case 'textarea':
                html = '<textarea name="' + name + '" id="' + id + '" class="textarea"' +
                    requiredAttr + '></textarea>';
                break;

            case 'dropdown':
                html = '<select name="' + name + '" id="' + id + '" class="select"' + requiredAttr + '>';
                html += '<option value="">' + escapeHtml($t('-- Please Select --')) + '</option>';
                $.each(field.options || {}, function (value, label) {
                    html += '<option value="' + escapeAttr(value) + '">' + escapeHtml(label) + '</option>';
                });
                html += '</select>';
                break;

            case 'radio':
                $.each(field.options || {}, function (value, label) {
                    var optId = id + '_' + value;

                    html += '<div class="control-radio">' +
                        '<input type="radio" name="' + name + '" id="' + optId + '" value="' +
                        escapeAttr(value) + '"' + requiredAttr + '>' +
                        '<label for="' + optId + '">' + escapeHtml(label) + '</label></div>';
                });
                break;

            case 'checkbox':
                html = '<input type="checkbox" name="' + name + '" id="' + id + '" value="1"' +
                    requiredAttr + '>';
                break;

            case 'file':
            case 'image':
                if (field.allowedExtensions) {
                    accept = ' accept=".' +
                        escapeAttr(field.allowedExtensions.split(',').join(',.')) + '"';
                }
                html = '<input type="file" name="' + fileName + '" id="' + id + '" class="input-file"' +
                    accept + requiredAttr + '>';
                break;

            case 'date':
                html = '<input type="date" name="' + name + '" id="' + id + '" class="input-text"' +
                    requiredAttr + '>';
                break;

            case 'number':
                html = '<input type="number" name="' + name + '" id="' + id + '" class="input-text"' +
                    requiredAttr + '>';
                break;

            case 'email':
                html = '<input type="email" name="' + name + '" id="' + id + '" class="input-text"' +
                    requiredAttr + '>';
                break;

            case 'phone':
                html = '<input type="tel" name="' + name + '" id="' + id + '" class="input-text"' +
                    requiredAttr + '>';
                break;

            case 'text':
            default:
                html = '<input type="text" name="' + name + '" id="' + id + '" class="input-text"' +
                    requiredAttr + '>';
                break;
        }

        return html;
    }

    /**
     * Validate a single field's current DOM value against the mirrored server rules: required
     * non-empty, file extension/size checks against allowedExtensions/maxFileSizeKb.
     *
     * @param {Object} field
     * @param {jQuery} $control
     * @return {String} Error message, or '' if valid.
     */
    function validateField(field, $control) {
        var value,
            file,
            ext,
            allowed,
            maxBytes,
            fileInput;

        if (field.fieldType === 'file' || field.fieldType === 'image') {
            fileInput = $control.find('input[type="file"]').get(0);
            file = fileInput && fileInput.files && fileInput.files[0];

            if (!file) {
                return field.isRequired ? $t('This is a required field.') : '';
            }

            if (field.allowedExtensions) {
                ext = file.name.split('.').pop().toLowerCase();
                allowed = field.allowedExtensions.split(',').map(function (extension) {
                    return extension.trim().toLowerCase().replace(/^\./, '');
                });

                if (allowed.indexOf(ext) === -1) {
                    return $t('File type not allowed. Allowed types: %1').replace('%1', field.allowedExtensions);
                }
            }

            if (field.maxFileSizeKb) {
                maxBytes = field.maxFileSizeKb * 1024;

                if (file.size > maxBytes) {
                    return $t('File is too large. Maximum size: %1 KB').replace('%1', field.maxFileSizeKb);
                }
            }

            return '';
        }

        if (field.fieldType === 'checkbox') {
            value = $control.find('input[type="checkbox"]').is(':checked');

            return field.isRequired && !value ? $t('This is a required field.') : '';
        }

        if (field.fieldType === 'radio') {
            value = $control.find('input[type="radio"]:checked').val();

            return field.isRequired && !value ? $t('This is a required field.') : '';
        }

        value = $control.find('input, select, textarea').val();

        if (field.isRequired && (value === undefined || value === null || String(value).trim() === '')) {
            return $t('This is a required field.');
        }

        return '';
    }

    /**
     * @param {Object} config
     * @param {HTMLElement} element The `#customercompliance-fields-container` element.
     */
    return function (config, element) {
        var groupsData = config.groupsData || [],
            $container = $(element),
            $validationSummary = $(config.validationSummarySelector || '#customercompliance-validation-summary'),
            prefersReducedMotion = Boolean(
                window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches
            ),
            viewModel;

        /**
         * Reveal the fields container with the design doc's 200ms fade+slide transition,
         * skipped entirely when the user prefers reduced motion.
         *
         * @param {jQuery} $el
         */
        function revealContainer($el) {
            if (prefersReducedMotion) {
                $el.show();
                return;
            }

            $el.css({ opacity: 0 }).show().animate({ opacity: 1 }, 200);
        }

        /**
         * @param {jQuery} $el
         */
        function hideContainer($el) {
            if (prefersReducedMotion) {
                $el.hide();
                return;
            }

            $el.animate({ opacity: 0 }, 200, function () {
                $el.hide();
            });
        }

        /**
         * Move focus to the first focusable field in the revealed block (a11y requirement).
         */
        function focusFirstField() {
            var $first = $container.find('input, select, textarea').filter(':visible').first();

            if ($first.length) {
                $first.trigger('focus');
            }
        }

        /**
         * @constructor
         */
        function ComplianceViewModel() {
            var self = this;

            self.groupOptions = groupsData;
            self.selectedGroupId = ko.observable(null);

            self.hasSelectedGroup = ko.computed(function () {
                var value = self.selectedGroupId();

                return value !== null && value !== undefined && value !== '';
            });

            self.currentGroupFields = ko.computed(function () {
                var groupId = self.selectedGroupId(),
                    group;

                if (!self.hasSelectedGroup()) {
                    return [];
                }

                group = ko.utils.arrayFirst(groupsData, function (candidate) {
                    return String(candidate.groupId) === String(groupId);
                });

                if (!group || !group.fields) {
                    return [];
                }

                return group.fields.slice().sort(function (a, b) {
                    return a.sortOrder - b.sortOrder;
                });
            });

            /**
             * `afterRender` callback for the `template` binding: builds the real input markup
             * for each rendered field row (switch on fieldType) and wires inline validation.
             *
             * @param {Array} renderedNodes
             * @param {Object} field
             */
            self.renderFieldControl = function (renderedNodes, field) {
                $.each(renderedNodes, function (index, node) {
                    var $node,
                        $control;

                    if (node.nodeType !== 1) {
                        return;
                    }

                    $node = $(node);
                    $control = $node.find('.compliance-field-control');

                    if (!$control.length) {
                        return;
                    }

                    $control.html(buildFieldControlMarkup(field));

                    $control.find('input, select, textarea').on('change blur', function () {
                        var error = validateField(field, $control);

                        $node.find('.compliance-field-error').text(error || '');
                        $node.toggleClass('_error', Boolean(error));
                    });
                });
            };

            /**
             * Validate every currently-rendered field. Populates each field's inline error and
             * returns the flat list of error messages for the S9 validation summary.
             *
             * @return {Array}
             */
            self.validateAll = function () {
                var errors = [];

                self.currentGroupFields().forEach(function (field) {
                    var $control = $container.find('#compliance_field_control_' + field.fieldId),
                        $field = $control.closest('.field'),
                        error = validateField(field, $control);

                    if (error) {
                        errors.push(field.label + ': ' + error);
                        $field.addClass('_error');
                        $field.find('.compliance-field-error').text(error);
                    } else {
                        $field.removeClass('_error');
                        $field.find('.compliance-field-error').text('');
                    }
                });

                return errors;
            };
        }

        viewModel = new ComplianceViewModel();

        // Bind the parent node so the group <select>, which is a sibling of this container
        // rather than a descendant, is covered by the same view model.
        ko.applyBindings(viewModel, element.parentNode || element);

        viewModel.selectedGroupId.subscribe(function () {
            if (viewModel.hasSelectedGroup()) {
                revealContainer($container);
                // Give the `template` binding time to finish rendering before moving focus.
                setTimeout(focusFirstField, prefersReducedMotion ? 0 : 210);
            } else {
                hideContainer($container);
            }
        });

        // Submit-time validation: populate the S9 validation summary and focus it on error.
        $container.closest('form').on('submit', function (event) {
            var errors;

            if (!viewModel.hasSelectedGroup()) {
                return;
            }

            errors = viewModel.validateAll();

            if (errors.length) {
                event.preventDefault();
                $validationSummary
                    .html('<ul>' + $.map(errors, function (msg) {
                        return '<li>' + escapeHtml(msg) + '</li>';
                    }).join('') + '</ul>')
                    .show();
                $validationSummary.trigger('focus');
            } else {
                $validationSummary.hide().empty();
            }
        });
    };
});
