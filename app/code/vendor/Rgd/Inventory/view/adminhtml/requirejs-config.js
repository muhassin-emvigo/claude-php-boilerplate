/**
 * Magento_Theme's theme.js calls $(...).collapse('show') on the .entry-edit
 * wrapper that every ui_component form renders, but no core requirejs-config
 * ever requires the plugin that defines $.fn.collapse (Magento replaced it
 * with mage/collapsible and left this call behind). The plugin file exists
 * at lib/web/jquery/bootstrap/collapse.js; it just needed something to
 * require it before theme.js runs.
 *
 * The mixin also registers $.fn.collapse synchronously. Bootstrap 5 only
 * attaches that jQuery plugin on DOMContentLoaded, which is too late for
 * theme.js — it calls .collapse('show') as soon as its factory runs.
 */
var config = {
    config: {
        mixins: {
            'js/theme': {
                'Rgd_Inventory/js/force-collapse-plugin-mixin': true
            }
        }
    }
};
