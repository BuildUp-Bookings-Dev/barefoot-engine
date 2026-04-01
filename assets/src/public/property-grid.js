import Swiper from 'swiper';
import { Navigation } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';

const PROPERTY_GRID_SELECTOR = '[data-be-property-grid]';
const DEFAULT_COLUMNS = {
  desktop: 3,
  tablet: 2,
  mobile: 1,
};
const RATING_ORDER = ['A+', 'A', 'B+', 'B', 'C+', 'C', 'D', 'F'];

export function bootPropertyGrid() {
  const mounts = document.querySelectorAll(PROPERTY_GRID_SELECTOR);

  mounts.forEach((mountNode) => {
    if (!(mountNode instanceof HTMLElement)) {
      return;
    }

    const configId = mountNode.dataset.bePropertyGridConfig;
    if (!configId) {
      console.error('[barefoot-engine] Property grid config id is missing.');
      return;
    }

    const configNode = getPropertyGridConfigNode(configId);
    if (!(configNode instanceof HTMLScriptElement)) {
      console.error('[barefoot-engine] Property grid config node was not found.');
      return;
    }

    const configKey = buildPropertyGridConfigKey(configId, configNode.textContent || '');
    if (!shouldInitializePropertyGridMount(mountNode, configId, configKey)) {
      return;
    }

    const config = parsePropertyGridConfig(configNode.textContent || '');
    if (!config) {
      return;
    }

    try {
      destroyPropertyGridMount(mountNode);
      initializePropertyGrid(mountNode, config);
      mountNode.dataset.bePropertyGridReady = 'true';
      mountNode.dataset.bePropertyGridReadyConfig = configId;
      mountNode.dataset.bePropertyGridReadyKey = configKey;
    } catch (error) {
      console.error('[barefoot-engine] Failed to initialize property grid.', error);
    }
  });
}

function getPropertyGridConfigNode(configId) {
  const configNode = document.getElementById(configId);
  return configNode instanceof HTMLScriptElement ? configNode : null;
}

function parsePropertyGridConfig(configText) {
  try {
    return JSON.parse(configText || '{}');
  } catch (error) {
    console.error('[barefoot-engine] Property grid config is invalid JSON.', error);
    return null;
  }
}

function buildPropertyGridConfigKey(configId, configText) {
  let hash = 0;
  const value = String(configText || '');

  for (let index = 0; index < value.length; index += 1) {
    hash = ((hash << 5) - hash + value.charCodeAt(index)) >>> 0;
  }

  return `${configId}:${value.length}:${hash.toString(16)}`;
}

function shouldInitializePropertyGridMount(mountNode, configId, configKey) {
  if (mountNode.dataset.bePropertyGridReady !== 'true') {
    return true;
  }

  if (mountNode.dataset.bePropertyGridReadyConfig !== configId) {
    return true;
  }

  if (mountNode.dataset.bePropertyGridReadyKey !== configKey) {
    return true;
  }

  return !(mountNode.querySelector('.barefoot-engine-property-grid__section') instanceof HTMLElement);
}

function destroyPropertyGridMount(mountNode) {
  destroyPropertyGridImageSwipers(mountNode);
  mountNode.bePropertyGridConfig = null;
  mountNode.bePropertyGridState = null;
}

function initializePropertyGrid(mountNode, rawConfig) {
  if (!(mountNode instanceof HTMLElement)) {
    return;
  }

  const config = normalizePropertyGridConfig(rawConfig);
  mountNode.bePropertyGridConfig = config;
  mountNode.bePropertyGridState = {
    currentPage: 1,
    filters: { ...config.initialFilters },
  };

  renderPropertyGrid(mountNode);
}

