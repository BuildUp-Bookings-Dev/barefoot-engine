import { BPCalendar, BP_Calendar } from '@braudypedrosa/bp-calendar';
import listingsMapModule from './bp-listings-runtime.js';
import { BPSearchWidget, BP_SearchWidget } from '@braudypedrosa/bp-search-widget';

const SEARCH_WIDGET_SELECTOR = '[data-be-search-widget]';
const LISTINGS_SELECTOR = '[data-be-listings]';
const ListingsMap = listingsMapModule?.ListingsMap ?? listingsMapModule ?? null;
const AJAX_SEARCH_MIN_BUFFER_MS = 1500;
const CHOICE_POPOVER_SCROLLBAR_MIN_THUMB = 44;
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

  widget.beInitialNonDateCriteria = captureNonDateCriteriaSnapshot(widget);
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

    return hasAtLeastOneNonDateCriterion(this, this.beInitialNonDateCriteria || null);
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
  const popover = widget?.openPopover?.popover;
  if (!(popover instanceof HTMLElement)) {
    return;
  }

  const state = ensureCustomChoicePopoverScrollbar(popover);
  if (!state) {
    return;
  }

  updateCustomChoicePopoverScrollbar(popover, state);
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

function captureNonDateCriteriaSnapshot(widget) {
  return {
    location: normalizeString(widget?.state?.location ?? ''),
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

function hasAtLeastOneNonDateCriterion(widget, baseline = null) {
  if (!widget || widget.isDestroyed) {
    return false;
  }

  const currentSnapshot = captureNonDateCriteriaSnapshot(widget);
  const baselineSnapshot = isPlainObject(baseline) ? baseline : {
    location: '',
    customFields: {},
    filters: {},
  };

  if (
    currentSnapshot.location !== baselineSnapshot.location
    && currentSnapshot.location !== ''
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

  button.hidden = !(mountNode.beEmbeddedSearchWidget && mountNode.beHasActiveSearch);
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

if (document.readyState === 'loading') {
  document.addEventListener(
    'DOMContentLoaded',
    () => {
      bootSearchWidgets();
      bootListingsWidgets();
    },
    { once: true },
  );
} else {
  bootSearchWidgets();
  bootListingsWidgets();
}
