import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

export default class extends Controller {
    static values = {
        placeholder: { type: String, default: '' },
        submitOnChange: { type: Boolean, default: false },
        noResultsText: { type: String, default: 'Nic nenalezeno' },
    };

    connect() {
        // Primary line = nickname when present; otherwise fall back to the option's visible text
        // (which is the fullName for nickname-less users, or '' for the empty placeholder).
        // Subtitle line = fullName, shown only when there's a separate nickname above it.
        const primary = (data) => data.nickname || data.text;
        const personSubtitle = (data) => (data.fullName && data.nickname) ? data.fullName : '';
        // Non-person options describe themselves with plain `data-sub` / `data-meta`
        // attributes (tom-select copies every `dataset` key onto the option data). The
        // soutěž switcher uses them for the zdroj-zápasů name and the date range.
        const subtitle = (data) => personSubtitle(data) || data.sub || '';
        const meta = (data) => data.meta || '';
        // Options may carry a leading image via `data-flag` (the country picker puts
        // the round flag asset there); it renders in both the dropdown and the control.
        const icon = (data, escape) => data.flag
            ? `<img src="${escape(data.flag)}" alt="" class="inline-block h-4 w-4 flex-none rounded-full object-contain" />`
            : '';

        const options = {
            // Render the dropdown into <body>, never inside the control's own card.
            // Cards clip (`.card-glass { overflow: hidden }`) and open a stacking context
            // (`backdrop-filter`), which crops an in-card dropdown at the card's edge.
            // See the "B3: tom-select in cards" block in assets/styles/app.css.
            dropdownParent: 'body',
            allowEmptyOption: true,
            create: false,
            maxOptions: 200,
            searchField: ['text', 'sub'],
            // Optgroups keep their DOM order instead of being reshuffled by search score —
            // the soutěž switcher must always list „Probíhající" above „Ukončené".
            lockOptgroupOrder: true,
            dataAttr: 'data-data',
            placeholder: this.placeholderValue || undefined,
            render: {
                no_results: () => `<div class="no-results">${this.noResultsTextValue}</div>`,
                option: (data, escape) => {
                    const sub = subtitle(data);
                    const extra = meta(data);
                    const unverified = data.unverified ? ' <span class="text-xs text-white/40">(neověřený)</span>' : '';
                    const secondLine = (sub || extra)
                        ? `<small class="mt-0.5 flex flex-wrap items-baseline gap-x-2 text-xs leading-tight text-white/60">${sub ? `<span>${escape(sub)}</span>` : ''}${extra ? `<span class="text-white/40">${escape(extra)}</span>` : ''}</small>`
                        : '';
                    return `<div class="flex items-center gap-2 py-1">${icon(data, escape)}<div class="min-w-0"><div class="leading-tight">${escape(primary(data))}${unverified}</div>${secondLine}</div></div>`;
                },
                // The control itself stays single-line: only the person subtitle (nickname +
                // full name) is worth the second row there, never the `data-sub` metadata.
                item: (data, escape) => {
                    const sub = personSubtitle(data);
                    return `<div class="flex items-center gap-2 leading-tight">${icon(data, escape)}<div><div>${escape(primary(data))}</div>${sub ? `<small class="block text-xs leading-tight text-white/60">${escape(sub)}</small>` : ''}</div></div>`;
                },
            },
        };

        if (this.submitOnChangeValue) {
            options.onChange = () => {
                const form = this.element.form;
                if (form) {
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                }
            };
        }

        this.instance = new TomSelect(this.element, options);
    }

    disconnect() {
        if (this.instance) {
            this.instance.destroy();
            this.instance = null;
        }
    }
}
