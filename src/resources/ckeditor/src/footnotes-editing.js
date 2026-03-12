import { Command, Plugin, toWidget, Widget } from 'ckeditor5';

class InsertFootnoteCommand extends Command {
    refresh() {
        const model = this.editor.model;
        const selection = model.document.selection;
        const firstPosition = selection.getFirstPosition();

        this.isEnabled = !!firstPosition && model.schema.checkChild(firstPosition.parent, 'footnote');
        this.value = getSelectedFootnote(selection);
    }

    execute(options = {}) {
        const editor = this.editor;
        const model = editor.model;
        const selection = model.document.selection;
        const selectedFootnote = getSelectedFootnote(selection);

        if (selectedFootnote) {
            return;
        }

        model.change((writer) => {
            const footnoteText = getInitialFootnoteText(model, selection, options);
            const footnoteNumber = getNextFootnoteNumber(model);
            const footnoteId = getNextFootnoteId(model);
            const footnote = writer.createElement('footnote', {
                footnoteText,
                footnoteNumber,
                footnoteId,
            });

            model.insertContent(footnote);
            writer.setSelection(footnote, 'on');
        });
    }
}

class RemoveFootnoteCommand extends Command {
    refresh() {
        const selection = this.editor.model.document.selection;
        this.value = getSelectedFootnote(selection);
        this.isEnabled = !!this.value;
    }

    execute() {
        const model = this.editor.model;
        const selection = model.document.selection;
        const footnote = getSelectedFootnote(selection);

        if (!footnote) {
            return;
        }

        model.change((writer) => {
            writer.remove(footnote);
        });
    }
}

class UpdateFootnoteTextCommand extends Command {
    refresh() {
        const selection = this.editor.model.document.selection;
        const footnote = getSelectedFootnote(selection);

        this.value = footnote ? footnote.getAttribute('footnoteText') : '';
        this.isEnabled = !!footnote;
    }

    execute(options = {}) {
        const text = `${options.text || ''}`.trim();
        const model = this.editor.model;
        const selection = model.document.selection;
        const footnote = getSelectedFootnote(selection);

        if (!footnote) {
            return;
        }

        model.change((writer) => {
            writer.setAttribute('footnoteText', text, footnote);
        });
    }
}

function getSelectedFootnote(selection) {
    const firstPosition = selection.getFirstPosition();

    if (!firstPosition) {
        return null;
    }

    const parent = firstPosition.parent;

    if (parent?.is('element', 'footnote')) {
        return parent;
    }

    const selectedElement = selection.getSelectedElement();
    if (selectedElement?.is('element', 'footnote')) {
        return selectedElement;
    }

    return null;
}

function getInitialFootnoteText(model, selection, options) {
    if (typeof options.text === 'string') {
        return options.text.trim();
    }

    return extractModelText(model.getSelectedContent(selection)).trim();
}

function extractViewText(node) {
    if (!node) {
        return '';
    }

    if (node.is('$text')) {
        return node.data;
    }

    if (!node.is('element')) {
        return '';
    }

    let text = '';

    for (const child of node.getChildren()) {
        text += extractViewText(child);
    }

    return text;
}

function getAllFootnotes(model) {
    const footnotes = [];

    for (const root of model.document.getRoots()) {
        if (root.rootName === '$graveyard') {
            continue;
        }

        for (const item of model.createRangeIn(root).getItems()) {
            if (item.is('element', 'footnote')) {
                footnotes.push(item);
            }
        }
    }

    return footnotes;
}

function getNextFootnoteNumber(model) {
    let max = 0;

    for (const footnote of getAllFootnotes(model)) {
        const number = parseInt(`${footnote.getAttribute('footnoteNumber') || ''}`, 10);

        if (Number.isFinite(number)) {
            max = Math.max(max, number);
        }
    }

    return `${max + 1}`;
}

function extractModelText(node) {
    if (!node) {
        return '';
    }

    if (node.is('$text')) {
        return node.data;
    }

    if (!node.is('element') && !node.is('documentFragment')) {
        return '';
    }

    let text = '';

    for (const child of node.getChildren()) {
        text += extractModelText(child);
    }

    return text;
}

export default class FootnotesEditing extends Plugin {
    static get pluginName() {
        return 'FootnotesEditing';
    }

    static get requires() {
        return [Widget];
    }

