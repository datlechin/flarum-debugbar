import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import { jest } from '@jest/globals';

import DebugbarState from '../../src/common/states/DebugbarState';
import type { ObservedRequest, Profile } from '../../src/common/types';

function profile(id: string): Profile {
  return {
    id,
    time: 1787816682.5,
    method: 'GET',
    uri: '/',
    status: 200,
    duration: 0.25,
    memory: 2097152,
    data: { queries: { count: 3 } },
  };
}

function xhr(id: string, overrides: Partial<ObservedRequest> = {}): ObservedRequest {
  return { id, method: 'POST', uri: '/discussions', status: 201, duration: 0.1, time: 1787816682.5, document: false, ...overrides };
}

function state(openByDefault = false): DebugbarState {
  return new DebugbarState({ requestId: 'page', openByDefault });
}

beforeAll(() => {
  bootstrapForum();
  app.boot();
  app.forum.pushAttributes({ apiUrl: 'https://forum.test/api' });
});

beforeEach(() => {
  localStorage.clear();
  jest.restoreAllMocks();
});

describe('starting up', () => {
  it('lists the page it is running on', () => {
    const debugbar = state();

    expect(debugbar.requests).toHaveLength(1);
    expect(debugbar.requests[0].document).toBe(true);
    expect(debugbar.selected).toBe('page');
  });

  it('follows the forum-wide preference when nothing is remembered', () => {
    expect(state(false).open).toBe(false);
    expect(state(true).open).toBe(true);
  });
});

describe('remembering how it was left', () => {
  it('keeps its shape across a page load', () => {
    // Navigating a forum is a full page load, so a bar that collapsed itself
    // every time would be useless for following a sequence of requests.
    const first = state();
    first.toggle(true);
    first.show('queries');
    first.resize(420);

    const second = state();

    expect(second.open).toBe(true);
    expect(second.panel).toBe('queries');
    expect(second.height).toBe(420);
  });

  it('overrides the forum-wide preference once a choice has been made', () => {
    state(true).toggle(false);

    expect(state(true).open).toBe(false);
  });

  it('still works where storage is unavailable', () => {
    // A browser set to block site data throws outright rather than returning
    // null, and a bar that cannot remember its position is still a usable bar.
    jest.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('denied');
    });
    jest.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('denied');
    });

    const debugbar = state(true);

    expect(debugbar.open).toBe(true);
    expect(() => debugbar.toggle()).not.toThrow();
  });

  it('ignores a stored value it cannot read', () => {
    localStorage.setItem('datlechin-debugbar', 'not json');

    expect(() => state()).not.toThrow();
    expect(state(true).open).toBe(true);
  });
});

describe('the request list', () => {
  it('puts the newest request first and selects it', () => {
    // When you click something and want to know what it cost, the request you
    // meant is the one that just happened.
    const debugbar = state();

    debugbar.observe(xhr('one'));
    debugbar.observe(xhr('two'));

    expect(debugbar.requests.map((request) => request.id)).toEqual(['two', 'one', 'page']);
    expect(debugbar.selected).toBe('two');
  });

  it('is bounded, so a long-lived page cannot grow it without limit', () => {
    const debugbar = state();

    for (let i = 0; i < 60; i++) debugbar.observe(xhr(`request-${i}`));

    expect(debugbar.requests).toHaveLength(50);
    expect(debugbar.requests[0].id).toBe('request-59');
  });

  it('reports the request that is selected', () => {
    const debugbar = state();
    debugbar.observe(xhr('one'));

    expect(debugbar.current()?.id).toBe('one');
  });
});

describe('loading a profile', () => {
  function mockRequest(handler: (options: any) => Promise<any>) {
    return jest.spyOn(app, 'request').mockImplementation(handler as any);
  }

  it('fetches the selected profile from the API', async () => {
    const request = mockRequest(async () => ({ data: profile('page') }));

    const debugbar = state();
    debugbar.load('page');

    await Promise.resolve();

    expect(request).toHaveBeenCalledTimes(1);
    expect((request.mock.calls[0][0] as any).url).toBe('https://forum.test/api/debugbar/profiles/page');
    expect(debugbar.profile()?.id).toBe('page');
  });

  it('fetches each profile only once', async () => {
    const request = mockRequest(async () => ({ data: profile('page') }));

    const debugbar = state();
    debugbar.load('page');
    await Promise.resolve();
    debugbar.load('page');
    debugbar.load('page');

    expect(request).toHaveBeenCalledTimes(1);
  });

  it('does not start a second fetch while the first is in flight', () => {
    const request = mockRequest(() => new Promise(() => {}));

    const debugbar = state();
    debugbar.load('page');
    debugbar.load('page');

    expect(request).toHaveBeenCalledTimes(1);
    expect(debugbar.isLoading()).toBe(true);
  });

  it('remembers a profile that has been pruned, rather than asking again', async () => {
    // Retention is bounded, so a request the bar still lists may already be
    // gone. Without remembering the failure this would refetch on every
    // redraw.
    const request = mockRequest(async () => {
      throw { status: 404 };
    });

    const debugbar = state();
    debugbar.load('page');
    await Promise.resolve();
    await Promise.resolve();

    expect(debugbar.error()).toBe('expired');

    debugbar.load('page');
    expect(request).toHaveBeenCalledTimes(1);
  });

  it('distinguishes a pruned profile from a failed request', async () => {
    mockRequest(async () => {
      throw { status: 500 };
    });

    const debugbar = state();
    debugbar.load('page');
    await Promise.resolve();
    await Promise.resolve();

    expect(debugbar.error()).toBe('failed');
  });

  it('never shows the forum an error alert for its own bookkeeping', async () => {
    const request = mockRequest(async () => ({ data: profile('page') }));

    state().load('page');

    expect(typeof (request.mock.calls[0][0] as any).errorHandler).toBe('function');
  });

  it('opening the bar does not refetch what it already has', async () => {
    // The profile is fetched as soon as its request is noticed, open or not —
    // a collapsed bar still reports status, time, memory and query count, and
    // those figures only exist in the profile.
    const request = mockRequest(async () => ({ data: profile('page') }));

    const debugbar = state();
    debugbar.load('page');
    await Promise.resolve();

    debugbar.toggle(true);
    debugbar.toggle(false);
    debugbar.toggle(true);

    expect(request).toHaveBeenCalledTimes(1);
  });

  it('selecting a request loads it', () => {
    const request = mockRequest(() => new Promise(() => {}));

    const debugbar = state();
    debugbar.observe(xhr('one'));
    debugbar.select('one');

    expect(debugbar.selected).toBe('one');
    expect(request).toHaveBeenCalledTimes(1);
  });
});

describe('resizing', () => {
  it('will not shrink below something usable', () => {
    const debugbar = state();
    debugbar.resize(10);

    expect(debugbar.height).toBe(160);
  });

  it('will not grow past the window', () => {
    const debugbar = state();
    debugbar.resize(100000);

    expect(debugbar.height).toBeLessThanOrEqual(window.innerHeight);
  });
});
