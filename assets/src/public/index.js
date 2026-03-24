import { BPCalendar, BP_Calendar } from '@braudypedrosa/bp-calendar';
import listingsMapModule from './bp-listings-runtime.js';
import { bootBookingCheckoutWidgets } from './booking-checkout.js';
import { bootFeaturedProperties } from './featured-properties.js';
import { BPSearchWidget, BP_SearchWidget } from '@braudypedrosa/bp-search-widget';

const SEARCH_WIDGET_SELECTOR = '[data-be-search-widget]';
const LISTINGS_SELECTOR = '[data-be-listings]';
const BOOKING_WIDGET_SELECTOR = '[data-be-booking-widget]';
const PRICING_TABLE_SELECTOR = '[data-be-pricing-table]';
const ListingsMap = listingsMapModule?.ListingsMap ?? listingsMapModule ?? null;
const AJAX_SEARCH_MIN_BUFFER_MS = 1500;
const CHOICE_POPOVER_SCROLLBAR_MIN_THUMB = 44;
const BOOKING_QUOTE_DEBOUNCE_MS = 350;
const BOOKING_SESSION_COOKIE = 'be_booking_checkout_session';
const choicePopoverScrollbarStates = new WeakMap();

if (typeof window !== 'undefined') {
  if (!window.BPCalendar) {
    window.BPCalendar = BPCalendar;
  }

  if (!window.BP_Calendar) {
    window.BP_Calendar = BP_Calendar;
  }

  if (!window.BPSearchWidget) {
    window.BPSearchWidget = BPSearchWidget;
  }

  if (!window.BP_SearchWidget) {
    window.BP_SearchWidget = BP_SearchWidget;
  }

  if (!window.ListingsMap && ListingsMap) {
    window.ListingsMap = ListingsMap;
  }
}

function bootSearchWidgets() {
  const mounts = document.querySelectorAll(SEARCH_WIDGET_SELECTOR);

  mounts.forEach((mountNode) => {
    if (!(mountNode instanceof HTMLElement) || mountNode.dataset.beSearchWidgetReady === 'true') {
      return;
    }

    const configId = mountNode.dataset.beSearchWidgetConfig;
    const config = readWidgetConfig(configId);

    if (!config) {
      return;
    }

    const { targetUrl = '', ...widgetOptions } = config;

    try {
      new BPSearchWidget(mountNode, {
        ...widgetOptions,
        onSearch: (payload) => {
          redirectToSearchResults(targetUrl, payload);
        },
      });
      mountNode.dataset.beSearchWidgetReady = 'true';
    } catch (error) {
      console.error('[barefoot-engine] Failed to initialize search widget.', error);
    }
  });
}

function bootListingsWidgets() {
  const mounts = document.querySelectorAll(LISTINGS_SELECTOR);
  const listingsApi = window.ListingsMap || ListingsMap;

  if (!listingsApi || typeof listingsApi.init !== 'function') {
    if (mounts.length > 0) {
      console.error('[barefoot-engine] ListingsMap.init is unavailable.');
    }

    return;
  }

  mounts.forEach((mountNode) => {
    if (!(mountNode instanceof HTMLElement) || mountNode.dataset.beListingsReady === 'true') {
      return;
    }

    const configId = mountNode.dataset.beListingsConfig;
    const config = readWidgetConfig(configId, 'Listings');

    if (!config) {
      return;
    }

    const allListings = Array.isArray(config.listings) ? config.listings : [];
    const searchWidgetConfig = isPlainObject(config.searchWidget) ? config.searchWidget : null;
    const fieldTypeMap = buildFieldTypeMap(searchWidgetConfig);
    const initialSearchPayload = parseSearchPayloadFromUrl(window.location.search);
    const hasInitialSearch = hasSearchPayload(initialSearchPayload);
    const hasInitialWidgetValues = hasInitialSearch
      || !isEmptyValue(initialSearchPayload.checkIn)
      || !isEmptyValue(initialSearchPayload.checkOut);
    const filteredListings = hasInitialSearch
      ? allListings.filter((listing) => matchListing(listing, initialSearchPayload, fieldTypeMap))
      : allListings;
    const { searchWidget, ...widgetConfig } = config;
    const hasSelectedCheckIn = typeof initialSearchPayload.checkIn === 'string' && initialSearchPayload.checkIn.trim() !== '';

    mountNode.classList.toggle('be-listings-initial-pricing', !hasSelectedCheckIn);

    try {
      const widget = listingsApi.init({
        container: mountNode,
        ...widgetConfig,
        listings: filteredListings,
        renderSearchSlot: searchWidgetConfig
          ? (containerEl) => {
            if (!(containerEl instanceof HTMLElement)) {
              return undefined;
            }

            const host = document.createElement('div');
            host.className = 'barefoot-engine-listings__search-slot';
            containerEl.appendChild(host);

            const { targetUrl = '', ...searchOptions } = searchWidgetConfig;
            const searchWidget = new BPSearchWidget(host, {
              ...searchOptions,
              onSearch: (payload) => {
                if (shouldRedirectListingsSearch(targetUrl)) {
                  redirectToSearchResults(targetUrl, payload);
                  return;
                }

                runAjaxListingsSearch(mountNode, fieldTypeMap, payload);
              },
            });
            installListingsSearchSubmitRule(searchWidget);
            installListingsClearButtonSync(searchWidget, mountNode, host);

            if (hasInitialWidgetValues) {
              applySearchPayloadToWidgetState(searchWidget, initialSearchPayload);
            }
            mountNode.beEmbeddedSearchWidget = searchWidget;

            return () => {
              if (typeof searchWidget.destroy === 'function') {
                searchWidget.destroy();
              }
            };
          }
          : undefined,
        onListingClick: (listing) => {
          if (typeof listing?.permalink === 'string' && listing.permalink.trim() !== '') {
            window.location.assign(listing.permalink);
          }
        },
      });
      mountNode.beAllListings = allListings;
      mountNode.beListingsWidget = widget;
      mountNode.beHasActiveSearch = hasInitialSearch;
      mountNode.dataset.beListingsReady = 'true';
      alignToolbarControls(mountNode);
      updateClearSearchButton(mountNode);
      updateListingsCount(mountNode, filteredListings.length);
      triggerAvailabilityPreflight(initialSearchPayload, 'initial-load');
      maybeRefineListingsWithAvailability(mountNode, fieldTypeMap, initialSearchPayload);
    } catch (error) {
      console.error('[barefoot-engine] Failed to initialize listings widget.', error);
    }
  });
}

function readWidgetConfig(configId, label = 'Widget') {
  if (!configId) {
    console.error(`[barefoot-engine] ${label} config id is missing.`);
    return null;
  }

  const configNode = document.getElementById(configId);
  if (!(configNode instanceof HTMLScriptElement)) {
    console.error(`[barefoot-engine] ${label} config node was not found.`);
    return null;
  }

  try {
    return JSON.parse(configNode.textContent || '{}');
  } catch (error) {
    console.error(`[barefoot-engine] ${label} config is invalid JSON.`, error);
    return null;
  }
}

function redirectToSearchResults(targetUrl, payload) {
  const url = new URL(targetUrl || window.location.href, window.location.origin);
  const params = new URLSearchParams(url.search);

  clearManagedParams(params);

  appendQueryValue(params, 'location', payload?.location);
  appendQueryValue(params, 'check_in', payload?.checkIn);
  appendQueryValue(params, 'check_out', payload?.checkOut);

  if (payload?.customFields && typeof payload.customFields === 'object') {
    Object.entries(payload.customFields).forEach(([key, value]) => {
      appendQueryValue(params, `field_${key}`, value);
    });
  }

  if (payload?.filters && typeof payload.filters === 'object') {
    Object.entries(payload.filters).forEach(([key, value]) => {
      appendQueryValue(params, `filter_${key}`, value);
    });
  }

  url.search = params.toString();
  window.location.assign(url.toString());
}

function clearManagedParams(params) {
  const keysToDelete = [];

  params.forEach((_, key) => {
    if (key === 'location' || key === 'check_in' || key === 'check_out' || key.startsWith('field_') || key.startsWith('filter_')) {
      keysToDelete.push(key);
    }
  });

  keysToDelete.forEach((key) => {
    params.delete(key);
  });
}

function appendQueryValue(params, key, value) {
  if (Array.isArray(value)) {
    value.forEach((item) => {
      if (isEmptyValue(item)) {
        return;
      }

      params.append(`${key}[]`, String(item));
    });

    return;
  }

  if (isEmptyValue(value)) {
    return;
  }

  params.set(key, String(value));
}

function isEmptyValue(value) {
  if (value === null || value === undefined) {
    return true;
  }

  if (typeof value === 'string') {
    return value.trim() === '';
  }

  return false;
}

function buildFieldTypeMap(searchWidgetConfig) {
  const entries = [];

  if (Array.isArray(searchWidgetConfig?.fields)) {
    entries.push(...searchWidgetConfig.fields);
  }

  if (Array.isArray(searchWidgetConfig?.filters)) {
    entries.push(...searchWidgetConfig.filters);
  }

  return new Map(
    entries
      .filter((entry) => isPlainObject(entry) && typeof entry.key === 'string')
      .map((entry) => [entry.key, typeof entry.type === 'string' ? entry.type : 'input']),
  );
}

function parseSearchPayloadFromUrl(search) {
  const params = new URLSearchParams(search);
  const payload = {
    location: '',
    checkIn: '',
    checkOut: '',
    customFields: {},
    filters: {},
  };

  params.forEach((value, rawKey) => {
    if (rawKey === 'location') {
      payload.location = value;
      return;
    }

    if (rawKey === 'check_in') {
      payload.checkIn = normalizeDateInput(value);
      return;
    }

    if (rawKey === 'check_out') {
      payload.checkOut = normalizeDateInput(value);
      return;
    }

    if (rawKey.startsWith('field_')) {
      appendPayloadValue(payload.customFields, rawKey.slice(6), value);
      return;
    }

    if (rawKey.startsWith('filter_')) {
      appendPayloadValue(payload.filters, rawKey.slice(7), value);
      return;
    }

    if (rawKey === 'guests') {
      appendPayloadValue(payload.customFields, 'guests', value);
      appendPayloadValue(payload.filters, 'guests', value);
      return;
    }

    if (rawKey === 'bedrooms' || rawKey === 'bathrooms') {
      appendPayloadValue(payload.customFields, rawKey, value);
      appendPayloadValue(payload.filters, rawKey, value);
      return;
    }

    if (rawKey === 'property_type' || rawKey === 'view') {
      appendPayloadValue(payload.filters, rawKey, value);
    }
  });

  return payload;
}

function normalizeDateInput(value) {
  const raw = String(value ?? '').trim();
  if (!raw) {
    return '';
  }

  if (isValidDateString(raw)) {
    return raw;
  }

  const slashMatch = raw.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
  if (!slashMatch) {
    return '';
  }

  const month = Number(slashMatch[1]);
  const day = Number(slashMatch[2]);
  const year = Number(slashMatch[3]);

  if (!Number.isInteger(month) || !Number.isInteger(day) || !Number.isInteger(year)) {
    return '';
  }

  const candidate = new Date(Date.UTC(year, month - 1, day));
  if (
    candidate.getUTCFullYear() !== year
    || candidate.getUTCMonth() !== month - 1
    || candidate.getUTCDate() !== day
  ) {
    return '';
  }

  const yyyy = String(year).padStart(4, '0');
  const mm = String(month).padStart(2, '0');
  const dd = String(day).padStart(2, '0');

  return `${yyyy}-${mm}-${dd}`;
}

function appendPayloadValue(target, rawKey, value) {
  const isArrayKey = rawKey.endsWith('[]');
  const key = isArrayKey ? rawKey.slice(0, -2) : rawKey;

  if (!key) {
    return;
  }

  if (isArrayKey) {
    if (!Array.isArray(target[key])) {
      target[key] = [];
    }

    target[key].push(value);
    return;
  }

  target[key] = value;
}

