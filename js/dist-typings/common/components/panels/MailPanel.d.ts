import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type { KeyValueEntries } from '../KeyValueList';
import type { MailData, MailMessage } from '../../types';
export interface MailPanelAttrs extends ComponentAttrs {
    data: MailData;
}
/**
 * Mail the request tried to send.
 *
 * The body is included because the usual question is not "was it sent" but
 * "what did it say" — and on a development forum with the `log` mail driver,
 * this is often the only place it can be read.
 */
export default class MailPanel<CustomAttrs extends MailPanelAttrs = MailPanelAttrs> extends Component<CustomAttrs> {
    protected expanded: Set<number>;
    view(vnode: Mithril.Vnode<CustomAttrs, this>): JSX.Element;
    row(message: MailMessage, index: number): Mithril.Children;
    headers(message: MailMessage): KeyValueEntries;
}
