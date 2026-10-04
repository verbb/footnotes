import { ButtonView, Plugin } from 'ckeditor5';

import { focusDefinition, focusReference } from './footnotes-editing.js';
import icon from '../theme/icon.svg';

export default class FootnotesUI extends Plugin {
    static get pluginName() {
        return 'FootnotesUI';
    }

    init() {
        const editor = this.editor;
        const viewDocument = editor.editing.view.document;

        editor.ui.componentFactory.add('footnotes', (locale) => {
            const command = editor.commands.get('footnotes');
            const view = new ButtonView(locale);

            view.set({
                label: editor.t('Footnote'),
                icon,
                tooltip: true,
            });
            view.bind('isEnabled').to(command, 'isEnabled');
            this.listenTo(view, 'execute', () => {
                editor.execute('footnotes');
                editor.editing.view.focus();
            });

            return view;
        });

        this.listenTo(viewDocument, 'click', (_evt, data) => {
            const marker = data.domTarget?.closest?.('sup.footnote-marker');
            const footnoteId = marker?.getAttribute('data-footnote-id') || '';

            if (footnoteId && focusDefinition(editor.model, footnoteId)) {
                editor.editing.view.focus();
                data.preventDefault();
            }
        });

        editor.keystrokes.set('Ctrl+Enter', (_data, cancel) => {
            if (this._returnToReference()) {
                cancel();
            }
        });
    }

    _returnToReference() {
        const editor = this.editor;
        const position = editor.model.document.selection.getFirstPosition();
        let parent = position?.parent || null;

        while (parent && !parent.is?.('element', 'footnoteItem')) {
            parent = parent.parent;
        }

        const footnoteId = parent?.getAttribute?.('footnoteId') || '';

        if (!footnoteId || !focusReference(editor.model, footnoteId)) {
            return false;
        }

        editor.editing.view.focus();

        return true;
    }
}