function hasSearchPayload(payload) {
  return !isEmptyValue(payload?.location)
    || hasNonEmptyPayloadEntries(payload?.customFields)
    || hasNonEmptyPayloadEntries(payload?.filters);
}

function hasNonEmptyPayloadEntries(values) {
  if (!isPlainObject(values)) {
    return false;
  }

  return Object.values(values).some((value) => {
    if (Array.isArray(value)) {
      return value.some((entry) => !isEmptyValue(entry));
    }

    return !isEmptyValue(value);
  });
}

function installListingsSearchSubmitRule(widget) {
  if (!widget || widget.isDestroyed) {
    return;
  }

  widget.beInitialSearchCriteria = captureListingsSearchCriteriaSnapshot(widget);
  installCustomChoicePopoverScrollbar(widget);

  widget.canSubmitSearch = function canSubmitSearchForListings() {
    const hasRequiredFields = typeof this.hasRequiredFieldsFilled === 'function'
      ? this.hasRequiredFieldsFilled()
      : true;
    const hasRequiredFilters = typeof this.hasRequiredFiltersFilled === 'function'
      ? this.hasRequiredFiltersFilled()
      : true;

    if (!hasRequiredFields || !hasRequiredFilters) {
      return false;
    }

    return hasAtLeastOneListingsCriterion(this, this.beInitialSearchCriteria || null);
  };

  if (typeof widget.syncSearchDisabledState === 'function') {
    widget.syncSearchDisabledState();
  }
}

function installCustomChoicePopoverScrollbar(widget) {
  if (!widget || widget.isDestroyed || widget.beCustomChoiceScrollbarInstalled) {
    return;
  }

  const originalOpenChoicePopover = typeof widget.openChoicePopover === 'function'
    ? widget.openChoicePopover.bind(widget)
    : null;
  const originalCloseChoicePopover = typeof widget.closeChoicePopover === 'function'
    ? widget.closeChoicePopover.bind(widget)
    : null;

  if (!originalOpenChoicePopover || !originalCloseChoicePopover) {
    return;
  }

  widget.openChoicePopover = function patchedOpenChoicePopover(...args) {
    const result = originalOpenChoicePopover(...args);
    window.requestAnimationFrame(() => {
      syncCustomChoicePopoverScrollbar(this);
    });
    return result;
  };

  widget.closeChoicePopover = function patchedCloseChoicePopover(...args) {
    teardownCustomChoicePopoverScrollbarForWidget(this);
    return originalCloseChoicePopover(...args);
  };

  widget.beCustomChoiceScrollbarInstalled = true;
}

function syncCustomChoicePopoverScrollbar(widget) {
  return;
}

function teardownCustomChoicePopoverScrollbarForWidget(widget) {
  const popover = widget?.openPopover?.popover;
  if (!(popover instanceof HTMLElement)) {
    return;
  }

  teardownCustomChoicePopoverScrollbar(popover);
}

function ensureCustomChoicePopoverScrollbar(popover) {
  if (!(popover instanceof HTMLElement) || !popover.classList.contains('bp-search-widget__popover')) {
    return null;
  }

  const existing = choicePopoverScrollbarStates.get(popover);
  if (existing) {
    return existing;
  }

  const track = document.createElement('div');
  track.className = 'barefoot-engine-choice-scrollbar is-hidden';
  track.setAttribute('aria-hidden', 'true');

  const thumb = document.createElement('div');
  thumb.className = 'barefoot-engine-choice-scrollbar__thumb';
  track.appendChild(thumb);
  popover.appendChild(track);

  const onScroll = () => {
    const state = choicePopoverScrollbarStates.get(popover);
    if (!state) {
      return;
    }
    updateCustomChoicePopoverScrollbar(popover, state);
  };

  popover.addEventListener('scroll', onScroll, { passive: true });

  const state = {
    track,
    thumb,
    onScroll,
    resizeObserver: null,
    onWindowResize: null,
  };

  if (typeof ResizeObserver === 'function') {
    const resizeObserver = new ResizeObserver(() => {
      const latestState = choicePopoverScrollbarStates.get(popover);
      if (!latestState) {
        return;
      }
      updateCustomChoicePopoverScrollbar(popover, latestState);
    });
    resizeObserver.observe(popover);
    state.resizeObserver = resizeObserver;
  } else {
    const onWindowResize = () => {
      const latestState = choicePopoverScrollbarStates.get(popover);
      if (!latestState) {
        return;
      }
      updateCustomChoicePopoverScrollbar(popover, latestState);
    };
    window.addEventListener('resize', onWindowResize);
    state.onWindowResize = onWindowResize;
  }

  choicePopoverScrollbarStates.set(popover, state);
  return state;
}

function updateCustomChoicePopoverScrollbar(popover, state) {
  if (!(popover instanceof HTMLElement) || !state?.track || !state?.thumb) {
    return;
  }

  const overflow = popover.scrollHeight - popover.clientHeight;
  if (overflow <= 1) {
    state.track.classList.add('is-hidden');
    state.thumb.style.height = '';
    state.thumb.style.transform = 'translateY(0px)';
    return;
  }

  const trackHeight = state.track.clientHeight;
  if (trackHeight <= 0) {
    return;
  }

  const rawThumbHeight = Math.round((popover.clientHeight / popover.scrollHeight) * trackHeight);
  const thumbHeight = Math.min(trackHeight, Math.max(CHOICE_POPOVER_SCROLLBAR_MIN_THUMB, rawThumbHeight));
  const maxThumbTop = Math.max(0, trackHeight - thumbHeight);
  const thumbTop = maxThumbTop === 0
    ? 0
    : Math.round((popover.scrollTop / overflow) * maxThumbTop);

  state.track.classList.remove('is-hidden');
  state.thumb.style.height = `${thumbHeight}px`;
  state.thumb.style.transform = `translateY(${thumbTop}px)`;
}

function teardownCustomChoicePopoverScrollbar(popover) {
  const state = choicePopoverScrollbarStates.get(popover);
  if (!state) {
    return;
  }

  popover.removeEventListener('scroll', state.onScroll);
  if (state.resizeObserver) {
    state.resizeObserver.disconnect();
  }
  if (state.onWindowResize) {
    window.removeEventListener('resize', state.onWindowResize);
  }
  if (state.track && state.track.parentElement === popover) {
    state.track.remove();
  }

  choicePopoverScrollbarStates.delete(popover);
}

function captureListingsSearchCriteriaSnapshot(widget) {
  return {
    location: normalizeString(widget?.state?.location ?? ''),
    checkIn: normalizeDateInput(widget?.state?.checkIn ?? ''),
    checkOut: normalizeDateInput(widget?.state?.checkOut ?? ''),
    customFields: normalizeComparableMap(widget?.state?.customFields),
    filters: normalizeComparableMap(widget?.state?.filters),
  };
}

function normalizeComparableMap(values) {
  if (!isPlainObject(values)) {
    return {};
  }

  const normalized = {};
  Object.entries(values).forEach(([key, value]) => {
    normalized[key] = normalizeComparableValue(value);
  });

  return normalized;
}

function normalizeComparableValue(value) {
  if (Array.isArray(value)) {
    return value
      .map((entry) => normalizeString(entry))
      .filter(Boolean)
      .sort();
  }

  return normalizeString(value);
}

function isComparableValueMeaningful(value) {
  if (Array.isArray(value)) {
    return value.length > 0;
  }

  return typeof value === 'string' && value !== '';
}

function areComparableValuesEqual(left, right) {
  if (Array.isArray(left) || Array.isArray(right)) {
    if (!Array.isArray(left) || !Array.isArray(right) || left.length !== right.length) {
      return false;
    }

    for (let index = 0; index < left.length; index += 1) {
      if (left[index] !== right[index]) {
        return false;
      }
    }

    return true;
  }

  return String(left ?? '') === String(right ?? '');
}

function hasChangedMeaningfulEntry(currentMap, baselineMap) {
  const keys = new Set([
    ...Object.keys(isPlainObject(currentMap) ? currentMap : {}),
    ...Object.keys(isPlainObject(baselineMap) ? baselineMap : {}),
  ]);

  for (const key of keys) {
    const currentValue = currentMap[key];
    const baselineValue = baselineMap[key];

    if (!areComparableValuesEqual(currentValue, baselineValue) && isComparableValueMeaningful(currentValue)) {
      return true;
    }
  }

  return false;
}

function hasCompleteDateRange(checkIn, checkOut) {
  return !isEmptyValue(checkIn) && !isEmptyValue(checkOut);
}

function hasAtLeastOneListingsCriterion(widget, baseline = null) {
  if (!widget || widget.isDestroyed) {
    return false;
  }

  const currentSnapshot = captureListingsSearchCriteriaSnapshot(widget);
  const baselineSnapshot = isPlainObject(baseline) ? baseline : {
    location: '',
    checkIn: '',
    checkOut: '',
    customFields: {},
    filters: {},
  };

  if (
    currentSnapshot.location !== baselineSnapshot.location
    && currentSnapshot.location !== ''
  ) {
    return true;
  }

  const currentHasDateRange = hasCompleteDateRange(currentSnapshot.checkIn, currentSnapshot.checkOut);
  const baselineHasDateRange = hasCompleteDateRange(baselineSnapshot.checkIn, baselineSnapshot.checkOut);
  if (
    currentHasDateRange
    && (
      !baselineHasDateRange
      || currentSnapshot.checkIn !== baselineSnapshot.checkIn
      || currentSnapshot.checkOut !== baselineSnapshot.checkOut
    )
  ) {
    return true;
  }

  if (hasChangedMeaningfulEntry(currentSnapshot.customFields, baselineSnapshot.customFields)) {
    return true;
  }

  return hasChangedMeaningfulEntry(currentSnapshot.filters, baselineSnapshot.filters);
}

function matchListing(listing, payload, fieldTypeMap) {
  const searchData = isPlainObject(listing?.searchData) ? listing.searchData : {};
  const fieldValues = isPlainObject(searchData.fields) ? searchData.fields : {};
  const filterValues = isPlainObject(searchData.filters) ? searchData.filters : {};

  if (!matchesSubstring(searchData.location, payload.location)) {
    return false;
  }

  if (
    !isEmptyValue(payload.checkIn)
    && !isEmptyValue(payload.checkOut)
    && !matchesAvailability(searchData.availability, payload.checkIn, payload.checkOut)
  ) {
    return false;
  }

  for (const [key, value] of Object.entries(payload.customFields || {})) {
    if (!matchValue(key, fieldTypeMap.get(key), fieldValues[key], value)) {
      return false;
    }
  }

  for (const [key, value] of Object.entries(payload.filters || {})) {
    if (!matchValue(key, fieldTypeMap.get(key), filterValues[key], value)) {
      return false;
    }
  }

  return true;
}

function matchValue(key, type, listingValue, submittedValue) {
  if (type === 'checkbox') {
    return matchesAllChoices(listingValue, submittedValue);
  }

  if (type === 'counter') {
    return matchesNumericAtLeast(listingValue, submittedValue);
  }

  if (type === 'select' || type === 'radio') {
    if (isAtLeastNumericKey(key) && hasNumericValue(listingValue) && hasNumericValue(submittedValue)) {
      return matchesNumericAtLeast(listingValue, submittedValue);
    }

    return matchesExact(listingValue, submittedValue);
  }

  if (Array.isArray(submittedValue)) {
    return matchesAllChoices(listingValue, submittedValue);
  }

  if (isAtLeastNumericKey(key) && hasNumericValue(listingValue) && hasNumericValue(submittedValue)) {
    return matchesNumericAtLeast(listingValue, submittedValue);
  }

  return matchesSubstring(listingValue, submittedValue);
}

