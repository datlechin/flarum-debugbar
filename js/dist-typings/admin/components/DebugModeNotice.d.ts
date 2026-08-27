import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
/**
 * Says whether the bar is actually going to appear.
 *
 * Debug mode lives in `config.php`, not in the admin panel, so an
 * administrator who enables this extension and sees nothing has no way to find
 * out why from inside Flarum. This is that way.
 */
export default class DebugModeNotice<CustomAttrs extends ComponentAttrs = ComponentAttrs> extends Component<CustomAttrs> {
    view(vnode: Mithril.Vnode<CustomAttrs, this>): JSX.Element;
}
