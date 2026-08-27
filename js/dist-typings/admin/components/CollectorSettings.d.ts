import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
export interface CollectorSettingsAttrs extends ComponentAttrs {
    /**
     * The `disabled_collectors` setting, as the admin page's own stream — so
     * these switches are saved by the page's Save button and counted by its
     * unsaved-changes indicator, like every other setting on it.
     */
    stream: Stream<string>;
}
/**
 * A switch per collector.
 *
 * The list comes from the backend rather than from a constant here, so a
 * collector registered by another extension through the extender gets a switch
 * without this file knowing it exists.
 */
export default class CollectorSettings<CustomAttrs extends CollectorSettingsAttrs = CollectorSettingsAttrs> extends Component<CustomAttrs> {
    view(vnode: Mithril.Vnode<CustomAttrs, this>): JSX.Element;
    /**
     * @return The names of the collectors currently switched off.
     */
    disabled(): string[];
    set(collector: string, enabled: boolean): void;
}
