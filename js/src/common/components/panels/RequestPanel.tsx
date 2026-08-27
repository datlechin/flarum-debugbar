import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';

import PanelLayout from '../PanelLayout';
import KeyValueList from '../KeyValueList';
import type { KeyValueEntries } from '../KeyValueList';
import { trans } from '../../config';
import type { RequestData } from '../../types';

export interface RequestPanelAttrs extends ComponentAttrs {
  data: RequestData;
}

/**
 * What was asked for, who asked, and what answered.
 *
 * Sections with nothing in them are left out rather than shown empty: on an
 * ordinary forum page the JSON:API section and the route parameters are both
 * blank, and four rows of dashes push the rows that do say something off the
 * bottom of the panel.
 */
export default class RequestPanel<CustomAttrs extends RequestPanelAttrs = RequestPanelAttrs> extends Component<CustomAttrs> {
  view(vnode: Mithril.Vnode<CustomAttrs, this>) {
    const data = this.attrs.data;

    return (
      <PanelLayout className="DebugbarRequest">
        <div className="DebugbarSections">
          {this.section(trans('request.sections.overview'), this.overview())}
          {this.section(trans('request.sections.actor'), this.actor())}
          {this.section(trans('request.sections.parameters'), data.route.parameters)}
          {this.section(trans('request.sections.query'), data.query)}
          {this.section(trans('request.sections.json_api'), data.jsonApi)}
          {this.section(trans('request.sections.request_headers'), data.requestHeaders)}
          {this.section(trans('request.sections.response_headers'), data.responseHeaders)}
        </div>
      </PanelLayout>
    );
  }

  section(title: Mithril.Children, entries: KeyValueEntries): Mithril.Children {
    const length = Array.isArray(entries) ? entries.length : Object.keys(entries).length;

    if (!length) return null;

    return (
      <section className="DebugbarSection">
        <h3 className="DebugbarSection-title">{title}</h3>
        <KeyValueList entries={entries} />
      </section>
    );
  }

  overview(): KeyValueEntries {
    const { method, uri, status, route } = this.attrs.data;

    const entries: KeyValueEntries = [
      [trans('request.method'), <code>{method}</code>],
      [trans('request.uri'), <code className="DebugbarRequest-uri">{uri}</code>],
      [trans('request.status'), String(status)],
      [trans('request.route'), <code>{route.name ?? '—'}</code>],
      [trans('request.handler'), <code>{route.handler}</code>],
    ];

    if (route.internal) {
      entries.push([trans('request.internal'), trans('yes')]);
    }

    return entries;
  }

  actor(): KeyValueEntries {
    const actor = this.attrs.data.actor;

    // The actor is only known once `InjectActorReference` has run, so a
    // request rejected before that reports nothing but why.
    if (!('isGuest' in actor)) {
      return [[trans('request.authentication'), actor.authentication]];
    }

    const entries: KeyValueEntries = [
      [trans('request.user'), actor.isGuest ? trans('request.guest') : `${actor.username} (#${actor.id})`],
      [trans('request.authentication'), actor.authentication],
    ];

    // The backend omits these when reading them would have cost a query it
    // was not otherwise going to make. Absent is not the same as false, so
    // they are left out here too rather than reported as "no".
    if ('isAdmin' in actor) {
      entries.push([trans('request.admin'), trans(actor.isAdmin ? 'yes' : 'no')]);
      entries.push([trans('request.groups'), actor.groups?.length ? actor.groups.join(', ') : '—']);
    }

    return entries;
  }
}