    init() {
        const editor = this.editor;

        editor.editing.view.domConverter.registerInlineObjectMatcher((element) => {
            return element.classList?.contains('footnote-marker') ? { name: true } : null;
        });

        editor.model.schema.register('footnote', {
            allowWhere: '$text',
            allowAttributes: ['footnoteText', 'footnoteNumber', 'footnoteId'],
            isInline: true,
            isObject: true,
        });

        editor.conversion.for('upcast').add((dispatcher) => {
            dispatcher.on('element:sup', (evt, data, conversionApi) => {
                const viewElement = data.viewItem;

                if (!viewElement.hasClass('footnote')) {
                    return;
                }

                if (!conversionApi.consumable.consume(viewElement, {
                    name: true,
                    classes: ['footnote'],
                })) {
                    return;
                }

                const footnote = conversionApi.writer.createElement('footnote', {
                    footnoteText: extractViewText(viewElement).trim(),
                    footnoteId: getNextFootnoteId(editor.model),
                });

                if (!conversionApi.safeInsert(footnote, data.modelCursor)) {
                    return;
                }

                conversionApi.updateConversionResult(footnote, data);
                evt.stop();
            }, { priority: 'high' });
        });

        editor.conversion.for('dataDowncast').elementToElement({
            model: {
                name: 'footnote',
                attributes: ['footnoteText', 'footnoteNumber', 'footnoteId'],
            },
            view: (modelItem, { writer }) => {
                const footnoteText = modelItem.getAttribute('footnoteText') || '';
                const footnoteId = modelItem.getAttribute('footnoteId') || '';
                const sup = writer.createContainerElement('sup', {
                    class: 'footnote',
                    'data-footnote-id': footnoteId,
                });

                if (footnoteText) {
                    writer.insert(writer.createPositionAt(sup, 0), writer.createText(footnoteText));
                }

                return sup;
            },
        });

        editor.conversion.for('editingDowncast').elementToElement({
            model: {
                name: 'footnote',
                attributes: ['footnoteText', 'footnoteNumber', 'footnoteId'],
            },
            view: (modelItem, { writer }) => {
                const number = modelItem.getAttribute('footnoteNumber') || '?';
                const footnoteText = modelItem.getAttribute('footnoteText') || '';
                const footnoteId = modelItem.getAttribute('footnoteId') || '';

                const numberElement = writer.createUIElement('span', { class: 'footnote-marker__number' }, function(domDocument) {
                    const domElement = this.toDomElement(domDocument);
                    domElement.textContent = number;
                    return domElement;
                });

                const marker = writer.createContainerElement('sup', {
                    class: 'footnote footnote-marker',
                    title: footnoteText,
                    'data-footnote-id': footnoteId,
                }, [numberElement]);

                return toWidget(marker, writer, { label: `Footnote ${number}` });
            },
        });

        editor.commands.add('footnotes', new InsertFootnoteCommand(editor));
        editor.commands.add('removeFootnote', new RemoveFootnoteCommand(editor));
        editor.commands.add('updateFootnoteText', new UpdateFootnoteTextCommand(editor));

        editor.model.document.registerPostFixer((writer) => {
            let changed = false;
            let number = 1;
            const usedIds = new Set();

            for (const footnote of getAllFootnotes(editor.model)) {
                const expected = `${number++}`;

                if (footnote.getAttribute('footnoteNumber') !== expected) {
                    writer.setAttribute('footnoteNumber', expected, footnote);
                    changed = true;
                }

                const currentId = `${footnote.getAttribute('footnoteId') || ''}`.trim();
                const hasValidAndUniqueId = currentId && !usedIds.has(currentId);

                if (!hasValidAndUniqueId) {
                    const nextId = getNextFootnoteId(editor.model, usedIds);
                    writer.setAttribute('footnoteId', nextId, footnote);
                    usedIds.add(nextId);
                    changed = true;
                } else {
                    usedIds.add(currentId);
                }
            }

            return changed;
        });
    }
}

function getNextFootnoteId(model, reservedIds = new Set()) {
    const usedIds = getAllFootnoteIds(model);

    for (const id of reservedIds) {
        usedIds.add(id);
    }

    let max = 0;

    for (const id of usedIds) {
        const match = /^fn-(\d+)$/.exec(id);

        if (match) {
            max = Math.max(max, parseInt(match[1], 10));
        }
    }

    let candidate = `fn-${max + 1}`;

    while (usedIds.has(candidate)) {
        max += 1;
        candidate = `fn-${max + 1}`;
    }

    return candidate;
}

function getAllFootnoteIds(model) {
    const ids = new Set();

    for (const footnote of getAllFootnotes(model)) {
        const id = `${footnote.getAttribute('footnoteId') || ''}`.trim();

        if (id) {
            ids.add(id);
        }
    }

    return ids;
}