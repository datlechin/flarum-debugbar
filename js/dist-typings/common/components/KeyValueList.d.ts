import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
export type KeyValueEntries = Array<[Mithril.Children, Mithril.Children]> | Record<string, Mithril.Children>;
export interface KeyValueListAttrs extends ComponentAttrs {
    /** Rows, in the order they should be read. */
    entries: KeyValueEntries;
    /** Shown instead of the list when there is nothing in it. */
    empty?: Mithril.Children;
    className?: string;
}
/**
 * A description list of labelled values.
 *
 * Several panels are, at heart, the same thing: a column of names and a column
 * of values. A `<dl>` says that in markup, reads correctly to a screen reader
 * without any ARIA, and lets one set of styles serve all of them.
 */
export default class KeyValueList<CustomAttrs extends KeyValueListAttrs = KeyValueListAttrs> extends Component<CustomAttrs> {
    view(vnode: Mithril.Vnode<CustomAttrs, this>): JSX.Element;
}
