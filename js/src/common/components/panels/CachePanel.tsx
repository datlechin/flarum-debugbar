import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';

import PanelLayout from '../PanelLayout';
import type { PanelStat } from '../PanelLayout';
import { trans } from '../../config';
import { count, duration, percentage } from '../../utils/format';
import type { CacheData, CacheOperation, CacheOperationType } from '../../types';

const TYPES: CacheOperationType[] = ['hit', 'miss', 'write', 'forget', 'flush'];

export interface CachePanelAttrs extends ComponentAttrs {
  data: CacheData;
  start: number;
}

/**
 * Reads and writes against the cache.
 *
 * The hit rate leads, because a cache that has quietly stopped working looks
 * exactly like a cache that is working until you compare the two totals.
 */
export default class CachePanel<CustomAttrs extends CachePanelAttrs = CachePanelAttrs> extends Component<CustomAttrs> {
  protected filter = '';

  view(vnode: Mithril.Vnode<CustomAttrs, this>) {
    const data = this.attrs.data;
    const matches = this.matches(data.operations);

    return (
      <PanelLayout
        className="DebugbarCache"
        stats={this.stats()}
        filter={this.filter}
        filterPlaceholder={trans('cache.filter')}
        onfilter={(query: string) => (this.filter = query)}
        isEmpty={!matches.length}
        empty={data.operations.length ? trans('no_matches') : trans('cache.empty')}
      >
        <ol className="DebugbarCache-list">
          {matches.map((operation, index) => (
            <li className={classList('DebugbarCacheOp', `DebugbarCacheOp--${operation.type}`)} key={index}>
              <span className="DebugbarCacheOp-offset">{duration(Math.max(0, operation.time - this.attrs.start))}</span>
              <span className="DebugbarBadge">{trans(`cache.types.${operation.type}`)}</span>
              <code className="DebugbarCacheOp-key">{operation.key}</code>
            </li>
          ))}
        </ol>

        {data.dropped > 0 && <p className="Debugbar-truncated">{trans('cache.dropped', { count: count(data.dropped) })}</p>}
      </PanelLayout>
    );
  }

  stats(): PanelStat[] {
    const data = this.attrs.data;

    const stats: PanelStat[] = TYPES.filter((type) => (data.totals[type] ?? 0) > 0).map((type) => ({
      label: trans(`cache.types.${type}`),
      value: count(data.totals[type]),
      // A cache miss is not a fault, but a panel full of them is the shape of
      // one, so they are worth being able to pick out at a glance.
      severity: type === 'miss' ? ('warning' as const) : undefined,
    }));

    if (data.hitRate !== null) {
      stats.unshift({
        label: trans('cache.hit_rate'),
        value: percentage(data.hitRate),
        severity: data.hitRate < 0.5 ? 'warning' : undefined,
      });
    }

    return stats;
  }

  matches(operations: CacheOperation[]): CacheOperation[] {
    const needle = this.filter.trim().toLowerCase();

    return needle ? operations.filter((operation) => operation.key.toLowerCase().includes(needle)) : operations;
  }
}