function normalizePropertyGridConfig(rawConfig) {
  const labels = isPlainObject(rawConfig?.labels) ? rawConfig.labels : {};
  const items = normalizeGridItems(rawConfig?.items);
  const initialFilters = normalizeInitialFilters(rawConfig?.initialFilters);
  const baseItems = items.filter((item) => matchesGridFilters(item, initialFilters));

  return {
    columns: {
      desktop: parsePositiveInt(rawConfig?.columns?.desktop, DEFAULT_COLUMNS.desktop, 1, 6),
      tablet: parsePositiveInt(rawConfig?.columns?.tablet, DEFAULT_COLUMNS.tablet, 1, 4),
      mobile: parsePositiveInt(rawConfig?.columns?.mobile, DEFAULT_COLUMNS.mobile, 1, 2),
    },
    limit: parsePositiveInt(rawConfig?.limit, 9, 1, 100),
    paginated: parseBoolean(rawConfig?.paginated, true),
    showFilter: parseBoolean(rawConfig?.showFilter, true),
    emptyText: sanitizeText(rawConfig?.emptyText, 'No properties matched your filters.'),
    labels: {
      type: sanitizeText(labels?.type, 'Type'),
      bedrooms: sanitizeText(labels?.bedrooms, 'Bedrooms'),
      bathrooms: sanitizeText(labels?.bathrooms, 'Bathrooms'),
      guests: sanitizeText(labels?.guests, 'Guests'),
      rating: sanitizeText(labels?.rating, 'Grade Letter'),
      allTypes: sanitizeText(labels?.allTypes, 'All types'),
      allBedrooms: sanitizeText(labels?.allBedrooms, 'All bedrooms'),
      allBathrooms: sanitizeText(labels?.allBathrooms, 'All bathrooms'),
      allGuests: sanitizeText(labels?.allGuests, 'All guests'),
      allRatings: sanitizeText(labels?.allRatings, 'All grades'),
      reset: sanitizeText(labels?.reset, 'Reset'),
      page: sanitizeText(labels?.page, 'Page'),
      previous: sanitizeText(labels?.previous, 'Previous'),
      next: sanitizeText(labels?.next, 'Next'),
      propertySingular: sanitizeText(labels?.propertySingular, 'property found'),
      propertyPlural: sanitizeText(labels?.propertyPlural, 'properties found'),
      propertyType: sanitizeText(labels?.propertyType, 'Type'),
      ratingMeta: sanitizeText(labels?.ratingMeta, 'Grade'),
      sleeps: sanitizeText(labels?.sleeps, 'Sleeps'),
      bedroomsMeta: sanitizeText(labels?.bedroomsMeta, 'Bedrooms'),
      bathroomsMeta: sanitizeText(labels?.bathroomsMeta, 'Bathrooms'),
      startsAtPrefix: sanitizeText(labels?.startsAtPrefix, 'Starts at'),
      studio: sanitizeText(labels?.studio, 'Studio'),
    },
    items: baseItems,
    initialFilters,
    filterOptions: buildFilterOptions(baseItems),
  };
}

function normalizeInitialFilters(rawFilters) {
  const filters = isPlainObject(rawFilters) ? rawFilters : {};

  return {
    type: sanitizeText(filters?.type, ''),
    bedrooms: sanitizeText(filters?.bedrooms, ''),
    bathrooms: sanitizeText(filters?.bathrooms, ''),
    guests: sanitizeText(filters?.guests, ''),
    rating: sanitizeText(filters?.rating, ''),
  };
}

function normalizeGridItems(items) {
  if (!Array.isArray(items)) {
    return [];
  }

  return items
    .filter((item) => isPlainObject(item))
    .map((item) => ({
      id: sanitizeText(item.id, ''),
      propertyId: sanitizeText(item.propertyId, ''),
      title: sanitizeText(item.title, ''),
      permalink: sanitizeUrl(item.permalink),
      images: normalizeImageUrls(item.images),
      propertyType: sanitizeText(item.propertyType, ''),
      rating: sanitizeText(item.rating, ''),
      guests: normalizeInteger(item.guests),
      bedrooms: normalizeInteger(item.bedrooms),
      bathrooms: normalizeBathroomValue(item.bathrooms),
      price: normalizeNumber(item.price),
      pricePeriod: sanitizeText(item.pricePeriod, ''),
      details: sanitizeText(item.details, ''),
      badge: sanitizeText(item.badge, ''),
    }))
    .filter((item) => item.title !== '');
}

