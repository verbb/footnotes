(function ($R) {
    $R.add('plugin', 'footnotebutton', {
        translations: {
            en: {
                "footnote": 'Footnote',
            },
            de: {
                "footnote": 'Fußnote',
            }
        },

        init: function (app) {
            this.app = app;
            this.lang = app.lang;
            this.toolbar = app.toolbar;
            this.inline = app.inline;
        },

        start: function () {
            this.button = this.toolbar.addButton('footnotebutton', {
                title: this.lang.get('footnote'),
                api: 'plugin.footnotebutton.formatSup',
                icon: '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="11.1" style="overflow:visible;enable-background:new 0 0 10 11.1" xml:space="preserve"><path d="M3.8 3.5v-2C3.8.6 4.3 0 5 0s1.3.6 1.3 1.5v1.9c.6-.3 1.1-.6 1.5-.9.8-.5 1.5-.3 1.9.3.4.7.1 1.3-.7 1.8-.4.4-.9.7-1.5 1 .6.4 1.2.7 1.8 1.1.7.4.9 1.2.5 1.8-.4.6-1 .7-1.8.3-.5-.3-1-.6-1.7-1v1.8c0 .9-.5 1.4-1.2 1.4-.8 0-1.2-.5-1.3-1.4V7.7c-.7.4-1.2.7-1.8 1-.7.5-1.5.3-1.8-.3-.4-.6-.2-1.3.6-1.8.5-.3 1-.6 1.7-1-.6-.3-1.1-.6-1.6-.9-.8-.5-1-1.1-.7-1.8.3-.6 1.1-.8 1.8-.4.6.3 1.1.6 1.8 1z"/></svg>',
            });
        },

        formatSup: function () {
            this.inline.format({ tag: 'sup', class: 'footnote', attr: { tabindex: '0' } });
        },
    });
})(Redactor);
