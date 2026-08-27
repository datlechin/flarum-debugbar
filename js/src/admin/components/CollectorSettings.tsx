import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Switch from 'flarum/common/components/Switch';
import type Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';

import { transAdmin } from '../../common/config';
import { collectorLabel } from '../catalogue';

export interface CollectorSettingsAttrs extends ComponentAttrs {
  /**
   * The `disabled_collectors` setting, as the admin page's own stream — so
   * these switches are saved by the page's Save button and counted by its
   * unsaved-changes indicator, like every other setting on it.
   */
  stream: Stream<string>;
}

/**
 * A switch per collector.
 *
 * The list comes from the backend rather than from a constant here, so a
 * collector registered by another extension through the extender gets a switch
 * without this file knowing it exists.
 */
export default class CollectorSettings<CustomAttrs extends CollectorSettingsAttrs = CollectorSettingsAttrs> extends Component<CustomAttrs> {
  view(vnode: Mithril.Vnode<CustomAttrs, this>) {
    const collectors = app.forum.attribute<string[]>('debugbarCollectors') ?? [];
    const disabled = this.disabled();

    return (
      <div className="Form-group DebugbarCollectorSettings">
        <label>{transAdmin('collectors.label')}</label>
        <div className="helpText">{transAdmin('collectors.help')}</div>

        <ul className="DebugbarCollectorSettings-list">
          {collectors.map((collector) => (
            <li key={collector}>
              <Switch state={!disabled.includes(collector)} onchange={(enabled: boolean) => this.set(collector, enabled)}>
                {collectorLabel(collector)}
              </Switch>
            </li>
          ))}
        </ul>
      </div>
    );
  }

  /**
   * @return The names of the collectors currently switched off.
   */
  disabled(): string[] {
    try {
      const parsed = JSON.parse(this.attrs.stream() || '[]');

      return Array.isArray(parsed) ? parsed.filter((name): name is string => typeof name === 'string') : [];
    } catch {
      // A hand-edited setting should not take the settings page down with it;
      // treating it as "nothing disabled" is both safe and correctable, since
      // saving overwrites it with something valid.
      return [];
    }
  }

  set(collector: string, enabled: boolean): void {
    const disabled = new Set(this.disabled());

    enabled ? disabled.delete(collector) : disabled.add(collector);

    this.attrs.stream(JSON.stringify([...disabled]));
  }
}