function matchesSubstring(haystackValue, needleValue) {
  const needle = normalizeString(needleValue);
  if (!needle) {
    return true;
  }

  return normalizeStringArray(haystackValue).some((value) => value.includes(needle));
}

function matchesExact(haystackValue, needleValue) {
  const needle = normalizeString(needleValue);
  if (!needle) {
    return true;
  }

  return normalizeStringArray(haystackValue).some((value) => value === needle);
}

function matchesAllChoices(haystackValue, needleValues) {
  const selectedValues = normalizeStringArray(needleValues);
  if (selectedValues.length === 0) {
    return true;
  }

  const availableValues = new Set(normalizeStringArray(haystackValue));

  return selectedValues.every((value) => availableValues.has(value));
}

function matchesNumericAtLeast(haystackValue, needleValue) {
  const numericHaystack = extractNumericValue(haystackValue);
  const numericNeedle = extractNumericValue(needleValue);

  if (!Number.isFinite(numericNeedle)) {
    return true;
  }

  if (!Number.isFinite(numericHaystack)) {
    return false;
  }

  return numericHaystack >= numericNeedle;
}

function matchesAvailability(availability, checkIn, checkOut) {
  if (!isValidDateString(checkIn) || !isValidDateString(checkOut)) {
    return true;
  }

  if (!Array.isArray(availability) || availability.length === 0) {
    return true;
  }

  return availability.some(
    (windowRange) => isPlainObject(windowRange)
      && isValidDateString(windowRange.start)
      && isValidDateString(windowRange.end)
      && windowRange.start <= checkIn
      && windowRange.end >= checkOut,
  );
}

function isValidDateString(value) {
  return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value);
}

function normalizeString(value) {
  if (value === null || value === undefined) {
    return '';
  }

  return String(value).trim().toLowerCase();
}

function normalizeStringArray(value) {
  return toArray(value)
    .map((entry) => normalizeString(entry))
    .filter(Boolean);
}

function toArray(value) {
  if (Array.isArray(value)) {
    return value;
  }

  if (value === null || value === undefined) {
    return [];
  }

  return [value];
}

function extractNumericValue(value) {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }

  if (Array.isArray(value) && value.length > 0) {
    return extractNumericValue(value[0]);
  }

  const match = String(value ?? '').match(/-?\d+(\.\d+)?/);

  return match ? Number(match[0]) : Number.NaN;
}

function hasNumericValue(value) {
  return Number.isFinite(extractNumericValue(value));
}

function isGuestKey(key) {
  return typeof key === 'string' && /(guest|occupancy)/i.test(key);
}

function isAtLeastNumericKey(key) {
  if (isGuestKey(key)) {
    return true;
  }

  return typeof key === 'string' && /(bedroom|bedrooms|bathroom|bathrooms|beds|baths)/i.test(key);
}

function isPlainObject(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function beginListingsSearch(mountNode) {
  if (!(mountNode instanceof HTMLElement)) {
    return Number.NaN;
  }

  const token = (Number(mountNode.beSearchToken) || 0) + 1;
  mountNode.beSearchToken = token;
  setListingsSearching(mountNode, true);
  return token;
}

function getRemainingSearchBufferMs(startedAt) {
  if (!Number.isFinite(startedAt)) {
    return 0;
  }

  return Math.max(0, AJAX_SEARCH_MIN_BUFFER_MS - (Date.now() - startedAt));
}

function finishListingsSearch(mountNode, token, startedAt = Number.NaN) {
  if (!(mountNode instanceof HTMLElement)) {
    return;
  }

  const finalize = () => {
    if (Number(mountNode.beSearchToken) !== Number(token)) {
      return;
    }

    setListingsSearching(mountNode, false);
  };

  const remainingMs = getRemainingSearchBufferMs(startedAt);
  if (remainingMs > 0) {
    window.setTimeout(finalize, remainingMs);
    return;
  }

  finalize();
}

function applyListingsAndFinishSearch(mountNode, token, startedAt, listings) {
  if (!(mountNode instanceof HTMLElement)) {
    return;
  }

  const nextListings = Array.isArray(listings) ? listings : [];

  const finalize = () => {
    if (Number(mountNode.beSearchToken) !== Number(token)) {
      return;
    }

    setListingsSearching(mountNode, false);

    if (mountNode.beListingsWidget && typeof mountNode.beListingsWidget.setListings === 'function') {
      mountNode.beListingsWidget.setListings(nextListings);
    }

    updateListingsCount(mountNode, nextListings.length);

    if (mountNode.beHasActiveSearch) {
      focusMapOnFirstResult(mountNode, nextListings);
    }
  };

  const remainingMs = getRemainingSearchBufferMs(startedAt);
  if (remainingMs > 0) {
    window.setTimeout(finalize, remainingMs);
    return;
  }

  finalize();
}

function focusMapOnFirstResult(mountNode, listings) {
  if (!(mountNode instanceof HTMLElement) || !Array.isArray(listings) || listings.length === 0) {
    return;
  }

  const widget = mountNode.beListingsWidget;
  if (!widget || typeof widget !== 'object') {
    return;
  }

  const firstMappableListing = listings.find(
    (listing) => listing
      && typeof listing.id === 'string'
      && Number.isFinite(Number(listing.lat))
      && Number.isFinite(Number(listing.lng)),
  );

  if (!firstMappableListing) {
    return;
  }

  if (typeof widget.panToListing === 'function') {
    widget.panToListing(firstMappableListing.id);
  }

  const mapInstance = widget.map;
  if (!mapInstance || typeof mapInstance.setView !== 'function') {
    return;
  }

  const lat = Number(firstMappableListing.lat);
  const lng = Number(firstMappableListing.lng);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    return;
  }

  const currentZoom = typeof mapInstance.getZoom === 'function'
    ? Number(mapInstance.getZoom())
    : 0;
  const targetZoom = Number.isFinite(currentZoom) ? Math.max(currentZoom, 14) : 14;

  mapInstance.setView([lat, lng], targetZoom, { animate: true });
}

function maybeRefineListingsWithAvailability(mountNode, fieldTypeMap, payload, searchContext = {}) {
  if (!(mountNode instanceof HTMLElement)) {
    return;
  }

  const token = Number.isFinite(searchContext?.token)
    ? Number(searchContext.token)
    : beginListingsSearch(mountNode);
  const startedAt = Number.isFinite(searchContext?.startedAt)
    ? Number(searchContext.startedAt)
    : Number.NaN;
  const allListings = Array.isArray(mountNode.beAllListings) ? mountNode.beAllListings : [];
  const baseListings = Array.isArray(searchContext?.baseListings)
    ? searchContext.baseListings
    : (
      hasSearchPayload(payload)
        ? allListings.filter((listing) => matchListing(listing, payload, fieldTypeMap))
        : allListings
    );

  if (!hasSearchPayload(payload) || !hasValidDateRangePayload(payload)) {
    applyListingsAndFinishSearch(mountNode, token, startedAt, baseListings);
    return;
  }

  const restUrl = buildAvailabilitySearchUrl();
  if (!restUrl) {
    applyListingsAndFinishSearch(mountNode, token, startedAt, baseListings);
    return;
  }

  fetch(restUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      check_in: payload.checkIn,
      check_out: payload.checkOut,
      force_refresh: false,
    }),
  })
    .then(async (response) => {
      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        const message = typeof data?.message === 'string' && data.message.trim() !== ''
          ? data.message
          : 'Live availability unavailable. Showing locally cached results.';
        throw new Error(message);
      }

      return data;
    })
    .then((response) => {
      const availablePropertyIds = Array.isArray(response?.data?.available_property_ids)
        ? response.data.available_property_ids
          .map((propertyId) => String(propertyId ?? '').trim())
          .filter(Boolean)
        : [];

      const availableLookup = new Set(availablePropertyIds);
      const refinedListings = allListings
        .filter((listing) => {
          const propertyId = typeof listing?.propertyId === 'string'
            ? listing.propertyId.trim()
            : String(listing?.propertyId ?? '').trim();

          return propertyId !== '' && availableLookup.has(propertyId);
        })
        .filter((listing) => matchListing(listing, payload, fieldTypeMap));
      applyListingsAndFinishSearch(mountNode, token, startedAt, refinedListings);
    })
    .catch((error) => {
      console.warn('[barefoot-engine] Failed to refine listings with live availability.', error);
      applyListingsAndFinishSearch(mountNode, token, startedAt, baseListings);
    });
}

function runAjaxListingsSearch(mountNode, fieldTypeMap, payload) {
  if (!(mountNode instanceof HTMLElement)) {
    return;
  }

  mountNode.beHasActiveSearch = hasSearchPayload(payload);
  updateClearSearchButton(mountNode);
  const searchStartedAt = Date.now();
  const searchToken = beginListingsSearch(mountNode);
  syncSearchPayloadToCurrentUrl(payload);

  const hasSelectedCheckIn = typeof payload?.checkIn === 'string' && payload.checkIn.trim() !== '';
  mountNode.classList.toggle('be-listings-initial-pricing', !hasSelectedCheckIn);

  const allListings = Array.isArray(mountNode.beAllListings) ? mountNode.beAllListings : [];
  const filteredListings = hasSearchPayload(payload)
    ? allListings.filter((listing) => matchListing(listing, payload, fieldTypeMap))
    : allListings;

  triggerAvailabilityPreflight(payload, 'search');
  maybeRefineListingsWithAvailability(mountNode, fieldTypeMap, payload, {
    token: searchToken,
    startedAt: searchStartedAt,
    baseListings: filteredListings,
  });
}

function triggerAvailabilityPreflight(payload, reason = 'search') {
  if (!hasValidDateRangePayload(payload)) {
    return;
  }

  const preflightUrl = buildAvailabilityPreflightUrl();
  if (!preflightUrl) {
    return;
  }

  fetch(preflightUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      reason,
      check_in: payload?.checkIn ?? '',
      check_out: payload?.checkOut ?? '',
    }),
  }).catch((error) => {
    console.warn('[barefoot-engine] Availability preflight request failed.', error);
  });
}

function hasValidDateRangePayload(payload) {
  return isValidDateString(payload?.checkIn)
    && isValidDateString(payload?.checkOut)
    && payload.checkOut > payload.checkIn;
}

function buildAvailabilitySearchUrl() {
  const bootstrap = typeof window !== 'undefined' && isPlainObject(window.BarefootEnginePublic)
    ? window.BarefootEnginePublic
    : {};
  const restBase = typeof bootstrap.restBase === 'string' ? bootstrap.restBase : '';
  const endpoint = typeof bootstrap.availabilitySearchEndpoint === 'string'
    ? bootstrap.availabilitySearchEndpoint
    : 'availability/search';

  if (!restBase) {
    return '';
  }

  return new URL(endpoint, restBase).toString();
}

function buildAvailabilityPreflightUrl() {
  const bootstrap = typeof window !== 'undefined' && isPlainObject(window.BarefootEnginePublic)
    ? window.BarefootEnginePublic
    : {};
  const restBase = typeof bootstrap.restBase === 'string' ? bootstrap.restBase : '';
  const endpoint = typeof bootstrap.availabilityPreflightEndpoint === 'string'
    ? bootstrap.availabilityPreflightEndpoint
    : 'availability/preflight';

  if (!restBase) {
    return '';
  }

  return new URL(endpoint, restBase).toString();
}

function shouldRedirectListingsSearch(targetUrl) {
  const target = new URL(targetUrl || window.location.href, window.location.origin);

  return target.pathname !== window.location.pathname;
}

function syncSearchPayloadToCurrentUrl(payload) {
  const url = new URL(window.location.href);
  const params = new URLSearchParams(url.search);

  clearManagedParams(params);

  appendQueryValue(params, 'location', payload?.location);
  appendQueryValue(params, 'check_in', payload?.checkIn);
  appendQueryValue(params, 'check_out', payload?.checkOut);

  if (payload?.customFields && typeof payload.customFields === 'object') {
    Object.entries(payload.customFields).forEach(([key, value]) => {
      appendQueryValue(params, `field_${key}`, value);
    });
  }

  if (payload?.filters && typeof payload.filters === 'object') {
    Object.entries(payload.filters).forEach(([key, value]) => {
      appendQueryValue(params, `filter_${key}`, value);
    });
  }

  url.search = params.toString();
  window.history.replaceState({}, '', url.toString());
}

