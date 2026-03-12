import { Plugin, Widget, toWidget, Command, ContextualBalloon, ButtonView, clickOutsideHandler, View } from 'ckeditor5';

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
        model.change((writer)=>{
            const footnoteText = getInitialFootnoteText(model, selection, options);
            const footnoteNumber = getNextFootnoteNumber(model);
            const footnoteId = getNextFootnoteId(model);
            const footnote = writer.createElement('footnote', {
                footnoteText,
                footnoteNumber,
                footnoteId
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
        model.change((writer)=>{
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
        model.change((writer)=>{
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
    for (const child of node.getChildren()){
        text += extractViewText(child);
    }
    return text;
}
function getAllFootnotes(model) {
    const footnotes = [];
    for (const root of model.document.getRoots()){
        if (root.rootName === '$graveyard') {
            continue;
        }
        for (const item of model.createRangeIn(root).getItems()){
            if (item.is('element', 'footnote')) {
                footnotes.push(item);
            }
        }
    }
    return footnotes;
}
function getNextFootnoteNumber(model) {
    let max = 0;
    for (const footnote of getAllFootnotes(model)){
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
    for (const child of node.getChildren()){
        text += extractModelText(child);
    }
    return text;
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
        editor.editing.view.domConverter.registerInlineObjectMatcher((element)=>{
            return element.classList?.contains('footnote-marker') ? {
                name: true
            } : null;
        });
        editor.model.schema.register('footnote', {
            allowWhere: '$text',
            allowAttributes: [
                'footnoteText',
                'footnoteNumber',
                'footnoteId'
            ],
            isInline: true,
            isObject: true
        });
        editor.conversion.for('upcast').add((dispatcher)=>{
            dispatcher.on('element:sup', (evt, data, conversionApi)=>{
                const viewElement = data.viewItem;
                if (!viewElement.hasClass('footnote')) {
                    return;
                }
                if (!conversionApi.consumable.consume(viewElement, {
                    name: true,
                    classes: [
                        'footnote'
                    ]
                })) {
                    return;
                }
                const footnote = conversionApi.writer.createElement('footnote', {
                    footnoteText: extractViewText(viewElement).trim(),
                    footnoteId: getNextFootnoteId(editor.model)
                });
                if (!conversionApi.safeInsert(footnote, data.modelCursor)) {
                    return;
                }
                conversionApi.updateConversionResult(footnote, data);
                evt.stop();
            }, {
                priority: 'high'
            });
        });
        editor.conversion.for('dataDowncast').elementToElement({
            model: {
                name: 'footnote',
                attributes: [
                    'footnoteText',
                    'footnoteNumber',
                    'footnoteId'
                ]
            },
            view: (modelItem, { writer })=>{
                const footnoteText = modelItem.getAttribute('footnoteText') || '';
                const footnoteId = modelItem.getAttribute('footnoteId') || '';
                const sup = writer.createContainerElement('sup', {
                    class: 'footnote',
                    'data-footnote-id': footnoteId
                });
                if (footnoteText) {
                    writer.insert(writer.createPositionAt(sup, 0), writer.createText(footnoteText));
                }
                return sup;
            }
        });
        editor.conversion.for('editingDowncast').elementToElement({
            model: {
                name: 'footnote',
                attributes: [
                    'footnoteText',
                    'footnoteNumber',
                    'footnoteId'
                ]
            },
            view: (modelItem, { writer })=>{
                const number = modelItem.getAttribute('footnoteNumber') || '?';
                const footnoteText = modelItem.getAttribute('footnoteText') || '';
                const footnoteId = modelItem.getAttribute('footnoteId') || '';
                const numberElement = writer.createUIElement('span', {
                    class: 'footnote-marker__number'
                }, function(domDocument) {
                    const domElement = this.toDomElement(domDocument);
                    domElement.textContent = number;
                    return domElement;
                });
                const marker = writer.createContainerElement('sup', {
                    class: 'footnote footnote-marker',
                    title: footnoteText,
                    'data-footnote-id': footnoteId
                }, [
                    numberElement
                ]);
                return toWidget(marker, writer, {
                    label: `Footnote ${number}`
                });
            }
        });
        editor.commands.add('footnotes', new InsertFootnoteCommand(editor));
        editor.commands.add('removeFootnote', new RemoveFootnoteCommand(editor));
        editor.commands.add('updateFootnoteText', new UpdateFootnoteTextCommand(editor));
        editor.model.document.registerPostFixer((writer)=>{
            let changed = false;
            let number = 1;
            const usedIds = new Set();
            for (const footnote of getAllFootnotes(editor.model)){
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
    for (const id of reservedIds){
        usedIds.add(id);
    }
    let max = 0;
    for (const id of usedIds){
        const match = /^fn-(\d+)$/.exec(id);
        if (match) {
            max = Math.max(max, parseInt(match[1], 10));
        }
    }
    let candidate = `fn-${max + 1}`;
    while(usedIds.has(candidate)){
        max += 1;
        candidate = `fn-${max + 1}`;
    }
    return candidate;
}
function getAllFootnoteIds(model) {
    const ids = new Set();
    for (const footnote of getAllFootnotes(model)){
        const id = `${footnote.getAttribute('footnoteId') || ''}`.trim();
        if (id) {
            ids.add(id);
        }
    }
    return ids;
}

var icon = "<?xml version=\"1.0\" encoding=\"utf-8\"?><svg version=\"1.1\" id=\"Layer_1\" xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\" x=\"0px\" y=\"0px\" viewBox=\"0 0 20 20\" style=\"enable-background:new 0 0 20 20;\" xml:space=\"preserve\"><path d=\"M8.8,7.9v-2C8.8,5,9.3,4.4,10,4.4s1.3,0.6,1.3,1.5v1.9c0.6-0.3,1.1-0.6,1.5-0.9c0.8-0.5,1.5-0.3,1.9,0.3C15.1,7.9,14.8,8.5,14,9c-0.4,0.4-0.9,0.7-1.5,1c0.6,0.4,1.2,0.7,1.8,1.1c0.7,0.4,0.9,1.2,0.5,1.8s-1,0.7-1.8,0.3c-0.5-0.3-1-0.6-1.7-1v1.8c0,0.9-0.5,1.4-1.2,1.4c-0.8,0-1.2-0.5-1.3-1.4v-1.9c-0.7,0.4-1.2,0.7-1.8,1c-0.7,0.5-1.5,0.3-1.8-0.3C4.8,12.2,5,11.5,5.8,11c0.5-0.3,1-0.6,1.7-1C6.9,9.7,6.4,9.4,5.9,9.1C5.1,8.6,4.9,8,5.2,7.3C5.5,6.7,6.3,6.5,7,6.9C7.6,7.2,8.1,7.5,8.8,7.9z\"/></svg>\n";

class FootnotesActionsView extends View {
    constructor(locale){
        super(locale);
        const t = this.t;
        const bind = this.bindTemplate;
        this.inputValue = '';
        this.saveButtonView = new ButtonView(locale);
        this.removeButtonView = new ButtonView(locale);
        this.cancelButtonView = new ButtonView(locale);
        this.saveButtonView.set({
            label: t('Save'),
            withText: true
        });
        this.removeButtonView.set({
            label: t('Remove footnote'),
            withText: true
        });
        this.cancelButtonView.set({
            label: t('Cancel'),
            withText: true
        });
        const children = this.createCollection([
            this.saveButtonView,
            this.removeButtonView,
            this.cancelButtonView
        ]);
        this.buttonsView = new View(locale);
        this.buttonsView.setTemplate({
            tag: 'div',
            attributes: {
                class: [
                    'ck-footnotes-actions-buttons'
                ]
            },
            children
        });
        this.setTemplate({
            tag: 'div',
            attributes: {
                class: [
                    'ck',
                    'ck-footnotes-actions'
                ]
            },
            children: [
                {
                    tag: 'input',
                    attributes: {
                        class: [
                            'ck',
                            'ck-input',
                            'ck-footnotes-actions-input'
                        ],
                        type: 'text',
                        placeholder: t('Footnote text'),
                        value: bind.to('inputValue')
                    },
                    on: {
                        input: bind.to((evt)=>{
                            this.inputValue = evt.target.value;
                        }),
                        keydown: bind.to((evt)=>{
                            if (evt.key === 'Enter') {
                                this.fire('submit');
                                evt.preventDefault();
                            }
                            if (evt.key === 'Escape') {
                                this.fire('cancel');
                                evt.preventDefault();
                            }
                        })
                    }
                },
                this.buttonsView
            ]
        });
    }
    focusInput() {
        this.element.querySelector('.ck-footnotes-actions-input')?.focus();
    }
    setInputValue(value) {
        this.inputValue = value;
        if (this.element) {
            const input = this.element.querySelector('.ck-footnotes-actions-input');
            if (input) {
                input.value = value;
            }
        }
    }
    destroy() {
        super.destroy();
        this.saveButtonView.destroy();
        this.removeButtonView.destroy();
        this.cancelButtonView.destroy();
        this.buttonsView.destroy();
    }
}
class FootnotesUI extends Plugin {
    static get pluginName() {
        return 'FootnotesUI';
    }
    static get requires() {
        return [
            ContextualBalloon
        ];
    }
    init() {
        const editor = this.editor;
        const t = editor.t;
        const viewDocument = editor.editing.view.document;
        this._balloon = editor.plugins.get(ContextualBalloon);
        this._actionsView = this._createActionsView();
        this._selectedFootnote = null;
        editor.ui.componentFactory.add('footnotes', (locale)=>{
            const insertCommand = editor.commands.get('footnotes');
            const removeCommand = editor.commands.get('removeFootnote');
            const view = new ButtonView(locale);
            view.set({
                label: t('Footnote'),
                icon: icon,
                tooltip: true,
                isToggleable: true
            });
            view.bind('isOn').to(removeCommand, 'value', (value)=>!!value);
            view.bind('isEnabled').to(insertCommand, 'isEnabled', removeCommand, 'isEnabled', (canInsert, canRemove)=>canInsert || canRemove);
            this.listenTo(view, 'execute', ()=>{
                if (removeCommand.value) {
                    this._showActionsBalloon();
                } else {
                    editor.execute('footnotes');
                    this._showActionsBalloon();
                }
                editor.editing.view.focus();
            });
            return view;
        });
        editor.keystrokes.set('Esc', (data, cancel)=>{
            if (this._isActionsVisible) {
                this._hideActionsBalloon();
                cancel();
            }
        });
        clickOutsideHandler({
            emitter: this._actionsView,
            activator: ()=>this._isActionsVisible,
            contextElements: ()=>[
                    this._balloon.view.element
                ],
            callback: ()=>{
                this._hideActionsBalloon();
            }
        });
        this.listenTo(viewDocument, 'click', (evt, data)=>{
            const footnoteFromClick = getFootnoteFromViewTarget(data, editor.editing.view.domConverter, editor.editing.mapper, editor.model);
            if (footnoteFromClick) {
                this._selectedFootnote = footnoteFromClick;
                this._showActionsBalloon(footnoteFromClick);
                return;
            }
            this._selectedFootnote = null;
            if (!this._isFocusInActionsView()) {
                this._hideActionsBalloon();
            }
        });
    }
    destroy() {
        super.destroy();
        this._actionsView.destroy();
    }
    get _isActionsVisible() {
        return this._balloon.hasView(this._actionsView);
    }
    _createActionsView() {
        const editor = this.editor;
        const actionsView = new FootnotesActionsView(editor.locale);
        this.listenTo(actionsView, 'submit', ()=>{
            this._runForSelectedFootnote(()=>{
                editor.execute('updateFootnoteText', {
                    text: actionsView.inputValue
                });
            });
            this._hideActionsBalloon();
            editor.editing.view.focus();
        });
        this.listenTo(actionsView.saveButtonView, 'execute', ()=>{
            this._runForSelectedFootnote(()=>{
                editor.execute('updateFootnoteText', {
                    text: actionsView.inputValue
                });
            });
            this._hideActionsBalloon();
            editor.editing.view.focus();
        });
        this.listenTo(actionsView.removeButtonView, 'execute', ()=>{
            this._runForSelectedFootnote(()=>{
                editor.execute('removeFootnote');
            });
            this._hideActionsBalloon();
            editor.editing.view.focus();
        });
        this.listenTo(actionsView, 'cancel', ()=>{
            this._hideActionsBalloon();
            editor.editing.view.focus();
        });
        this.listenTo(actionsView.cancelButtonView, 'execute', ()=>{
            this._hideActionsBalloon();
            editor.editing.view.focus();
        });
        return actionsView;
    }
    _showActionsBalloon(selectedFootnote = null) {
        const footnote = selectedFootnote || this._selectedFootnote || getFootnoteAncestor(this.editor.model.document.selection);
        if (!footnote) {
            return;
        }
        this._selectedFootnote = footnote;
        this._actionsView.setInputValue(footnote.getAttribute('footnoteText') || '');
        if (this._isActionsVisible) {
            this._refreshBalloonPosition();
            this._actionsView.focusInput();
            return;
        }
        this._balloon.add({
            view: this._actionsView,
            position: this._getBalloonPositionData()
        });
        this._actionsView.focusInput();
    }
    _hideActionsBalloon() {
        if (!this._isActionsVisible) {
            return;
        }
        this._selectedFootnote = null;
        this._balloon.remove(this._actionsView);
    }
    _runForSelectedFootnote(callback) {
        if (!this._selectedFootnote) {
            return;
        }
        const model = this.editor.model;
        model.change((writer)=>{
            writer.setSelection(this._selectedFootnote, 'on');
        });
        callback();
    }
    _isFocusInActionsView() {
        if (!this._actionsView.element) {
            return false;
        }
        const activeElement = this.editor.sourceElement?.ownerDocument?.activeElement || document.activeElement;
        return !!activeElement && this._actionsView.element.contains(activeElement);
    }
    _refreshBalloonPosition() {
        if (this._isActionsVisible) {
            this._balloon.updatePosition(this._getBalloonPositionData());
        }
    }
    _getBalloonPositionData() {
        const editor = this.editor;
        const editableElement = editor.ui.getEditableElement();
        const footnote = this._selectedFootnote || getFootnoteAncestor(editor.model.document.selection);
        return {
            target: ()=>{
                if (!footnote) {
                    return editableElement || null;
                }
                const viewElement = editor.editing.mapper.toViewElement(footnote);
                const domElement = viewElement ? editor.editing.view.domConverter.mapViewToDom(viewElement) : null;
                return domElement || editableElement || null;
            }
        };
    }
}
function getFootnoteAncestor(selection) {
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
function getFootnoteFromViewTarget(eventData, domConverter, mapper, model) {
    const domTarget = eventData?.domTarget || null;
    if (domTarget?.closest) {
        const domMarker = domTarget.closest('sup.footnote-marker');
        if (domMarker) {
            const footnoteId = domMarker.getAttribute('data-footnote-id') || '';
            const footnoteNumber = `${domMarker.textContent || ''}`.trim();
            if (footnoteId) {
                const modelById = getFootnoteById(model, footnoteId);
                if (modelById) {
                    return modelById;
                }
            }
            if (footnoteNumber) {
                const modelByNumber = getFootnoteByNumber(model, footnoteNumber);
                if (modelByNumber) {
                    return modelByNumber;
                }
            }
            const viewMarker = domConverter.mapDomToView(domMarker);
            const modelFromDomMarker = viewMarker ? mapper.toModelElement(viewMarker) : null;
            if (modelFromDomMarker) {
                return modelFromDomMarker;
            }
        }
    }
    const viewTarget = eventData?.target || domConverter.mapDomToView(domTarget);
    if (!viewTarget) {
        return null;
    }
    let marker = null;
    let current = viewTarget;
    while(current){
        if (current.is?.('element') && current.hasClass?.('footnote-marker')) {
            marker = current;
            break;
        }
        current = current.parent || null;
    }
    if (!marker) {
        return null;
    }
    return mapper.toModelElement(marker);
}
function getFootnoteById(model, footnoteId) {
    return findFootnote(model, (item)=>item.getAttribute('footnoteId') === footnoteId);
}
function getFootnoteByNumber(model, footnoteNumber) {
    return findFootnote(model, (item)=>`${item.getAttribute('footnoteNumber') || ''}` === footnoteNumber);
}
function findFootnote(model, predicate) {
    for (const root of model.document.getRoots()){
        if (root.rootName === '$graveyard') {
            continue;
        }
        for (const item of model.createRangeIn(root).getItems()){
            if (!item.is('element', 'footnote')) {
                continue;
            }
            if (predicate(item)) {
                return item;
            }
        }
    }
    return null;
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
