import { Plugin, Widget, toWidget, Command, ButtonView } from 'ckeditor5';

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const CLIPBOARD_MIME_TYPE = 'application/x-verbb-footnotes+json';
const MAX_CLIPBOARD_PAYLOAD_BYTES = 1048576;
const MAX_CLIPBOARD_DEFINITIONS = 100;
class FootnotesCommand extends Command {
    refresh() {
        const selection = this.editor.model.document.selection;
        const selectedReference = getSelectedReference(selection);
        const position = selection.getFirstPosition();
        this.value = selectedReference;
        this.isEnabled = !!selectedReference || !!position && !getAncestor(position, 'footnoteItem') && this.editor.model.schema.checkChild(position.parent, 'footnoteReference');
    }
    execute(options = {}) {
        const editor = this.editor;
        const model = editor.model;
        const selection = model.document.selection;
        const selectedReference = getSelectedReference(selection);
        const requestedId = typeof options.footnoteId === 'string' ? options.footnoteId : '';
        const footnoteId = requestedId || selectedReference?.getAttribute('footnoteId');
        if (footnoteId) {
            focusDefinition(model, footnoteId);
            return;
        }
        const selectedText = extractModelText(model.getSelectedContent(selection)).trim();
        model.change((writer)=>{
            const noteId = createUuid();
            const referenceId = createUuid();
            const reference = writer.createElement('footnoteReference', {
                footnoteId: noteId,
                footnoteReferenceId: referenceId,
                footnoteNumber: '1',
                footnoteText: selectedText
            });
            model.insertContent(reference);
            let list = findFootnoteList(model);
            if (!list) {
                list = writer.createElement('footnoteList');
                writer.insert(list, model.document.getRoot(), 'end');
            }
            const item = writer.createElement('footnoteItem', {
                footnoteId: noteId,
                footnoteNumber: '1',
                footnoteReferenceIds: JSON.stringify([
                    referenceId
                ])
            });
            const paragraph = writer.createElement('paragraph');
            writer.append(item, list);
            writer.append(paragraph, item);
            if (selectedText) {
                writer.appendText(selectedText, paragraph);
            }
            writer.setSelection(paragraph, 'end');
        });
    }
}
class RemoveFootnoteCommand extends Command {
    refresh() {
        this.value = getSelectedReference(this.editor.model.document.selection);
        this.isEnabled = !!this.value;
    }
    execute() {
        const model = this.editor.model;
        const reference = getSelectedReference(model.document.selection);
        if (!reference) {
            return;
        }
        model.change((writer)=>{
            writer.remove(reference);
        });
    }
}
class FootnotesEditing extends Plugin {
    static get pluginName() {
        return 'FootnotesEditing';
    }
    static get requires() {
        return [
            Widget
        ];
    }
    init() {
        const editor = this.editor;
        const schema = editor.model.schema;
        editor.editing.view.domConverter.registerInlineObjectMatcher((element)=>{
            return element.classList?.contains('footnote-reference') ? {
                name: true
            } : null;
        });
        schema.register('footnoteReference', {
            allowWhere: '$text',
            allowAttributes: [
                'footnoteId',
                'footnoteReferenceId',
                'footnoteNumber',
                'footnoteText'
            ],
            isInline: true,
            isObject: true
        });
        schema.register('footnoteList', {
            allowIn: '$root',
            isLimit: true
        });
        schema.register('footnoteItem', {
            allowIn: 'footnoteList',
            allowAttributes: [
                'footnoteId',
                'footnoteNumber',
                'footnoteReferenceIds'
            ],
            isLimit: true
        });
        schema.extend('paragraph', {
            allowIn: 'footnoteItem'
        });
        if (schema.isRegistered('listItem')) {
            schema.extend('listItem', {
                allowIn: 'footnoteItem'
            });
        }
        schema.addChildCheck((context, childDefinition)=>{
            const insideDefinition = Array.from(context.getNames()).includes('footnoteItem');
            if (insideDefinition && childDefinition.name === 'footnoteReference') {
                return false;
            }
            if (context.endsWith('footnoteItem') && ![
                'paragraph',
                'listItem'
            ].includes(childDefinition.name)) {
                return false;
            }
            return undefined;
        });
        this._registerUpcast();
        this._registerDataDowncast();
        this._registerEditingDowncast();
        editor.commands.add('footnotes', new FootnotesCommand(editor));
        editor.commands.add('removeFootnote', new RemoveFootnoteCommand(editor));
        editor.model.document.registerPostFixer((writer)=>normalizeDocument(editor.model, writer));
        this._registerClipboardHandling();
    }
    _registerUpcast() {
        const editor = this.editor;
        editor.conversion.for('upcast').elementToElement({
            view: {
                name: 'ol',
                attributes: {
                    'data-footnotes': true
                }
            },
            model: 'footnoteList',
            converterPriority: 'high'
        });
        editor.conversion.for('upcast').elementToElement({
            view: {
                name: 'ol',
                classes: 'footnotes'
            },
            model: 'footnoteList',
            converterPriority: 'high'
        });
        editor.conversion.for('upcast').elementToElement({
            view: {
                name: 'li',
                attributes: {
                    'data-footnote-id': true
                }
            },
            model: (viewElement, { writer })=>createFootnoteItem(viewElement, writer),
            converterPriority: 'high'
        });
        editor.conversion.for('upcast').elementToElement({
            view: {
                name: 'li',
                classes: 'footnote-item'
            },
            model: (viewElement, { writer })=>createFootnoteItem(viewElement, writer),
            converterPriority: 'high'
        });
        editor.conversion.for('upcast').add((dispatcher)=>{
            dispatcher.on('element:sup', (evt, data, conversionApi)=>{
                const viewElement = data.viewItem;
                const anchor = findViewAnchor(viewElement);
                const isCanonical = viewElement.hasAttribute('data-footnote-reference') || viewElement.hasClass('footnote-reference');
                const isLegacy = viewElement.hasClass('footnote') && !isCanonical;
                if (!isCanonical && !isLegacy) {
                    return;
                }
                if (!conversionApi.consumable.consume(viewElement, {
                    name: true
                })) {
                    return;
                }
                const noteId = isCanonical ? viewElement.getAttribute('data-footnote-id') || anchor?.getAttribute('data-footnote-id') || idFromFragment(anchor?.getAttribute('href'), 'fn') || createUuid() : createUuid();
                const referenceId = viewElement.getAttribute('data-footnote-reference-id') || anchor?.getAttribute('data-footnote-reference-id') || idFromFragment(anchor?.getAttribute('id'), 'fnref') || createUuid();
                const text = viewElement.getAttribute('data-footnote-text') || anchor?.getAttribute('data-footnote-text') || (isLegacy ? extractViewText(viewElement).trim() : '');
                const reference = conversionApi.writer.createElement('footnoteReference', {
                    footnoteId: noteId,
                    footnoteReferenceId: referenceId,
                    footnoteNumber: extractViewText(viewElement).trim(),
                    footnoteText: text
                });
                if (!conversionApi.safeInsert(reference, data.modelCursor)) {
                    return;
                }
                conversionApi.updateConversionResult(reference, data);
                evt.stop();
            }, {
                priority: 'high'
            });
            dispatcher.on('element:a', (evt, data, conversionApi)=>{
                const viewElement = data.viewItem;
                if (!viewElement.hasAttribute('data-footnote-backlink') && !viewElement.hasClass('footnote-backlink')) {
                    return;
                }
                if (!conversionApi.consumable.consume(viewElement, {
                    name: true
                })) {
                    return;
                }
                data.modelRange = conversionApi.writer.createRange(data.modelCursor);
                evt.stop();
            }, {
                priority: 'highest'
            });
        });
    }
    _registerDataDowncast() {
        const editor = this.editor;
        editor.conversion.for('dataDowncast').elementToElement({
            model: 'footnoteList',
            view: (_modelItem, { writer })=>writer.createContainerElement('ol', {
                    class: 'footnotes',
                    'data-footnotes': '',
                    role: 'doc-endnotes',
                    'aria-label': editor.t('Footnotes')
                })
        });
        editor.conversion.for('dataDowncast').elementToStructure({
            model: {
                name: 'footnoteItem',
                attributes: [
                    'footnoteId',
                    'footnoteNumber',
                    'footnoteReferenceIds'
                ]
            },
            view: (modelItem, { writer })=>{
                const noteId = modelItem.getAttribute('footnoteId') || '';
                const number = modelItem.getAttribute('footnoteNumber') || '';
                const referenceIds = parseReferenceIds(modelItem.getAttribute('footnoteReferenceIds'));
                const children = [
                    writer.createSlot()
                ];
                referenceIds.forEach((referenceId, index)=>{
                    children.push(writer.createContainerElement('a', {
                        class: 'footnote-backlink',
                        'data-footnote-backlink': '',
                        href: `#fnref-${referenceId}`,
                        role: 'doc-backlink',
                        'aria-label': referenceIds.length > 1 ? editor.t('Back to footnote %0, reference %1', [
                            number,
                            index + 1
                        ]) : editor.t('Back to footnote %0', number)
                    }, [
                        writer.createText('↩')
                    ]));
                });
                return writer.createContainerElement('li', {
                    class: 'footnote-item',
                    'data-footnote-id': noteId,
                    'data-footnote-number': number,
                    'data-footnote-reference-ids': JSON.stringify(referenceIds),
                    id: `fn-${noteId}`,
                    role: 'doc-endnote'
                }, children);
            }
        });
        editor.conversion.for('dataDowncast').elementToElement({
            model: {
                name: 'footnoteReference',
                attributes: [
                    'footnoteId',
                    'footnoteReferenceId',
                    'footnoteNumber',
                    'footnoteText'
                ]
            },
            view: (modelItem, { writer })=>{
                const noteId = modelItem.getAttribute('footnoteId') || '';
                const referenceId = modelItem.getAttribute('footnoteReferenceId') || '';
                const number = modelItem.getAttribute('footnoteNumber') || '';
                const text = modelItem.getAttribute('footnoteText') || '';
                const anchor = writer.createContainerElement('a', {
                    id: `fnref-${referenceId}`,
                    href: `#fn-${noteId}`,
                    'data-footnote-id': noteId,
                    'data-footnote-reference-id': referenceId,
                    'data-footnote-text': text,
                    role: 'doc-noteref',
                    'aria-label': editor.t('Footnote %0', number)
                }, [
                    writer.createText(number)
                ]);
                return writer.createContainerElement('sup', {
                    class: 'footnote footnote-reference',
                    'data-footnote-reference': '',
                    'data-footnote-id': noteId,
                    'data-footnote-reference-id': referenceId,
                    'data-footnote-text': text
                }, [
                    anchor
                ]);
            }
        });
    }
    _registerEditingDowncast() {
        const editor = this.editor;
        editor.conversion.for('editingDowncast').elementToElement({
            model: 'footnoteList',
            view: (_modelItem, { writer })=>writer.createContainerElement('ol', {
                    class: 'footnotes ck-footnotes',
                    'data-footnotes': '',
                    'aria-label': editor.t('Footnotes')
                })
        });
        editor.conversion.for('editingDowncast').elementToElement({
            model: {
                name: 'footnoteItem',
                attributes: [
                    'footnoteId',
                    'footnoteNumber'
                ]
            },
            view: (modelItem, { writer })=>writer.createContainerElement('li', {
                    class: 'footnote-item ck-footnote-item',
                    'data-footnote-id': modelItem.getAttribute('footnoteId') || '',
                    'data-footnote-number': modelItem.getAttribute('footnoteNumber') || ''
                })
        });
        editor.conversion.for('editingDowncast').elementToElement({
            model: {
                name: 'footnoteReference',
                attributes: [
                    'footnoteId',
                    'footnoteReferenceId',
                    'footnoteNumber',
                    'footnoteText'
                ]
            },
            view: (modelItem, { writer })=>{
                const number = modelItem.getAttribute('footnoteNumber') || '?';
                const marker = writer.createContainerElement('sup', {
                    class: 'footnote footnote-reference footnote-marker',
                    'data-footnote-id': modelItem.getAttribute('footnoteId') || '',
                    'data-footnote-reference-id': modelItem.getAttribute('footnoteReferenceId') || '',
                    title: modelItem.getAttribute('footnoteText') || ''
                }, [
                    writer.createText(number)
                ]);
                return toWidget(marker, writer, {
                    label: editor.t('Footnote %0', number)
                });
            }
        });
    }
    _registerClipboardHandling() {
        const editor = this.editor;
        const clipboard = editor.plugins.get('ClipboardPipeline');
        this.listenTo(clipboard, 'outputTransformation', (_event, data)=>{
            const noteIds = getAllFromNode(data.content, 'footnoteReference').map((reference)=>`${reference.getAttribute('footnoteId') || ''}`).filter(Boolean);
            if (!noteIds.length || typeof DOMParser === 'undefined') {
                return;
            }
            const document = new DOMParser().parseFromString(editor.getData(), 'text/html');
            const definitions = [];
            for (const noteId of [
                ...new Set(noteIds)
            ].slice(0, MAX_CLIPBOARD_DEFINITIONS)){
                const item = Array.from(document.querySelectorAll('li.footnote-item')).find((candidate)=>{
                    return candidate.getAttribute('data-footnote-id') === noteId || candidate.id === `fn-${noteId}`;
                });
                if (item) {
                    definitions.push({
                        id: noteId,
                        html: item.outerHTML
                    });
                }
            }
            if (!definitions.length) {
                return;
            }
            const payload = JSON.stringify({
                version: 1,
                definitions
            });
            if (payload.length <= MAX_CLIPBOARD_PAYLOAD_BYTES) {
                data.dataTransfer.setData(CLIPBOARD_MIME_TYPE, payload);
            }
        }, {
            priority: 'high'
        });
        this.listenTo(clipboard, 'inputTransformation', (_event, data)=>{
            const payload = parseClipboardPayload(data.dataTransfer.getData(CLIPBOARD_MIME_TYPE));
            if (!payload || typeof DOMParser === 'undefined') {
                return;
            }
            const html = editor.data.htmlProcessor.toData(data.content);
            const document = new DOMParser().parseFromString(html, 'text/html');
            const referencedIds = getReferenceIdsFromDocument(document);
            const existingIds = new Set(Array.from(document.querySelectorAll('li.footnote-item')).map((item)=>{
                return item.getAttribute('data-footnote-id') || idFromFragment(item.id, 'fn');
            }));
            const currentIds = new Set(getAll(editor.model, 'footnoteItem').map((item)=>`${item.getAttribute('footnoteId') || ''}`));
            const definitions = payload.definitions.filter((definition)=>{
                return referencedIds.has(definition.id) && !existingIds.has(definition.id) && (data.sourceEditorId !== editor.id || !currentIds.has(definition.id));
            });
            if (!definitions.length) {
                return;
            }
            const definitionsHtml = definitions.map((definition)=>definition.html).join('');
            data.content = editor.data.htmlProcessor.toView(`${html}<ol class="footnotes" data-footnotes="">${definitionsHtml}</ol>`);
        }, {
            priority: 'high'
        });
        this.listenTo(clipboard, 'contentInsertion', (_event, data)=>{
            if (data.sourceEditorId === editor.id) {
                return;
            }
            const references = getAllFromNode(data.content, 'footnoteReference');
            if (!references.length) {
                return;
            }
            const noteIds = new Map();
            editor.model.change((writer)=>{
                for (const reference of references){
                    const oldNoteId = `${reference.getAttribute('footnoteId') || ''}`;
                    const noteId = noteIds.get(oldNoteId) || createUuid();
                    noteIds.set(oldNoteId, noteId);
                    writer.setAttribute('footnoteId', noteId, reference);
                    writer.setAttribute('footnoteReferenceId', createUuid(), reference);
                }
                for (const item of getAllFromNode(data.content, 'footnoteItem')){
                    const oldNoteId = `${item.getAttribute('footnoteId') || ''}`;
                    if (noteIds.has(oldNoteId)) {
                        writer.setAttribute('footnoteId', noteIds.get(oldNoteId), item);
                        writer.setAttribute('footnoteReferenceIds', '[]', item);
                    }
                }
            });
        }, {
            priority: 'high'
        });
    }
}
function focusDefinition(model, footnoteId) {
    const item = findFootnoteItem(model, footnoteId);
    if (!item) {
        return false;
    }
    model.change((writer)=>{
        const firstBlock = item.getChild(0);
        writer.setSelection(firstBlock || item, firstBlock ? 0 : 'end');
    });
    return true;
}
function focusReference(model, footnoteId) {
    const reference = getAll(model, 'footnoteReference').find((item)=>item.getAttribute('footnoteId') === footnoteId);
    if (!reference) {
        return false;
    }
    model.change((writer)=>{
        writer.setSelection(reference, 'on');
    });
    return true;
}
function normalizeDocument(model, writer) {
    const references = getAll(model, 'footnoteReference');
    const lists = getAll(model, 'footnoteList');
    let list = lists[0] || null;
    let changed = false;
    if (!references.length) {
        for (const existingList of lists){
            writer.remove(existingList);
            changed = true;
        }
        return changed;
    }
    if (!list) {
        list = writer.createElement('footnoteList');
        writer.insert(list, model.document.getRoot(), 'end');
        changed = true;
    }
    for (const extraList of lists.slice(1)){
        for (const item of Array.from(extraList.getChildren())){
            writer.move(writer.createRangeOn(item), writer.createPositionAt(list, 'end'));
        }
        writer.remove(extraList);
        changed = true;
    }
    const itemsById = new Map();
    for (const item of Array.from(list.getChildren())){
        const noteId = `${item.getAttribute('footnoteId') || ''}`;
        if (noteId && !itemsById.has(noteId)) {
            itemsById.set(noteId, item);
        } else {
            writer.remove(item);
            changed = true;
        }
    }
    const usedReferenceIds = new Set();
    const noteOrder = [];
    const noteNumbers = new Map();
    const referenceIdsByNote = new Map();
    for (const reference of references){
        let noteId = `${reference.getAttribute('footnoteId') || ''}`;
        if (!itemsById.has(noteId)) {
            noteId = createUuid();
            writer.setAttribute('footnoteId', noteId, reference);
            const item = writer.createElement('footnoteItem', {
                footnoteId: noteId
            });
            const paragraph = writer.createElement('paragraph');
            const fallbackText = `${reference.getAttribute('footnoteText') || ''}`;
            writer.append(item, list);
            writer.append(paragraph, item);
            if (fallbackText) {
                writer.appendText(fallbackText, paragraph);
            }
            itemsById.set(noteId, item);
            changed = true;
        }
        let referenceId = `${reference.getAttribute('footnoteReferenceId') || ''}`;
        if (!UUID_PATTERN.test(referenceId) || usedReferenceIds.has(referenceId)) {
            referenceId = createUuid();
            writer.setAttribute('footnoteReferenceId', referenceId, reference);
            changed = true;
        }
        usedReferenceIds.add(referenceId);
        if (!noteNumbers.has(noteId)) {
            noteOrder.push(noteId);
            noteNumbers.set(noteId, noteOrder.length);
            referenceIdsByNote.set(noteId, []);
        }
        referenceIdsByNote.get(noteId).push(referenceId);
        const number = `${noteNumbers.get(noteId)}`;
        const text = extractModelText(itemsById.get(noteId)).trim().slice(0, 5000);
        if (reference.getAttribute('footnoteNumber') !== number) {
            writer.setAttribute('footnoteNumber', number, reference);
            changed = true;
        }
        if (reference.getAttribute('footnoteText') !== text) {
            writer.setAttribute('footnoteText', text, reference);
            changed = true;
        }
    }
    for (const [noteId, item] of itemsById){
        if (!noteNumbers.has(noteId)) {
            writer.remove(item);
            changed = true;
        }
    }
    noteOrder.forEach((noteId, index)=>{
        const item = itemsById.get(noteId);
        const number = `${index + 1}`;
        const referenceIds = JSON.stringify(referenceIdsByNote.get(noteId));
        if (item.index !== index) {
            writer.move(writer.createRangeOn(item), writer.createPositionAt(list, index));
            changed = true;
        }
        if (item.getAttribute('footnoteNumber') !== number) {
            writer.setAttribute('footnoteNumber', number, item);
            changed = true;
        }
        if (item.getAttribute('footnoteReferenceIds') !== referenceIds) {
            writer.setAttribute('footnoteReferenceIds', referenceIds, item);
            changed = true;
        }
    });
    return changed;
}
function getSelectedReference(selection) {
    const selectedElement = selection.getSelectedElement();
    if (selectedElement?.is('element', 'footnoteReference')) {
        return selectedElement;
    }
    const position = selection.getFirstPosition();
    if (position?.parent?.is('element', 'footnoteReference')) {
        return position.parent;
    }
    return null;
}
function getAncestor(position, name) {
    let parent = position?.parent || null;
    while(parent){
        if (parent.is?.('element', name)) {
            return parent;
        }
        parent = parent.parent;
    }
    return null;
}
function getAll(model, name) {
    const items = [];
    for (const root of model.document.getRoots()){
        if (root.rootName === '$graveyard') {
            continue;
        }
        for (const item of model.createRangeIn(root).getItems()){
            if (item.is('element', name)) {
                items.push(item);
            }
        }
    }
    return items;
}
function getAllFromNode(node, name) {
    const items = [];
    if (!node?.getChildren) {
        return items;
    }
    for (const child of node.getChildren()){
        if (child.is?.('element', name)) {
            items.push(child);
        }
        items.push(...getAllFromNode(child, name));
    }
    return items;
}
function findFootnoteList(model) {
    return getAll(model, 'footnoteList')[0] || null;
}
function findFootnoteItem(model, footnoteId) {
    return getAll(model, 'footnoteItem').find((item)=>item.getAttribute('footnoteId') === footnoteId) || null;
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
    for (const child of node.getChildren()){
        const childText = extractModelText(child);
        if (text && child.is?.('element') && childText) {
            text += ' ';
        }
        text += childText;
    }
    return text;
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
    for (const child of node.getChildren()){
        text += extractViewText(child);
    }
    return text;
}
function parseReferenceIds(value) {
    try {
        const ids = JSON.parse(`${value || '[]'}`);
        return Array.isArray(ids) ? ids.filter((id)=>typeof id === 'string' && id) : [];
    } catch  {
        return [];
    }
}
function createFootnoteItem(viewElement, writer) {
    const noteId = viewElement.getAttribute('data-footnote-id') || idFromFragment(viewElement.getAttribute('id'), 'fn') || createUuid();
    return writer.createElement('footnoteItem', {
        footnoteId: noteId,
        footnoteNumber: viewElement.getAttribute('data-footnote-number') || '',
        footnoteReferenceIds: viewElement.getAttribute('data-footnote-reference-ids') || '[]'
    });
}
function findViewAnchor(viewElement) {
    for (const child of viewElement.getChildren()){
        if (child.is?.('element', 'a')) {
            return child;
        }
    }
    return null;
}
function idFromFragment(value, prefix) {
    const match = `${value || ''}`.match(new RegExp(`^#?${prefix}-(.+)$`));
    return match?.[1] || '';
}
function getReferenceIdsFromDocument(document) {
    return new Set(Array.from(document.querySelectorAll('sup.footnote-reference')).map((reference)=>{
        const anchor = reference.querySelector('a');
        return reference.getAttribute('data-footnote-id') || anchor?.getAttribute('data-footnote-id') || idFromFragment(anchor?.getAttribute('href'), 'fn');
    }).filter(Boolean));
}
function parseClipboardPayload(value) {
    if (!value || value.length > MAX_CLIPBOARD_PAYLOAD_BYTES) {
        return null;
    }
    try {
        const payload = JSON.parse(value);
        if (payload?.version !== 1 || !Array.isArray(payload.definitions)) {
            return null;
        }
        const definitions = payload.definitions.slice(0, MAX_CLIPBOARD_DEFINITIONS).filter((definition)=>{
            return definition && typeof definition.id === 'string' && typeof definition.html === 'string' && UUID_PATTERN.test(definition.id) && definition.html.length <= MAX_CLIPBOARD_PAYLOAD_BYTES;
        });
        return definitions.length ? {
            version: 1,
            definitions
        } : null;
    } catch  {
        return null;
    }
}
function createUuid() {
    if (typeof crypto?.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = bytes[6] & 0x0f | 0x40;
    bytes[8] = bytes[8] & 0x3f | 0x80;
    const hex = [
        ...bytes
    ].map((byte)=>byte.toString(16).padStart(2, '0'));
    return `${hex.slice(0, 4).join('')}-${hex.slice(4, 6).join('')}-${hex.slice(6, 8).join('')}-${hex.slice(8, 10).join('')}-${hex.slice(10).join('')}`;
}

var icon = "<?xml version=\"1.0\" encoding=\"utf-8\"?><svg version=\"1.1\" id=\"Layer_1\" xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\" x=\"0px\" y=\"0px\" viewBox=\"0 0 20 20\" style=\"enable-background:new 0 0 20 20;\" xml:space=\"preserve\"><path d=\"M8.8,7.9v-2C8.8,5,9.3,4.4,10,4.4s1.3,0.6,1.3,1.5v1.9c0.6-0.3,1.1-0.6,1.5-0.9c0.8-0.5,1.5-0.3,1.9,0.3C15.1,7.9,14.8,8.5,14,9c-0.4,0.4-0.9,0.7-1.5,1c0.6,0.4,1.2,0.7,1.8,1.1c0.7,0.4,0.9,1.2,0.5,1.8s-1,0.7-1.8,0.3c-0.5-0.3-1-0.6-1.7-1v1.8c0,0.9-0.5,1.4-1.2,1.4c-0.8,0-1.2-0.5-1.3-1.4v-1.9c-0.7,0.4-1.2,0.7-1.8,1c-0.7,0.5-1.5,0.3-1.8-0.3C4.8,12.2,5,11.5,5.8,11c0.5-0.3,1-0.6,1.7-1C6.9,9.7,6.4,9.4,5.9,9.1C5.1,8.6,4.9,8,5.2,7.3C5.5,6.7,6.3,6.5,7,6.9C7.6,7.2,8.1,7.5,8.8,7.9z\"/></svg>\n";

class FootnotesUI extends Plugin {
    static get pluginName() {
        return 'FootnotesUI';
    }
    init() {
        const editor = this.editor;
        const viewDocument = editor.editing.view.document;
        editor.ui.componentFactory.add('footnotes', (locale)=>{
            const command = editor.commands.get('footnotes');
            const view = new ButtonView(locale);
            view.set({
                label: editor.t('Footnote'),
                icon,
                tooltip: true
            });
            view.bind('isEnabled').to(command, 'isEnabled');
            this.listenTo(view, 'execute', ()=>{
                editor.execute('footnotes');
                editor.editing.view.focus();
            });
            return view;
        });
        this.listenTo(viewDocument, 'click', (_evt, data)=>{
            const marker = data.domTarget?.closest?.('sup.footnote-marker');
            const footnoteId = marker?.getAttribute('data-footnote-id') || '';
            if (footnoteId && focusDefinition(editor.model, footnoteId)) {
                editor.editing.view.focus();
                data.preventDefault();
            }
        });
        editor.keystrokes.set('Ctrl+Enter', (_data, cancel)=>{
            if (this._returnToReference()) {
                cancel();
            }
        });
    }
    _returnToReference() {
        const editor = this.editor;
        const position = editor.model.document.selection.getFirstPosition();
        let parent = position?.parent || null;
        while(parent && !parent.is?.('element', 'footnoteItem')){
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

class Footnotes extends Plugin {
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

export { Footnotes };
//# sourceMappingURL=index.js.map
