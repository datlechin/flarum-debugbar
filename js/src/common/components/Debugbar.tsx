import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Placeholder from 'flarum/common/components/Placeholder';
import Tooltip from 'flarum/common/components/Tooltip';
import ItemList from 'flarum/common/utils/ItemList';
import classList from 'flarum/common/utils/classList';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

import RequestPicker from './RequestPicker';
import CachePanel from './panels/CachePanel';
import EnvironmentPanel from './panels/EnvironmentPanel';
import EventsPanel from './panels/EventsPanel';
import ExtensionsPanel from './panels/ExtensionsPanel';
import GenericPanel from './panels/GenericPanel';
import MailPanel from './panels/MailPanel';
import MessagesPanel from './panels/MessagesPanel';
import QueriesPanel from './panels/QueriesPanel';
import RequestPanel from './panels/RequestPanel';
import SettingsPanel from './panels/SettingsPanel';
import TimelinePanel from './panels/TimelinePanel';
import { trans, transIfExists } from '../config';
import { bytes, count, duration, statusClass } from '../utils/format';
import type DebugbarState from '../states/DebugbarState';
import type {
  CacheData,
  EnvironmentData,
  EventsData,
  ExtensionsData,
  MailData,
  MessagesData,
  Profile,
  QueriesData,
  RequestData,
  SettingsData,
  TimelineData,
} from '../types';

/**
 * One tab in the bar, and what to draw when it is chosen.
 *
 * The `ItemList` key is the collector's name, which is what ties a panel to
 * the data it renders.
 */
export interface PanelDefinition<T = any> {
  title: () => Mithril.Children;
  /** A count for the tab, when a count is worth reading before opening it. */
  badge?: (data: T) => Mithril.Children | null;
  /** Whether the tab should draw attention to itself. */
  severity?: (data: T) => 'warning' | 'error' | null;
  render: (data: T, profile: Profile) => Mithril.Children;
}

export interface DebugbarAttrs extends ComponentAttrs {
  state: DebugbarState;
}

/**
 * The bar itself.
 *
 * It is docked to the bottom of the window like the composer, is built out of
 * the same components and tokens as the rest of the forum, and so follows the
 * forum's colour scheme, primary colour and dark mode without knowing that any
 * of those exist.
 */
export default class Debugbar<CustomAttrs extends DebugbarAttrs = DebugbarAttrs> extends Component<CustomAttrs> {
  /** Set while a drag on the resize handle is in progress. */
  protected resizing: { pointer: number; startY: number; startHeight: number } | null = null;

