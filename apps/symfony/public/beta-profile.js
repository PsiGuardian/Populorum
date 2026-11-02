(() => {
  'use strict';
  const field = document.querySelector('[data-beta-commune]');
  const list = document.querySelector('#beta-city-results');
  const feedback = document.querySelector('[data-beta-city-feedback]');
  if (!field || !list) return;
  let timer, controller;
  const clear = () => { list.replaceChildren(); list.hidden = true; field.setAttribute('aria-expanded', 'false'); };
  field.addEventListener('input', () => {
    clearTimeout(timer); controller?.abort(); clear();
    const term = field.value.trim();
    if (term.length < 2) { if (feedback) feedback.textContent = 'Saisissez au moins deux caractères.'; return; }
    timer = setTimeout(async () => {
      controller = new AbortController();
      try {
        const url = new URL(field.dataset.suggestions, location.origin);
        url.searchParams.set('q', term);
        const response = await fetch(url, {credentials: 'omit', signal: controller.signal, headers: {Accept: 'application/json'}});
        if (!response.ok) throw new Error('search failed');
        const result = await response.json();
        if (field.value.trim() !== term) return;
        (result.communes || []).slice(0, 20).forEach(city => {
          const option = document.createElement('button');
          option.type = 'button';
          option.className = 'beta-suggestion';
          option.setAttribute('role', 'option');
          option.textContent = city.name + ' · ' + (city.postalCodes ? 'CP ' + city.postalCodes + ' · ' : '') + 'INSEE ' + city.code;
          option.addEventListener('click', () => {
            field.value = city.code;
            if (feedback) feedback.textContent = 'Commune sélectionnée : ' + city.name;
            clear();
          });
          list.appendChild(option);
        });
        list.hidden = list.childElementCount === 0;
        field.setAttribute('aria-expanded', String(!list.hidden));
        if (feedback && !list.childElementCount) feedback.textContent = 'Aucune commune trouvée. Vérifiez le code INSEE.';
      } catch (error) {
        if (error.name !== 'AbortError' && feedback) feedback.textContent = 'Suggestions indisponibles : vous pouvez saisir le code INSEE.';
      }
    }, 220);
  });
  document.addEventListener('click', event => { if (!list.contains(event.target) && event.target !== field) clear(); });
  field.addEventListener('keydown', event => {
    if (event.key === 'Escape') clear();
  });
})();
