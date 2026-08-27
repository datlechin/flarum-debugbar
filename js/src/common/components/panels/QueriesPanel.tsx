import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import Tooltip from 'flarum/common/components/Tooltip';
import classList from 'flarum/common/utils/classList';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

import PanelLayout from '../PanelLayout';
import type { PanelStat } from '../PanelLayout';
import { trans } from '../../config';
import { count, duration } from '../../utils/format';
import type { QueriesData, Query } from '../../types';

export interface QueriesPanelAttrs extends ComponentAttrs {
  data: QueriesData;
}

/**
 * The statements the request ran.
 *
 * Rows are collapsed to the statement itself, because that is what you scan
 * for; the parameters, the interpolated statement and the line of code that
 * ran it are one click away, because that is what you read once you have found
 * the row you wanted.
 */
export default class QueriesPanel<CustomAttrs extends QueriesPanelAttrs = QueriesPanelAttrs> extends Component<CustomAttrs> {
  protected filter = '';

  protected expanded = new Set<number>();

  /** Show only the statements that ran more than once. */
  protected duplicatesOnly = false;

  view(vnode: Mithril.Vnode<CustomAttrs, this>) {
    const data = this.attrs.data;
    const matches = this.matches(data.queries);

    return (
      <PanelLayout
        className="DebugbarQueries"
        stats={this.stats()}
        filter={this.filter}
        filterPlaceholder={trans('queries.filter')}
        onfilter={(query: string) => {
          this.filter = query;
          this.expanded.clear();
        }}
        isEmpty={!matches.length}
        empty={data.queries.length ? trans('no_matches') : trans('queries.empty')}
      >
        <ol className="DebugbarQueries-list">{matches.map(({ query, index }) => this.row(query, index))}</ol>

        {data.dropped > 0 && <p className="Debugbar-truncated">{trans('queries.dropped', { count: count(data.dropped) })}</p>}
      </PanelLayout>
    );
  }

  stats(): PanelStat[] {
    const data = this.attrs.data;

    const stats: PanelStat[] = [
      { label: trans('queries.count'), value: count(data.count) },
      { label: trans('queries.duration'), value: duration(data.duration) },
    ];

    if (data.duplicates > 0) {
      stats.push({
        label: trans('queries.duplicates'),
        value: count(data.duplicates),
        severity: 'warning',
        active: this.duplicatesOnly,
        onclick: () => {
          this.duplicatesOnly = !this.duplicatesOnly;
          this.expanded.clear();
        },
      });
    }

    return stats;
  }

  /**
   * The rows to show, each keeping the index it had in the full list so that
   * expansion survives a change of filter.
   */
  matches(queries: Query[]): Array<{ query: Query; index: number }> {
    const needle = this.filter.trim().toLowerCase();

    return queries
      .map((query, index) => ({ query, index }))
      .filter(({ query }) => !this.duplicatesOnly || query.occurrences > 1)
      .filter(({ query }) => !needle || query.sql.toLowerCase().includes(needle) || (query.origin ?? '').toLowerCase().includes(needle));
  }

  row(query: Query, index: number): Mithril.Children {
    const open = this.expanded.has(index);

    return (
      <li className={classList('DebugbarQuery', query.occurrences > 1 && 'DebugbarQuery--duplicate')} key={index}>
        <button
          type="button"
          className="DebugbarQuery-summary"
          aria-expanded={open}
          onclick={() => (open ? this.expanded.delete(index) : this.expanded.add(index))}
        >
          <Icon name={open ? 'fas fa-caret-down' : 'fas fa-caret-right'} className="DebugbarQuery-caret" />

          <code className="DebugbarQuery-sql">{query.sql}</code>

          {query.occurrences > 1 && (
            <Tooltip text={extractText(trans('queries.occurrences', { count: query.occurrences }))}>
              <span className="DebugbarBadge">×{query.occurrences}</span>
            </Tooltip>
          )}

          <span className="DebugbarQuery-duration">{duration(query.duration)}</span>
        </button>

        {open && this.details(query)}
      </li>
    );
  }

  details(query: Query): Mithril.Children {
    return (
      <div className="DebugbarQuery-details">
        <div className="DebugbarQuery-detail">
          <h4 className="DebugbarQuery-detailTitle">{trans('queries.preview')}</h4>
          <pre className="DebugbarCode">{query.preview}</pre>
        </div>

        {!!query.bindings.length && (
          <div className="DebugbarQuery-detail">
            <h4 className="DebugbarQuery-detailTitle">{trans('queries.bindings')}</h4>
            <ol className="DebugbarQuery-bindings">
              {query.bindings.map((binding, position) => (
                <li key={position}>
                  <span className="DebugbarQuery-bindingIndex">{position + 1}</span>
                  <code>{binding}</code>
                </li>
              ))}
            </ol>
          </div>
        )}

        <div className="DebugbarQuery-meta">
          <span>
            {trans('queries.connection')}: <code>{query.connection}</code>
          </span>
          {query.origin && (
            <span>
              {trans('queries.origin')}: <code>{query.origin}</code>
            </span>
          )}
        </div>
      </div>
    );
  }
}
