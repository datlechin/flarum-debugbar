import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';

import PanelLayout from '../PanelLayout';
import { trans } from '../../config';
import { count, duration } from '../../utils/format';
import type { EventEntry, EventsData } from '../../types';

export interface EventsPanelAttrs extends ComponentAttrs {
  data: EventsData;
  start: number;
}

/**
 * Events dispatched during the request, in order.
 */
export default class EventsPanel<CustomAttrs extends EventsPanelAttrs = EventsPanelAttrs> extends Component<CustomAttrs> {
  protected filter = '';

  view(vnode: Mithril.Vnode<CustomAttrs, this>) {
    const data = this.attrs.data;
    const matches = this.matches(data.events);

    return (
      <PanelLayout
        className="DebugbarEvents"
        stats={[
          { label: trans('events.count'), value: count(data.count) },
          { label: trans('events.listed'), value: count(data.events.length) },
        ]}
        filter={this.filter}
        filterPlaceholder={trans('events.filter')}
        onfilter={(query: string) => (this.filter = query)}
        isEmpty={!matches.length && !data.collapsed.length}
        empty={data.events.length ? trans('no_matches') : trans('events.empty')}
      >
        <ol className="DebugbarEvents-list">
          {matches.map((event, index) => (
            <li className="DebugbarEvent" key={index}>
              <span className="DebugbarEvent-offset">{duration(Math.max(0, event.time - this.attrs.start))}</span>
              <code className="DebugbarEvent-name">{event.name}</code>
              {!!event.payload.length && <span className="DebugbarEvent-payload">{event.payload.join(', ')}</span>}
            </li>
          ))}
        </ol>

        {data.dropped > 0 && <p className="Debugbar-truncated">{trans('events.dropped', { count: count(data.dropped) })}</p>}

        {!!data.collapsed.length && this.collapsed()}
      </PanelLayout>
    );
  }

  matches(events: EventEntry[]): EventEntry[] {
    const needle = this.filter.trim().toLowerCase();

    return needle ? events.filter((event) => event.name.toLowerCase().includes(needle)) : events;
  }

  /**
   * Eloquent's model events, counted rather than listed. There are hundreds of
   * them on any page that loads a list, and the totals are the only part
   * anybody reads.
   */
  collapsed(): Mithril.Children {
    return (
      <section className="DebugbarSection DebugbarEvents-collapsed">
        <h3 className="DebugbarSection-title">{trans('events.collapsed_title')}</h3>
        <p className="DebugbarSection-note">{trans('events.collapsed_help')}</p>

        <ul className="DebugbarEvents-collapsedList">
          {this.attrs.data.collapsed.map((entry) => (
            <li key={entry.name}>
              <code>{entry.name}</code>
              <span className="DebugbarBadge">{count(entry.count)}</span>
            </li>
          ))}
        </ul>
      </section>
    );
  }
}
