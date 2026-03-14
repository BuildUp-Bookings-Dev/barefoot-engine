import Swiper from 'swiper';
import { Autoplay, Navigation } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';

const FEATURED_PROPERTIES_SELECTOR = '[data-be-featured-properties]';
const FEATURED_META_KEYS = ['starts_at', 'property_type', 'view', 'sleeps', 'bedrooms', 'bathrooms'];

export function bootFeaturedProperties() {
  const mounts = document.querySelectorAll(FEATURED_PROPERTIES_SELECTOR);

  mounts.forEach((mountNode) => {
    if (!(mountNode instanceof HTMLElement)) {
      return;
    }

    const configId = mountNode.dataset.beFeaturedPropertiesConfig;
    if (!configId) {
      console.error('[barefoot-engine] Featured properties config id is missing.');
      return;
    }

    const configNode = getFeaturedPropertiesConfigNode(configId);
    if (!(configNode instanceof HTMLScriptElement)) {
      console.error('[barefoot-engine] Featured properties config node was not found.');
      return;
    }

    const configKey = buildFeaturedPropertiesConfigKey(configId, configNode.textContent || '');
    if (!shouldInitializeFeaturedPropertiesMount(mountNode, configId, configKey)) {
      return;
    }

    const config = parseFeaturedPropertiesConfig(configNode.textContent || '');
    if (!config) {
      return;
    }

    try {
      destroyFeaturedPropertiesMount(mountNode);
      initializeFeaturedProperties(mountNode, config);
      mountNode.dataset.beFeaturedPropertiesReady = 'true';
      mountNode.dataset.beFeaturedPropertiesReadyConfig = configId;
      mountNode.dataset.beFeaturedPropertiesReadyKey = configKey;
    } catch (error) {
      console.error('[barefoot-engine] Failed to initialize featured properties.', error);
    }
  });
}

function getFeaturedPropertiesConfigNode(configId) {
  const configNode = document.getElementById(configId);
  return configNode instanceof HTMLScriptElement ? configNode : null;
}

function parseFeaturedPropertiesConfig(configText) {
  try {
    return JSON.parse(configText || '{}');
  } catch (error) {
    console.error('[barefoot-engine] Featured properties config is invalid JSON.', error);
    return null;
  }
}

function buildFeaturedPropertiesConfigKey(configId, configText) {
  let hash = 0;
  const value = String(configText || '');

  for (let index = 0; index < value.length; index += 1) {
    hash = ((hash << 5) - hash + value.charCodeAt(index)) >>> 0;
  }

  return `${configId}:${value.length}:${hash.toString(16)}`;
}

function shouldInitializeFeaturedPropertiesMount(mountNode, configId, configKey) {
  if (mountNode.dataset.beFeaturedPropertiesReady !== 'true') {
    return true;
  }

  if (mountNode.dataset.beFeaturedPropertiesReadyConfig !== configId) {
    return true;
  }

  if (mountNode.dataset.beFeaturedPropertiesReadyKey !== configKey) {
    return true;
  }

  return !(mountNode.querySelector('.barefoot-engine-featured-properties__section') instanceof HTMLElement);
}

function destroyFeaturedPropertiesMount(mountNode) {
  if (mountNode.beFeaturedOuterSwiper && typeof mountNode.beFeaturedOuterSwiper.destroy === 'function') {
    mountNode.beFeaturedOuterSwiper.destroy(true, true);
  }

  if (Array.isArray(mountNode.beFeaturedImageSwipers)) {
    mountNode.beFeaturedImageSwipers.forEach((swiperInstance) => {
      if (swiperInstance && typeof swiperInstance.destroy === 'function') {
        swiperInstance.destroy(true, true);
      }
    });
  }

  mountNode.beFeaturedOuterSwiper = null;
  mountNode.beFeaturedImageSwipers = [];
}

function initializeFeaturedProperties(mountNode, rawConfig) {
  if (!(mountNode instanceof HTMLElement)) {
    return;
  }

  const config = normalizeFeaturedPropertiesConfig(rawConfig);
  mountNode.beFeaturedConfig = config;
  renderFeaturedPropertiesMarkup(mountNode, config);

  if (config.items.length === 0) {
    return;
  }

  initializeOuterSlider(mountNode, config);
  initializeNestedImageSliders(mountNode);
}