function ensureListingsMeta(mountNode) {
  if (!(mountNode instanceof HTMLElement)) {
    return null;
  }

  const toolbarLeft = mountNode.querySelector('.lm-toolbar .lm-toolbar-left');
  if (!(toolbarLeft instanceof HTMLElement)) {
    return null;
  }

  const existing = toolbarLeft.querySelector('.barefoot-engine-listings__meta');
  if (existing instanceof HTMLElement) {
    return existing;
  }

  const metaNode = document.createElement('div');
  metaNode.className = 'barefoot-engine-listings__meta';

  const countNode = document.createElement('p');
  countNode.className = 'barefoot-engine-listings__count';
  countNode.textContent = '';
  metaNode.appendChild(countNode);

  const searchingNode = document.createElement('p');
  searchingNode.className = 'barefoot-engine-listings__searching';
  searchingNode.hidden = true;
  searchingNode.innerHTML = '<span class="barefoot-engine-listings__searching-dot" aria-hidden="true"></span><span>Search in progress</span>';
  metaNode.appendChild(searchingNode);

  toolbarLeft.prepend(metaNode);

  return metaNode;
}

function ensureListingsSkeleton(mountNode) {
  if (!(mountNode instanceof HTMLElement)) {
    return null;
  }

  const gridNode = mountNode.querySelector('.lm-listings-grid');
  if (!(gridNode instanceof HTMLElement) || !(gridNode.parentElement instanceof HTMLElement)) {
    return null;
  }

  const existing = mountNode.querySelector('.barefoot-engine-listings__skeleton-grid');
  if (existing instanceof HTMLElement) {
    return existing;
  }

  const skeletonGrid = document.createElement('div');
  skeletonGrid.className = 'lm-listings-grid barefoot-engine-listings__skeleton-grid';

  for (let index = 0; index < 6; index += 1) {
    const card = document.createElement('div');
    card.className = 'barefoot-engine-listings__skeleton-card';
    card.innerHTML = `
      <div class="barefoot-engine-listings__skeleton-media"></div>
      <div class="barefoot-engine-listings__skeleton-body">
        <div class="barefoot-engine-listings__skeleton-line barefoot-engine-listings__skeleton-line--title"></div>
        <div class="barefoot-engine-listings__skeleton-line"></div>
        <div class="barefoot-engine-listings__skeleton-line barefoot-engine-listings__skeleton-line--short"></div>
        <div class="barefoot-engine-listings__skeleton-line barefoot-engine-listings__skeleton-line--price"></div>
      </div>
    `;
    skeletonGrid.appendChild(card);
  }

  gridNode.parentElement.insertBefore(skeletonGrid, gridNode);

  return skeletonGrid;
}

function ensureMapSearchingOverlay(mountNode) {
  if (!(mountNode instanceof HTMLElement)) {
    return null;
  }

  const mapPanel = mountNode.querySelector('.lm-map-panel');
  if (!(mapPanel instanceof HTMLElement)) {
    return null;
  }

  const existing = mapPanel.querySelector('.barefoot-engine-listings__map-searching');
  if (existing instanceof HTMLElement) {
    return existing;
  }

  const overlay = document.createElement('div');
  overlay.className = 'barefoot-engine-listings__map-searching';
  overlay.setAttribute('role', 'status');
  overlay.setAttribute('aria-live', 'polite');
  overlay.hidden = true;
  overlay.innerHTML = '<span class="barefoot-engine-listings__map-searching-dot" aria-hidden="true"></span><span>Updating map…</span>';
  mapPanel.appendChild(overlay);

  return overlay;
}

function applySearchPayloadToWidgetState(widget, payload) {
  if (!widget || widget.isDestroyed || !isPlainObject(payload)) {
    return;
  }

  widget.state.location = typeof payload.location === 'string' ? payload.location : '';
  widget.state.checkIn = isValidDateString(payload.checkIn) ? payload.checkIn : '';
  widget.state.checkOut = isValidDateString(payload.checkOut) ? payload.checkOut : '';

  if (typeof widget.buildFieldState === 'function') {
    widget.state.customFields = widget.buildFieldState(
      widget.options?.fields || [],
      isPlainObject(payload.customFields) ? payload.customFields : {},
    );
  } else {
    widget.state.customFields = isPlainObject(payload.customFields) ? payload.customFields : {};
  }

  if (typeof widget.buildFilterState === 'function') {
    widget.state.filters = widget.buildFilterState(
      widget.options?.filters || [],
      isPlainObject(payload.filters) ? payload.filters : {},
    );
  } else {
    widget.state.filters = isPlainObject(payload.filters) ? payload.filters : {};
  }

  if (typeof widget.render === 'function') {
    widget.render();
  }
}

function getEmbeddedSearchWidgetPayload(widget) {
  if (!widget || widget.isDestroyed) {
    return {
      location: '',
      checkIn: '',
      checkOut: '',
      customFields: {},
      filters: {},
    };
  }

  return {
    location: normalizeString(widget.state?.location ?? ''),
    checkIn: normalizeDateInput(widget.state?.checkIn ?? ''),
    checkOut: normalizeDateInput(widget.state?.checkOut ?? ''),
    customFields: normalizeComparableMap(widget.state?.customFields),
    filters: normalizeComparableMap(widget.state?.filters),
  };
}

function hasEmbeddedSearchWidgetSelections(widget) {
  const payload = getEmbeddedSearchWidgetPayload(widget);

  return hasSearchPayload(payload)
    || !isEmptyValue(payload.checkIn)
    || !isEmptyValue(payload.checkOut);
}

function hasEmbeddedSearchDraftValues(mountNode) {
  if (!(mountNode instanceof HTMLElement)) {
    return false;
  }

  const host = mountNode.querySelector('.barefoot-engine-listings__search-slot');
  if (!(host instanceof HTMLElement)) {
    return false;
  }

  const keywordInput = host.querySelector('.bp-search-widget__input');
  if (keywordInput instanceof HTMLInputElement && keywordInput.value.trim() !== '') {
    return true;
  }

  const dateInput = host.querySelector('.bp-calendar-datepicker-input');
  if (dateInput instanceof HTMLInputElement && dateInput.value.trim() !== '') {
    return true;
  }

  return false;
}

function ensureClearSearchButton(mountNode) {
  if (!(mountNode instanceof HTMLElement)) {
    return null;
  }

  const toolbarRight = mountNode.querySelector('.lm-toolbar .lm-toolbar-right');
  if (!(toolbarRight instanceof HTMLElement)) {
    return null;
  }

  const existing = toolbarRight.querySelector('.barefoot-engine-listings__clear-search');
  if (existing instanceof HTMLButtonElement) {
    return existing;
  }

  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'barefoot-engine-listings__clear-search';
  button.innerHTML = `
    <span class="barefoot-engine-listings__clear-search-icon" aria-hidden="true">
      <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="8" cy="8" r="6.25"></circle>
        <path d="M5.5 5.5L10.5 10.5"></path>
        <path d="M10.5 5.5L5.5 10.5"></path>
      </svg>
    </span>
    <span class="barefoot-engine-listings__clear-search-label">Clear</span>
  `;
  button.hidden = true;
  button.addEventListener('click', () => {
    clearEmbeddedSearchInputs(mountNode);
  });

  const sortWrapper = toolbarRight.querySelector('.lm-sort-wrapper');
  if (sortWrapper instanceof HTMLElement) {
    toolbarRight.insertBefore(button, sortWrapper);
  } else {
    toolbarRight.prepend(button);
  }

  return button;
}

function updateClearSearchButton(mountNode) {
  const button = ensureClearSearchButton(mountNode);
  if (!(button instanceof HTMLButtonElement)) {
    return;
  }

  const widgetHasSelections = hasEmbeddedSearchWidgetSelections(mountNode.beEmbeddedSearchWidget);
  const draftHasSelections = hasEmbeddedSearchDraftValues(mountNode);
  button.hidden = !(mountNode.beEmbeddedSearchWidget && (mountNode.beHasActiveSearch || widgetHasSelections || draftHasSelections));
}

function installListingsClearButtonSync(widget, mountNode, hostNode = null) {
  if (!widget || widget.isDestroyed || !(mountNode instanceof HTMLElement) || widget.beClearButtonSyncInstalled) {
    return;
  }

  const originalRender = typeof widget.render === 'function'
    ? widget.render.bind(widget)
    : null;

  if (!originalRender) {
    return;
  }

  widget.render = function patchedRender(...args) {
    const result = originalRender(...args);
    updateClearSearchButton(mountNode);
    return result;
  };

  const syncVisibility = () => {
    window.requestAnimationFrame(() => {
      updateClearSearchButton(mountNode);
    });
  };

  if (hostNode instanceof HTMLElement) {
    hostNode.addEventListener('input', syncVisibility);
    hostNode.addEventListener('change', syncVisibility);
    hostNode.addEventListener('click', syncVisibility);
  }

  widget.beClearButtonSyncInstalled = true;
}

function clearEmbeddedSearchInputs(mountNode) {
  if (!(mountNode instanceof HTMLElement)) {
    return;
  }

  const widget = mountNode.beEmbeddedSearchWidget;
  if (!widget || widget.isDestroyed) {
    return;
  }

  if (typeof widget.closeChoicePopover === 'function') {
    widget.closeChoicePopover();
  }

  if (typeof widget.closeFilterPanel === 'function') {
    widget.closeFilterPanel();
  }

  widget.state.location = '';
  widget.state.checkIn = '';
  widget.state.checkOut = '';

  if (typeof widget.buildFieldState === 'function') {
    widget.state.customFields = widget.buildFieldState(widget.options.fields || [], {});
  } else {
    widget.state.customFields = {};
  }

  if (typeof widget.buildFilterState === 'function') {
    widget.state.filters = widget.buildFilterState(widget.options.filters || [], {});
  } else {
    widget.state.filters = {};
  }

  if (typeof widget.render === 'function') {
    widget.render();
  }

  mountNode.beHasActiveSearch = false;
  updateClearSearchButton(mountNode);
}

function updateListingsCount(mountNode, count) {
  const metaNode = ensureListingsMeta(mountNode);
  if (!(metaNode instanceof HTMLElement)) {
    return;
  }

  const countNode = metaNode.querySelector('.barefoot-engine-listings__count');
  if (!(countNode instanceof HTMLElement)) {
    return;
  }

  const total = Number.isFinite(Number(count)) ? Number(count) : 0;
  countNode.textContent = `${total} ${total === 1 ? 'Property' : 'Properties'}`;
}

function setListingsSearching(mountNode, isSearching) {
  if (!(mountNode instanceof HTMLElement)) {
    return;
  }

  const clearButton = mountNode.querySelector('.barefoot-engine-listings__clear-search');
  if (clearButton instanceof HTMLButtonElement) {
    clearButton.disabled = isSearching;
  }

  mountNode.classList.toggle('be-listings-searching', isSearching);

  if (isSearching) {
    ensureListingsSkeleton(mountNode);
  }

  const mapOverlay = ensureMapSearchingOverlay(mountNode);
  if (mapOverlay instanceof HTMLElement) {
    mapOverlay.hidden = !isSearching;
  }

  const metaNode = ensureListingsMeta(mountNode);
  if (!(metaNode instanceof HTMLElement)) {
    return;
  }

  const searchingNode = metaNode.querySelector('.barefoot-engine-listings__searching');
  if (!(searchingNode instanceof HTMLElement)) {
    return;
  }

  const countNode = metaNode.querySelector('.barefoot-engine-listings__count');
  if (countNode instanceof HTMLElement) {
    countNode.hidden = isSearching;
  }

  searchingNode.hidden = !isSearching;
}

