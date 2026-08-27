import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import DetailedDropdownItem from 'flarum/common/components/DetailedDropdownItem';
import Dropdown from 'flarum/common/components/Dropdown';
import Icon from 'flarum/common/components/Icon';
import classList from 'flarum/common/utils/classList';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

import { trans } from '../config';
import { duration, statusClass, time } from '../utils/format';
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
  view(vnode: Mithril.Vnode<CustomAttrs, this>) {
    const state = this.attrs.state;
    const current = state.current();

    return (
      <Dropdown
        className="DebugbarRequests"
        buttonClassName="Button Button--link DebugbarRequests-button"
        // The bar sits on the bottom edge of the window, so a menu that opened
        // downward would open off the screen. Core has a variant for exactly
        // this.
        menuClassName="Dropdown-menu--top Dropdown-menu--right"
        icon="fas fa-clock-rotate-left"
        label={current ? `${current.method} ${current.uri}` : extractText(trans('requests.none'))}
        accessibleToggleLabel={extractText(trans('requests.toggle'))}
      >
        {state.requests.map((request) => (
          <DetailedDropdownItem
            key={request.id}
            active={request.id === state.selected}
            icon={
              <Icon
                name="fas fa-circle"
                className={classList('Button-icon', 'DebugbarRequests-dot', `DebugbarRequests-dot--${statusClass(request.status)}`)}
              />
            }
            label={`${request.method} ${request.uri}`}
            description={this.detail(request)}
            onclick={() => state.select(request.id)}
          />
        ))}
      </Dropdown>
    );
  }

  detail(request: ObservedRequest): string {
    const parts = [
      String(request.status),
      request.document ? extractText(trans('requests.document')) : duration(request.duration),
      time(request.time),
    ];

    return parts.join(' · ');
  }
}
