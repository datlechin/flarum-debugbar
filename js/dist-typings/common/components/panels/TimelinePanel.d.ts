import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type { Measure, TimelineData } from '../../types';
export interface TimelinePanelAttrs extends ComponentAttrs {
    data: TimelineData;
}
/**
 * Named spans of the request drawn against its total duration.
 *
 * The bars are positioned by where each span actually fell, not stacked in
 * sequence, so a span that overlaps another is visibly concurrent and a gap
 * between two spans is visibly a gap — which is usually the part worth asking
 * about.
 */
export default class TimelinePanel<CustomAttrs extends TimelinePanelAttrs = TimelinePanelAttrs> extends Component<CustomAttrs> {
    view(vnode: Mithril.Vnode<CustomAttrs, this>): JSX.Element;
    rows(measures: Measure[], total: number): Mithril.Children;
    /**
     * How deeply each span is nested, worked out from the spans themselves.
     *
     * Callers never declare nesting — a measure around a measure is just two
     * calls — so it is recovered here by tracking which spans are still open at
     * the point each new one starts.
     */
    withDepth(measures: Measure[]): Array<{
        measure: Measure;
        depth: number;
    }>;
}
