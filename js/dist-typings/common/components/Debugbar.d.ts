import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import type DebugbarState from '../states/DebugbarState';
import type { Profile } from '../types';
/**
 * One tab in the bar, and what to draw when it is chosen.
 *
 * The `ItemList` key is the collector's name, which is what ties a panel to
 * the data it renders.
 */
export interface PanelDefinition<T = any> {
    title: () => Mithril.Children;
    /** A count for the tab, when a count is worth reading before opening it. */
    badge?: (data: T) => Mithril.Children | null;
    /** Whether the tab should draw attention to itself. */
    severity?: (data: T) => 'warning' | 'error' | null;
    render: (data: T, profile: Profile) => Mithril.Children;
}
export interface DebugbarAttrs extends ComponentAttrs {
    state: DebugbarState;
}
/**
 * The bar itself.
 *
 * It is docked to the bottom of the window like the composer, is built out of
 * the same components and tokens as the rest of the forum, and so follows the
 * forum's colour scheme, primary colour and dark mode without knowing that any
 * of those exist.
 */
export default class Debugbar<CustomAttrs extends DebugbarAttrs = DebugbarAttrs> extends Component<CustomAttrs> {
    /** Set while a drag on the resize handle is in progress. */
    protected resizing: {
        pointer: number;
        startY: number;
        startHeight: number;
    } | null;
    /**
     * The panels the bar can show, keyed by collector name.
     *
     * Another extension adds one by extending this:
     *
     * ```ts
     * extend(Debugbar.prototype, 'panels', (items) => {
     *   items.add('widgets', { title: () => 'Widgets', render: (data) => ... }, 5);
     * });
     * ```
     *
     * A collector with no panel registered still gets a tab; it is rendered by
     * `GenericPanel`.
     */
    panels(): ItemList<PanelDefinition>;
    view(vnode: Mithril.Vnode<CustomAttrs, this>): JSX.Element;
    /**
     * The panels this request actually has data for, in registry order.
     */
    visiblePanels(): Array<{
        name: string;
        panel: PanelDefinition;
        data: unknown;
    }>;
    fallbackPanel(name: string): PanelDefinition;
    /**
     * Which panel is showing. Falls back to the first one rather than to
     * nothing, so a panel that was open when its collector got switched off does
     * not leave the bar blank.
     */
    activePanel(): string | null;
    tabs(): Mithril.Children;
    /**
     * Keep the tab strip honest about being scrollable.
     *
     * Ten tabs do not fit a laptop window, so the strip scrolls sideways. The
     * trailing fade that says so must appear only when there is something to
     * scroll to — a fade drawn unconditionally would dim the last tab on a wide
     * screen and mean nothing. And the tab you just chose is scrolled back into
     * view, so choosing one never hides it.
     */
    trackOverflow(vnode: Mithril.VnodeDOM): void;
    /**
     * The figures that describe a request without opening anything.
     *
     * This is what a collapsed bar is for, so it shows whatever is known: the
     * status and round trip come from the browser and are there immediately,
     * while the server-side figures appear as the profile arrives.
     */
    summary(): Mithril.Children;
    body(): Mithril.Children;
    /**
     * The grab handle along the top edge.
     *
     * Pointer events cover mouse, touch and pen with one code path, and pointer
     * capture means a drag that leaves the handle — which every drag does —
     * keeps being delivered to it.
     */
    resizer(): Mithril.Children;
    startResize(event: PointerEvent): void;
    resize(event: PointerEvent): void;
    endResize(event: PointerEvent): void;
    resizeByKey(event: KeyboardEvent): void;
    oncreate(vnode: Mithril.VnodeDOM<CustomAttrs, this>): void;
    onupdate(vnode: Mithril.VnodeDOM<CustomAttrs, this>): void;
    onremove(vnode: Mithril.VnodeDOM<CustomAttrs, this>): void;
    /**
     * Keep the bottom of the page reachable.
     *
     * The bar is fixed to the viewport, so without this it sits on top of the
     * last few hundred pixels of every page — including, on a discussion, the
     * reply control.
     */
    protected reserveSpace(vnode: Mithril.VnodeDOM<CustomAttrs, this>): void;
}
