import { defineScreenshotScenario } from '@verbb/craft-screenshots/api';

import { seedFootnotesFixture } from '../../support/fixtures';

let entryEditRoute = '/admin/entries';

export default defineScreenshotScenario({
    id: 'footnotes-feature-tour-editor',
    output: 'feature-tour/footnotes-editor.png',
    route: () => entryEditRoute,
    viewport: { width: 1180, height: 760, deviceScaleFactor: 2 },
    async setup(context) {
        const fixture = await seedFootnotesFixture(context);
        entryEditRoute = fixture.entryEditRoute;
    },
    waitFor: [
        { type: 'loadState', state: 'networkidle' },
        { type: 'selector', selector: '.ck-editor', state: 'visible', timeout: 30000 },
        { type: 'selector', selector: '.footnote-marker', state: 'visible', timeout: 30000 },
    ],
    steps: [
        { type: 'click', selector: '.footnote-marker' },
        { type: 'wait', waitFor: { type: 'selector', selector: '.ck-footnotes-actions', state: 'visible', timeout: 10000 } },
        { type: 'wait', waitFor: { type: 'timeout', ms: 250 } },
    ],
    target: {
        type: 'selector',
        selector: '.field:has(.ck-editor)',
        padding: { top: 24, right: 0, bottom: 24, left: 0 },
    },
    caption: 'A footnote being edited in Footnotes’ genuine CKEditor integration.',
    intent: 'Show the Footnotes toolbar button, numbered marker, and inline editing balloon in a real Craft entry.',
});