function normalizeFeaturedPropertiesConfig(rawConfig) {
  const labels = isPlainObject(rawConfig?.labels) ? rawConfig.labels : {};
  const slider = normalizeSliderConfig(rawConfig?.slider);

  return {
    title: sanitizeText(rawConfig?.title, 'Featured Properties'),
    currency: sanitizeText(rawConfig?.currency, '$'),
    emptyText: sanitizeText(rawConfig?.emptyText, 'No featured properties available yet.'),
    metaDisplay: normalizeMetaDisplay(rawConfig?.metaDisplay),
    headingPosition: normalizeHeadingPosition(rawConfig?.headingPosition),
    sliderControlPosition: normalizeSliderControlPosition(rawConfig?.sliderControlPosition),
    slider,
    labels: {
      propertyType: sanitizeText(labels?.propertyType, 'Type'),
      view: sanitizeText(labels?.view, 'View'),
      sleeps: sanitizeText(labels?.sleeps, 'Sleeps'),
      bedrooms: sanitizeText(labels?.bedrooms, 'Bedrooms'),
      bathrooms: sanitizeText(labels?.bathrooms, 'Bathrooms'),
      startsAtPrefix: sanitizeText(labels?.startsAtPrefix, 'Starts at'),
      previous: sanitizeText(labels?.previous, 'Previous'),
      next: sanitizeText(labels?.next, 'Next'),
    },
    items: normalizeFeaturedItems(rawConfig?.items),
  };
}

function normalizeFeaturedItems(items) {
  if (!Array.isArray(items)) {
    return [];
  }

  return items
    .filter((item) => isPlainObject(item))
    .map((item) => {
      const images = normalizeImageUrls(item.images);
      const permalink = sanitizeUrl(item.permalink);

      return {
        id: sanitizeText(item.id, ''),
        propertyId: sanitizeText(item.propertyId, ''),
        title: sanitizeText(item.title, ''),
        propertyType: sanitizeText(item.propertyType, ''),
        view: sanitizeText(item.view, ''),
        sleeps: sanitizeStatValue(item.sleeps),
        bedrooms: sanitizeStatValue(item.bedrooms),
        bathrooms: sanitizeStatValue(item.bathrooms),
        startingPrice: normalizeNumber(item.startingPrice),
        images,
        permalink,
      };
    })
    .filter((item) => item.title !== '');
}

function renderFeaturedPropertiesMarkup(mountNode, config) {
  const hasItems = config.items.length > 0;
  const showOuterNavigation = config.items.length > 1 && config.slider.outer.navigation;
  const navMarkup = `
    <div class="barefoot-engine-featured-properties__outer-navigation${showOuterNavigation ? '' : ' is-hidden'}">
      <button
        type="button"
        class="barefoot-engine-featured-properties__outer-nav-btn barefoot-engine-featured-properties__outer-prev"
        aria-label="${escapeHtml(config.labels.previous)}"
      >
        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
      </button>
      <button
        type="button"
        class="barefoot-engine-featured-properties__outer-nav-btn barefoot-engine-featured-properties__outer-next"
        aria-label="${escapeHtml(config.labels.next)}"
      >
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
      </button>
    </div>
  `;

  const headerClasses = [
    'barefoot-engine-featured-properties__header',
    `is-heading-${config.headingPosition}`,
  ];

  const sectionClasses = [
    'barefoot-engine-featured-properties__section',
    `is-nav-${config.sliderControlPosition}`,
  ];

  let contentMarkup = '';
  if (hasItems) {
    const sliderMarkup = renderFeaturedPropertiesSlider(config);

    if (config.sliderControlPosition === 'side') {
      contentMarkup = `
        <div class="barefoot-engine-featured-properties__content">
          ${navMarkup}
          ${sliderMarkup}
        </div>
      `;
    } else if (config.sliderControlPosition === 'bottom-center') {
      contentMarkup = `
        ${sliderMarkup}
        ${navMarkup}
      `;
    } else {
      contentMarkup = sliderMarkup;
    }
  } else {
    contentMarkup = `<p class="barefoot-engine-featured-properties__empty">${escapeHtml(config.emptyText)}</p>`;
  }

  mountNode.innerHTML = `
    <section class="${sectionClasses.join(' ')}">
      <header class="${headerClasses.join(' ')}">
        <h2 class="barefoot-engine-featured-properties__title">${escapeHtml(config.title)}</h2>
        ${config.sliderControlPosition === 'top-right' && hasItems ? navMarkup : ''}
      </header>
      ${contentMarkup}
    </section>
  `;
}