function alignToolbarControls(mountNode) {
  if (!(mountNode instanceof HTMLElement)) {
    return;
  }

  const toolbarLeft = mountNode.querySelector('.lm-toolbar .lm-toolbar-left');
  const toolbarRight = mountNode.querySelector('.lm-toolbar .lm-toolbar-right');
  const sortWrapper = mountNode.querySelector('.lm-toolbar .lm-sort-wrapper');
  const clearButton = mountNode.querySelector('.lm-toolbar .barefoot-engine-listings__clear-search');

  if (!(toolbarLeft instanceof HTMLElement) || !(toolbarRight instanceof HTMLElement) || !(sortWrapper instanceof HTMLElement)) {
    return;
  }

  if (clearButton instanceof HTMLButtonElement && clearButton.parentElement !== toolbarRight) {
    toolbarRight.prepend(clearButton);
  }

  if (sortWrapper.parentElement !== toolbarRight) {
    const currentClearButton = toolbarRight.querySelector('.barefoot-engine-listings__clear-search');
    if (currentClearButton instanceof HTMLElement) {
      toolbarRight.insertBefore(sortWrapper, currentClearButton.nextSibling);
    } else {
      toolbarRight.prepend(sortWrapper);
    }
  }

  const metaNode = toolbarLeft.querySelector('.barefoot-engine-listings__meta');
  if (metaNode instanceof HTMLElement) {
    toolbarLeft.prepend(metaNode);
  }
}

function bootPricingTables() {
  const mounts = document.querySelectorAll(PRICING_TABLE_SELECTOR);

  mounts.forEach((mountNode) => {
    if (!(mountNode instanceof HTMLElement) || mountNode.dataset.bePricingTableReady === 'true') {
      return;
    }

    const configId = mountNode.dataset.bePricingTableConfig;
    const config = readWidgetConfig(configId, 'Pricing table');
    if (!isPlainObject(config)) {
      return;
    }

    try {
      initializePricingTable(mountNode, config);
      mountNode.dataset.bePricingTableReady = 'true';
    } catch (error) {
      console.error('[barefoot-engine] Failed to initialize pricing table.', error);
    }
  });
}

function initializePricingTable(mountNode, config) {
  if (!(mountNode instanceof HTMLElement)) {
    return;
  }

  const title = sanitizeBookingText(config?.title, 'Rates');
  const showSearch = config?.showSearch !== false;
  const searchPlaceholder = sanitizeBookingText(config?.searchPlaceholder, 'Search rates...');
  const emptyText = sanitizeBookingText(config?.emptyText, 'No rates available for this property yet.');
  const rows = normalizePricingRows(config?.rows);
  const columns = normalizePricingColumns(config?.columns);
  const sortConfig = isPlainObject(config?.sort) ? config.sort : {};
  const initialSortKey = normalizePricingSortKey(sortConfig?.key);
  const initialSortDirection = sortConfig?.direction === 'desc' ? 'desc' : 'asc';

  mountNode.innerHTML = `
    <section class="barefoot-engine-pricing-table__panel">
      <header class="barefoot-engine-pricing-table__header">
        <h3 class="barefoot-engine-pricing-table__title">${escapeHtml(title)}</h3>
        <div class="barefoot-engine-pricing-table__search" ${showSearch ? '' : 'hidden'}>
          <label class="screen-reader-text" for="${escapeHtml(mountNode.id)}-search">${escapeHtml(searchPlaceholder)}</label>
          <input
            id="${escapeHtml(mountNode.id)}-search"
            class="barefoot-engine-pricing-table__search-input"
            type="search"
            placeholder="${escapeHtml(searchPlaceholder)}"
            autocomplete="off"
          />
        </div>
      </header>
      <div class="barefoot-engine-pricing-table__table-wrap">
        <table class="barefoot-engine-pricing-table__table">
          <thead>
            <tr>
              <th scope="col">
                <button type="button" class="barefoot-engine-pricing-table__sort" data-sort-key="date_start">
                  <span>${escapeHtml(columns.dateRange)}</span>
                  <span class="barefoot-engine-pricing-table__sort-indicator" aria-hidden="true"></span>
                </button>
              </th>
              <th scope="col">
                <button type="button" class="barefoot-engine-pricing-table__sort" data-sort-key="daily">
                  <span>${escapeHtml(columns.daily)}</span>
                  <span class="barefoot-engine-pricing-table__sort-indicator" aria-hidden="true"></span>
                </button>
              </th>
              <th scope="col">
                <button type="button" class="barefoot-engine-pricing-table__sort" data-sort-key="weekly">
                  <span>${escapeHtml(columns.weekly)}</span>
                  <span class="barefoot-engine-pricing-table__sort-indicator" aria-hidden="true"></span>
                </button>
              </th>
              <th scope="col">
                <button type="button" class="barefoot-engine-pricing-table__sort" data-sort-key="monthly">
                  <span>${escapeHtml(columns.monthly)}</span>
                  <span class="barefoot-engine-pricing-table__sort-indicator" aria-hidden="true"></span>
                </button>
              </th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
        <p class="barefoot-engine-pricing-table__empty" hidden>${escapeHtml(emptyText)}</p>
      </div>
    </section>
  `;

  const bodyNode = mountNode.querySelector('tbody');
  const emptyNode = mountNode.querySelector('.barefoot-engine-pricing-table__empty');
  const searchInput = mountNode.querySelector('.barefoot-engine-pricing-table__search-input');
  const sortButtons = mountNode.querySelectorAll('.barefoot-engine-pricing-table__sort');

  if (!(bodyNode instanceof HTMLTableSectionElement) || !(emptyNode instanceof HTMLElement)) {
    return;
  }

  const state = {
    rows,
    query: '',
    sortKey: initialSortKey,
    sortDirection: initialSortDirection,
    bodyNode,
    emptyNode,
    sortButtons,
  };

  const render = () => {
    const visibleRows = getVisiblePricingRows(state.rows, state.query);
    const sortedRows = sortPricingRows(visibleRows, state.sortKey, state.sortDirection);

    if (sortedRows.length === 0) {
      state.bodyNode.innerHTML = '';
      state.emptyNode.hidden = false;
    } else {
      state.emptyNode.hidden = true;
      state.bodyNode.innerHTML = sortedRows
        .map((row) => renderPricingTableRow(row))
        .join('');
    }

    state.sortButtons.forEach((buttonNode) => {
      if (!(buttonNode instanceof HTMLButtonElement)) {
        return;
      }

      const key = normalizePricingSortKey(buttonNode.dataset.sortKey);
      const indicator = buttonNode.querySelector('.barefoot-engine-pricing-table__sort-indicator');
      const isActive = key === state.sortKey;
      buttonNode.dataset.active = isActive ? 'true' : 'false';
      buttonNode.setAttribute('aria-pressed', isActive ? 'true' : 'false');

      if (indicator instanceof HTMLElement) {
        indicator.textContent = isActive
          ? (state.sortDirection === 'asc' ? '↑' : '↓')
          : '↕';
      }
    });
  };

  if (searchInput instanceof HTMLInputElement) {
    searchInput.addEventListener('input', () => {
      state.query = String(searchInput.value || '').trim().toLowerCase();
      render();
    });
  }

  sortButtons.forEach((buttonNode) => {
    if (!(buttonNode instanceof HTMLButtonElement)) {
      return;
    }

    buttonNode.addEventListener('click', () => {
      const key = normalizePricingSortKey(buttonNode.dataset.sortKey);
      if (key === state.sortKey) {
        state.sortDirection = state.sortDirection === 'asc' ? 'desc' : 'asc';
      } else {
        state.sortKey = key;
        state.sortDirection = key === 'date_start' ? 'asc' : 'desc';
      }

      render();
    });
  });

  render();
}

function normalizePricingColumns(columns) {
  const normalized = isPlainObject(columns) ? columns : {};
  return {
    dateRange: sanitizeBookingText(normalized.dateRange, 'Date Range'),
    daily: sanitizeBookingText(normalized.daily, 'Daily'),
    weekly: sanitizeBookingText(normalized.weekly, 'Weekly'),
    monthly: sanitizeBookingText(normalized.monthly, 'Monthly'),
  };
}

function normalizePricingRows(rows) {
  if (!Array.isArray(rows)) {
    return [];
  }

  return rows
    .filter((row) => isPlainObject(row))
    .map((row) => {
      const dateRange = sanitizeBookingText(row.date_range, '');
      const dailyDisplay = sanitizeBookingText(row.daily_display, 'N/A');
      const weeklyDisplay = sanitizeBookingText(row.weekly_display, 'N/A');
      const monthlyDisplay = sanitizeBookingText(row.monthly_display, 'N/A');
      const searchIndex = sanitizeBookingText(
        row.search_index,
        `${dateRange} ${dailyDisplay} ${weeklyDisplay} ${monthlyDisplay}`,
      ).toLowerCase();

      return {
        dateStart: sanitizeBookingText(row.date_start, ''),
        dateRange,
        daily: normalizePricingAmount(row.daily),
        weekly: normalizePricingAmount(row.weekly),
        monthly: normalizePricingAmount(row.monthly),
        dailyDisplay,
        weeklyDisplay,
        monthlyDisplay,
        searchIndex,
      };
    });
}

function normalizePricingAmount(value) {
  const numeric = Number(value);
  return Number.isFinite(numeric) ? numeric : null;
}

function normalizePricingSortKey(value) {
  const key = String(value || '').trim().toLowerCase();
  if (['date_start', 'daily', 'weekly', 'monthly'].includes(key)) {
    return key;
  }

  return 'date_start';
}

function getVisiblePricingRows(rows, query) {
  if (!query) {
    return rows.slice();
  }

  return rows.filter((row) => row.searchIndex.includes(query));
}

function sortPricingRows(rows, key, direction) {
  const multiplier = direction === 'desc' ? -1 : 1;
  const clone = rows.slice();

  clone.sort((left, right) => {
    if (key === 'date_start') {
      return multiplier * left.dateStart.localeCompare(right.dateStart);
    }

    const leftValue = Number.isFinite(left[key]) ? Number(left[key]) : null;
    const rightValue = Number.isFinite(right[key]) ? Number(right[key]) : null;

    if (leftValue === null && rightValue === null) {
      return 0;
    }

    if (leftValue === null) {
      return 1;
    }

    if (rightValue === null) {
      return -1;
    }

    if (leftValue === rightValue) {
      return 0;
    }

    return leftValue > rightValue ? multiplier : -multiplier;
  });

  return clone;
}

function renderPricingTableRow(row) {
  return `
    <tr>
      <td>${escapeHtml(row.dateRange)}</td>
      <td>${escapeHtml(row.dailyDisplay)}</td>
      <td>${escapeHtml(row.weeklyDisplay)}</td>
      <td>${escapeHtml(row.monthlyDisplay)}</td>
    </tr>
  `;
}

function bootBookingWidgets() {
  const mounts = document.querySelectorAll(BOOKING_WIDGET_SELECTOR);

  mounts.forEach((mountNode) => {
    if (!(mountNode instanceof HTMLElement) || mountNode.dataset.beBookingWidgetReady === 'true') {
      return;
    }

    const configId = mountNode.dataset.beBookingWidgetConfig;
    const config = readWidgetConfig(configId, 'Booking widget');
    if (!isPlainObject(config)) {
      return;
    }

    try {
      initializeBookingWidget(mountNode, config);
      mountNode.dataset.beBookingWidgetReady = 'true';
    } catch (error) {
      console.error('[barefoot-engine] Failed to initialize booking widget.', error);
    }
  });
}

