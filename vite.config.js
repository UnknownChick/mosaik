import { existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import Symfony from '@symfony/reprise/vite';
import { defineConfig } from 'vite';

// The Git package contains these Sass modules but does not yet export them individually.
const myuiScss = dirname(fileURLToPath(import.meta.resolve('myui/scss')));
const myuiVendor = join(myuiScss, 'vendor', '_index.scss');

if (!existsSync(myuiVendor)) {
    console.warn('My UI : les adaptateurs tiers ne sont pas encore publiés dans le paquet Git installé.');
}

export default defineConfig({
    input: {
        app: './assets/app.js',
    },
    plugins: [Symfony({ stimulus: 'assets/controllers.json' })],
    resolve: {
        alias: {
            '@myui-scss': myuiScss,
            '@myui-vendor': existsSync(myuiVendor)
                ? myuiVendor
                : fileURLToPath(new URL('./assets/styles/_myui-vendor-pending.scss', import.meta.url)),
        },
    },
});
