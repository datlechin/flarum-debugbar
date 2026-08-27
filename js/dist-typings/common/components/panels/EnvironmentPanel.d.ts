import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type { EnvironmentData } from '../../types';
export interface EnvironmentPanelAttrs extends ComponentAttrs {
    data: EnvironmentData;
}
/**
 * Versions, drivers and limits.
 */
export default class EnvironmentPanel<CustomAttrs extends EnvironmentPanelAttrs = EnvironmentPanelAttrs> extends Component<CustomAttrs> {
    view(vnode: Mithril.Vnode<CustomAttrs, this>): JSX.Element;
}
