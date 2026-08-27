import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';

import { transAdmin } from '../../common/config';

/**
 * Deletes the stored profile history.
 *
 * This is an action rather than a setting, so it sits outside the page's save
 * cycle and takes effect the moment it is confirmed — which is what someone
 * clicking "clear" expects, and why it does not wait for Save.
 */
export default class StoredProfiles<CustomAttrs extends ComponentAttrs = ComponentAttrs> extends Component<CustomAttrs> {
  protected clearing = false;

  view(vnode: Mithril.Vnode<CustomAttrs, this>) {
    return (
      <div className="Form-group DebugbarStoredProfiles">
        <label>{transAdmin('storage.label')}</label>
        <div className="helpText">{transAdmin('storage.help')}</div>

        <Button className="Button" icon="fas fa-trash-can" loading={this.clearing} disabled={this.clearing} onclick={() => this.clear()}>
          {transAdmin('storage.clear')}
        </Button>
      </div>
    );
  }

  clear(): void {
    this.clearing = true;

    app
      .request<{ data: { cleared: number } }>({
        method: 'DELETE',
        url: `${app.forum.attribute('apiUrl')}/debugbar/profiles`,
      })
      .then((response) => {
        app.alerts.show({ type: 'success' }, transAdmin('storage.cleared', { count: response.data.cleared }));
      })
      .finally(() => {
        this.clearing = false;
        m.redraw();
      });
  }
}
