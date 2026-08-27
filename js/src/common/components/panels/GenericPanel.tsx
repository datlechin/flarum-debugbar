import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';

import PanelLayout from '../PanelLayout';
import { trans } from '../../config';

export interface GenericPanelAttrs extends ComponentAttrs {
  data: unknown;
}

/**
 * What a collector gets when nothing has registered a panel for it.
 *
 * An extension can add a collector with the extender and see its data straight
 * away, then write a panel for it later — rather than having to write both
 * before either is any use.
 */
export default class GenericPanel<CustomAttrs extends GenericPanelAttrs = GenericPanelAttrs> extends Component<CustomAttrs> {
  view(vnode: Mithril.Vnode<CustomAttrs, this>) {
    const data = this.attrs.data;
    const empty = data === null || data === undefined || (typeof data === 'object' && !Object.keys(data as object).length);

    return (
      <PanelLayout className="DebugbarGeneric" isEmpty={empty} empty={trans('empty')}>
        <pre className="DebugbarCode">{JSON.stringify(data, null, 2)}</pre>
      </PanelLayout>
    );
  }
}