function initializeBookingWidget(mountNode, config) {
  if (!(mountNode instanceof HTMLElement)) {
    return;
  }

  const runtime = buildBookingRuntime(config);
  mountNode.innerHTML = runtime.markup;

  const calendarHost = mountNode.querySelector('.barefoot-engine-booking-widget__calendar-host');
  const guestsSelect = mountNode.querySelector('.barefoot-engine-booking-widget__guests-select');
  const statusNode = mountNode.querySelector('.barefoot-engine-booking-widget__status');
  const summaryNode = mountNode.querySelector('.barefoot-engine-booking-widget__summary');
  const bookNowButton = mountNode.querySelector('[data-be-booking-action="book-now"]');
  const dailyValueNode = mountNode.querySelector('[data-be-booking-total="daily"]');
  const subtotalValueNode = mountNode.querySelector('[data-be-booking-total="subtotal"]');
  const taxValueNode = mountNode.querySelector('[data-be-booking-total="tax"]');
  const depositRowNode = mountNode.querySelector('[data-be-booking-row="deposit"]');
  const depositValueNode = mountNode.querySelector('[data-be-booking-total="deposit"]');
  const totalValueNode = mountNode.querySelector('[data-be-booking-total="grand"]');

  if (
    !(calendarHost instanceof HTMLElement)
    || !(guestsSelect instanceof HTMLSelectElement)
    || !(statusNode instanceof HTMLElement)
    || !(summaryNode instanceof HTMLElement)
    || !(bookNowButton instanceof HTMLButtonElement)
    || !(dailyValueNode instanceof HTMLElement)
    || !(subtotalValueNode instanceof HTMLElement)
    || !(taxValueNode instanceof HTMLElement)
    || !(depositRowNode instanceof HTMLElement)
    || !(depositValueNode instanceof HTMLElement)
    || !(totalValueNode instanceof HTMLElement)
  ) {
    return;
  }

  const initialGuestValue = resolveInitialGuestValue(runtime.guestOptions, runtime.defaultGuestValue);
  guestsSelect.innerHTML = runtime.guestOptions
    .map((optionValue) => {
      const escapedValue = escapeHtml(optionValue);
      const selectedAttribute = optionValue === initialGuestValue ? ' selected' : '';
      return `<option value="${escapedValue}"${selectedAttribute}>${escapedValue}</option>`;
    })
    .join('');

  const state = {
    propertyId: runtime.propertyId,
    redirectUrl: runtime.redirectUrl,
    currency: runtime.currency,
    reztypeid: runtime.reztypeid,
    labels: runtime.labels,
    mountNode,
    calendar: null,
    statusNode,
    summaryNode,
    bookNowButton,
    dailyValueNode,
    subtotalValueNode,
    taxValueNode,
    depositRowNode,
    depositValueNode,
    totalValueNode,
    guestsSelect,
    disabledDates: new Set(),
    dailyPrices: new Map(),
    loadedCalendarRangeKeys: new Set(),
    calendarFetchInFlightKey: '',
    checkIn: '',
    checkOut: '',
    quoteTimer: null,
    quoteRequestToken: 0,
    bookNowRequestInFlight: false,
  };

  mountNode.beBookingWidgetState = state;
  mountNode.classList.toggle('be-booking-missing-context', runtime.missingContext);
  state.bookNowButton.disabled = true;

  if (runtime.missingContext) {
    guestsSelect.disabled = true;
    state.bookNowButton.disabled = true;
    setBookingStatus(state, 'missingContext', 'error');
    renderBookingTotals(state, null);
    return;
  }

  const calendar = new BPCalendar(calendarHost, {
    mode: 'datepicker',
    monthsToShow: runtime.calendarOptions.monthsToShow,
    datepickerPlacement: runtime.calendarOptions.datepickerPlacement,
    defaultMinDays: runtime.calendarOptions.defaultMinDays,
    tooltipLabel: runtime.calendarOptions.tooltipLabel,
    showTooltip: runtime.calendarOptions.showTooltip,
    showClearButton: runtime.calendarOptions.showClearButton,
    dateConfig: {},
    onRangeSelect: (range) => {
      handleBookingRangeSelect(state, range);
    },
  });

  state.calendar = calendar;
  patchCalendarPopupRender(state);

  guestsSelect.addEventListener('change', () => {
    state.bookNowButton.disabled = true;
    if (!state.checkIn || !state.checkOut) {
      setBookingStatus(state, 'idle', 'idle');
      return;
    }

    scheduleBookingQuoteCheck(state);
  });

  setBookingStatus(state, 'idle', 'idle');
  renderBookingTotals(state, null);
  refreshBookingCalendarAvailability(state, true);

  bookNowButton.addEventListener('click', (event) => {
    event.preventDefault();
    startBookingSessionAndNavigate(state);
  });
}

function buildBookingRuntime(config) {
  const propertyId = sanitizeBookingPropertyId(config?.propertyId);
  const missingContext = !propertyId || Boolean(config?.missingContext);
  const currency = sanitizeBookingCurrency(config?.currency);
  const redirectUrl = sanitizeBookingRedirectUrl(config?.redirectUrl);
  const reztypeid = sanitizeBookingReztypeid(config?.reztypeid, 26);
  const calendarOptions = sanitizeBookingCalendarOptions(config?.calendarOptions);
  const labels = buildBookingLabels(config?.labels);
  const guestsLabel = sanitizeBookingText(config?.guests?.label, 'Guests');
  const guestsPlaceholder = sanitizeBookingText(config?.guests?.placeholder, '');
  const guestOptions = sanitizeBookingGuestOptions(config?.guests?.options);
  const defaultGuestValue = sanitizeBookingText(config?.guests?.defaultValue, guestOptions[0]);
  const title = labels.title;
  const datesLabel = labels.dates;

  return {
    propertyId,
    missingContext,
    currency,
    redirectUrl,
    reztypeid,
    calendarOptions,
    labels,
    guestOptions,
    defaultGuestValue,
    markup: `
      <section class="barefoot-engine-booking-widget__panel">
        <header class="barefoot-engine-booking-widget__header">
          <h3 class="barefoot-engine-booking-widget__title">${escapeHtml(title)}</h3>
        </header>
        <div class="barefoot-engine-booking-widget__controls">
          <div class="barefoot-engine-booking-widget__field barefoot-engine-booking-widget__field--dates">
            <label class="barefoot-engine-booking-widget__label">${escapeHtml(datesLabel)}</label>
            <div class="barefoot-engine-booking-widget__calendar-host"></div>
          </div>
          <div class="barefoot-engine-booking-widget__field barefoot-engine-booking-widget__field--guests">
            <label class="barefoot-engine-booking-widget__label">${escapeHtml(guestsLabel)}</label>
            <select class="barefoot-engine-booking-widget__guests-select" aria-label="${escapeHtml(guestsLabel)}" ${guestsPlaceholder ? `title="${escapeHtml(guestsPlaceholder)}"` : ''}></select>
          </div>
        </div>
        <p class="barefoot-engine-booking-widget__status" role="status" aria-live="polite"></p>
        <div class="barefoot-engine-booking-widget__summary" hidden>
          <div class="barefoot-engine-booking-widget__summary-row">
            <span>${escapeHtml(labels.daily)}</span>
            <strong data-be-booking-total="daily"></strong>
          </div>
          <div class="barefoot-engine-booking-widget__summary-row">
            <span>${escapeHtml(labels.subtotal)}</span>
            <strong data-be-booking-total="subtotal"></strong>
          </div>
          <div class="barefoot-engine-booking-widget__summary-row">
            <span>${escapeHtml(labels.tax)}</span>
            <strong data-be-booking-total="tax"></strong>
          </div>
          <div class="barefoot-engine-booking-widget__summary-row" data-be-booking-row="deposit" hidden>
            <span>${escapeHtml(labels.depositAmount)}</span>
            <strong data-be-booking-total="deposit"></strong>
          </div>
          <div class="barefoot-engine-booking-widget__summary-row barefoot-engine-booking-widget__summary-row--total">
            <span>${escapeHtml(labels.total)}</span>
            <strong data-be-booking-total="grand"></strong>
          </div>
        </div>
        <button type="button" class="barefoot-engine-booking-widget__book-now" data-be-booking-action="book-now" disabled>${escapeHtml(labels.bookNow)}</button>
      </section>
    `,
  };
}

function patchCalendarPopupRender(state) {
  if (!state || !state.calendar || typeof state.calendar.renderCalendarInPopup !== 'function') {
    return;
  }

  const originalRenderCalendarInPopup = state.calendar.renderCalendarInPopup.bind(state.calendar);
  state.calendar.renderCalendarInPopup = (...args) => {
    const result = originalRenderCalendarInPopup(...args);
    refreshBookingCalendarAvailability(state, false);
    return result;
  };
}

function handleBookingRangeSelect(state, range) {
  if (!state || !state.calendar) {
    return;
  }

  const start = range?.start instanceof Date ? formatDateToYmd(range.start) : '';
  const end = range?.end instanceof Date ? formatDateToYmd(range.end) : '';
  state.checkIn = start;
  state.checkOut = end;

  if (!start || !end) {
    state.bookNowButton.disabled = true;
    renderBookingTotals(state, null);
    setBookingStatus(state, 'idle', 'idle');
    return;
  }

  state.bookNowButton.disabled = true;
  scheduleBookingQuoteCheck(state);
}

function scheduleBookingQuoteCheck(state) {
  if (!state || !state.propertyId || !state.checkIn || !state.checkOut) {
    return;
  }

  if (state.quoteTimer) {
    window.clearTimeout(state.quoteTimer);
  }

  state.quoteTimer = window.setTimeout(() => {
    runBookingQuoteCheck(state);
  }, BOOKING_QUOTE_DEBOUNCE_MS);
}

function runBookingQuoteCheck(state) {
  if (!state || !state.propertyId || !state.checkIn || !state.checkOut) {
    return;
  }

  const quoteUrl = buildBookingQuoteUrl();
  if (!quoteUrl) {
    setBookingStatus(state, 'error', 'error');
    renderBookingTotals(state, null);
    return;
  }

  const requestToken = Number(state.quoteRequestToken || 0) + 1;
  state.quoteRequestToken = requestToken;
  setBookingStatus(state, 'checking', 'loading');
  state.bookNowButton.disabled = true;
  renderBookingTotals(state, null);

  fetch(quoteUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      property_id: state.propertyId,
      check_in: state.checkIn,
      check_out: state.checkOut,
      guests: normalizeGuestCount(state.guestsSelect?.value),
      reztypeid: state.reztypeid,
    }),
  })
    .then(async (response) => {
      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        const message = typeof data?.message === 'string' && data.message.trim() !== ''
          ? data.message
          : state.labels.error;
        throw new Error(message);
      }

      return data;
    })
    .then((response) => {
      if (Number(state.quoteRequestToken) !== requestToken) {
        return;
      }

      const payload = isPlainObject(response?.data) ? response.data : {};
      if (payload.available !== true) {
        state.bookNowButton.disabled = true;
        setBookingStatus(state, 'unavailable', 'error');
        renderBookingTotals(state, null);
        return;
      }

      setBookingStatus(state, 'available', 'success');
      renderBookingTotals(state, payload);
      state.bookNowButton.disabled = !canNavigateBookingNow(state);
    })
    .catch((error) => {
      if (Number(state.quoteRequestToken) !== requestToken) {
        return;
      }

      console.warn('[barefoot-engine] Booking quote check failed.', error);
      state.bookNowButton.disabled = true;
      setBookingStatus(state, 'error', 'error');
      renderBookingTotals(state, null);
    });
}