function buildFilterOptions(items) {
  const typeMap = new Map();
  const ratingMap = new Map();
  const bedroomMap = new Map();
  const bathroomMap = new Map();
  const guestMap = new Map();

  items.forEach((item) => {
    if (item.propertyType) {
      typeMap.set(item.propertyType, item.propertyType);
    }

    if (item.rating) {
      ratingMap.set(item.rating, item.rating);
    }

    const bedroomValue = getBedroomFilterValue(item.bedrooms);
    if (bedroomValue) {
      bedroomMap.set(bedroomValue, formatBedroomOptionLabel(bedroomValue));
    }

    const bathroomValue = getBathroomFilterValue(item.bathrooms);
    if (bathroomValue) {
      bathroomMap.set(bathroomValue, bathroomValue);
    }

    const guestValue = getGuestFilterValue(item.guests);
    if (guestValue) {
      guestMap.set(guestValue, guestValue);
    }
  });

  return {
    type: [...typeMap.entries()]
      .sort((left, right) => left[1].localeCompare(right[1]))
      .map(([value, label]) => ({ value, label })),
    rating: [...ratingMap.entries()]
      .sort(([left], [right]) => compareRatings(left, right))
      .map(([value, label]) => ({ value, label })),
    bedrooms: [...bedroomMap.entries()]
      .sort(([left], [right]) => compareBucketedNumbers(left, right, 'studio'))
      .map(([value, label]) => ({ value, label })),
    bathrooms: [...bathroomMap.entries()]
      .sort(([left], [right]) => compareBucketedNumbers(left, right, ''))
      .map(([value, label]) => ({ value, label })),
    guests: [...guestMap.entries()]
      .sort(([left], [right]) => compareBucketedNumbers(left, right, ''))
      .map(([value, label]) => ({ value, label })),
  };
}

function renderPropertyGrid(mountNode) {
  if (!(mountNode instanceof HTMLElement)) {
    return;
  }

  const config = isPlainObject(mountNode.bePropertyGridConfig) ? mountNode.bePropertyGridConfig : null;
  const state = isPlainObject(mountNode.bePropertyGridState) ? mountNode.bePropertyGridState : null;

  if (!config || !state) {
    return;
  }

  destroyPropertyGridImageSwipers(mountNode);

  const filteredItems = config.items.filter((item) => matchesGridFilters(item, state.filters));
  const totalPages = config.paginated
    ? Math.max(1, Math.ceil(filteredItems.length / config.limit))
    : 1;

  state.currentPage = Math.min(Math.max(state.currentPage || 1, 1), totalPages);

  const visibleItems = config.paginated
    ? filteredItems.slice((state.currentPage - 1) * config.limit, state.currentPage * config.limit)
    : filteredItems.slice(0, config.limit);

  mountNode.innerHTML = buildPropertyGridMarkup(config, state, visibleItems, filteredItems.length, totalPages);
  initializePropertyGridEvents(mountNode, config);
  initializePropertyGridImageSliders(mountNode);
}

function buildPropertyGridMarkup(config, state, visibleItems, filteredCount, totalPages) {
  const sectionStyle = [
    `--be-property-grid-columns-desktop:${String(config.columns.desktop)}`,
    `--be-property-grid-columns-tablet:${String(config.columns.tablet)}`,
    `--be-property-grid-columns-mobile:${String(config.columns.mobile)}`,
  ].join(';');

  const filterMarkup = config.showFilter
    ? renderFilterMarkup(config, state.filters)
    : '';
  const summaryMarkup = renderResultsSummary(config, filteredCount);
  const contentMarkup = visibleItems.length > 0
    ? `
      <div class="barefoot-engine-property-grid__grid">
        ${visibleItems
    .map((item, index) => `
            <div class="barefoot-engine-property-grid__grid-item">
              ${renderPropertyGridCard(item, config, index)}
            </div>
          `)
    .join('')}
      </div>
      ${config.paginated ? renderPaginationMarkup(config, state.currentPage, totalPages) : ''}
    `
    : `<p class="barefoot-engine-featured-properties__empty">${escapeHtml(config.emptyText)}</p>`;

  return `
    <section class="barefoot-engine-property-grid__section" style="${escapeHtml(sectionStyle)}">
      ${filterMarkup}
      ${summaryMarkup}
      ${contentMarkup}
    </section>
  `;
}

