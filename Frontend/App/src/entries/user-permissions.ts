import { createApp } from "vue";
import UserPermissions from "../pages/UserPermissions.vue";
import "../style.css";
import Toast from "vue-toastification";
import "vue-toastification/dist/index.css";

const app = createApp(UserPermissions);

app.use(Toast, {
  transition: "Vue-Toastification__bounce",
  maxToasts: 20,
  newestOnTop: true,
});

// Theme support - listen for theme changes from parent FeatherPanel
function applyTheme(theme: 'light' | 'dark') {
  if (theme === 'dark') {
    document.documentElement.classList.add('dark');
  } else {
    document.documentElement.classList.remove('dark');
  }
}

// Listen for theme messages from parent
window.addEventListener('message', (event) => {
  if (event.data?.type === 'featherpanel-theme') {
    applyTheme(event.data.theme);
  }
});

// Signal readiness to parent to receive initial theme
if (window.parent !== window) {
  window.parent.postMessage({ type: 'featherpanel-ready' }, '*');
}

// Initial theme and transparent iframe background are set in user-permissions.html (before CSS loads).

app.mount("#app");
