/* Shared enhanced municipality chooser. Server-side GET/POST remains fully functional. */
(() => {
  'use strict';
  document.querySelectorAll('[data-geo-chooser]').forEach(form => {
    const input = form.querySelector('input[type="search"]');
    const list = form.querySelector('[role="listbox"]');
    const feedback = form.querySelector('[data-geo-feedback]');
    if (!input || !list) return;
    const chooser = form.dataset.geoMode === 'choose';
    const endpoint = form.dataset.suggestions;
    const directory = form.dataset.directory;
    let timer, controller, active = -1;
    const close = () => { list.replaceChildren(); list.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; };
    const items = () => Array.from(list.querySelectorAll('[role="option"]'));
    const move = direction => {
      const options = items();
      if (!options.length) return;
      active = (active + direction + options.length) % options.length;
      options.forEach((option, i) => option.setAttribute('aria-selected', String(i === active)));
      input.setAttribute('aria-activedescendant', options[active].id);
    };
    input.addEventListener('input', () => {
      clearTimeout(timer); controller?.abort(); close();
      const typeInput = form.querySelector('input[name="code_type"]');
      if (typeInput) typeInput.value = '';
      const term = input.value.trim();
      input.removeAttribute('aria-activedescendant');
      if (term.length < 2) { feedback.textContent = 'Tapez au moins deux caractères.'; return; }
      feedback.textContent = 'Recherche en cours…';
      timer = setTimeout(async () => {
        controller = new AbortController();
        try {
          const url = new URL(endpoint, location.origin);
          url.searchParams.set('q', term);
          const response = await fetch(url, {signal: controller.signal, headers: {Accept: 'application/json'}});
          if (!response.ok) throw new Error('HTTP ' + response.status);
          const result = await response.json();
          if (term !== input.value.trim()) return;
          for (const [index, city] of (result.communes || []).entries()) {
            const option = document.createElement(chooser ? 'button' : 'a');
            option.className = 'geo-chooser-option';
            option.id = input.id + '-option-' + index;
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            const label = document.createElement('strong');
            // Postal input: show the exact matching postal codes first, never the INSEE code as the match.
            // The API returns only postal codes respecting the typed prefix/exact value.
            const matchingPostals = (city.postalCodes || '').split(',').map(x => x.trim()).filter(Boolean);
            const postalMatch = result.postalQuery ? matchingPostals.filter(code =>
              /^\d{5}$/.test(code) && (term.length === 5 ? code === term : code.startsWith(term))
            ) : [];
            if (result.postalQuery && !postalMatch.length) continue;
            label.textContent = result.postalQuery
              ? city.name + ' · CP ' + postalMatch.join(', ')
              : city.name;
            const description = document.createElement('small');
            description.textContent = [
              'Code INSEE ' + city.code,
              city.department,
              city.region,
              ...(!result.postalQuery && city.postalCodes ? ['Codes postaux ' + city.postalCodes] : []),
            ].filter(Boolean).join(' · ');
            option.append(label, description);
            if (chooser) {
              option.type = 'button';
              option.addEventListener('click', () => {
                input.value = city.code;
                const typeInput = form.querySelector('input[name="code_type"]');
                if (typeInput) typeInput.value = 'insee';
                close();
                form.requestSubmit();
              });
            } else {
              option.href = '/communes/' + encodeURIComponent(city.code);
            }
            list.appendChild(option);
          }
          if ((result.communes || []).length && directory) {
            const all = document.createElement('a');
            all.className = 'geo-chooser-option';
            all.id = input.id + '-all-results';
            all.setAttribute('role', 'option');
            all.setAttribute('aria-selected', 'false');
            const allUrl = new URL(directory, location.origin);
            allUrl.searchParams.set('q', term);
            all.href = allUrl.pathname + allUrl.search;
            all.textContent = result.postalQuery
              ? (term.length === 5
                ? 'Voir toutes les communes du code postal ' + term
                : 'Voir toutes les communes des codes postaux ' + term + 'xx…')
              : 'Voir tous les résultats dans l’annuaire';
            list.appendChild(all);
          }
          list.hidden = list.childElementCount === 0;
          input.setAttribute('aria-expanded', String(!list.hidden));
          feedback.textContent = (result.communes || []).length
            ? (result.communes || []).length + ' commune(s) proposée(s) — choisissez une commune ou ouvrez la liste complète.'
            : (/^\d{5}$/.test(term) && !result.postalAvailable
              ? 'Référentiel postal absent : utilisez php bin/console app:geography:install --env=demo.'
              : 'Aucune commune trouvée. Vérifiez le référentiel et le code saisi.');
        } catch (error) {
          if (error.name !== 'AbortError') feedback.textContent = 'Recherche indisponible. Ouvrez l’annuaire ou saisissez le code INSEE.';
        }
      }, 190);
    });
    input.addEventListener('keydown', event => {
      if (event.key === 'Escape') { close(); return; }
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        if (!list.hidden) { event.preventDefault(); move(event.key === 'ArrowDown' ? 1 : -1); }
      }
      if (event.key === 'Enter' && active >= 0 && !list.hidden) {
        event.preventDefault(); items()[active]?.click();
      }
    });
    document.addEventListener('click', event => { if (!form.contains(event.target)) close(); });
  });
})();