  /**
   * The panels the bar can show, keyed by collector name.
   *
   * Another extension adds one by extending this:
   *
   * ```ts
   * extend(Debugbar.prototype, 'panels', (items) => {
   *   items.add('widgets', { title: () => 'Widgets', render: (data) => ... }, 5);
   * });
   * ```
   *
   * A collector with no panel registered still gets a tab; it is rendered by
   * `GenericPanel`.
   */
  panels(): ItemList<PanelDefinition> {
    const items = new ItemList<PanelDefinition>();

    items.add(
      'timeline',
      {
        title: () => trans('tabs.timeline'),
        render: (data: TimelineData) => <TimelinePanel data={data} />,
      },
      100
    );

    items.add(
      'queries',
      {
        title: () => trans('tabs.queries'),
        badge: (data: QueriesData) => count(data.count),
        severity: (data: QueriesData) => (data.duplicates > 0 ? 'warning' : null),
        render: (data: QueriesData) => <QueriesPanel data={data} />,
      },
      90
    );

    items.add(
      'messages',
      {
        title: () => trans('tabs.messages'),
        badge: (data: MessagesData) => (data.count ? count(data.count) : null),
        severity: (data: MessagesData) => (data.messages.some((message) => message.level === 'error') ? 'error' : null),
        render: (data: MessagesData, profile: Profile) => <MessagesPanel data={data} start={profile.time} />,
      },
      80
    );

    items.add(
      'request',
      {
        title: () => trans('tabs.request'),
        render: (data: RequestData) => <RequestPanel data={data} />,
      },
      70
    );

    items.add(
      'events',
      {
        title: () => trans('tabs.events'),
        badge: (data: EventsData) => (data.count ? count(data.count) : null),
        render: (data: EventsData, profile: Profile) => <EventsPanel data={data} start={profile.time} />,
      },
      60
    );

    items.add(
      'cache',
      {
        title: () => trans('tabs.cache'),
        badge: (data: CacheData) => (data.count ? count(data.count) : null),
        render: (data: CacheData, profile: Profile) => <CachePanel data={data} start={profile.time} />,
      },
      50
    );

    items.add(
      'mail',
      {
        title: () => trans('tabs.mail'),
        badge: (data: MailData) => (data.count ? count(data.count) : null),
        render: (data: MailData) => <MailPanel data={data} />,
      },
      40
    );

    items.add(
      'settings',
      {
        title: () => trans('tabs.settings'),
        render: (data: SettingsData) => <SettingsPanel data={data} />,
      },
      30
    );

    items.add(
      'extensions',
      {
        title: () => trans('tabs.extensions'),
        badge: (data: ExtensionsData) => count(data.enabled),
        render: (data: ExtensionsData) => <ExtensionsPanel data={data} />,
      },
      20
    );

    items.add(
      'environment',
      {
        title: () => trans('tabs.environment'),
        render: (data: EnvironmentData) => <EnvironmentPanel data={data} />,
      },
      10
    );

    return items;
  }

  view(vnode: Mithril.Vnode<CustomAttrs, this>) {
    const state = this.attrs.state;

    // Wrapped in core's own `.container`, which is what puts the bar on the
    // same grid as everything else on the page. Flarum's other bottom-docked
    // panel — the composer — is a card inset to the content column rather than
    // a strip welded across the window, and a debug bar that ignored that read
    // as something bolted on from outside.
    return (
      <div className="container">
        <div className={classList('Debugbar', state.open && 'Debugbar--open')}>
          {state.open && this.resizer()}

          <div className="Debugbar-header">
            <Tooltip text={extractText(trans(state.open ? 'collapse' : 'expand'))}>
              <Button
                className="Button Button--icon Button--link Debugbar-toggle"
                icon="fas fa-bug"
                onclick={() => state.toggle()}
                aria-expanded={state.open}
                aria-label={extractText(trans(state.open ? 'collapse' : 'expand'))}
              />
            </Tooltip>

            {/* Collapsed, the bar is something you glance at, so the figures
                take the row. Open, it is something you navigate, so the tabs
                do — and the figures come back only where there is genuinely
                room for both. Trying to fit both at every width is what cut
                "Environment" down to "En". */}
            {state.open && (
              <div className="Tabs-nav Debugbar-tabs" role="tablist" oncreate={this.trackOverflow} onupdate={this.trackOverflow}>
                {this.tabs()}
              </div>
            )}

            <div className="Debugbar-summary">{this.summary()}</div>

            <RequestPicker state={state} />
          </div>

          {state.open && (
            <div className="Debugbar-body" style={{ height: `${state.height}px` }}>
              {this.body()}
            </div>
          )}
        </div>
      </div>
    );
  }

  /**
   * The panels this request actually has data for, in registry order.
   */
  visiblePanels(): Array<{ name: string; panel: PanelDefinition; data: unknown }> {
    const profile = this.attrs.state.profile();

    if (!profile) return [];

    const registered = this.panels();

    // `toArray()` is ordered by priority and tags each item with the key it
    // was added under, which is the collector name.
    const known = registered.toArray().map((panel) => panel.itemName);

    // Panels first, in the order they were registered; then any collector the
    // bar has data for but no panel for, so a new collector is visible before
    // anyone has written a panel for it.
    const names = [...known.filter((name) => name in profile.data), ...Object.keys(profile.data).filter((name) => !known.includes(name))];

    return names.map((name) => ({
      name,
      panel: registered.has(name) ? registered.get(name) : this.fallbackPanel(name),
      data: profile.data[name],
    }));
  }

