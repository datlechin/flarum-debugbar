import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import { jest } from '@jest/globals';

import Debugbar from '../../src/common/components/Debugbar';
import DebugbarState from '../../src/common/states/DebugbarState';
import type { Profile } from '../../src/common/types';

/**
 * A bar showing a profile that carries exactly the given collectors.
 */
function bar(collectors: Record<string, unknown>): Debugbar {
  const state = new DebugbarState({ requestId: 'page', openByDefault: true });

  const profile: Profile = {
    id: 'page',
    time: 1787816682.5,
    method: 'GET',
    uri: '/',
    status: 200,
    duration: 0.25,
    memory: 2097152,
    data: collectors,
  };

  // The bar reads profiles through `profile()`; what put them there is not
  // what these tests are about.
  jest.spyOn(state, 'profile').mockReturnValue(profile);

  const component = new Debugbar();
  (component as any).attrs = { state };

  return component;
}

function names(component: Debugbar): string[] {
  return component.visiblePanels().map(({ name }) => name);
}

beforeAll(() => {
  bootstrapForum();
  app.boot();
});

beforeEach(() => {
  localStorage.clear();
});

describe('choosing which tabs to show', () => {
  it('shows only the panels this request has data for', () => {
    // Every collector can be switched off, so a bar that always drew ten tabs
    // would offer eight that say nothing.
    expect(names(bar({ queries: {}, timeline: {} }))).toEqual(['timeline', 'queries']);
  });

  it('orders panels by the priority they were registered with', () => {
    const shown = names(bar({ environment: {}, queries: {}, timeline: {}, messages: {} }));

    expect(shown).toEqual(['timeline', 'queries', 'messages', 'environment']);
  });

  it('shows a collector it has never heard of, after the ones it knows', () => {
    // An extension can add a collector with the extender and see its data
    // straight away, rather than having to write a panel first.
    expect(names(bar({ queries: {}, 'acme-widgets': {} }))).toEqual(['queries', 'acme-widgets']);
  });

  it('renders an unknown collector with the generic panel', () => {
    const panel = bar({ 'acme-widgets': { count: 2 } }).visiblePanels()[0];

    expect(panel.panel.title()).toBe('acme-widgets');
    expect(panel.panel.render(panel.data, {} as Profile)).toBeTruthy();
  });

  it('shows nothing at all before a profile has arrived', () => {
    const state = new DebugbarState({ requestId: 'page', openByDefault: true });
    const component = new Debugbar();
    (component as any).attrs = { state };

    expect(component.visiblePanels()).toEqual([]);
    expect(component.activePanel()).toBeNull();
  });
});

describe('choosing which tab is open', () => {
  it('opens the panel that was open last time', () => {
    const component = bar({ timeline: {}, queries: {} });
    component.attrs.state.show('queries');

    expect(component.activePanel()).toBe('queries');
  });

  it('falls back to the first panel when the remembered one is gone', () => {
    // A panel whose collector has since been switched off would otherwise
    // leave the bar blank with no way to recover from inside it.
    const component = bar({ timeline: {}, queries: {} });
    component.attrs.state.show('mail');

    expect(component.activePanel()).toBe('timeline');
  });

  it('falls back to the first panel when nothing was ever chosen', () => {
    expect(bar({ timeline: {}, queries: {} }).activePanel()).toBe('timeline');
  });
});

describe('badges', () => {
  it('counts queries on the tab, so the figure is readable without opening it', () => {
    const panel = bar({ queries: { count: 12, duplicates: 0 } }).visiblePanels()[0];

    expect(panel.panel.badge?.(panel.data)).toBe((12).toLocaleString());
  });

  it('marks the queries tab when a statement ran more than once', () => {
    const clean = bar({ queries: { count: 12, duplicates: 0 } }).visiblePanels()[0];
    const duplicated = bar({ queries: { count: 12, duplicates: 3 } }).visiblePanels()[0];

    expect(clean.panel.severity?.(clean.data)).toBeNull();
    expect(duplicated.panel.severity?.(duplicated.data)).toBe('warning');
  });

  it('marks the messages tab when something was logged as an error', () => {
    const quiet = bar({ messages: { count: 1, messages: [{ level: 'info' }] } }).visiblePanels()[0];
    const loud = bar({ messages: { count: 1, messages: [{ level: 'error' }] } }).visiblePanels()[0];

    expect(quiet.panel.severity?.(quiet.data)).toBeNull();
    expect(loud.panel.severity?.(loud.data)).toBe('error');
  });

  it('leaves a badge off a panel that has nothing to count', () => {
    const panel = bar({ mail: { count: 0, messages: [] } }).visiblePanels()[0];

    expect(panel.panel.badge?.(panel.data)).toBeNull();
  });
});

describe('extensibility', () => {
  it('lets another extension add a panel', () => {
    extend(Debugbar.prototype, 'panels', (items: any) => {
      items.add('acme-widgets', { icon: 'fas fa-cube', title: () => 'Widgets', render: () => 'rendered' }, 95);
    });

    const panels = bar({ timeline: {}, queries: {}, 'acme-widgets': {} }).visiblePanels();

    // Registered at 95, so between timeline (100) and queries (90) — a panel
    // added this way is a peer of the built-in ones, not an afterthought.
    expect(panels.map(({ name }) => name)).toEqual(['timeline', 'acme-widgets', 'queries']);
    expect(panels[1].panel.title()).toBe('Widgets');
  });
});