function renderResultsSummary(config, filteredCount) {
  const label = filteredCount === 1
    ? config.labels.propertySingular
    : config.labels.propertyPlural;

  return `
    <div class="barefoot-engine-property-grid__summary" aria-live="polite">
      <p class="barefoot-engine-property-grid__count">
        <strong class="barefoot-engine-property-grid__count-number">${String(filteredCount)}</strong>
        <span class="barefoot-engine-property-grid__count-label">${escapeHtml(label)}</span>
      </p>
    </div>
  `;
}

function renderFilterMarkup(config, filters) {
  return `
    <form class="barefoot-engine-property-grid__filters" data-be-property-grid-filters>
      ${renderFilterField('type', config.labels.type, config.labels.allTypes, config.filterOptions.type, filters.type)}
      ${renderFilterField('bedrooms', config.labels.bedrooms, config.labels.allBedrooms, config.filterOptions.bedrooms, filters.bedrooms)}
      ${renderFilterField('bathrooms', config.labels.bathrooms, config.labels.allBathrooms, config.filterOptions.bathrooms, filters.bathrooms)}
      ${renderFilterField('guests', config.labels.guests, config.labels.allGuests, config.filterOptions.guests, filters.guests)}
      ${renderFilterField('rating', config.labels.rating, config.labels.allRatings, config.filterOptions.rating, filters.rating)}
      <div class="barefoot-engine-property-grid__filter-action">
        <button type="button" class="barefoot-engine-property-grid__reset" data-be-property-grid-reset>${escapeHtml(config.labels.reset)}</button>
      </div>
    </form>
  `;
}

function renderFilterField(key, label, placeholder, options, value) {
  const optionMarkup = Array.isArray(options)
    ? options
      .map((option) => `
          <option value="${escapeHtml(option.value)}"${option.value === value ? ' selected' : ''}>
            ${escapeHtml(option.label)}
          </option>
        `)
      .join('')
    : '';

  return `
    <label class="barefoot-engine-property-grid__filter-field">
      <span class="barefoot-engine-property-grid__filter-label">${escapeHtml(label)}</span>
      <span class="barefoot-engine-property-grid__filter-select-wrap">
        <select class="barefoot-engine-property-grid__filter-select" data-be-property-grid-filter="${escapeHtml(key)}">
          <option value="">${escapeHtml(placeholder)}</option>
          ${optionMarkup}
        </select>
      </span>
    </label>
  `;
}

