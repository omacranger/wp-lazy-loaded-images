(function (root, $, undefined) {
    "use strict";

    $(function () {
        $('.lazy-load').lazyload({
            data_attribute: 'lazy',
            appear: function () {
                $(this).addClass('loaded')
            }
        });
    });

}(this, jQuery));