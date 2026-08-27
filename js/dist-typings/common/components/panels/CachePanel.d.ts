import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type { PanelStat } from '../PanelLayout';
import type { CacheData, CacheOperation } from '../../types';
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
    protected filter: string;
    view(vnode: Mithril.Vnode<CustomAttrs, this>): JSX.Element;
    stats(): PanelStat[];
    matches(operations: CacheOperation[]): CacheOperation[];
}
