(function (global, factory) {
    typeof exports === 'object' && typeof module !== 'undefined' ? factory(exports, require('ckeditor5')) :
    typeof define === 'function' && define.amd ? define(['exports', 'ckeditor5'], factory) :
    (global = typeof globalThis !== 'undefined' ? globalThis : global || self, factory(global.footnotes = {}, global.CKEDITOR));
})(this, (function (exports, ckeditor5) { 'use strict';

    class FootnotesEditing extends ckeditor5.Plugin {
        static get pluginName() {
            return 'FootnotesEditing';
        }
        static get requires() {
            return [
                ckeditor5.TwoStepCaretMovement
            ];
        }
        init() {
            const editor = this.editor;
            editor.model.schema.extend('$text', {
                allowAttributes: 'footnotes'
            });
            editor.conversion.attributeToElement({
                model: 'footnotes',
                view: {
                    name: 'sup',
                    classes: 'footnote'
                }
            });
            editor.commands.add('footnotes', new ckeditor5.AttributeCommand(editor, 'footnotes'));
            editor.plugins.get(ckeditor5.TwoStepCaretMovement).registerAttribute('footnotes');
            ckeditor5.inlineHighlight(editor, 'footnotes', 'sup', 'footnote-selected');
        }
    }

    var icon = "<?xml version=\"1.0\" encoding=\"utf-8\"?><svg version=\"1.1\" id=\"Layer_1\" xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\" x=\"0px\" y=\"0px\" viewBox=\"0 0 20 20\" style=\"enable-background:new 0 0 20 20;\" xml:space=\"preserve\"><path d=\"M8.8,7.9v-2C8.8,5,9.3,4.4,10,4.4s1.3,0.6,1.3,1.5v1.9c0.6-0.3,1.1-0.6,1.5-0.9c0.8-0.5,1.5-0.3,1.9,0.3C15.1,7.9,14.8,8.5,14,9c-0.4,0.4-0.9,0.7-1.5,1c0.6,0.4,1.2,0.7,1.8,1.1c0.7,0.4,0.9,1.2,0.5,1.8s-1,0.7-1.8,0.3c-0.5-0.3-1-0.6-1.7-1v1.8c0,0.9-0.5,1.4-1.2,1.4c-0.8,0-1.2-0.5-1.3-1.4v-1.9c-0.7,0.4-1.2,0.7-1.8,1c-0.7,0.5-1.5,0.3-1.8-0.3C4.8,12.2,5,11.5,5.8,11c0.5-0.3,1-0.6,1.7-1C6.9,9.7,6.4,9.4,5.9,9.1C5.1,8.6,4.9,8,5.2,7.3C5.5,6.7,6.3,6.5,7,6.9C7.6,7.2,8.1,7.5,8.8,7.9z\"/></svg>\n";

    class FootnotesUI extends ckeditor5.Plugin {
        static get pluginName() {
            return 'FootnotesUI';
        }
        init() {
            const editor = this.editor;
            const t = editor.t;
            editor.model;
            editor.ui.componentFactory.add('footnotes', (locale)=>{
                const command = editor.commands.get('footnotes');
                const view = new ckeditor5.ButtonView(locale);
                view.set({
                    label: t('Footnotes'),
                    icon: icon,
                    tooltip: true,
                    isToggleable: true
                });
                view.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');
                this.listenTo(view, 'execute', ()=>{
                    editor.execute('footnotes');
                    editor.editing.view.focus();
                });
                return view;
            });
        }
    }

    class Footnotes extends ckeditor5.Plugin {
        static get pluginName() {
            return 'Footnotes';
        }
        static get requires() {
            return [
                FootnotesEditing,
                FootnotesUI
            ];
        }
    }

    exports.Footnotes = Footnotes;

}));
//# sourceMappingURL=index.umd.js.map
