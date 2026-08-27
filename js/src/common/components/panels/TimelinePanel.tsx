import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';

import PanelLayout from '../PanelLayout';
import { trans, transIfExists } from '../../config';
import { duration } from '../../utils/format';
import type { Measure, TimelineData } from '../../types';

export interface TimelinePanelAttrs extends ComponentAttrs {
  data: TimelineData;
}

/**
 * Named spans of the request drawn against its total duration.
 *
 * The bars are positioned by where each span actually fell, not stacked in
 * sequence, so a span that overlaps another is visibly concurrent and a gap
 * between two spans is visibly a gap — which is usually the part worth asking
 * about.
 */
export default class TimelinePanel<CustomAttrs extends TimelinePanelAttrs = TimelinePanelAttrs> extends Component<CustomAttrs> {
  view(vnode: Mithril.Vnode<CustomAttrs, this>) {
    const { measures, duration: total } = this.attrs.data;

    return (
      <PanelLayout
        className="DebugbarTimeline"
        stats={[
          { label: trans('timeline.total'), value: duration(total) },
          { label: trans('timeline.measures'), value: String(measures.length) },
        ]}
        isEmpty={!measures.length}
        empty={trans('timeline.empty')}
      >
        <ol className="DebugbarTimeline-list">{this.rows(measures, total)}</ol>
      </PanelLayout>
    );
  }

  rows(measures: Measure[], total: number): Mithril.Children {
    // Guard the divisor: a request fast enough to round to zero would
    // otherwise give every bar a width of Infinity.
    const scale = total > 0 ? total : 1;

    return this.withDepth(measures).map(({ measure, depth }) => {
      const width = Math.max((measure.duration / scale) * 100, 0.4);
      const offset = Math.min((measure.start / scale) * 100, 100 - width);

      // Spans the request always has get a translated name; everything else —
      // a span opened by application code — keeps the label it was given.
      const label = transIfExists(`timeline.spans.${measure.name}`) ?? measure.label;

      return (
        <li className="DebugbarTimeline-item" key={measure.name}>
          <span className="DebugbarTimeline-label" style={{ paddingInlineStart: `${depth * 12}px` }} title={measure.label}>
            {label}
          </span>

          <span className="DebugbarTimeline-track">
            <span
              className={classList('DebugbarTimeline-bar', measure.unfinished && 'DebugbarTimeline-bar--unfinished')}
              style={{ insetInlineStart: `${offset}%`, width: `${width}%` }}
            />
          </span>

          <span className="DebugbarTimeline-duration">
            {duration(measure.duration)}
            {measure.unfinished && <span className="DebugbarTimeline-unfinished"> {trans('timeline.unfinished')}</span>}
          </span>
        </li>
      );
    });
  }

  /**
   * How deeply each span is nested, worked out from the spans themselves.
   *
   * Callers never declare nesting — a measure around a measure is just two
   * calls — so it is recovered here by tracking which spans are still open at
   * the point each new one starts.
   */
  withDepth(measures: Measure[]): Array<{ measure: Measure; depth: number }> {
    const open: Measure[] = [];

    return measures.map((measure) => {
      while (open.length && open[open.length - 1].start + open[open.length - 1].duration <= measure.start) {
        open.pop();
      }

      const depth = open.length;

      open.push(measure);

      return { measure, depth };
    });
  }
}
