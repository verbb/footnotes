import { ButtonView, clickOutsideHandler, ContextualBalloon, Plugin, View } from 'ckeditor5';

import icon from '../theme/icon.svg';

class FootnotesActionsView extends View {
    constructor(locale) {
        super(locale);

        const t = this.t;
        const bind = this.bindTemplate;

        this.inputValue = '';

        this.saveButtonView = new ButtonView(locale);
        this.removeButtonView = new ButtonView(locale);
        this.cancelButtonView = new ButtonView(locale);

        this.saveButtonView.set({
            label: t('Save'),
            withText: true,
        });

        this.removeButtonView.set({
            label: t('Remove footnote'),
            withText: true,
        });

        this.cancelButtonView.set({
            label: t('Cancel'),
            withText: true,
        });

        const children = this.createCollection([
            this.saveButtonView,
            this.removeButtonView,
            this.cancelButtonView,
        ]);

        this.buttonsView = new View(locale);
        this.buttonsView.setTemplate({
            tag: 'div',
            attributes: {
                class: ['ck-footnotes-actions-buttons'],
            },
            children,
        });

        this.setTemplate({
            tag: 'div',
            attributes: {
                class: ['ck', 'ck-footnotes-actions'],
            },
            children: [
                {
                    tag: 'input',
                    attributes: {
                        class: ['ck', 'ck-input', 'ck-footnotes-actions-input'],
                        type: 'text',
                        placeholder: t('Footnote text'),
                        value: bind.to('inputValue'),
                    },
                    on: {
                        input: bind.to((evt) => {
                            this.inputValue = evt.target.value;
                        }),
                        keydown: bind.to((evt) => {
                            if (evt.key === 'Enter') {
                                this.fire('submit');
                                evt.preventDefault();
                            }

                            if (evt.key === 'Escape') {
                                this.fire('cancel');
                                evt.preventDefault();
                            }
                        }),
                    },
                },
                this.buttonsView,
            ],
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

export default class FootnotesUI extends Plugin {
    static get pluginName() {
        return 'FootnotesUI';
    }

    static get requires() {
        return [ContextualBalloon];
    }

    init() {
        const editor = this.editor;
        const t = editor.t;
        const viewDocument = editor.editing.view.document;

        this._balloon = editor.plugins.get(ContextualBalloon);
        this._actionsView = this._createActionsView();
        this._selectedFootnote = null;

        editor.ui.componentFactory.add('footnotes', (locale) => {
            const insertCommand = editor.commands.get('footnotes');
            const removeCommand = editor.commands.get('removeFootnote');
            const view = new ButtonView(locale);

            view.set({
                label: t('Footnote'),
                icon: icon,
                tooltip: true,
                isToggleable: true,
            });

            view.bind('isOn').to(removeCommand, 'value', (value) => !!value);
            view.bind('isEnabled').to(
                insertCommand,
                'isEnabled',
                removeCommand,
                'isEnabled',
                (canInsert, canRemove) => canInsert || canRemove
            );

            this.listenTo(view, 'execute', () => {
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

        editor.keystrokes.set('Esc', (data, cancel) => {
            if (this._isActionsVisible) {
                this._hideActionsBalloon();
                cancel();
            }
        });

        clickOutsideHandler({
            emitter: this._actionsView,
            activator: () => this._isActionsVisible,
            contextElements: () => [this._balloon.view.element],
            callback: () => {
                this._hideActionsBalloon();
            },
        });

        this.listenTo(viewDocument, 'click', (evt, data) => {
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

        this.listenTo(actionsView, 'submit', () => {
            this._runForSelectedFootnote(() => {
                editor.execute('updateFootnoteText', {
                    text: actionsView.inputValue,
                });
            });

            this._hideActionsBalloon();
            editor.editing.view.focus();
        });

        this.listenTo(actionsView.saveButtonView, 'execute', () => {
            this._runForSelectedFootnote(() => {
                editor.execute('updateFootnoteText', {
                    text: actionsView.inputValue,
                });
            });

            this._hideActionsBalloon();
            editor.editing.view.focus();
        });

        this.listenTo(actionsView.removeButtonView, 'execute', () => {
            this._runForSelectedFootnote(() => {
                editor.execute('removeFootnote');
            });

            this._hideActionsBalloon();
            editor.editing.view.focus();
        });

        this.listenTo(actionsView, 'cancel', () => {
            this._hideActionsBalloon();
            editor.editing.view.focus();
        });

        this.listenTo(actionsView.cancelButtonView, 'execute', () => {
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
            position: this._getBalloonPositionData(),
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

        model.change((writer) => {
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
            target: () => {
                if (!footnote) {
                    return editableElement || null;
                }

                const viewElement = editor.editing.mapper.toViewElement(footnote);
                const domElement = viewElement ? editor.editing.view.domConverter.mapViewToDom(viewElement) : null;

                return domElement || editableElement || null;
            },
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

    while (current) {
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
    return findFootnote(model, (item) => item.getAttribute('footnoteId') === footnoteId);
}

function getFootnoteByNumber(model, footnoteNumber) {
    return findFootnote(model, (item) => `${item.getAttribute('footnoteNumber') || ''}` === footnoteNumber);
}

function findFootnote(model, predicate) {
    for (const root of model.document.getRoots()) {
        if (root.rootName === '$graveyard') {
            continue;
        }

        for (const item of model.createRangeIn(root).getItems()) {
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