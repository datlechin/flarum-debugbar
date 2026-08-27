import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';

import PanelLayout from '../PanelLayout';
import type { PanelStat } from '../PanelLayout';
import { trans } from '../../config';
import { count, duration } from '../../utils/format';
import type { Message, MessageLevel, MessagesData } from '../../types';

const LEVELS: MessageLevel[] = ['debug', 'info', 'warning', 'error'];

export interface MessagesPanelAttrs extends ComponentAttrs {
  data: MessagesData;
  /** When the request started, so entries can be shown as offsets. */
  start: number;
}

/**
 * Messages logged by application code, and the exceptions that got away.
 *
 * Levels are filtered by toggling them rather than by choosing one, because
 * the useful views are "errors and warnings" and "everything" — not "warnings
 * alone".
 */
export default class MessagesPanel<CustomAttrs extends MessagesPanelAttrs = MessagesPanelAttrs> extends Component<CustomAttrs> {
  protected filter = '';

  protected hidden = new Set<MessageLevel>();

  protected expanded = new Set<number>();

  view(vnode: Mithril.Vnode<CustomAttrs, this>) {
    const data = this.attrs.data;
    const matches = this.matches(data.messages);

    return (
      <PanelLayout
        className="DebugbarMessages"
        stats={this.stats()}
        filter={this.filter}
        filterPlaceholder={trans('messages.filter')}
        onfilter={(query: string) => (this.filter = query)}
        isEmpty={!matches.length}
        empty={data.messages.length ? trans('no_matches') : trans('messages.empty')}
      >
        <ol className="DebugbarMessages-list">{matches.map(({ message, index }) => this.row(message, index))}</ol>
      </PanelLayout>
    );
  }

  stats(): PanelStat[] {
    const totals = this.totals();

    return LEVELS.filter((level) => totals[level] > 0).map((level) => ({
      label: trans(`messages.levels.${level}`),
      value: count(totals[level]),
      severity: level === 'error' ? ('error' as const) : level === 'warning' ? ('warning' as const) : undefined,
      active: !this.hidden.has(level),
      onclick: () => (this.hidden.has(level) ? this.hidden.delete(level) : this.hidden.add(level)),
    }));
  }

  totals(): Record<MessageLevel, number> {
    const totals = { debug: 0, info: 0, warning: 0, error: 0 };

    for (const message of this.attrs.data.messages) {
      if (message.level in totals) totals[message.level]++;
    }

    return totals;
  }

  matches(messages: Message[]): Array<{ message: Message; index: number }> {
    const needle = this.filter.trim().toLowerCase();

    return messages
      .map((message, index) => ({ message, index }))
      .filter(({ message }) => !this.hidden.has(message.level))
      .filter(({ message }) => !needle || message.message.toLowerCase().includes(needle));
  }

  row(message: Message, index: number): Mithril.Children {
    const open = this.expanded.has(index);
    const hasTrace = !!message.trace?.length;

    return (
      <li className={classList('DebugbarMessage', `DebugbarMessage--${message.level}`)} key={index}>
        <div className="DebugbarMessage-head">
          <span className="DebugbarMessage-offset">{duration(Math.max(0, message.time - this.attrs.start))}</span>
          <span className="DebugbarBadge DebugbarMessage-level">{trans(`messages.levels.${message.level}`)}</span>
          <span className="DebugbarMessage-text">{message.message}</span>
        </div>

        {message.file && (
          <div className="DebugbarMessage-origin">
            <code>
              {message.file}:{message.line}
            </code>
            {hasTrace && (
              <Button
                className="Button Button--link DebugbarMessage-traceToggle"
                onclick={() => (open ? this.expanded.delete(index) : this.expanded.add(index))}
                aria-expanded={open}
              >
                {trans(open ? 'messages.hide_trace' : 'messages.show_trace')}
              </Button>
            )}
          </div>
        )}

        {open && hasTrace && <pre className="DebugbarCode DebugbarMessage-trace">{message.trace!.join('\n')}</pre>}
      </li>
    );
  }
}