function renderFeaturedPropertiesSlider(config) {
  const slides = config.items
    .map((item, index) => `
      <div class="swiper-slide barefoot-engine-featured-properties__slide">
        ${renderFeaturedCard(item, config, index)}
      </div>
    `)
    .join('');

  return `
    <div class="swiper barefoot-engine-featured-properties__outer-swiper">
      <div class="swiper-wrapper">
        ${slides}
      </div>
    </div>
  `;
}

function renderFeaturedCard(item, config, index) {
  const { labels, currency, metaDisplay } = config;
  const startsAtText = formatStartsAtText(item.startingPrice, currency, labels.startsAtPrefix);
  const stats = [
    { key: 'sleeps', label: labels.sleeps, value: formatStatValue(item.sleeps), icon: 'fa-solid fa-users' },
    { key: 'bedrooms', label: labels.bedrooms, value: formatStatValue(item.bedrooms), icon: 'fa-solid fa-bed' },
    { key: 'bathrooms', label: labels.bathrooms, value: formatStatValue(item.bathrooms), icon: 'fa-solid fa-bath' },
  ].filter((stat) => metaDisplay.includes(stat.key));

  const metaItems = [];
  if (metaDisplay.includes('property_type')) {
    metaItems.push(`
      <span class="barefoot-engine-featured-properties__meta-item">
        <i class="fa-solid fa-house" aria-hidden="true"></i>
        ${escapeHtml(item.propertyType || '—')}
      </span>
    `);
  }

  if (metaDisplay.includes('view')) {
    metaItems.push(`
      <span class="barefoot-engine-featured-properties__meta-item">
        <i class="fa-solid fa-tag" aria-hidden="true"></i>
        ${escapeHtml(item.view || '—')}
      </span>
    `);
  }

  const imageSlider = item.images.length > 0
    ? renderNestedImageSlider(item, index, config.slider.inner.navigation)
    : '<div class="barefoot-engine-featured-properties__image-placeholder" aria-hidden="true"></div>';

  const safeTitle = escapeHtml(item.title);
  const safePermalink = item.permalink ? escapeHtml(item.permalink) : '';
  const titleMarkup = safePermalink
    ? `<a href="${safePermalink}" class="barefoot-engine-featured-properties__card-title-link">${safeTitle}</a>`
    : `<span class="barefoot-engine-featured-properties__card-title-link">${safeTitle}</span>`;

  return `
    <article class="barefoot-engine-featured-properties__card">
      <div class="barefoot-engine-featured-properties__media">
        ${imageSlider}
      </div>
      <div class="barefoot-engine-featured-properties__body">
        <h3 class="barefoot-engine-featured-properties__card-title">${titleMarkup}</h3>
        ${metaDisplay.includes('starts_at') && startsAtText ? `<p class="barefoot-engine-featured-properties__starts-at">${escapeHtml(startsAtText)}</p>` : ''}
        ${metaItems.length > 0
    ? `<div class="barefoot-engine-featured-properties__meta-row">${metaItems.join('')}</div>`
    : ''}
        ${stats.length > 0
    ? `<div class="barefoot-engine-featured-properties__stats-row">${stats
    .map((stat) => `
              <span
                class="barefoot-engine-featured-properties__stat-item"
                title="${escapeHtml(stat.label)}"
                aria-label="${escapeHtml(`${stat.label} ${stat.value}`)}"
              >
                <i class="${escapeHtml(stat.icon)}" aria-hidden="true"></i>
                <strong>${escapeHtml(stat.value)}</strong>
              </span>
            `)
    .join('')}</div>`
    : ''}
      </div>
    </article>
  `;
}

