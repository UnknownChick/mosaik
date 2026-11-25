import './stimulus_bootstrap.js';
import { Accordion, initIcons, initMyUI } from 'myui';
import './styles/app.scss';

const menu = document.querySelector('#sidebar-menu');
const compactScreen = window.matchMedia('(max-width: 860px)');

if (menu) {
    menu.open = !compactScreen.matches;
}

initMyUI({ components: { accordion: { duration: 180 } } });
initIcons();

if (menu) {
    const syncMenu = () => {
        Accordion.getInstance(menu)?.destroy();
        menu.open = !compactScreen.matches;
        Accordion.getOrCreate(menu, { duration: 180 });
    };

    compactScreen.addEventListener('change', syncMenu);
}
