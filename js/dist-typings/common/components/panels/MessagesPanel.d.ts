import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type { PanelStat } from '../PanelLayout';
import type { Message, MessageLevel, MessagesData } from '../../types';
export interface MessagesPanelAttrs extends ComponentAttrs {
    data: MessagesData;
    /** When the request started, so entries can be shown as offsets. */
    start: number;
}
/**
 * Messages logged by application code, and the exceptions that got away.
 *
 * Levels are filtered by toggling them rather than by choosing one, because
 * the useful views are "errors and warnings" and "everything" — not "warnings
 * alone".
 */
export default class MessagesPanel<CustomAttrs extends MessagesPanelAttrs = MessagesPanelAttrs> extends Component<CustomAttrs> {
    protected filter: string;
    protected hidden: Set<MessageLevel>;
    protected expanded: Set<number>;
    view(vnode: Mithril.Vnode<CustomAttrs, this>): JSX.Element;
    stats(): PanelStat[];
    totals(): Record<MessageLevel, number>;
    matches(messages: Message[]): Array<{
        message: Message;
        index: number;
    }>;
    row(message: Message, index: number): Mithril.Children;
}
