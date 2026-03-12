import { AttributeCommand, Plugin, TwoStepCaretMovement, inlineHighlight } from 'ckeditor5';

export default class FootnotesEditing extends Plugin {
    static get pluginName() {
        return 'FootnotesEditing';
    }

    static get requires() {
        return [TwoStepCaretMovement];
    }

    init() {
        const editor = this.editor;

        editor.model.schema.extend('$text', { allowAttributes: 'footnotes' });

        editor.conversion.attributeToElement({
            model: 'footnotes',
                view: {
                name: 'sup',
                classes: 'footnote',
            },
        });

        editor.commands.add('footnotes', new AttributeCommand(editor, 'footnotes'));

        editor.plugins.get(TwoStepCaretMovement).registerAttribute('footnotes');
        inlineHighlight(editor, 'footnotes', 'sup', 'footnote-selected');
    }
}