function renderNestedImageSlider(item, index, showNavigation) {
  const slides = item.images
    .map((imageUrl) => `
      <div class="swiper-slide barefoot-engine-featured-properties__image-slide">
        <img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(item.title)}" loading="lazy" />
      </div>
    `)
    .join('');

  const hasNavigation = showNavigation && item.images.length > 1;

  return `
    <div class="swiper barefoot-engine-featured-properties__image-swiper" data-be-featured-image-swiper data-image-count="${String(item.images.length)}">
      <div class="swiper-wrapper">
        ${slides}
      </div>
      <button
        type="button"
        class="barefoot-engine-featured-properties__image-nav barefoot-engine-featured-properties__image-prev${hasNavigation ? '' : ' is-hidden'}"
        aria-label="Previous image ${index + 1}"
        data-be-featured-image-prev
      >
        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
      </button>
      <button
        type="button"
        class="barefoot-engine-featured-properties__image-nav barefoot-engine-featured-properties__image-next${hasNavigation ? '' : ' is-hidden'}"
        aria-label="Next image ${index + 1}"
        data-be-featured-image-next
      >
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
      </button>
    </div>
  `;
}

function initializeOuterSlider(mountNode, config) {
  const sliderNode = mountNode.querySelector('.barefoot-engine-featured-properties__outer-swiper');
  if (!(sliderNode instanceof HTMLElement)) {
    return;
  }

  const prevButton = mountNode.querySelector('.barefoot-engine-featured-properties__outer-prev');
  const nextButton = mountNode.querySelector('.barefoot-engine-featured-properties__outer-next');
  const navContainer = mountNode.querySelector('.barefoot-engine-featured-properties__outer-navigation');
  const sliderConfig = config.slider.outer;
  const enableNavigation = sliderConfig.navigation
    && prevButton instanceof HTMLElement
    && nextButton instanceof HTMLElement;
  const enableAutoplay = sliderConfig.autoplay && config.items.length > 1;
  const modules = [Navigation];
  if (enableAutoplay) {
    modules.push(Autoplay);
  }

  const outerSwiper = new Swiper(sliderNode, {
    modules,
    loop: sliderConfig.loop && config.items.length > 1,
    slidesPerView: sliderConfig.slidesPerView.mobile,
    spaceBetween: sliderConfig.spaceBetween.mobile,
    watchOverflow: true,
    navigation: {
      enabled: enableNavigation,
      prevEl: enableNavigation ? prevButton : null,
      nextEl: enableNavigation ? nextButton : null,
    },
    autoplay: enableAutoplay
      ? {
        delay: sliderConfig.autoplayDelay,
        disableOnInteraction: false,
        pauseOnMouseEnter: true,
      }
      : false,
    breakpoints: {
      768: {
        slidesPerView: sliderConfig.slidesPerView.tablet,
        spaceBetween: sliderConfig.spaceBetween.tablet,
      },
      1024: {
        slidesPerView: sliderConfig.slidesPerView.desktop,
        spaceBetween: sliderConfig.spaceBetween.desktop,
      },
    },
  });

  const syncOuterNavigationState = () => {
    if (!(navContainer instanceof HTMLElement)) {
      return;
    }

    const shouldHide = !sliderConfig.navigation || config.items.length <= 1;
    navContainer.classList.toggle('is-hidden', shouldHide);
  };

  outerSwiper.on('lock', syncOuterNavigationState);
  outerSwiper.on('unlock', syncOuterNavigationState);
  outerSwiper.on('resize', syncOuterNavigationState);
  syncOuterNavigationState();

  mountNode.beFeaturedOuterSwiper = outerSwiper;
}