function renderPaginationMarkup(config, currentPage, totalPages) {
  if (totalPages <= 1) {
    return '';
  }

  const paginationSequence = buildPaginationSequence(currentPage, totalPages);
  const pageButtons = paginationSequence.map((entry) => {
    if (entry === 'ellipsis') {
      return '<span class="barefoot-engine-property-grid__page-ellipsis" aria-hidden="true">…</span>';
    }

    const page = entry;
    const isActive = page === currentPage;

    return `
      <button
        type="button"
        class="barefoot-engine-property-grid__page-btn${isActive ? ' is-active' : ''}"
        data-be-property-grid-page="${String(page)}"
        aria-label="${escapeHtml(`${config.labels.page} ${page}`)}"
        ${isActive ? 'aria-current="page"' : ''}
      >
        ${String(page)}
      </button>
    `;
  }).join('');

  return `
    <nav class="barefoot-engine-property-grid__pagination" aria-label="${escapeHtml(config.labels.page)}">
      <button
        type="button"
        class="barefoot-engine-property-grid__page-btn is-nav${currentPage <= 1 ? ' is-disabled' : ''}"
        data-be-property-grid-page="${String(Math.max(1, currentPage - 1))}"
        aria-label="${escapeHtml(config.labels.previous)}"
        ${currentPage <= 1 ? 'disabled aria-disabled="true"' : ''}
      >
        ${escapeHtml(config.labels.previous)}
      </button>
      ${pageButtons}
      <button
        type="button"
        class="barefoot-engine-property-grid__page-btn is-nav${currentPage >= totalPages ? ' is-disabled' : ''}"
        data-be-property-grid-page="${String(Math.min(totalPages, currentPage + 1))}"
        aria-label="${escapeHtml(config.labels.next)}"
        ${currentPage >= totalPages ? 'disabled aria-disabled="true"' : ''}
      >
        ${escapeHtml(config.labels.next)}
      </button>
    </nav>
  `;
}

function buildPaginationSequence(currentPage, totalPages) {
  if (totalPages <= 7) {
    return Array.from({ length: totalPages }, (_, index) => index + 1);
  }

  const sequence = [1];
  let windowStart = Math.max(2, currentPage - 1);
  let windowEnd = Math.min(totalPages - 1, currentPage + 1);

  if (currentPage <= 3) {
    windowStart = 2;
    windowEnd = 4;
  } else if (currentPage >= totalPages - 2) {
    windowStart = totalPages - 3;
    windowEnd = totalPages - 1;
  }

  if (windowStart > 2) {
    sequence.push('ellipsis');
  }

  for (let page = windowStart; page <= windowEnd; page += 1) {
    sequence.push(page);
  }

  if (windowEnd < totalPages - 1) {
    sequence.push('ellipsis');
  }

  sequence.push(totalPages);

  return sequence;
}

function renderPropertyGridCard(item, config, index) {
  const startsAtText = formatStartsAtText(item.price, item.pricePeriod, config.labels.startsAtPrefix);
  const metaItems = [];

  if (item.propertyType) {
    metaItems.push(`
      <span class="barefoot-engine-featured-properties__meta-item">
        <i class="fa-solid fa-house" aria-hidden="true"></i>
        ${escapeHtml(item.propertyType)}
      </span>
    `);
  }

  if (item.rating) {
    metaItems.push(`
      <span class="barefoot-engine-featured-properties__meta-item">
        <i class="fa-solid fa-tag" aria-hidden="true"></i>
        ${escapeHtml(item.rating)}
      </span>
    `);
  }

  if (Number.isFinite(item.guests)) {
    metaItems.push(`
      <span class="barefoot-engine-featured-properties__meta-item">
        <i class="fa-solid fa-users" aria-hidden="true"></i>
        ${escapeHtml(formatStatValue(item.guests))}
      </span>
    `);
  }
  
  metaItems.push(`
    <span class="barefoot-engine-featured-properties__meta-item">
      <i class="fa-solid fa-bed" aria-hidden="true"></i>
      ${escapeHtml(formatStatValue(formatBedroomDisplayValue(item.bedrooms, config.labels.studio)))}
    </span>
  `);

  metaItems.push(`
    <span class="barefoot-engine-featured-properties__meta-item">
      <i class="fa-solid fa-bath" aria-hidden="true"></i>
      ${escapeHtml(formatStatValue(item.bathrooms))}
    </span>
  `);

  const imageMarkup = item.images.length > 0
    ? renderPropertyGridImageSlider(item, index)
    : '<div class="barefoot-engine-featured-properties__image-placeholder" aria-hidden="true"></div>';

  const safeTitle = escapeHtml(item.title);
  const safePermalink = item.permalink ? escapeHtml(item.permalink) : '';
  const titleMarkup = safePermalink
    ? `<a href="${safePermalink}" class="barefoot-engine-featured-properties__card-title-link">${safeTitle}</a>`
    : `<span class="barefoot-engine-featured-properties__card-title-link">${safeTitle}</span>`;

  return `
    <article class="barefoot-engine-featured-properties__card barefoot-engine-property-grid__card">
      <div class="barefoot-engine-featured-properties__media">
        ${imageMarkup}
      </div>
      <div class="barefoot-engine-featured-properties__body">
        <h3 class="barefoot-engine-featured-properties__card-title">${titleMarkup}</h3>
        ${startsAtText ? `<p class="barefoot-engine-featured-properties__starts-at">${escapeHtml(startsAtText)}</p>` : ''}
        ${metaItems.length > 0 ? `<div class="barefoot-engine-featured-properties__meta-row">${metaItems.join('')}</div>` : ''}
      </div>
    </article>
  `;
}

