import { uiLoader } from "/vendor/helpers.pbb.ph/js/ui/ui.loader.js";

uiLoader.setPreferBundles(true);

if (typeof window !== "undefined") {
  window.uiLoader = uiLoader;
}

export { uiLoader };
