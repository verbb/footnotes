import { build } from '@ckeditor/ckeditor5-dev-build-tools';

const input = 'src/index.js';

await build({
    input,
    output: 'dist/index.js',
    clean: true,
    sourceMap: true,
    external: ['ckeditor5'],
});

await build({
    input,
    output: 'dist/browser/index.js',
    clean: true,
    sourceMap: true,
    browser: true,
    name: 'footnotes',
    external: ['ckeditor5'],
});
