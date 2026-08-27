import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import Tooltip from 'flarum/common/components/Tooltip';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

import PanelLayout from '../PanelLayout';
import KeyValueList from '../KeyValueList';
import { trans } from '../../config';
import { count } from '../../utils/format';
import type { SettingEntry, SettingsData } from '../../types';

export interface SettingsPanelAttrs extends ComponentAttrs {
  data: SettingsData;
}

/**
 * The settings table, grouped by the extension that owns each key.
 */
export default class SettingsPanel<CustomAttrs extends SettingsPanelAttrs = SettingsPanelAttrs> extends Component<CustomAttrs> {
  protected filter = '';

  view(vnode: Mithril.Vnode<CustomAttrs, this>) {
    const data = this.attrs.data;
    const groups = this.matches();

    return (
      <PanelLayout
        className="DebugbarSettings"
        stats={[
          { label: trans('settings.count'), value: count(data.count) },
          { label: trans('settings.groups'), value: count(data.groups.length) },
        ]}
        filter={this.filter}
        filterPlaceholder={trans('settings.filter')}
        onfilter={(query: string) => (this.filter = query)}
        isEmpty={!groups.length}
        empty={data.count ? trans('no_matches') : trans('settings.empty')}
      >
        <div className="DebugbarSections">
          {groups.map((group) => (
            <section className="DebugbarSection" key={group.name}>
              <h3 className="DebugbarSection-title">{group.name}</h3>

              {/* A key and a value is not tabular data, and rendering it as a
                  table gave it striped rows and a key column that swallowed
                  most of the width. This is the same `<dl>` the Request and
                  Environment panels use for the same shape of thing. */}
              <KeyValueList entries={group.settings.map((setting) => [<code>{setting.name}</code>, this.value(setting)])} />
            </section>
          ))}
        </div>
      </PanelLayout>
    );
  }

  value(setting: SettingEntry): Mithril.Children {
    if (setting.sensitive) {
      return (
        <Tooltip text={extractText(trans('settings.redacted'))}>
          <span className="DebugbarSettings-redacted">
            <Icon name="fas fa-lock" /> {setting.value}
          </span>
        </Tooltip>
      );
    }

    return setting.value === '' ? <span className="DebugbarKeyValue-blank">{trans('blank')}</span> : <code>{setting.value}</code>;
  }

  matches(): SettingsData['groups'] {
    const needle = this.filter.trim().toLowerCase();

    if (!needle) return this.attrs.data.groups;

    // A group matches wholesale when its own name matches, so filtering by
    // extension id shows everything that extension owns. Matching on the full
    // key rather than the shortened name keeps the prefix searchable even
    // though the rows no longer show it.
    return this.attrs.data.groups
      .map((group) =>
        group.name.toLowerCase().includes(needle)
          ? group
          : { ...group, settings: group.settings.filter((setting) => setting.key.toLowerCase().includes(needle)) }
      )
      .filter((group) => group.settings.length > 0);
  }
}
