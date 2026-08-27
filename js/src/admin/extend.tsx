import Extend from 'flarum/common/extenders';
import extractText from 'flarum/common/utils/extractText';
import type AdminPage from 'flarum/admin/components/AdminPage';
import type Mithril from 'mithril';

import CollectorSettings from './components/CollectorSettings';
import DebugModeNotice from './components/DebugModeNotice';
import StoredProfiles from './components/StoredProfiles';
import { SETTINGS, transAdmin } from '../common/config';

/**
 * The extension's settings, registered against core's own extension page.
 *
 * There is no custom page here on purpose: three settings, a list of switches
 * and one button are exactly what `ExtensionPage` is for, and using it means
 * the save button, the unsaved-changes count, the reset dialogue and the admin
 * search index all work without being reimplemented.
 *
 * The two custom entries are called with the page as their `this`, so they can
 * use `this.setting()` and be saved along with everything else.
 */
export default [
  new Extend.Admin()
    .customSetting(function (this: AdminPage): Mithril.Children {
      return <DebugModeNotice />;
    }, 100)

    .setting(
      () => ({
        setting: SETTINGS.maxProfiles,
        type: 'number',
        min: 1,
        max: 500,
        label: transAdmin('max_profiles.label'),
        help: transAdmin('max_profiles.help'),
      }),
      90
    )

    .setting(
      () => ({
        setting: SETTINGS.traceQueries,
        type: 'bool',
        label: transAdmin('trace_queries.label'),
        help: transAdmin('trace_queries.help'),
      }),
      80
    )

    .setting(
      () => ({
        setting: SETTINGS.openByDefault,
        type: 'bool',
        label: transAdmin('open_by_default.label'),
        help: transAdmin('open_by_default.help'),
      }),
      70
    )

    .customSetting(function (this: AdminPage): Mithril.Children {
      return <CollectorSettings stream={this.setting(SETTINGS.disabledCollectors, '[]')} />;
    }, 60)

    .customSetting(function (this: AdminPage): Mithril.Children {
      return <StoredProfiles />;
    }, 50)

    // So that an administrator searching the admin panel for "queries" or
    // "debug" lands here without having to know the extension's name.
    .generalIndexItems('settings', () => [
      {
        id: SETTINGS.maxProfiles,
        label: extractText(transAdmin('max_profiles.label')),
        help: extractText(transAdmin('max_profiles.help')),
      },
      {
        id: SETTINGS.traceQueries,
        label: extractText(transAdmin('trace_queries.label')),
        help: extractText(transAdmin('trace_queries.help')),
      },
      {
        id: SETTINGS.openByDefault,
        label: extractText(transAdmin('open_by_default.label')),
        help: extractText(transAdmin('open_by_default.help')),
      },
      {
        id: SETTINGS.disabledCollectors,
        label: extractText(transAdmin('collectors.label')),
        help: extractText(transAdmin('collectors.help')),
      },
    ]),
];
