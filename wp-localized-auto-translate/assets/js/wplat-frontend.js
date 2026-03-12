(function () {
  const cfg = window.WPLAT_CONFIG;
  if (!cfg || !Array.isArray(cfg.locales) || cfg.locales.length === 0) {
    return;
  }

  let originalTexts = null;
  let textNodes = null;

  function normalizeLocale(locale) {
    return (locale || '').toLowerCase();
  }

  function pickLocale(browserLocale, supportedLocales) {
    const exact = supportedLocales.find((loc) => normalizeLocale(loc) === normalizeLocale(browserLocale));
    if (exact) return exact;

    const base = normalizeLocale(browserLocale).split('-')[0];
    return supportedLocales.find((loc) => normalizeLocale(loc).split('-')[0] === base) || null;
  }

  function collectTextNodes(root, minChars) {
    const nodes = [];
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
      acceptNode(node) {
        if (!node.nodeValue) return NodeFilter.FILTER_REJECT;
        const value = node.nodeValue.trim();
        if (value.length < minChars) return NodeFilter.FILTER_REJECT;
        if (!/[A-Za-z]/.test(value)) return NodeFilter.FILTER_REJECT;
        const parent = node.parentElement;
        if (!parent) return NodeFilter.FILTER_REJECT;
        const tag = parent.tagName.toLowerCase();
        if (['script', 'style', 'noscript', 'textarea', 'code', 'pre'].includes(tag)) return NodeFilter.FILTER_REJECT;
        return NodeFilter.FILTER_ACCEPT;
      },
    });

    let current;
    while ((current = walker.nextNode())) {
      nodes.push(current);
    }

    return nodes;
  }

  async function requestTranslations(texts, targetLocale) {
    const response = await fetch(cfg.restUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce,
      },
      body: JSON.stringify({ texts, targetLocale }),
    });

    if (!response.ok) {
      return null;
    }

    const data = await response.json();
    if (!data || !Array.isArray(data.translations) || data.translations.length !== texts.length) {
      return null;
    }

    return data.translations;
  }

  function applyTranslations(nodes, translations, locale) {
    for (let i = 0; i < nodes.length; i += 1) {
      nodes[i].nodeValue = translations[i];
    }
    document.documentElement.setAttribute('lang', locale);
    sessionStorage.setItem('wplat_translated_locale', locale);
  }

  function restoreOriginal(nodes, originals) {
    for (let i = 0; i < nodes.length; i += 1) {
      nodes[i].nodeValue = originals[i];
    }
    document.documentElement.setAttribute('lang', cfg.sourceLocale || 'en');
    sessionStorage.removeItem('wplat_translated_locale');
  }

  function getLocaleDisplay(locale) {
    try {
      if (window.Intl && Intl.DisplayNames) {
        const display = new Intl.DisplayNames([locale], { type: 'language' });
        const languageName = display.of(locale.split('-')[0]);
        return `${locale} (${languageName})`;
      }
    } catch (e) {
      // noop
    }
    return locale;
  }

  function createSwitcher(onChange) {
    if (!cfg.showSwitcher) {
      return;
    }

    const wrap = document.createElement('div');
    wrap.setAttribute('id', 'wplat-switcher');
    wrap.style.position = 'fixed';
    wrap.style.right = '16px';
    wrap.style.bottom = '16px';
    wrap.style.zIndex = '999999';
    wrap.style.background = '#fff';
    wrap.style.border = '1px solid #ddd';
    wrap.style.borderRadius = '8px';
    wrap.style.padding = '8px 10px';
    wrap.style.boxShadow = '0 3px 12px rgba(0,0,0,0.15)';

    const select = document.createElement('select');
    select.setAttribute('aria-label', 'Language switcher');
    select.style.fontSize = '14px';

    const autoOption = document.createElement('option');
    autoOption.value = '__auto__';
    autoOption.textContent = 'Auto';
    select.appendChild(autoOption);

    const englishOption = document.createElement('option');
    englishOption.value = '__english__';
    englishOption.textContent = 'English (Original)';
    select.appendChild(englishOption);

    cfg.locales.forEach((locale) => {
      const option = document.createElement('option');
      option.value = locale;
      option.textContent = getLocaleDisplay(locale);
      select.appendChild(option);
    });

    select.addEventListener('change', () => onChange(select.value));
    wrap.appendChild(select);
    document.body.appendChild(wrap);
  }

  async function translateToLocale(targetLocale) {
    if (!textNodes || textNodes.length === 0 || !originalTexts) {
      return;
    }

    if (targetLocale === '__english__') {
      restoreOriginal(textNodes, originalTexts);
      return;
    }

    if (targetLocale === '__auto__') {
      const browserLocale = navigator.language || navigator.userLanguage || 'en-US';
      targetLocale = pickLocale(browserLocale, cfg.locales);
    }

    if (!targetLocale || normalizeLocale(targetLocale).startsWith('en')) {
      restoreOriginal(textNodes, originalTexts);
      return;
    }

    const translations = await requestTranslations(originalTexts, targetLocale);
    if (!translations) {
      return;
    }
    applyTranslations(textNodes, translations, targetLocale);
  }

  async function init() {
    textNodes = collectTextNodes(document.body, Number(cfg.minChars || 20));
    if (!textNodes.length) {
      return;
    }

    originalTexts = textNodes.map((node) => node.nodeValue.trim());

    createSwitcher((selected) => {
      translateToLocale(selected);
    });

    const browserLocale = navigator.language || navigator.userLanguage || 'en-US';
    const targetLocale = pickLocale(browserLocale, cfg.locales);
    await translateToLocale(targetLocale);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
