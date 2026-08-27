import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type { SettingEntry, SettingsData } from '../../types';
export interface SettingsPanelAttrs extends ComponentAttrs {
    data: SettingsData;
}
/**
 * The settings table, grouped by the extension that owns each key.
 */
export default class SettingsPanel<CustomAttrs extends SettingsPanelAttrs = SettingsPanelAttrs> extends Component<CustomAttrs> {
    protected filter: string;
    view(vnode: Mithril.Vnode<CustomAttrs, this>): JSX.Element;
    value(setting: SettingEntry): Mithril.Children;
    matches(): SettingsData['groups'];
}