  fallbackPanel(name: string): PanelDefinition {
    return {
      title: () => transIfExists(`tabs.${name}`) ?? name,
      render: (data: unknown) => <GenericPanel data={data} />,
    };
  }

  /**
   * Which panel is showing. Falls back to the first one rather than to
   * nothing, so a panel that was open when its collector got switched off does
   * not leave the bar blank.
   */
  activePanel(): string | null {
    const panels = this.visiblePanels();

    if (!panels.length) return null;

    return panels.some(({ name }) => name === this.attrs.state.panel) ? this.attrs.state.panel : panels[0].name;
  }

  tabs(): Mithril.Children {
    const active = this.activePanel();

    return this.visiblePanels().map(({ name, panel, data }) => {
      const badge = panel.badge?.(data);
      const severity = panel.severity?.(data);

      return (
        <Button
          key={name}
          className={classList('Button Button--link Debugbar-tab', severity && `Debugbar-tab--${severity}`)}
          active={name === active}
          role="tab"
          aria-selected={name === active}
          onclick={() => {
            this.attrs.state.show(name);
            if (!this.attrs.state.open) this.attrs.state.toggle(true);
          }}
        >
          <span className="Debugbar-tabTitle">{panel.title()}</span>
          {badge !== null && badge !== undefined && <span className="DebugbarBadge">{badge}</span>}
        </Button>
      );
    });
  }

  /**
   * Keep the tab strip honest about being scrollable.
   *
   * Ten tabs do not fit a laptop window, so the strip scrolls sideways. The
   * trailing fade that says so must appear only when there is something to
   * scroll to — a fade drawn unconditionally would dim the last tab on a wide
   * screen and mean nothing. And the tab you just chose is scrolled back into
   * view, so choosing one never hides it.
   */
  trackOverflow(vnode: Mithril.VnodeDOM): void {
    const strip = vnode.dom as HTMLElement;

    strip.classList.toggle('Debugbar-tabs--overflowing', strip.scrollWidth > strip.clientWidth + 1);

    strip.querySelector('.Debugbar-tab[active]')?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
  }

  /**
   * The figures that describe a request without opening anything.
   *
   * This is what a collapsed bar is for, so it shows whatever is known: the
   * status and round trip come from the browser and are there immediately,
   * while the server-side figures appear as the profile arrives.
   */
  summary(): Mithril.Children {
    const state = this.attrs.state;
    const profile = state.profile();
    const request = state.current();
    const status = profile?.status ?? request?.status;

    if (status === undefined) {
      return state.isLoading() ? <LoadingIndicator size="small" display="inline" /> : null;
    }

    const queries = profile?.data.queries as { count: number } | undefined;

    return [
      <span className={classList('DebugbarStatus', `DebugbarStatus--${statusClass(status)}`)}>{status}</span>,

      profile && (
        <Tooltip text={extractText(trans('summary.duration'))}>
          <span className="Debugbar-stat">
            <Icon name="fas fa-stopwatch" /> {duration(profile.duration)}
          </span>
        </Tooltip>
      ),

      queries && (
        <Tooltip text={extractText(trans('summary.queries'))}>
          <span className="Debugbar-stat">
            <Icon name="fas fa-database" /> {count(queries.count)}
          </span>
        </Tooltip>
      ),

      profile && (
        <Tooltip text={extractText(trans('summary.memory'))}>
          <span className="Debugbar-stat">
            <Icon name="fas fa-memory" /> {bytes(profile.memory)}
          </span>
        </Tooltip>
      ),

      request && !request.document && (
        <Tooltip text={extractText(trans('summary.round_trip'))}>
          <span className="Debugbar-stat">
            <Icon name="fas fa-arrows-left-right" /> {duration(request.duration)}
          </span>
        </Tooltip>
      ),
    ];
  }

