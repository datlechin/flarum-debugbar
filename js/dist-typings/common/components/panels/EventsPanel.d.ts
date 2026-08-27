import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type { EventEntry, EventsData } from '../../types';
export interface EventsPanelAttrs extends ComponentAttrs {
    data: EventsData;
    start: number;
}
/**
 * Events dispatched during the request, in order.
 */
export default class EventsPanel<CustomAttrs extends EventsPanelAttrs = EventsPanelAttrs> extends Component<CustomAttrs> {
    protected filter: string;
    view(vnode: Mithril.Vnode<CustomAttrs, this>): JSX.Element;
    matches(events: EventEntry[]): EventEntry[];
    /**
     * Eloquent's model events, counted rather than listed. There are hundreds of
     * them on any page that loads a list, and the totals are the only part
     * anybody reads.
     */
    collapsed(): Mithril.Children;
}