function initializeNestedImageSliders(mountNode) {
  const config = isPlainObject(mountNode.beFeaturedConfig) ? mountNode.beFeaturedConfig : {};
  const sliderConfig = isPlainObject(config.slider) && isPlainObject(config.slider.inner)
    ? config.slider.inner
    : { loop: true, navigation: true };
  const imageSwipers = [];

  mountNode.querySelectorAll('[data-be-featured-image-swiper]').forEach((sliderNode) => {
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

    const prevButton = card.querySelector('[data-be-featured-image-prev]');
    const nextButton = card.querySelector('[data-be-featured-image-next]');
    if (!(prevButton instanceof HTMLElement) || !(nextButton instanceof HTMLElement)) {
      return;
    }

    const imageSwiper = new Swiper(sliderNode, {
      modules: [Navigation],
      loop: Boolean(sliderConfig.loop),
      slidesPerView: 1,
      spaceBetween: 0,
      watchOverflow: true,
      nested: true,
      navigation: {
        enabled: Boolean(sliderConfig.navigation),
        prevEl: sliderConfig.navigation ? prevButton : null,
        nextEl: sliderConfig.navigation ? nextButton : null,
      },
    });

    imageSwipers.push(imageSwiper);
  });

  mountNode.beFeaturedImageSwipers = imageSwipers;
}

function normalizeMetaDisplay(rawValue) {
  if (Array.isArray(rawValue)) {
    const normalized = rawValue
      .map((value) => String(value ?? '').trim().toLowerCase())
      .filter((value, index, list) => value !== '' && FEATURED_META_KEYS.includes(value) && list.indexOf(value) === index);

    if (normalized.length > 0) {
      return normalized;
    }
  } else if (typeof rawValue === 'string') {
    const normalized = rawValue
      .split(',')
      .map((value) => value.trim().toLowerCase())
      .filter((value, index, list) => value !== '' && FEATURED_META_KEYS.includes(value) && list.indexOf(value) === index);

    if (normalized.length > 0) {
      return normalized;
    }
  }

  return [...FEATURED_META_KEYS];
}

function normalizeSliderConfig(rawSlider) {
  const rawOuter = isPlainObject(rawSlider?.outer) ? rawSlider.outer : {};
  const rawInner = isPlainObject(rawSlider?.inner) ? rawSlider.inner : {};

  return {
    outer: {
      loop: parseBoolean(rawOuter?.loop, true),
      navigation: parseBoolean(rawOuter?.navigation, true),
      autoplay: parseBoolean(rawOuter?.autoplay, false),
      autoplayDelay: parsePositiveInt(rawOuter?.autoplayDelay, 5000, 1000, 30000),
      slidesPerView: {
        mobile: parsePositiveInt(rawOuter?.slidesPerView?.mobile, 1, 1, 2),
        tablet: parsePositiveInt(rawOuter?.slidesPerView?.tablet, 2, 1, 3),
        desktop: parsePositiveInt(rawOuter?.slidesPerView?.desktop, 3, 1, 6),
      },
      spaceBetween: {
        mobile: parsePositiveInt(rawOuter?.spaceBetween?.mobile, 16, 0, 80),
        tablet: parsePositiveInt(rawOuter?.spaceBetween?.tablet, 20, 0, 80),
        desktop: parsePositiveInt(rawOuter?.spaceBetween?.desktop, 24, 0, 120),
      },
    },
    inner: {
      loop: parseBoolean(rawInner?.loop, true),
      navigation: parseBoolean(rawInner?.navigation, true),
    },
  };
}

function normalizeHeadingPosition(value) {
  const normalized = String(value ?? '').trim().toLowerCase();
  if (['left', 'center', 'right'].includes(normalized)) {
    return normalized;
  }

  return 'left';
}

function normalizeSliderControlPosition(value) {
  const normalized = String(value ?? '').trim().toLowerCase();
  if (['side', 'top-right', 'bottom-center'].includes(normalized)) {
    return normalized;
  }

  return 'top-right';
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

function sanitizeStatValue(value) {
  if (value === null || value === undefined) {
    return '';
  }

  const normalized = String(value).trim();
  return normalized;
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

function formatStartsAtText(value, currency, prefix) {
  if (!Number.isFinite(value) || Number(value) <= 0) {
    return '';
  }

  return `${sanitizeText(prefix, 'Starts at')} ${formatMoney(Number(value), currency)}`;
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
