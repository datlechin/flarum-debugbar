import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
/**
 * Deletes the stored profile history.
 *
 * This is an action rather than a setting, so it sits outside the page's save
 * cycle and takes effect the moment it is confirmed — which is what someone
 * clicking "clear" expects, and why it does not wait for Save.
 */
export default class StoredProfiles<CustomAttrs extends ComponentAttrs = ComponentAttrs> extends Component<CustomAttrs> {
    protected clearing: boolean;
    view(vnode: Mithril.Vnode<CustomAttrs, this>): JSX.Element;
    clear(): void;
}