  body(): Mithril.Children {
    const state = this.attrs.state;

    if (state.isLoading()) {
      return <LoadingIndicator />;
    }

    const error = state.error();

    if (error) {
      return <Placeholder text={trans(error === 'expired' ? 'errors.expired' : 'errors.failed')} />;
    }

    const active = this.activePanel();
    const panel = this.visiblePanels().find(({ name }) => name === active);
    const profile = state.profile();

    if (!panel || !profile) {
      return <Placeholder text={trans('errors.no_data')} />;
    }

    return (
      <div className="Debugbar-panel" role="tabpanel">
        {panel.panel.render(panel.data, profile)}
      </div>
    );
  }

  /**
   * The grab handle along the top edge.
   *
   * Pointer events cover mouse, touch and pen with one code path, and pointer
   * capture means a drag that leaves the handle — which every drag does —
   * keeps being delivered to it.
   */
  resizer(): Mithril.Children {
    return (
      <div
        className="Debugbar-resizer"
        role="separator"
        aria-orientation="horizontal"
        aria-label={extractText(trans('resize'))}
        tabindex="0"
        onpointerdown={(event: PointerEvent) => this.startResize(event)}
        onpointermove={(event: PointerEvent) => this.resize(event)}
        onpointerup={(event: PointerEvent) => this.endResize(event)}
        onpointercancel={(event: PointerEvent) => this.endResize(event)}
        onkeydown={(event: KeyboardEvent) => this.resizeByKey(event)}
      />
    );
  }

  startResize(event: PointerEvent): void {
    const target = event.target as HTMLElement;

    target.setPointerCapture(event.pointerId);

    this.resizing = { pointer: event.pointerId, startY: event.clientY, startHeight: this.attrs.state.height };

    event.preventDefault();
  }

  resize(event: PointerEvent): void {
    if (this.resizing?.pointer !== event.pointerId) return;

    // Dragging up makes the bar taller, so the delta is inverted.
    this.attrs.state.resize(this.resizing.startHeight + (this.resizing.startY - event.clientY));
  }

  endResize(event: PointerEvent): void {
    if (this.resizing?.pointer !== event.pointerId) return;

    (event.target as HTMLElement).releasePointerCapture(event.pointerId);
    this.resizing = null;
  }

  resizeByKey(event: KeyboardEvent): void {
    const step = event.shiftKey ? 64 : 16;

    if (event.key === 'ArrowUp') {
      this.attrs.state.resize(this.attrs.state.height + step);
    } else if (event.key === 'ArrowDown') {
      this.attrs.state.resize(this.attrs.state.height - step);
    } else {
      return;
    }

    event.preventDefault();
  }

  oncreate(vnode: Mithril.VnodeDOM<CustomAttrs, this>) {
    super.oncreate(vnode);
    this.reserveSpace(vnode);
  }

  onupdate(vnode: Mithril.VnodeDOM<CustomAttrs, this>) {
    super.onupdate(vnode);
    this.reserveSpace(vnode);
  }

  onremove(vnode: Mithril.VnodeDOM<CustomAttrs, this>) {
    super.onremove(vnode);
    document.documentElement.style.removeProperty('--debugbar-offset');
  }

  /**
   * Keep the bottom of the page reachable.
   *
   * The bar is fixed to the viewport, so without this it sits on top of the
   * last few hundred pixels of every page — including, on a discussion, the
   * reply control.
   */
  protected reserveSpace(vnode: Mithril.VnodeDOM<CustomAttrs, this>): void {
    document.documentElement.style.setProperty('--debugbar-offset', `${vnode.dom.clientHeight}px`);
  }
}
