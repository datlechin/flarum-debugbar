import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';

import PanelLayout from '../PanelLayout';
import KeyValueList from '../KeyValueList';
import { trans, transIfExists } from '../../config';
import type { EnvironmentData } from '../../types';

export interface EnvironmentPanelAttrs extends ComponentAttrs {
  data: EnvironmentData;
}

/**
 * Versions, drivers and limits.
 */
export default class EnvironmentPanel<CustomAttrs extends EnvironmentPanelAttrs = EnvironmentPanelAttrs> extends Component<CustomAttrs> {
  view(vnode: Mithril.Vnode<CustomAttrs, this>) {
    const groups = this.attrs.data.groups ?? [];

    return (
      <PanelLayout className="DebugbarEnvironment" isEmpty={!groups.length} empty={trans('environment.empty')}>
        <div className="DebugbarSections DebugbarSections--columns">
          {groups.map((group) => (
            <section className="DebugbarSection" key={group.name}>
              <h3 className="DebugbarSection-title">{transIfExists(`environment.groups.${group.name}`) ?? group.name}</h3>
              <KeyValueList entries={Object.entries(group.values).map(([key, value]) => [transIfExists(`environment.keys.${key}`) ?? key, value])} />
            </section>
          ))}
        </div>
      </PanelLayout>
    );
  }
}
