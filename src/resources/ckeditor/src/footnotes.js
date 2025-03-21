import { Plugin } from 'ckeditor5/src/core.js';

import FootnotesEditing from './footnotes-editing.js';
import FootnotesUI from './footnotes-ui.js';

import '../theme/footnotes.css';

export default class Footnotes extends Plugin {
	static get pluginName() {
		return 'Footnotes';
	}

    static get requires() {
        return [FootnotesEditing, FootnotesUI];
    }
}
