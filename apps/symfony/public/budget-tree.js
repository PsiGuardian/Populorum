/* Démos Kratos · exploration progressive, no third-party libraries. */
(() => {
    'use strict';
    const browser = document.getElementById('budget-tree-browser');
    if (!browser) return;
    const endpoint = browser.dataset.endpoint;
    const target = document.getElementById('budget-tree-list');
    const status = document.getElementById('budget-tree-status');
    const euro = new Intl.NumberFormat('fr-FR', {style: 'currency', currency: 'EUR', maximumFractionDigits: 2});
    const make = (tag, css, value) => {
        const el = document.createElement(tag);
        if (css) el.className = css;
        if (value !== undefined) el.textContent = value;
        return el;
    };
    const format = cents => Number.isSafeInteger(cents) ? euro.format(cents / 100) : 'Montant non documenté';

    const query = async (id, offset = 0) => {
        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set('node', id);
        url.searchParams.set('offset', String(offset));
        const response = await fetch(url, {credentials: 'same-origin', headers: {Accept: 'application/json'}});
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
    };

    async function loadChildren(node, append = false, offset = 0) {
        if (node.loading) return;
        node.loading = true;
        node.control.disabled = true;
        status.textContent = 'Chargement de « ' + node.label + ' »…';
        try {
            const data = await query(node.id, offset);
            if (!append) node.list.replaceChildren();
            const oldMore = node.list.querySelector(':scope > li.budget-tree-more');
            if (oldMore) oldMore.remove();
            for (const child of data.children) node.list.appendChild(renderNode(child));
            if (data.nextOffset !== null) {
                const li = make('li', 'budget-tree-more');
                const more = make('button', 'button', 'Afficher les éléments suivants');
                more.type = 'button';
                more.addEventListener('click', () => loadChildren(node, true, data.nextOffset));
                li.append(more);
                node.list.appendChild(li);
            }
            node.loaded = true;
            node.list.hidden = false;
            node.control.setAttribute('aria-expanded', 'true');
            status.textContent = (data.children.length || 0) + ' branche(s) affichée(s). Dépliez une branche pour continuer.';
        } catch (error) {
            status.textContent = 'Chargement impossible (' + error.message + '). Vérifiez la connexion et réessayez.';
        } finally {
            node.loading = false;
            node.control.disabled = false;
        }
    }

    function renderNode(info) {
        const li = make('li', 'budget-tree-item budget-tree-item--' + info.kind);
        const item = make('div', 'budget-tree-node');
        const icon = make('span', 'budget-tree-symbol', info.hasChildren ? '＋' : '•');
        icon.setAttribute('aria-hidden', 'true');
        const label = make(info.hasChildren ? 'button' : 'span', info.hasChildren ? 'budget-tree-toggle' : 'budget-tree-label', info.label);
        if (info.hasChildren) label.type = 'button';
        item.append(icon, label);
        if (Number.isSafeInteger(info.amountCents)) item.append(make('strong', 'budget-tree-money', format(info.amountCents)));
        else if (info.kind === 'unknown') item.append(make('small', 'budget-tree-unknown', 'Non vérifiable'));
        if (info.sourceUrl) {
            const link = make('a', 'budget-tree-source', 'Source');
            link.href = info.sourceUrl;
            link.rel = 'noopener noreferrer';
            if (info.sourceUrl.startsWith('/marches-publics')) {
                link.textContent = 'Fiche';
            } else {
                link.target = '_blank';
            }
            item.append(link);
        }
        li.append(item);
        if (info.note) li.append(make('p', 'budget-tree-description', info.note));
        if (info.hasChildren) {
            const list = make('ul', 'budget-tree-children');
            list.hidden = true;
            li.append(list);
            const node = {id: info.id, label: info.label, list, control: label, loading: false, loaded: false};
            li._budgetNode = node;
            label.setAttribute('aria-expanded', 'false');
            label.addEventListener('click', async () => {
                if (node.loaded) {
                    node.list.hidden = !node.list.hidden;
                    label.setAttribute('aria-expanded', String(!node.list.hidden));
                    icon.textContent = node.list.hidden ? '＋' : '−';
                } else {
                    await loadChildren(node);
                    if (node.loaded) icon.textContent = '−';
                }
            });
        }
        return li;
    }

    function openAt(id, label) {
        target.replaceChildren();
        const root = renderNode({id, label, hasChildren: true, amountCents: null, kind: 'root', note: '', sourceUrl: null});
        target.append(root);
        root._budgetNode.control.click();
    }

    openAt('root', 'France · explorer les régions et communes présentes dans les données');
    document.getElementById('budget-tree-root')?.addEventListener('click', () => openAt('root', 'France · régions et communes disponibles'));
    document.getElementById('budget-tree-focus')?.addEventListener('click', () => openAt(browser.dataset.focus, 'Commune sélectionnée · dépenses et justificatifs'));
    document.getElementById('budget-tree-collapse')?.addEventListener('click', () => openAt('root', 'France · régions et communes disponibles'));
    document.getElementById('budget-tree-evidence')?.addEventListener('click', () => openAt('evidence:root', 'Références publiques · documents vérifiables par catégorie'));
    document.getElementById('budget-tree-expand')?.addEventListener('click', async () => {
        const root = target.firstElementChild?._budgetNode;
        if (!root) return;
        if (!root.loaded) await loadChildren(root);
        // Bound requests and DOM expansion: never fetch thousands of nodes at once.
        const branches = Array.from(root.list.children).slice(0, 4);
        for (const li of branches) if (li._budgetNode && !li._budgetNode.loaded) await loadChildren(li._budgetNode);
        status.textContent = 'Premiers niveaux dépliés. Continuez branche par branche.';
    });

    const search = document.getElementById('be-city-code');
    const choices = document.getElementById('be-city-results');
    const scope = document.getElementById('be-city');
    if (search && choices && scope) {
        let abort = null;
        let debounce = null;
        scope.addEventListener('change', () => { search.value = ''; choices.hidden = true; });
        search.addEventListener('input', () => {
            clearTimeout(debounce);
            abort?.abort();
            choices.replaceChildren();
            choices.hidden = true;
            const term = search.value.trim();
            if (term.length < 2) return;
            debounce = setTimeout(async () => {
                abort = new AbortController();
                try {
                    const url = new URL(search.dataset.citySearch, location.origin);
                    url.searchParams.set('q', term);
                    const res = await fetch(url, {signal: abort.signal});
                    if (!res.ok) return;
                    const data = await res.json();
                    for (const city of data.cities || []) {
                        const btn = make('button', 'budget-city-choice', city.name + ' (' + city.code + ', ' + city.department + ')');
                        btn.type = 'button';
                        btn.setAttribute('role', 'option');
                        btn.addEventListener('click', () => {search.value = city.code; choices.hidden = true; search.focus();});
                        choices.append(btn);
                    }
                    choices.hidden = choices.childElementCount === 0;
                } catch (error) {
                    if (error.name !== 'AbortError') choices.hidden = true;
                }
            }, 220);
        });
    }
})();