function renderPropertyGridImageSlider(item, index) {
  const slides = item.images
    .map((imageUrl) => `
      <div class="swiper-slide barefoot-engine-featured-properties__image-slide">
        <img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(item.title)}" loading="lazy" />
      </div>
    `)
    .join('');

  const hasNavigation = item.images.length > 1;

  return `
    <div class="swiper barefoot-engine-featured-properties__image-swiper" data-be-property-grid-image-swiper data-image-count="${String(item.images.length)}">
      <div class="swiper-wrapper">
        ${slides}
      </div>
      <button
        type="button"
        class="barefoot-engine-featured-properties__image-nav barefoot-engine-featured-properties__image-prev${hasNavigation ? '' : ' is-hidden'}"
        aria-label="Previous image ${index + 1}"
        data-be-property-grid-image-prev
      >
        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
      </button>
      <button
        type="button"
        class="barefoot-engine-featured-properties__image-nav barefoot-engine-featured-properties__image-next${hasNavigation ? '' : ' is-hidden'}"
        aria-label="Next image ${index + 1}"
        data-be-property-grid-image-next
      >
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
      </button>
    </div>
  `;
}

function initializePropertyGridEvents(mountNode) {
  const state = isPlainObject(mountNode.bePropertyGridState) ? mountNode.bePropertyGridState : null;
  if (!state) {
    return;
  }

  mountNode.querySelectorAll('[data-be-property-grid-filter]').forEach((fieldNode) => {
    if (!(fieldNode instanceof HTMLSelectElement)) {
      return;
    }

    fieldNode.addEventListener('change', (event) => {
      const target = event.currentTarget;
      if (!(target instanceof HTMLSelectElement)) {
        return;
      }

      const filterKey = sanitizeText(target.dataset.bePropertyGridFilter, '');
      if (!filterKey) {
        return;
      }

      state.filters[filterKey] = sanitizeText(target.value, '');
      state.currentPage = 1;
      renderPropertyGrid(mountNode);
    });
  });

  const resetButton = mountNode.querySelector('[data-be-property-grid-reset]');
  if (resetButton instanceof HTMLButtonElement) {
    resetButton.addEventListener('click', () => {
      state.filters = {
        ...config.initialFilters,
      };
      state.currentPage = 1;
      renderPropertyGrid(mountNode);
    });
  }

  mountNode.querySelectorAll('[data-be-property-grid-page]').forEach((buttonNode) => {
    if (!(buttonNode instanceof HTMLButtonElement)) {
      return;
    }

    buttonNode.addEventListener('click', (event) => {
      const target = event.currentTarget;
      if (!(target instanceof HTMLButtonElement)) {
        return;
      }

      const nextPage = parsePositiveInt(target.dataset.bePropertyGridPage, 1, 1, 999);
      if (nextPage === state.currentPage) {
        return;
      }

      state.currentPage = nextPage;
      renderPropertyGrid(mountNode);
    });
  });
}

