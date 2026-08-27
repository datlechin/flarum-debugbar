import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
export interface PanelStat {
    label: Mithril.Children;
    value: Mithril.Children;
    severity?: 'warning' | 'error';
    /**
     * Makes the figure a filter. Several panels have one statistic that is also
     * the most useful way to narrow the list below it — "duplicates" on queries,
     * a level on messages — and turning the figure itself into the control is
     * fewer things on screen than a figure plus a button that mean the same.
     */
    onclick?: () => void;
    active?: boolean;
}
export interface PanelLayoutAttrs extends ComponentAttrs {
    /** Headline figures, shown above the content. */
    stats?: PanelStat[];
    /** Called as the reader types, if this panel can be filtered. */
    onfilter?: (query: string) => void;
    filter?: string;
    filterPlaceholder?: Mithril.Children;
    /** Rendered in place of the children when there is nothing to show. */
    empty?: Mithril.Children;
    isEmpty?: boolean;
    className?: string;
}
/**
 * The frame every panel is rendered in: a row of headline figures, an optional
 * filter box, and the panel's own content below.
 *
 * The figures are core's `LabelValue`, and the row is spaced and bordered like
 * `HeaderList-header` — the strip above the notifications list, which is the
 * same thing in the same place doing the same job.
 */
export default class PanelLayout<CustomAttrs extends PanelLayoutAttrs = PanelLayoutAttrs> extends Component<CustomAttrs> {
    view(vnode: Mithril.Vnode<CustomAttrs, this>): JSX.Element;
    stat(stat: PanelStat): Mithril.Children;
}
