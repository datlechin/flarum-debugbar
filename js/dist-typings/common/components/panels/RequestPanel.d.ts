import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type { KeyValueEntries } from '../KeyValueList';
import type { RequestData } from '../../types';
export interface RequestPanelAttrs extends ComponentAttrs {
    data: RequestData;
}
/**
 * What was asked for, who asked, and what answered.
 *
 * Sections with nothing in them are left out rather than shown empty: on an
 * ordinary forum page the JSON:API section and the route parameters are both
 * blank, and four rows of dashes push the rows that do say something off the
 * bottom of the panel.
 */
export default class RequestPanel<CustomAttrs extends RequestPanelAttrs = RequestPanelAttrs> extends Component<CustomAttrs> {
    view(vnode: Mithril.Vnode<CustomAttrs, this>): JSX.Element;
    section(title: Mithril.Children, entries: KeyValueEntries): Mithril.Children;
    overview(): KeyValueEntries;
    actor(): KeyValueEntries;
}
