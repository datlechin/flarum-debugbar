import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';

import PanelLayout from '../PanelLayout';
import { trans } from '../../config';
import { count } from '../../utils/format';
import type { ExtensionEntry, ExtensionsData } from '../../types';

export interface ExtensionsPanelAttrs extends ComponentAttrs {
  data: ExtensionsData;
}

/**
 * Which extensions are installed, which are running, and at what version.
 */
export default class ExtensionsPanel<CustomAttrs extends ExtensionsPanelAttrs = ExtensionsPanelAttrs> extends Component<CustomAttrs> {
  protected filter = '';

  view(vnode: Mithril.Vnode<CustomAttrs, this>) {
    const data = this.attrs.data;
    const matches = this.matches(data.extensions);

    return (
      <PanelLayout
        className="DebugbarExtensions"
        stats={[
          { label: trans('extensions.enabled'), value: count(data.enabled) },
          { label: trans('extensions.installed'), value: count(data.count) },
        ]}
        filter={this.filter}
        filterPlaceholder={trans('extensions.filter')}
        onfilter={(query: string) => (this.filter = query)}
        isEmpty={!matches.length}
        empty={data.count ? trans('no_matches') : trans('extensions.empty')}
      >
        <table className="DebugbarTable DebugbarExtensions-table">
          <thead>
            <tr>
              <th scope="col">{trans('extensions.columns.id')}</th>
              <th scope="col">{trans('extensions.columns.title')}</th>
              <th scope="col">{trans('extensions.columns.version')}</th>
              <th scope="col">{trans('extensions.columns.dependencies')}</th>
            </tr>
          </thead>
          <tbody>
            {matches.map((extension) => (
              <tr className={classList('DebugbarExtensions-row', !extension.enabled && 'DebugbarExtensions-row--disabled')} key={extension.id}>
                <th scope="row">
                  <Icon
                    name={extension.enabled ? 'fas fa-circle-check' : 'far fa-circle'}
                    className={classList('DebugbarExtensions-state', extension.enabled && 'DebugbarExtensions-state--on')}
                  />
                  <code>{extension.id}</code>
                </th>
                <td>{extension.title}</td>
                <td>
                  <code>{extension.version ?? '—'}</code>
                </td>
                <td>{extension.dependencies.length ? extension.dependencies.join(', ') : '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </PanelLayout>
    );
  }

  matches(extensions: ExtensionEntry[]): ExtensionEntry[] {
    const needle = this.filter.trim().toLowerCase();

    return needle
      ? extensions.filter((extension) => extension.id.toLowerCase().includes(needle) || extension.title.toLowerCase().includes(needle))
      : extensions;
  }
}
