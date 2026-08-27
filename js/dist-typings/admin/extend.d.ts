/**
 * The extension's settings, registered against core's own extension page.
 *
 * There is no custom page here on purpose: three settings, a list of switches
 * and one button are exactly what `ExtensionPage` is for, and using it means
 * the save button, the unsaved-changes count, the reset dialogue and the admin
 * search index all work without being reimplemented.
 *
 * The two custom entries are called with the page as their `this`, so they can
 * use `this.setting()` and be saved along with everything else.
 */
declare const _default: import("flarum/common/extenders/Admin").default[];
export default _default;