function initializePropertyGridImageSliders(mountNode) {
  const imageSwipers = [];

  mountNode.querySelectorAll('[data-be-property-grid-image-swiper]').forEach((sliderNode) => {
    if (!(sliderNode instanceof HTMLElement)) {
      return;
    }

    const imageCount = Number(sliderNode.dataset.imageCount || 0);
    if (!Number.isFinite(imageCount) || imageCount <= 1) {
      return;
    }

    const card = sliderNode.closest('.barefoot-engine-featured-properties__card');
    if (!(card instanceof HTMLElement)) {
      return;
    }

    const prevButton = card.querySelector('[data-be-property-grid-image-prev]');
    const nextButton = card.querySelector('[data-be-property-grid-image-next]');
    if (!(prevButton instanceof HTMLElement) || !(nextButton instanceof HTMLElement)) {
      return;
    }

    const imageSwiper = new Swiper(sliderNode, {
      modules: [Navigation],
      loop: true,
      slidesPerView: 1,
      spaceBetween: 0,
      watchOverflow: true,
      nested: true,
      navigation: {
        enabled: true,
        prevEl: prevButton,
        nextEl: nextButton,
      },
    });

    imageSwipers.push(imageSwiper);
  });

  mountNode.bePropertyGridImageSwipers = imageSwipers;
}

function destroyPropertyGridImageSwipers(mountNode) {
  if (!Array.isArray(mountNode.bePropertyGridImageSwipers)) {
    mountNode.bePropertyGridImageSwipers = [];
    return;
  }

  mountNode.bePropertyGridImageSwipers.forEach((swiperInstance) => {
    if (swiperInstance && typeof swiperInstance.destroy === 'function') {
      swiperInstance.destroy(true, true);
    }
  });

  mountNode.bePropertyGridImageSwipers = [];
}

function matchesGridFilters(item, filters) {
  if (filters.type && sanitizeText(item.propertyType, '') !== filters.type) {
    return false;
  }

  if (filters.rating && sanitizeText(item.rating, '') !== filters.rating) {
    return false;
  }

  if (filters.bedrooms && getBedroomFilterValue(item.bedrooms) !== filters.bedrooms) {
    return false;
  }

  if (filters.bathrooms && getBathroomFilterValue(item.bathrooms) !== filters.bathrooms) {
    return false;
  }

  if (filters.guests) {
    const minimumGuests = getMinimumGuestCount(filters.guests);
    if (!Number.isFinite(minimumGuests) || !Number.isFinite(item.guests) || Number(item.guests) < minimumGuests) {
      return false;
    }
  }

  return true;
}

function getBedroomFilterValue(value) {
  if (!Number.isFinite(value)) {
    return '';
  }

  if (Number(value) <= 0) {
    return 'studio';
  }

  if (Number(value) >= 8) {
    return '8+';
  }

  return String(Math.round(Number(value)));
}

function getBathroomFilterValue(value) {
  if (value === null || value === undefined || value === '') {
    return '';
  }

  const numeric = Number(value);
  if (Number.isFinite(numeric)) {
    if (numeric >= 6) {
      return '6+';
    }

    return Number.isInteger(numeric) ? String(numeric) : String(numeric).replace(/\.0+$/, '');
  }

  return sanitizeText(value, '');
}

function getGuestFilterValue(value) {
  if (!Number.isFinite(value)) {
    return '';
  }

  if (Number(value) >= 8) {
    return '8+';
  }

  return String(Math.round(Number(value)));
}

function getMinimumGuestCount(value) {
  const normalized = sanitizeText(value, '');
  if (!normalized) {
    return 0;
  }

  if (normalized.endsWith('+')) {
    return Number.parseInt(normalized, 10);
  }

  return Number.parseInt(normalized, 10);
}

function formatBedroomOptionLabel(value) {
  if (value === 'studio') {
    return 'Studio';
  }

  return value;
}

function formatBedroomDisplayValue(value, studioLabel) {
  if (!Number.isFinite(value)) {
    return '—';
  }

  if (Number(value) <= 0) {
    return sanitizeText(studioLabel, 'Studio');
  }

  return String(Math.round(Number(value)));
}