function refreshBookingCalendarAvailability(state, forceFetch = false) {
  if (!state || !state.calendar || !state.propertyId) {
    return;
  }

  const calendarUrl = buildBookingCalendarUrl();
  if (!calendarUrl) {
    return;
  }

  const range = resolveBookingCalendarRange(state.calendar);
  if (!range) {
    return;
  }

  const rangeKey = `${range.monthStart}:${range.monthEnd}`;
  if (!forceFetch && state.loadedCalendarRangeKeys.has(rangeKey)) {
    return;
  }

  if (state.calendarFetchInFlightKey === rangeKey) {
    return;
  }

  state.calendarFetchInFlightKey = rangeKey;

  fetch(calendarUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      property_id: state.propertyId,
      month_start: range.monthStart,
      month_end: range.monthEnd,
    }),
  })
    .then(async (response) => {
      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        const message = typeof data?.message === 'string' && data.message.trim() !== ''
          ? data.message
          : 'Calendar availability request failed.';
        throw new Error(message);
      }

      return data;
    })
    .then((response) => {
      const payload = isPlainObject(response?.data) ? response.data : {};
      const disabledDates = Array.isArray(payload.disabled_dates) ? payload.disabled_dates : [];
      const dailyPrices = isPlainObject(payload.daily_prices) ? payload.daily_prices : {};
      applyDisabledDatesForRange(state, range.monthStart, range.monthEnd, disabledDates, dailyPrices);
      state.loadedCalendarRangeKeys.add(rangeKey);
    })
    .catch((error) => {
      console.warn('[barefoot-engine] Booking calendar availability request failed.', error);
    })
    .finally(() => {
      if (state.calendarFetchInFlightKey === rangeKey) {
        state.calendarFetchInFlightKey = '';
      }

    });
}

function applyDisabledDatesForRange(state, monthStart, monthEnd, disabledDates, dailyPrices = {}) {
  if (!state || !state.calendar || !isValidDateString(monthStart) || !isValidDateString(monthEnd)) {
    return;
  }

  removeDateRangeFromDisabledSet(state.disabledDates, monthStart, monthEnd);
  removeDateRangeFromPriceMap(state.dailyPrices, monthStart, monthEnd);

  disabledDates.forEach((value) => {
    const normalized = normalizeDateInput(value);
    if (!isValidDateString(normalized)) {
      return;
    }

    if (normalized < monthStart || normalized > monthEnd) {
      return;
    }

    state.disabledDates.add(normalized);
  });

  Object.entries(dailyPrices).forEach(([dateKey, amountValue]) => {
    const normalized = normalizeDateInput(dateKey);
    if (!isValidDateString(normalized)) {
      return;
    }

    if (normalized < monthStart || normalized > monthEnd) {
      return;
    }

    const amount = Number(amountValue);
    if (!Number.isFinite(amount) || amount <= 0) {
      return;
    }

    state.dailyPrices.set(normalized, amount);
  });

  const dateConfig = {};
  state.disabledDates.forEach((disabledDate) => {
    dateConfig[disabledDate] = {
      date: disabledDate,
      isDisabled: true,
    };
  });
  state.dailyPrices.forEach((amount, pricedDate) => {
    const priceText = formatBookingCalendarPrice(amount, state.currency);
    const currentConfig = isPlainObject(dateConfig[pricedDate]) ? dateConfig[pricedDate] : { date: pricedDate };

    dateConfig[pricedDate] = {
      ...currentConfig,
      date: pricedDate,
      price: priceText,
    };
  });

  applyBookingCalendarDateConfig(state, dateConfig);
}

function applyBookingCalendarDateConfig(state, dateConfig) {
  if (!state || !state.calendar || !isPlainObject(dateConfig)) {
    return;
  }

  const calendar = state.calendar;
  const isDatepickerMode = calendar.options?.mode === 'datepicker';

  if (!isDatepickerMode || typeof calendar.renderCalendarInPopup !== 'function') {
    if (typeof calendar.updateOptions === 'function') {
      calendar.updateOptions({
        dateConfig,
      });
    }
    return;
  }

  const popupWasOpen = Boolean(
    calendar.popupWrapper instanceof HTMLElement
    && calendar.popupWrapper.style.display !== 'none',
  );

  calendar.options = {
    ...calendar.options,
    dateConfig,
  };

  calendar.renderCalendarInPopup();

  if (typeof calendar.attachDatepickerEventListeners === 'function') {
    calendar.attachDatepickerEventListeners();
  }

  if (popupWasOpen && typeof calendar.positionPopup === 'function') {
    calendar.positionPopup();
  }
}

function removeDateRangeFromDisabledSet(disabledDates, startDate, endDate) {
  if (!(disabledDates instanceof Set)) {
    return;
  }

  let cursor = parseYmdDate(startDate);
  const end = parseYmdDate(endDate);
  if (!(cursor instanceof Date) || !(end instanceof Date)) {
    return;
  }

  while (cursor.getTime() <= end.getTime()) {
    disabledDates.delete(formatDateToYmd(cursor));
    cursor = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + 1);
  }
}

function removeDateRangeFromPriceMap(priceMap, startDate, endDate) {
  if (!(priceMap instanceof Map)) {
    return;
  }

  let cursor = parseYmdDate(startDate);
  const end = parseYmdDate(endDate);
  if (!(cursor instanceof Date) || !(end instanceof Date)) {
    return;
  }

  while (cursor.getTime() <= end.getTime()) {
    priceMap.delete(formatDateToYmd(cursor));
    cursor = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + 1);
  }
}

function resolveBookingCalendarRange(calendar) {
  if (!calendar) {
    return null;
  }

  const currentDate = calendar.currentDate instanceof Date
    ? new Date(calendar.currentDate)
    : new Date();
  if (!(currentDate instanceof Date) || Number.isNaN(currentDate.getTime())) {
    return null;
  }

  const monthsToShow = (() => {
    if (typeof calendar.getPopupMonthsToShow === 'function') {
      const value = Number(calendar.getPopupMonthsToShow());
      if (Number.isFinite(value) && value >= 1) {
        return Math.max(1, Math.min(6, Math.round(value)));
      }
    }

    const optionValue = Number(calendar.options?.monthsToShow ?? 1);
    if (!Number.isFinite(optionValue)) {
      return 1;
    }

    return Math.max(1, Math.min(6, Math.round(optionValue)));
  })();

  const monthStartDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
  const monthEndDate = new Date(currentDate.getFullYear(), currentDate.getMonth() + monthsToShow, 0);

  return {
    monthStart: formatDateToYmd(monthStartDate),
    monthEnd: formatDateToYmd(monthEndDate),
  };
}

function setBookingStatus(state, labelKey, type, customMessage = '') {
  if (!state || !(state.statusNode instanceof HTMLElement)) {
    return;
  }

  const message = customMessage || state.labels?.[labelKey] || '';
  state.statusNode.textContent = message;
  state.statusNode.dataset.state = type || 'idle';

  if (state.calendar && typeof state.calendar.setAvailabilityMessage === 'function') {
    if (!message || type === 'idle') {
      state.calendar.setAvailabilityMessage('', null);
      return;
    }

    if (type === 'loading') {
      state.calendar.setAvailabilityMessage(message, 'loading');
      return;
    }

    if (type === 'success') {
      state.calendar.setAvailabilityMessage(message, 'success');
      return;
    }

    state.calendar.setAvailabilityMessage(message, 'error');
  }
}

function renderBookingTotals(state, totals) {
  if (
    !state
    || !(state.summaryNode instanceof HTMLElement)
    || !(state.bookNowButton instanceof HTMLButtonElement)
    || !(state.dailyValueNode instanceof HTMLElement)
    || !(state.subtotalValueNode instanceof HTMLElement)
    || !(state.taxValueNode instanceof HTMLElement)
    || !(state.depositRowNode instanceof HTMLElement)
    || !(state.depositValueNode instanceof HTMLElement)
    || !(state.totalValueNode instanceof HTMLElement)
  ) {
    return;
  }

  const payload = isPlainObject(totals) ? totals : null;
  const totalsData = isPlainObject(payload?.totals) ? payload.totals : payload;

  if (!isPlainObject(totalsData)) {
    state.summaryNode.hidden = true;
    state.bookNowButton.disabled = true;
    state.dailyValueNode.textContent = '';
    state.subtotalValueNode.textContent = '';
    state.taxValueNode.textContent = '';
    state.depositRowNode.hidden = true;
    state.depositValueNode.textContent = '';
    state.totalValueNode.textContent = '';
    return;
  }

  const dailyPrice = Number(totalsData.daily_price);
  const subtotal = Number(totalsData.subtotal);
  const taxTotal = Number(totalsData.tax_total);
  const grandTotal = Number(totalsData.grand_total);
  const depositAmount = Number(payload?.depositAmount ?? payload?.deposit_amount);

  if (
    !Number.isFinite(dailyPrice)
    || !Number.isFinite(subtotal)
    || !Number.isFinite(taxTotal)
    || !Number.isFinite(grandTotal)
  ) {
    state.summaryNode.hidden = true;
    state.bookNowButton.disabled = true;
    state.dailyValueNode.textContent = '';
    state.subtotalValueNode.textContent = '';
    state.taxValueNode.textContent = '';
    state.depositRowNode.hidden = true;
    state.depositValueNode.textContent = '';
    state.totalValueNode.textContent = '';
    return;
  }

  state.summaryNode.hidden = false;
  state.dailyValueNode.textContent = formatBookingCurrency(dailyPrice, state.currency);
  state.subtotalValueNode.textContent = formatBookingCurrency(subtotal, state.currency);
  state.taxValueNode.textContent = formatBookingCurrency(taxTotal, state.currency);
  if (Number.isFinite(depositAmount) && depositAmount > 0) {
    state.depositRowNode.hidden = false;
    state.depositValueNode.textContent = formatBookingCurrency(depositAmount, state.currency);
  } else {
    state.depositRowNode.hidden = true;
    state.depositValueNode.textContent = '';
  }
  state.totalValueNode.textContent = formatBookingCurrency(grandTotal, state.currency);
  state.bookNowButton.disabled = !canNavigateBookingNow(state);
}

function formatBookingCurrency(value, currency) {
  const normalizedCurrency = sanitizeBookingCurrency(currency);
  const formattedValue = Number(value).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  return `${normalizedCurrency}${formattedValue}`;
}

function formatBookingCalendarPrice(value, currency) {
  const normalizedCurrency = sanitizeBookingCurrency(currency);
  const roundedValue = Number.isFinite(Number(value)) ? Math.round(Number(value)) : 0;
  const formattedValue = roundedValue.toLocaleString(undefined, {
    maximumFractionDigits: 0,
  });

  return `${normalizedCurrency}${formattedValue}`;
}

function sanitizeBookingPropertyId(value) {
  return typeof value === 'string' || typeof value === 'number'
    ? String(value).trim()
    : '';
}

function sanitizeBookingCurrency(value) {
  const normalized = typeof value === 'string' ? value.trim() : '';
  return normalized || '$';
}

function sanitizeBookingRedirectUrl(value) {
  const normalized = typeof value === 'string' ? value.trim() : '';
  return normalized || '/booking-confirmation';
}

function sanitizeBookingReztypeid(value, fallback = 26) {
  const normalized = Number(value);
  return Number.isFinite(normalized) && normalized > 0 ? Math.floor(normalized) : fallback;
}

function sanitizeBookingText(value, fallback = '') {
  if (typeof value !== 'string') {
    return fallback;
  }

  const normalized = value.trim();
  return normalized || fallback;
}

function sanitizeBookingGuestOptions(value) {
  const options = Array.isArray(value) ? value : ['1', '2', '3', '4', '5', '6', '7', '8+'];
  const normalized = options
    .map((optionValue) => String(optionValue ?? '').trim())
    .filter(Boolean);

  return normalized.length > 0 ? Array.from(new Set(normalized)) : ['1', '2', '3', '4', '5', '6', '7', '8+'];
}

function resolveInitialGuestValue(options, preferred) {
  const normalizedPreferred = String(preferred ?? '').trim();
  if (normalizedPreferred && options.includes(normalizedPreferred)) {
    return normalizedPreferred;
  }

  return options[0] || '1';
}

