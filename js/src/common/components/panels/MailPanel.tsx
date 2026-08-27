import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';

import PanelLayout from '../PanelLayout';
import KeyValueList from '../KeyValueList';
import type { KeyValueEntries } from '../KeyValueList';
import { trans } from '../../config';
import { count } from '../../utils/format';
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
  protected expanded = new Set<number>();

  view(vnode: Mithril.Vnode<CustomAttrs, this>) {
    const data = this.attrs.data;

    return (
      <PanelLayout
        className="DebugbarMail"
        stats={[{ label: trans('mail.count'), value: count(data.count) }]}
        isEmpty={!data.messages.length}
        empty={trans('mail.empty')}
      >
        <ol className="DebugbarMail-list">{data.messages.map((message, index) => this.row(message, index))}</ol>
      </PanelLayout>
    );
  }

  row(message: MailMessage, index: number): Mithril.Children {
    const open = this.expanded.has(index);

    return (
      <li className={classList('DebugbarMailMessage', `DebugbarMailMessage--${message.status}`)} key={index}>
        <div className="DebugbarMailMessage-head">
          <span className="DebugbarBadge">{trans(`mail.statuses.${message.status}`)}</span>
          <span className="DebugbarMailMessage-subject">{message.subject}</span>
          <span className="DebugbarMailMessage-to">{message.to.join(', ')}</span>

          <Button
            className="Button Button--text"
            onclick={() => (open ? this.expanded.delete(index) : this.expanded.add(index))}
            aria-expanded={open}
          >
            {trans(open ? 'mail.hide' : 'mail.show')}
          </Button>
        </div>

        {open && (
          <div className="DebugbarMailMessage-details">
            <KeyValueList entries={this.headers(message)} />
            {!!message.body && <pre className="DebugbarCode DebugbarMailMessage-body">{message.body}</pre>}
          </div>
        )}
      </li>
    );
  }

  headers(message: MailMessage): KeyValueEntries {
    const fields: Array<[string, string[]]> = [
      ['from', message.from],
      ['to', message.to],
      ['cc', message.cc],
      ['bcc', message.bcc],
      ['reply_to', message.replyTo],
    ];

    // Most messages have no Cc, Bcc or Reply-To, and three empty rows push the
    // body out of view for nothing.
    return fields
      .filter(([, addresses]) => addresses.length > 0)
      .map(([key, addresses]): [Mithril.Children, Mithril.Children] => [trans(`mail.headers.${key}`), addresses.join(', ')]);
  }
}
