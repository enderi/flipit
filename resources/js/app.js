require('./bootstrap');

// Import modules...
import { createApp, h } from 'vue';
import { App as InertiaApp, plugin as InertiaPlugin } from '@inertiajs/inertia-vue3';
import { InertiaProgress } from '@inertiajs/progress';
import 'animate.css'
import Toast from 'vue3-toast-single'
import 'vue3-toast-single/dist/toast.css'


const el = document.getElementById('app');

const app = createApp({
    render: () =>
        h(InertiaApp, {
            initialPage: JSON.parse(el.dataset.page),
            resolveComponent: (name) => require(`./Pages/${name}`).default,
        }),
})
    .mixin({ methods: { route } })
    .use(InertiaPlugin)
    .use(Toast, {verticalPosition: 'top', horizontalPosition: 'right', transition: 'fade', duration: 3000})
    .mount(el);

InertiaProgress.init({ color: '#4B5563' });
