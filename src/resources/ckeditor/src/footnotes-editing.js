import { Command, Plugin, toWidget, Widget } from 'ckeditor5';

const FOOTNOTE_UPCAST_ID_STORE_KEY = 'verbbFootnotesUpcastId';

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

                // Upcast builds a detached fragment, so every marker in this conversion sees the
                // same document state. Reuse its provisional ID and repair duplicates in one pass.
                let footnoteId = conversionApi.store[FOOTNOTE_UPCAST_ID_STORE_KEY];

                if (!footnoteId) {
                    footnoteId = getNextFootnoteId(editor.model);
                    conversionApi.store[FOOTNOTE_UPCAST_ID_STORE_KEY] = footnoteId;
                }

                const footnote = conversionApi.writer.createElement('footnote', {
                    footnoteText: extractViewText(viewElement).trim(),
                    footnoteId,
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
            const footnotes = getAllFootnotes(editor.model);
            const allocateFootnoteId = createFootnoteIdAllocator(footnotes);

            for (const footnote of footnotes) {
                const expected = `${number++}`;

                if (footnote.getAttribute('footnoteNumber') !== expected) {
                    writer.setAttribute('footnoteNumber', expected, footnote);
                    changed = true;
                }

                const currentId = `${footnote.getAttribute('footnoteId') || ''}`.trim();
                const hasValidAndUniqueId = currentId && !usedIds.has(currentId);

                if (!hasValidAndUniqueId) {
                    const nextId = allocateFootnoteId();
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

function getNextFootnoteId(model) {
    return createFootnoteIdAllocator(getAllFootnotes(model))();
}

function createFootnoteIdAllocator(footnotes) {
    const usedIds = new Set();
    let max = 0;

    // Reserve every existing ID before allocating so replacements never displace an ID that
    // appears later in model traversal order.
    for (const footnote of footnotes) {
        const id = `${footnote.getAttribute('footnoteId') || ''}`.trim();

        if (!id) {
            continue;
        }

        usedIds.add(id);

        const match = /^fn-(\d+)$/.exec(id);

        if (match) {
            max = Math.max(max, parseInt(match[1], 10));
        }
    }

    return () => {
        let candidate = `fn-${max + 1}`;

        while (usedIds.has(candidate)) {
            max += 1;
            candidate = `fn-${max + 1}`;
        }

        max += 1;
        usedIds.add(candidate);

        return candidate;
    };
}
