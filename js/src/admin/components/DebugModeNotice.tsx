import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import InfoTile from 'flarum/common/components/InfoTile';
import type Mithril from 'mithril';

import { transAdmin } from '../../common/config';

/**
 * Says whether the bar is actually going to appear.
 *
 * Debug mode lives in `config.php`, not in the admin panel, so an
 * administrator who enables this extension and sees nothing has no way to find
 * out why from inside Flarum. This is that way.
 */
export default class DebugModeNotice<CustomAttrs extends ComponentAttrs = ComponentAttrs> extends Component<CustomAttrs> {
  view(vnode: Mithril.Vnode<CustomAttrs, this>) {
    const debug = !!app.forum.attribute('debug');

    return (
      <InfoTile
        icon={debug ? 'fas fa-circle-check' : 'fas fa-triangle-exclamation'}
        // A modifier of our own rather than `InfoTile--warning`: core has no
        // such variant, and claiming the name would collide the day it adds
        // one.
        className={`DebugbarNotice DebugbarNotice--${debug ? 'on' : 'off'}`}
      >
        {transAdmin(debug ? 'debug_mode.on' : 'debug_mode.off')}
      </InfoTile>
    );
  }
}
