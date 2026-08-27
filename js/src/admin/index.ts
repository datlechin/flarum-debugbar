import app from 'flarum/admin/app';

import boot from '../common/boot';
import { EXTENSION } from '../common/config';

app.initializers.add(EXTENSION, () => boot());

export { default as extend } from './extend';

export { default as Debugbar } from '../common/components/Debugbar';
export { default as DebugbarState } from '../common/states/DebugbarState';
export { default as CollectorSettings } from './components/CollectorSettings';
export { default as StoredProfiles } from './components/StoredProfiles';
export { default as DebugModeNotice } from './components/DebugModeNotice';
export * from './catalogue';
export * from '../common/config';
export * from '../common/utils/format';
export type * from '../common/types';
