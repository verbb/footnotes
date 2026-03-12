import { ButtonView, Plugin } from 'ckeditor5';

import icon from '../theme/icon.svg';

export default class FootnotesUI extends Plugin {
    static get pluginName() {
        return 'FootnotesUI';
    }

    init() {
        const editor = this.editor;
        const t = editor.t;
        const model = editor.model;

        editor.ui.componentFactory.add('footnotes', (locale) => {
            const command = editor.commands.get('footnotes');
            const view = new ButtonView(locale);

            view.set({
                label: t('Footnotes'),
                icon: icon,
                tooltip: true,
                isToggleable: true,
            });

            view.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');

            this.listenTo(view, 'execute', () => {
                editor.execute('footnotes');
                editor.editing.view.focus();
            });

            return view;
        });
    }
}