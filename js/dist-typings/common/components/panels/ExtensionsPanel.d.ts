import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type { ExtensionEntry, ExtensionsData } from '../../types';
export interface ExtensionsPanelAttrs extends ComponentAttrs {
    data: ExtensionsData;
}
/**
 * Which extensions are installed, which are running, and at what version.
 */
export default class ExtensionsPanel<CustomAttrs extends ExtensionsPanelAttrs = ExtensionsPanelAttrs> extends Component<CustomAttrs> {
    protected filter: string;
    view(vnode: Mithril.Vnode<CustomAttrs, this>): JSX.Element;
    matches(extensions: ExtensionEntry[]): ExtensionEntry[];
}
