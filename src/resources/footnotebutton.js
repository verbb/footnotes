(function ($R) {
    $R.add('plugin', 'footnotebutton', {

        //  i18n
        translations: {
            en: {
                "footnote": 'Footnote'
            },
            de: {
                "footnote": 'Fußnote'
            }
            //  TODO: add other translations
        },

        //  plugin construct
        init: function (app) {
            //  define redactor app
            this.app = app;
            //  define services
            this.lang = app.lang;
            this.toolbar = app.toolbar;
            this.inline = app.inline;
        },

        //  button init
        start: function () {
            //  add the button to the toolbar
            this.button = this.toolbar.addButton('footnotebutton', {
                //  set label
                title: this.lang.get('footnote'),
                //  add callback function for the button to be invoked on each click
                api: 'plugin.footnotebutton.formatSup',
                //  set icon (see also vendor/craftcms/redactor/lib/redactor/redactor.css for all icon-classes)
                icon: '<i class="re-icon-sup"></i>'
            });
        },

        //  on button toggle
        formatSup: function () {
            this.inline.format('sup');
        }

    });
})(Redactor);
