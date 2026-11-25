import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

const projectRoot = fileURLToPath(new URL('../..', import.meta.url));
const entrypoints = JSON.parse(await readFile(path.join(projectRoot, 'public/build/entrypoints.json'), 'utf8'));
const cssFiles = entrypoints.entryPoints?.app?.css ?? [];
const myuiScss = path.dirname(fileURLToPath(import.meta.resolve('myui/scss')));
const vendorPublished = existsSync(path.join(myuiScss, 'vendor', '_index.scss'));

assert.ok(cssFiles.length > 0, 'Le point d’entrée Vite doit produire une feuille de styles.');

const css = (await Promise.all(cssFiles.map((file) =>
    readFile(path.join(projectRoot, 'public', file.replace(/^\/+/, '')), 'utf8'),
))).join('\n');

function assertClassIsCompiled(className) {
    const escaped = className.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    assert.match(css, new RegExp(`(?:^|[\\s{},])\\.${escaped}(?=[\\s,.:#\\[>+~{])`), `La classe .${className} est absente du CSS compilé.`);
}

test('les utilitaires My UI sont compilés', () => {
    for (const className of ['flex', 'items-center', 'p-2', 'gap-4', 'grid']) {
        assertClassIsCompiled(className);
    }
});

test('les utilitaires avancés My UI sont compilés', () => {
    for (const className of ['row', 'col', 'col-6', 'col-md-6', 'order-2', 'offset-lg-3', 'relative', 'absolute', 'opacity-50', 'prose']) {
        assertClassIsCompiled(className);
    }

    assert.ok(css.includes('.md\\:relative'), 'La variante responsive .md:relative est absente.');
});

test('les deux échelles de gap restent compatibles', () => {
    assert.match(css, /\.gap-4:not\(\.row\)\s*\{[^}]*gap:\s*var\(--spacing-4\)/);
    assert.match(css, /\.row\.gap-8\s*\{[^}]*--gap-size:\s*var\(--spacing-8\)/);
});

test('les composants et le thème My UI sont compilés', () => {
    for (const className of ['btn--primary', 'breadcrumb', 'icon--sm', 'dropdown__content']) {
        assertClassIsCompiled(className);
    }

    assert.match(css, /\[data-theme=["']?dark["']?\]/, 'Le thème sombre My UI est absent du CSS compilé.');
});

test('les adaptateurs tiers My UI sont compilés', {
    skip: vendorPublished ? false : 'En attente de publication du dossier vendor de My UI.',
}, () => {
    assertClassIsCompiled('dropzone');
    assertClassIsCompiled('select2-container');
    assert.match(css, /#toast-container/, 'Les styles Toastr sont absents du CSS compilé.');
});