function sanitizeBookingCalendarOptions(calendarOptions) {
  const options = isPlainObject(calendarOptions) ? calendarOptions : {};
  const monthsToShow = Number(options.monthsToShow);
  const defaultMinDays = Number(options.defaultMinDays);

  return {
    monthsToShow: Number.isFinite(monthsToShow) ? Math.max(1, Math.min(6, Math.round(monthsToShow))) : 2,
    datepickerPlacement: options.datepickerPlacement === 'default' ? 'default' : 'auto',
    defaultMinDays: Number.isFinite(defaultMinDays) ? Math.max(1, Math.round(defaultMinDays)) : 1,
    tooltipLabel: sanitizeBookingText(options.tooltipLabel, 'Nights'),
    showTooltip: options.showTooltip !== false,
    showClearButton: options.showClearButton !== false,
  };
}

function buildBookingLabels(inputLabels) {
  const labels = isPlainObject(inputLabels) ? inputLabels : {};
  return {
    title: sanitizeBookingText(labels.title, 'Book This Property'),
    dates: sanitizeBookingText(labels.dates, 'Dates'),
    checking: sanitizeBookingText(labels.checking, 'Checking live availability...'),
    available: sanitizeBookingText(labels.available, 'Selected dates are available.'),
    unavailable: sanitizeBookingText(labels.unavailable, 'Selected dates are unavailable.'),
    error: sanitizeBookingText(labels.error, 'Live availability check failed. Please try again.'),
    idle: sanitizeBookingText(labels.idle, 'Select dates and guests to check availability.'),
    daily: sanitizeBookingText(labels.daily, 'Rent'),
    subtotal: sanitizeBookingText(labels.subtotal, 'Subtotal'),
    tax: sanitizeBookingText(labels.tax, 'Tax'),
    depositAmount: sanitizeBookingText(labels.depositAmount, 'Deposit Amount'),
    total: sanitizeBookingText(labels.total, 'Total'),
    bookNow: sanitizeBookingText(labels.bookNow, 'BOOK NOW'),
    missingContext: sanitizeBookingText(labels.missingContext, 'Property context is required to load the booking widget.'),
  };
}

function normalizeGuestCount(value) {
  const rawValue = String(value ?? '').trim();
  const match = rawValue.match(/\d+/);
  if (!match) {
    return 1;
  }

  const numeric = Number(match[0]);
  if (!Number.isFinite(numeric) || numeric <= 0) {
    return 1;
  }

  return Math.min(99, Math.round(numeric));
}

function parseYmdDate(value) {
  if (!isValidDateString(value)) {
    return null;
  }

  const [year, month, day] = value.split('-').map((part) => Number(part));
  const date = new Date(year, month - 1, day);
  if (
    !Number.isFinite(date.getTime())
    || date.getFullYear() !== year
    || date.getMonth() !== month - 1
    || date.getDate() !== day
  ) {
    return null;
  }

  return date;
}

function formatDateToYmd(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function buildBookingCalendarUrl() {
  const bootstrap = typeof window !== 'undefined' && isPlainObject(window.BarefootEnginePublic)
    ? window.BarefootEnginePublic
    : {};
  const restBase = typeof bootstrap.restBase === 'string' ? bootstrap.restBase : '';
  const endpoint = typeof bootstrap.bookingCalendarEndpoint === 'string'
    ? bootstrap.bookingCalendarEndpoint
    : 'booking/calendar';

  if (!restBase) {
    return '';
  }

  return new URL(endpoint, restBase).toString();
}

function buildBookingQuoteUrl() {
  const bootstrap = typeof window !== 'undefined' && isPlainObject(window.BarefootEnginePublic)
    ? window.BarefootEnginePublic
    : {};
  const restBase = typeof bootstrap.restBase === 'string' ? bootstrap.restBase : '';
  const endpoint = typeof bootstrap.bookingQuoteEndpoint === 'string'
    ? bootstrap.bookingQuoteEndpoint
    : 'booking/quote';

  if (!restBase) {
    return '';
  }

  return new URL(endpoint, restBase).toString();
}

function buildCheckoutStartUrl() {
  const bootstrap = typeof window !== 'undefined' && isPlainObject(window.BarefootEnginePublic)
    ? window.BarefootEnginePublic
    : {};
  const restBase = typeof bootstrap.restBase === 'string' ? bootstrap.restBase : '';
  const endpoint = typeof bootstrap.bookingCheckoutStartEndpoint === 'string'
    ? bootstrap.bookingCheckoutStartEndpoint
    : 'booking-checkout/start';

  if (!restBase) {
    return '';
  }

  return new URL(endpoint, restBase).toString();
}

function canNavigateBookingNow(state) {
  if (!state) {
    return false;
  }

  return Boolean(
    sanitizeBookingPropertyId(state.propertyId)
    && isValidDateString(state.checkIn)
    && isValidDateString(state.checkOut)
  );
}

async function startBookingSessionAndNavigate(state) {
  if (!canNavigateBookingNow(state)) {
    return;
  }

  if (state.bookNowRequestInFlight === true) {
    return;
  }

  const startUrl = buildCheckoutStartUrl();
  const fallbackRedirectUrl = buildBookingRedirectUrl(state);
  if (!fallbackRedirectUrl) {
    return;
  }

  if (!startUrl) {
    writeBookingSessionCookie('');
    window.location.assign(fallbackRedirectUrl);
    return;
  }

  state.bookNowRequestInFlight = true;
  state.bookNowButton.disabled = true;
  setBookingStatus(state, 'checking', 'loading', 'Preparing checkout...');

  try {
    const response = await fetch(startUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        property_id: sanitizeBookingPropertyId(state.propertyId),
        check_in: state.checkIn,
        check_out: state.checkOut,
        guests: normalizeGuestCount(state.guestsSelect?.value),
        reztypeid: sanitizeBookingReztypeid(state.reztypeid, 26),
        redirect_url: sanitizeBookingRedirectUrl(state.redirectUrl),
        existing_session_token: readBookingSessionCookie(),
      }),
    });

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      const message = typeof data?.message === 'string' && data.message.trim() !== ''
        ? data.message
        : state.labels.error;
      throw new Error(message);
    }

    const payload = isPlainObject(data?.data) ? data.data : {};
    const sessionToken = sanitizeBookingText(payload.sessionToken, '');
    if (!sessionToken) {
      throw new Error('Checkout session could not be created.');
    }

    writeBookingSessionCookie(sessionToken);
    const redirectTarget = sanitizeBookingText(payload.redirectUrl, fallbackRedirectUrl);
    window.location.assign(redirectTarget || fallbackRedirectUrl);
  } catch (error) {
    console.warn('[barefoot-engine] Booking start session failed.', error);
    state.bookNowRequestInFlight = false;
    state.bookNowButton.disabled = !canNavigateBookingNow(state);
    setBookingStatus(
      state,
      'error',
      'error',
      error instanceof Error && error.message ? error.message : state.labels.error,
    );
  }
}

function buildBookingRedirectUrl(state) {
  if (!state || !canNavigateBookingNow(state)) {
    return '';
  }

  let url;
  try {
    url = new URL(sanitizeBookingRedirectUrl(state.redirectUrl), window.location.origin);
  } catch (error) {
    console.warn('[barefoot-engine] Invalid booking redirect URL.', error);
    return '';
  }

  const params = new URLSearchParams(url.search);
  params.set('property_id', sanitizeBookingPropertyId(state.propertyId));
  params.set('check_in', state.checkIn);
  params.set('check_out', state.checkOut);
  params.set('guests', String(normalizeGuestCount(state.guestsSelect?.value)));
  params.set('reztypeid', String(sanitizeBookingReztypeid(state.reztypeid, 26)));
  url.search = params.toString();

  return url.toString();
}

function readBookingSessionCookie() {
  if (typeof document === 'undefined') {
    return '';
  }

  const encodedName = `${encodeURIComponent(BOOKING_SESSION_COOKIE)}=`;
  const chunks = document.cookie ? document.cookie.split(';') : [];

  for (const chunk of chunks) {
    const normalized = chunk.trim();
    if (!normalized.startsWith(encodedName)) {
      continue;
    }

    const value = normalized.slice(encodedName.length);
    return sanitizeBookingText(decodeURIComponent(value), '');
  }

  return '';
}

function writeBookingSessionCookie(sessionToken) {
  if (typeof document === 'undefined') {
    return;
  }

  const normalizedToken = sanitizeBookingText(sessionToken, '');
  if (!normalizedToken) {
    document.cookie = `${encodeURIComponent(BOOKING_SESSION_COOKIE)}=; path=/; Max-Age=0; SameSite=Lax`;
    return;
  }

  document.cookie = `${encodeURIComponent(BOOKING_SESSION_COOKIE)}=${encodeURIComponent(normalizedToken)}; path=/; SameSite=Lax`;
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

let elementorFeaturedHooksBound = false;
let elementorFeaturedObserver = null;

function registerElementorFeaturedPreviewBoot() {
  if (typeof window === 'undefined') {
    return;
  }

  const ensureObserver = () => {
    if (elementorFeaturedObserver || typeof window.MutationObserver !== 'function' || !(document.body instanceof HTMLElement)) {
      return;
    }

    let rafId = 0;
    const queueBoot = () => {
      if (rafId) {
        return;
      }

      rafId = window.requestAnimationFrame(() => {
        rafId = 0;
        bootFeaturedProperties();
      });
    };

    elementorFeaturedObserver = new window.MutationObserver((mutations) => {
      const shouldBoot = mutations.some((mutation) => {
        if (!mutation || mutation.type !== 'childList' || mutation.addedNodes.length === 0) {
          return false;
        }

        return Array.from(mutation.addedNodes).some((node) => {
          if (!(node instanceof HTMLElement)) {
            return false;
          }

          return node.matches?.('[data-be-featured-properties]') || Boolean(node.querySelector?.('[data-be-featured-properties]'));
        });
      });

      if (shouldBoot) {
        queueBoot();
      }
    });

    elementorFeaturedObserver.observe(document.body, {
      childList: true,
      subtree: true,
    });

    // Run once after wiring the observer so mounts already on the page get initialized.
    bootFeaturedProperties();
  };

  const bindHooks = () => {
    if (elementorFeaturedHooksBound) {
      return;
    }

    const frontend = window.elementorFrontend;
    if (!frontend || !frontend.hooks || typeof frontend.hooks.addAction !== 'function') {
      ensureObserver();
      return;
    }

    const reinitializeFeaturedProperties = () => {
      bootFeaturedProperties();
    };

    frontend.hooks.addAction('frontend/element_ready/barefoot-featured-properties.default', reinitializeFeaturedProperties);
    frontend.hooks.addAction('frontend/element_ready/global', reinitializeFeaturedProperties);
    ensureObserver();
    elementorFeaturedHooksBound = true;
  };

  const isElementorPreviewFrame = (() => {
    try {
      return Boolean(window.frameElement && window.frameElement.id === 'elementor-preview-iframe');
    } catch (error) {
      return false;
    }
  })();

  if (window.jQuery && typeof window.jQuery === 'function') {
    window.jQuery(window).on('elementor/frontend/init', bindHooks);
  } else {
    window.addEventListener('elementor/frontend/init', bindHooks);
  }

  if (isElementorPreviewFrame) {
    ensureObserver();
  }

  bindHooks();
}

registerElementorFeaturedPreviewBoot();

if (document.readyState === 'loading') {
  document.addEventListener(
    'DOMContentLoaded',
    () => {
      bootSearchWidgets();
      bootListingsWidgets();
      bootPricingTables();
      bootBookingWidgets();
      bootBookingCheckoutWidgets();
      bootFeaturedProperties();
    },
    { once: true },
  );
} else {
  bootSearchWidgets();
  bootListingsWidgets();
  bootPricingTables();
  bootBookingWidgets();
  bootBookingCheckoutWidgets();
  bootFeaturedProperties();
}