function compareRatings(left, right) {
  const leftIndex = RATING_ORDER.indexOf(left);
  const rightIndex = RATING_ORDER.indexOf(right);

  if (leftIndex !== -1 || rightIndex !== -1) {
    if (leftIndex === -1) {
      return 1;
    }

    if (rightIndex === -1) {
      return -1;
    }

    return leftIndex - rightIndex;
  }

  return left.localeCompare(right);
}

function compareBucketedNumbers(left, right, specialFirst) {
  if (specialFirst && left === specialFirst) {
    return -1;
  }

  if (specialFirst && right === specialFirst) {
    return 1;
  }

  const leftNumber = Number.parseFloat(String(left).replace('+', ''));
  const rightNumber = Number.parseFloat(String(right).replace('+', ''));

  if (Number.isFinite(leftNumber) && Number.isFinite(rightNumber)) {
    return leftNumber - rightNumber;
  }

  return String(left).localeCompare(String(right));
}

function formatStartsAtText(value, pricePeriod, prefix) {
  if (!Number.isFinite(value) || Number(value) <= 0) {
    return '';
  }

  const suffix = sanitizeText(pricePeriod, '');

  return [sanitizeText(prefix, 'Starts at'), formatMoney(Number(value), '$'), suffix].filter(Boolean).join(' ');
}

function formatMoney(value, currency) {
  const amount = Number(value);
  if (!Number.isFinite(amount)) {
    return '';
  }

  const symbol = sanitizeText(currency, '$');
  const formattedAmount = Number.isInteger(amount)
    ? String(amount)
    : amount.toFixed(2);

  return `${symbol}${formattedAmount}`;
}

function formatStatValue(value) {
  const normalized = String(value ?? '').trim();
  return normalized !== '' ? normalized : '—';
}

function normalizeInteger(value) {
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) {
    return null;
  }

  return Math.round(numeric);
}

function normalizeBathroomValue(value) {
  if (value === null || value === undefined || value === '') {
    return null;
  }

  const numeric = Number(value);
  if (Number.isFinite(numeric)) {
    return Number.isInteger(numeric) ? Math.round(numeric) : numeric;
  }

  return sanitizeText(value, '');
}

function normalizeNumber(value) {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }

  const normalized = Number(value);
  if (!Number.isFinite(normalized)) {
    return null;
  }

  return normalized;
}

function parsePositiveInt(value, fallback, min, max) {
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) {
    return fallback;
  }

  const normalized = Math.round(numeric);
  if (normalized < min) {
    return min;
  }

  if (normalized > max) {
    return max;
  }

  return normalized;
}

function parseBoolean(value, fallback) {
  if (typeof value === 'boolean') {
    return value;
  }

  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase();
    if (['1', 'true', 'yes', 'on'].includes(normalized)) {
      return true;
    }

    if (['0', 'false', 'no', 'off'].includes(normalized)) {
      return false;
    }
  }

  if (typeof value === 'number') {
    if (value === 1) {
      return true;
    }

    if (value === 0) {
      return false;
    }
  }

  return fallback;
}

function sanitizeText(value, fallback = '') {
  const normalized = String(value ?? '').trim();
  return normalized || fallback;
}

function sanitizeUrl(value) {
  const candidate = String(value ?? '').trim();
  if (!candidate) {
    return '';
  }

  try {
    const url = new URL(candidate, window.location.origin);
    return url.toString();
  } catch (error) {
    return '';
  }
}

function normalizeImageUrls(images) {
  if (!Array.isArray(images)) {
    return [];
  }

  const seen = new Set();
  const normalized = [];

  images.forEach((image) => {
    const url = sanitizeUrl(image);
    if (!url || seen.has(url)) {
      return;
    }

    seen.add(url);
    normalized.push(url);
  });

  return normalized;
}

function isPlainObject(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
