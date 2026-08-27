import type Mithril from 'mithril';

import { trans, transIfExists } from '../common/config';

/**
 * A collector's display name.
 *
 * Collectors this extension ships have a translated tab title already, and the
 * settings page should call them what the bar calls them. One registered by
 * another extension has none, so its own name is used — which is at least
 * recognisable, and is what its author chose.
 */
export function collectorLabel(collector: string): Mithril.Children {
  return transIfExists(`tabs.${collector}`) ?? collector;
}

export { trans };
