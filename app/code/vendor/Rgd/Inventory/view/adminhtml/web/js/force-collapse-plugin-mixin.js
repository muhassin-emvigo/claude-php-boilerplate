/**
 * Mixin for js/theme (Magento admin theme script).
 *
 * theme.js calls $(...).collapse('show'), but Bootstrap 5 only registers
 * $.fn.collapse inside onDOMContentLoaded — after theme.js has already run.
 * Require the collapse module here and attach the jQuery plugin synchronously
 * so $.fn.collapse exists before theme.js executes.
 */
define(['jquery', 'jquery/bootstrap/collapse'], function ($, Collapse) {
    'use strict';

    if ($ && Collapse && typeof $.fn.collapse === 'undefined' && Collapse.jQueryInterface) {
        $.fn.collapse = Collapse.jQueryInterface;
        $.fn.collapse.Constructor = Collapse;
        $.fn.collapse.noConflict = function () {
            return Collapse;
        };
    }

    return function (target) {
        return target;
    };
});
