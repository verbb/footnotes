import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

import type { ScreenshotSetupContext } from '@verbb/craft-screenshots/types';

type FootnotesFixture = {
    entryEditRoute: string;
};

const supportDir = dirname(fileURLToPath(import.meta.url));
const seedScript = readFileSync(join(supportDir, 'seed', 'seed-footnotes-entry.php'), 'utf8');

/** Seed the CKEditor entry used by the Footnotes feature capture. */
export async function seedFootnotesFixture(context: ScreenshotSetupContext): Promise<FootnotesFixture> {
    const output = await context.runCraftScript(seedScript, { label: 'seed-footnotes-entry' });
    const fixture = JSON.parse(output.trim()) as FootnotesFixture;

    if (!fixture.entryEditRoute) {
        throw new Error(`Invalid Footnotes fixture payload: ${output}`);
    }

    return fixture;
}
