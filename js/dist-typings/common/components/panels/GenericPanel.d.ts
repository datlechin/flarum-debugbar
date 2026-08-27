import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
export interface GenericPanelAttrs extends ComponentAttrs {
    data: unknown;
}
/**
 * What a collector gets when nothing has registered a panel for it.
 *
 * An extension can add a collector with the extender and see its data straight
 * away, then write a panel for it later — rather than having to write both
 * before either is any use.
 */
export default class GenericPanel<CustomAttrs extends GenericPanelAttrs = GenericPanelAttrs> extends Component<CustomAttrs> {
    view(vnode: Mithril.Vnode<CustomAttrs, this>): JSX.Element;
}
