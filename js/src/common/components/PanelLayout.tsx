import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Input from 'flarum/common/components/Input';
import LabelValue from 'flarum/common/components/LabelValue';
import Placeholder from 'flarum/common/components/Placeholder';
import classList from 'flarum/common/utils/classList';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

import { trans } from '../config';

export interface PanelStat {
  label: Mithril.Children;
  value: Mithril.Children;
  severity?: 'warning' | 'error';
  /**
   * Makes the figure a filter. Several panels have one statistic that is also
   * the most useful way to narrow the list below it — "duplicates" on queries,
   * a level on messages — and turning the figure itself into the control is
   * fewer things on screen than a figure plus a button that mean the same.
   */
  onclick?: () => void;
  active?: boolean;
}

export interface PanelLayoutAttrs extends ComponentAttrs {
  /** Headline figures, shown above the content. */
  stats?: PanelStat[];
  /** Called as the reader types, if this panel can be filtered. */
  onfilter?: (query: string) => void;
  filter?: string;
  filterPlaceholder?: Mithril.Children;
  /** Rendered in place of the children when there is nothing to show. */
  empty?: Mithril.Children;
  isEmpty?: boolean;
  className?: string;
}

/**
 * The frame every panel is rendered in: a row of headline figures, an optional
 * filter box, and the panel's own content below.
 *
 * The figures are core's `LabelValue`, and the row is spaced and bordered like
 * `HeaderList-header` — the strip above the notifications list, which is the
 * same thing in the same place doing the same job.
 */
export default class PanelLayout<CustomAttrs extends PanelLayoutAttrs = PanelLayoutAttrs> extends Component<CustomAttrs> {
  view(vnode: Mithril.Vnode<CustomAttrs, this>) {
    const { stats, onfilter, empty, isEmpty } = this.attrs;
    const hasHeader = !!stats?.length || !!onfilter;

    return (
      <div className={classList('DebugbarPanel', this.attrs.className)}>
        {hasHeader && (
          <div className="DebugbarPanel-header">
            <div className="DebugbarStats">{stats?.map((stat) => this.stat(stat))}</div>

            {onfilter && (
              <Input
                className="DebugbarPanel-filter"
                type="search"
                clearable={true}
                value={this.attrs.filter ?? ''}
                placeholder={extractText(this.attrs.filterPlaceholder ?? trans('filter_placeholder'))}
                ariaLabel={extractText(this.attrs.filterPlaceholder ?? trans('filter_placeholder'))}
                onchange={onfilter}
              />
            )}
          </div>
        )}

        <div className="DebugbarPanel-content">{isEmpty ? <Placeholder text={empty ?? trans('empty')} /> : vnode.children}</div>
      </div>
    );
  }

  stat(stat: PanelStat): Mithril.Children {
    const figure = <LabelValue label={stat.label} value={stat.value} />;

    if (!stat.onclick) {
      return <div className={classList('DebugbarStats-item', stat.severity && `DebugbarStats-item--${stat.severity}`)}>{figure}</div>;
    }

    return (
      <Button
        className={classList('Button Button--link DebugbarStats-item', stat.severity && `DebugbarStats-item--${stat.severity}`)}
        active={stat.active}
        aria-pressed={!!stat.active}
        onclick={stat.onclick}
      >
        {figure}
      </Button>
    );
  }
}
