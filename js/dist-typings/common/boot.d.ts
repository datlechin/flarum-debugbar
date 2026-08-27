import DebugbarState from './states/DebugbarState';
declare module 'flarum/common/Application' {
    export default interface Application {
        debugbar?: DebugbarState;
    }
}
/**
 * Start the debug bar, if this page has one.
 *
 * Everything here hangs off the presence of `app.data.debugbar`, which the
 * backend only writes in debug mode and only for administrators. On any other
 * page these extenders run, find nothing, and cost nothing.
 */
export default function boot(): void;
