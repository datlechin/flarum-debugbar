import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type DebugbarState from '../states/DebugbarState';
import type { ObservedRequest } from '../types';
export interface RequestPickerAttrs extends ComponentAttrs {
    state: DebugbarState;
}
/**
 * Which request the panels are describing.
 *
 * The page load is one entry among the XHRs it went on to make, because from
 * the reader's point of view they are the same kind of thing: something the
 * browser asked for and the server spent time on.
 *
 * Rows are core's `DetailedDropdownItem` — a choice with a label, a line of
 * detail and a tick on the current one, which is exactly what this is. The
 * status band rides in its icon slot as a coloured dot, so a 500 in the list
 * is findable without reading it.
 */
export default class RequestPicker<CustomAttrs extends RequestPickerAttrs = RequestPickerAttrs> extends Component<CustomAttrs> {
    view(vnode: Mithril.Vnode<CustomAttrs, this>): JSX.Element;
    detail(request: ObservedRequest): string;
}
