import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type { PanelStat } from '../PanelLayout';
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
    protected filter: string;
    protected expanded: Set<number>;
    /** Show only the statements that ran more than once. */
    protected duplicatesOnly: boolean;
    view(vnode: Mithril.Vnode<CustomAttrs, this>): JSX.Element;
    stats(): PanelStat[];
    /**
     * The rows to show, each keeping the index it had in the full list so that
     * expansion survives a change of filter.
     */
    matches(queries: Query[]): Array<{
        query: Query;
        index: number;
    }>;
    row(query: Query, index: number): Mithril.Children;
    details(query: Query): Mithril.Children;
}
