import { BPCalendar } from '@braudypedrosa/bp-calendar';

const BOOKING_CHECKOUT_SELECTOR = '[data-be-booking-checkout]';
const QUOTE_DEBOUNCE_MS = 350;
const BOOKING_SESSION_COOKIE = 'be_booking_checkout_session';

export function bootBookingCheckoutWidgets() {
  const mounts = document.querySelectorAll(BOOKING_CHECKOUT_SELECTOR);

  mounts.forEach((mountNode) => {
    if (!(mountNode instanceof HTMLElement) || mountNode.dataset.beBookingCheckoutReady === 'true') {
      return;
    }

    const configId = mountNode.dataset.beBookingCheckoutConfig;
    const config = readWidgetConfig(configId, 'Booking checkout');

    if (!config) {
      return;
    }

    try {
      initializeBookingCheckoutWidget(mountNode, config);
      mountNode.dataset.beBookingCheckoutReady = 'true';
    } catch (error) {
      console.error('[barefoot-engine] Failed to initialize booking checkout.', error);
    }
  });
}

function initializeBookingCheckoutWidget(mountNode, config) {
  const runtime = buildCheckoutRuntime(config);
  mountNode.innerHTML = runtime.markup;

  const calendarHost = mountNode.querySelector('.barefoot-engine-booking-checkout__calendar-host');
  const guestsSelect = mountNode.querySelector('.barefoot-engine-booking-checkout__guests-select');
  const statusNode = mountNode.querySelector('.barefoot-engine-booking-checkout__quote-status');
  const noticeNode = mountNode.querySelector('.barefoot-engine-booking-checkout__notice');
  const dailyValueNode = mountNode.querySelector('[data-be-booking-checkout-total="daily"]');
  const subtotalLabelNode = mountNode.querySelector('[data-be-booking-checkout-label="subtotal"]');
  const subtotalValueNode = mountNode.querySelector('[data-be-booking-checkout-total="subtotal"]');
  const taxValueNode = mountNode.querySelector('[data-be-booking-checkout-total="tax"]');
  const totalValueNode = mountNode.querySelector('[data-be-booking-checkout-total="grand"]');
  const depositRow = mountNode.querySelector('[data-be-booking-checkout-row="deposit"]');
  const depositValueNode = mountNode.querySelector('[data-be-booking-checkout-total="deposit"]');
  const payableFooterNode = mountNode.querySelector('[data-be-booking-checkout-row="payable"]');
  const payableValueNode = mountNode.querySelector('[data-be-booking-checkout-total="payable"]');
  const proceedButton = mountNode.querySelector('[data-be-booking-checkout-action="proceed"]');
  const backButton = mountNode.querySelector('[data-be-booking-checkout-action="back"]');
  const payButton = mountNode.querySelector('[data-be-booking-checkout-action="pay"]');
  const changeDatesButton = mountNode.querySelector('[data-be-booking-checkout-action="change-dates"]');
  const changeGuestsButton = mountNode.querySelector('[data-be-booking-checkout-action="change-guests"]');
  const datesModalNode = mountNode.querySelector('[data-be-booking-checkout-modal="dates"]');
  const datesModalBackdrop = mountNode.querySelector('[data-be-booking-checkout-action="dismiss-dates-modal"]');
  const datesModalCancelButton = mountNode.querySelector('[data-be-booking-checkout-action="cancel-dates"]');
  const datesModalClearButton = mountNode.querySelector('[data-be-booking-checkout-action="clear-dates"]');
  const datesModalApplyButton = mountNode.querySelector('[data-be-booking-checkout-action="apply-dates"]');
  const guestsPopoverNode = mountNode.querySelector('[data-be-booking-checkout-popover="guests"]');
  const guestDecrementButton = mountNode.querySelector('[data-be-booking-checkout-action="guest-decrement"]');
  const guestIncrementButton = mountNode.querySelector('[data-be-booking-checkout-action="guest-increment"]');
  const guestApplyButton = mountNode.querySelector('[data-be-booking-checkout-action="apply-guests"]');
  const guestValueNode = mountNode.querySelector('[data-be-booking-checkout-guest-value]');
  const guestsCompactItemNode = mountNode.querySelector('[data-be-booking-checkout-compact="guests"]');
  const compactDatesValueNode = mountNode.querySelector('[data-be-booking-checkout-summary="dates"]');
  const compactGuestsValueNode = mountNode.querySelector('[data-be-booking-checkout-summary="guests"]');
  const guestForm = mountNode.querySelector('[data-be-booking-checkout-form="guest"]');
  const paymentForm = mountNode.querySelector('[data-be-booking-checkout-form="payment"]');
  const paymentStep = mountNode.querySelector('[data-be-booking-checkout-step="payment"]');
  const successPanel = mountNode.querySelector('[data-be-booking-checkout-success]');
  const successAmountNode = mountNode.querySelector('[data-be-booking-checkout-success="amount"]');
  const successFolioNode = mountNode.querySelector('[data-be-booking-checkout-success="folio"]');

  if (
    !(calendarHost instanceof HTMLElement)
    || !(guestsSelect instanceof HTMLSelectElement)
    || !(statusNode instanceof HTMLElement)
    || !(noticeNode instanceof HTMLElement)
    || !(dailyValueNode instanceof HTMLElement)
    || !(subtotalLabelNode instanceof HTMLElement)
    || !(subtotalValueNode instanceof HTMLElement)
    || !(taxValueNode instanceof HTMLElement)
    || !(totalValueNode instanceof HTMLElement)
    || !(depositRow instanceof HTMLElement)
    || !(depositValueNode instanceof HTMLElement)
    || !(payableFooterNode instanceof HTMLElement)
    || !(payableValueNode instanceof HTMLElement)
    || !(proceedButton instanceof HTMLButtonElement)
    || !(backButton instanceof HTMLButtonElement)
    || !(payButton instanceof HTMLButtonElement)
    || !(changeDatesButton instanceof HTMLButtonElement)
    || !(changeGuestsButton instanceof HTMLButtonElement)
    || !(datesModalNode instanceof HTMLElement)
    || !(datesModalBackdrop instanceof HTMLElement)
    || !(datesModalCancelButton instanceof HTMLButtonElement)
    || !(datesModalClearButton instanceof HTMLButtonElement)
    || !(datesModalApplyButton instanceof HTMLButtonElement)
    || !(guestsPopoverNode instanceof HTMLElement)
    || !(guestDecrementButton instanceof HTMLButtonElement)
    || !(guestIncrementButton instanceof HTMLButtonElement)
    || !(guestApplyButton instanceof HTMLButtonElement)
    || !(guestValueNode instanceof HTMLElement)
    || !(guestsCompactItemNode instanceof HTMLElement)
    || !(compactDatesValueNode instanceof HTMLElement)
    || !(compactGuestsValueNode instanceof HTMLElement)
    || !(guestForm instanceof HTMLFormElement)
    || !(paymentForm instanceof HTMLFormElement)
    || !(paymentStep instanceof HTMLElement)
    || !(successPanel instanceof HTMLElement)
    || !(successAmountNode instanceof HTMLElement)
    || !(successFolioNode instanceof HTMLElement)
  ) {
    return;
  }

  const initialGuestValue = resolveInitialGuestValue(runtime.guestOptions, runtime.initialGuests || runtime.defaultGuestValue);
  guestsSelect.innerHTML = runtime.guestOptions
    .map((optionValue) => {
      const escapedValue = escapeHtml(optionValue);
      const selectedAttribute = optionValue === initialGuestValue ? ' selected' : '';
      return `<option value="${escapedValue}"${selectedAttribute}>${escapedValue}</option>`;
    })
    .join('');
  const guestBounds = buildGuestOptionBounds(runtime.guestOptions);

  const state = {
    mountNode,
    propertyId: runtime.propertyId,
    propertySummary: runtime.propertySummary,
    missingContext: runtime.missingContext,
    currency: runtime.currency,
    reztypeid: runtime.reztypeid,
    paymentMode: runtime.paymentMode,
    portalId: runtime.portalId,
    sourceOfBusiness: runtime.sourceOfBusiness,
    labels: runtime.labels,
    links: runtime.links,
    calendar: null,
    calendarHost,
    guestsSelect,
    statusNode,
    noticeNode,
    dailyValueNode,
    subtotalLabelNode,
    subtotalValueNode,
    taxValueNode,
    totalValueNode,
    depositRow,
    depositValueNode,
    payableFooterNode,
    payableValueNode,
    proceedButton,
    backButton,
    payButton,
    changeDatesButton,
    changeGuestsButton,
    datesModalNode,
    datesModalBackdrop,
    datesModalCancelButton,
    datesModalClearButton,
    datesModalApplyButton,
    guestsPopoverNode,
    guestDecrementButton,
    guestIncrementButton,
    guestApplyButton,
    guestValueNode,
    guestsCompactItemNode,
    compactDatesValueNode,
    compactGuestsValueNode,
    guestForm,
    paymentForm,
    paymentStep,
    successPanel,
    successAmountNode,
    successFolioNode,
    disabledDates: new Set(),
    dailyPrices: new Map(),
    loadedCalendarRangeKeys: new Set(),
    calendarFetchInFlightKey: '',
    quoteTimer: null,
    quoteRequestToken: 0,
    checkIn: runtime.initialCheckIn,
    checkOut: runtime.initialCheckOut,
    draftCheckIn: runtime.initialCheckIn,
    draftCheckOut: runtime.initialCheckOut,
    guestMin: guestBounds.min,
    guestMax: guestBounds.max,
    pendingGuests: coerceGuestCountIntoBounds(normalizeGuestCount(initialGuestValue), guestBounds.min, guestBounds.max),
    baseMonthsToShow: runtime.calendarOptions.monthsToShow,
    currentMonthsToShow: 1,
    datesModalOpen: false,
    guestsPopoverOpen: false,
    viewportResizeHandler: null,
    sessionToken: runtime.initialSessionToken,
    quoteData: null,
  };

  mountNode.beBookingCheckoutState = state;
  if (state.sessionToken) {
    writeBookingSessionCookie(state.sessionToken);
  }
  setDateModalVisibility(state, false);
  setGuestPopoverVisibility(state, false);
  renderCheckoutSummaryMeta(state);
  renderCheckoutTotals(state, null, null);
  setCheckoutNotice(state, '', 'idle');
  state.backButton.disabled = true;
  state.payButton.disabled = true;

  if (runtime.missingContext) {
    guestsSelect.disabled = true;
    state.proceedButton.disabled = true;
    state.changeDatesButton.disabled = true;
    state.changeGuestsButton.disabled = true;
    setCheckoutStatus(state, 'missingContext', 'error');
    return;
  }

  const selectedRange = runtime.initialCheckIn && runtime.initialCheckOut
    ? {
      start: parseYmdDate(runtime.initialCheckIn),
      end: parseYmdDate(runtime.initialCheckOut),
    }
    : null;
  const initialMonthsToShow = resolveCheckoutMonthsToShow(runtime.calendarOptions.monthsToShow);
  state.currentMonthsToShow = initialMonthsToShow;

  const calendar = new BPCalendar(calendarHost, {
    mode: 'datepicker',
    monthsToShow: initialMonthsToShow,
    datepickerPlacement: runtime.calendarOptions.datepickerPlacement,
    defaultMinDays: runtime.calendarOptions.defaultMinDays,
    tooltipLabel: runtime.calendarOptions.tooltipLabel,
    showTooltip: runtime.calendarOptions.showTooltip,
    showClearButton: false,
    selectedRange: selectedRange?.start instanceof Date && selectedRange?.end instanceof Date ? selectedRange : null,
    dateConfig: {},
    onRangeSelect: (range) => {
      handleCheckoutRangeSelect(state, range);
    },
  });

  state.calendar = calendar;
  patchCalendarPopupRender(state);
  syncCheckoutCalendarViewportMode(state);
  state.viewportResizeHandler = () => {
    syncCheckoutCalendarViewportMode(state);
  };
  window.addEventListener('resize', state.viewportResizeHandler, { passive: true });

  changeDatesButton.addEventListener('click', (event) => {
    event.preventDefault();
    openDateModal(state);
  });

  changeGuestsButton.addEventListener('click', (event) => {
    event.preventDefault();
    toggleGuestPopover(state);
  });

  datesModalCancelButton.addEventListener('click', (event) => {
    event.preventDefault();
    cancelDateModal(state);
  });

  datesModalBackdrop.addEventListener('click', (event) => {
    event.preventDefault();
    cancelDateModal(state);
  });

  datesModalClearButton.addEventListener('click', (event) => {
    event.preventDefault();
    clearDateModalRange(state);
  });

  datesModalApplyButton.addEventListener('click', (event) => {
    event.preventDefault();
    applyDateModalSelection(state);
  });

  guestDecrementButton.addEventListener('click', (event) => {
    event.preventDefault();
    updateGuestDraftCount(state, -1);
  });

  guestIncrementButton.addEventListener('click', (event) => {
    event.preventDefault();
    updateGuestDraftCount(state, 1);
  });

  guestApplyButton.addEventListener('click', (event) => {
    event.preventDefault();
    applyGuestDraftCount(state);
  });

  document.addEventListener('click', (event) => {
    if (!state.guestsPopoverOpen) {
      return;
    }

    const target = event.target;
    if (!(target instanceof Node)) {
      return;
    }

    if (state.guestsCompactItemNode.contains(target)) {
      return;
    }

    setGuestPopoverVisibility(state, false);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
      return;
    }

    if (state.datesModalOpen) {
      cancelDateModal(state);
      return;
    }

    if (state.guestsPopoverOpen) {
      setGuestPopoverVisibility(state, false);
    }
  });

  guestForm.addEventListener('submit', (event) => {
    event.preventDefault();
    createCheckoutSession(state);
  });

  paymentForm.addEventListener('submit', (event) => {
    event.preventDefault();
    completeCheckoutSession(state);
  });

  backButton.addEventListener('click', (event) => {
    event.preventDefault();
    resetCheckoutSession(state, true);
  });

  if (runtime.initialCheckIn && runtime.initialCheckOut) {
    updateCheckoutStayState(state);
  } else {
    setCheckoutStatus(state, 'idle', 'idle');
  }

  refreshCheckoutCalendarAvailability(state, true);
}

function buildCheckoutRuntime(config) {
  const propertyId = sanitizeText(config?.propertyId, '');
  const propertySummary = sanitizePropertySummary(config?.propertySummary);
  const missingContext = !propertyId || Boolean(config?.missingContext);
  const currency = sanitizeText(config?.currency, '$');
  const reztypeid = sanitizePositiveInt(config?.reztypeid, 26);
  const paymentMode = sanitizePaymentMode(config?.paymentMode, 'ON');
  const portalId = sanitizeText(config?.portalId, '');
  const sourceOfBusiness = sanitizeText(config?.sourceOfBusiness, '');
  const calendarOptions = sanitizeCalendarOptions(config?.calendarOptions);
  const guestsConfig = isPlainObject(config?.guests) ? config.guests : {};
  const guestOptions = sanitizeGuestOptions(guestsConfig.options);
  const defaultGuestValue = sanitizeText(guestsConfig.defaultValue, guestOptions[0] || '1');
  const initialSearch = parseCheckoutSearchState(window.location.search);
  const labels = buildCheckoutLabels(config?.labels);
  const links = buildCheckoutLinks(config?.links);

  return {
    propertyId,
    propertySummary,
    missingContext,
    currency,
    reztypeid,
    paymentMode,
    portalId,
    sourceOfBusiness,
    calendarOptions,
    guestOptions,
    defaultGuestValue,
    initialCheckIn: initialSearch.checkIn,
    initialCheckOut: initialSearch.checkOut,
    initialGuests: initialSearch.guests,
    initialSessionToken: initialSearch.sessionToken,
    labels,
    links,
    markup: `
      <section class="barefoot-engine-booking-checkout__layout">
        <div class="barefoot-engine-booking-checkout__main">
          <div class="barefoot-engine-booking-checkout__notice" data-state="idle"></div>
          <section class="barefoot-engine-booking-checkout__step barefoot-engine-booking-checkout__step--active" data-be-booking-checkout-step="guest">
            <header class="barefoot-engine-booking-checkout__step-header">
              <span class="barefoot-engine-booking-checkout__step-index">1</span>
              <h3 class="barefoot-engine-booking-checkout__step-title">${escapeHtml(labels.guestStepTitle)}</h3>
            </header>
            <form class="barefoot-engine-booking-checkout__form" data-be-booking-checkout-form="guest">
              <div class="barefoot-engine-booking-checkout__grid barefoot-engine-booking-checkout__grid--two">
                ${renderInputField('first_name', 'First name', true)}
                ${renderInputField('last_name', 'Last name', true)}
                ${renderInputField('email', 'Email', true, 'email')}
                ${renderInputField('cell_phone', 'Cell Phone', true, 'tel')}
              </div>
              ${renderInputField('address_1', 'Address 1', true)}
              ${renderInputField('address_2', 'Address 2', false)}
              <div class="barefoot-engine-booking-checkout__grid barefoot-engine-booking-checkout__grid--two">
                ${renderInputField('city', 'City', true)}
                ${renderInputField('state', 'State', true)}
                ${renderInputField('country', 'Country', true)}
                ${renderInputField('postal_code', 'Postal code', true)}
              </div>
              <label class="barefoot-engine-booking-checkout__checkbox">
                <input type="checkbox" name="age_confirmed" value="1" />
                <span>${escapeHtml(labels.ageConfirmation)}</span>
              </label>
              <div class="barefoot-engine-booking-checkout__actions">
                <button type="submit" class="barefoot-engine-booking-checkout__button barefoot-engine-booking-checkout__button--primary" data-be-booking-checkout-action="proceed">${escapeHtml(labels.proceedToPay)}</button>
              </div>
            </form>
          </section>
          <section class="barefoot-engine-booking-checkout__step barefoot-engine-booking-checkout__step--disabled" data-be-booking-checkout-step="payment" aria-disabled="true">
            <header class="barefoot-engine-booking-checkout__step-header">
              <span class="barefoot-engine-booking-checkout__step-index">2</span>
              <h3 class="barefoot-engine-booking-checkout__step-title">${escapeHtml(labels.paymentStepTitle)}</h3>
            </header>
            <form class="barefoot-engine-booking-checkout__form" data-be-booking-checkout-form="payment">
              <label class="barefoot-engine-booking-checkout__radio">
                <input type="radio" checked disabled />
                <span>Credit Card</span>
              </label>
              ${renderInputField('card_number', 'Card number', true, 'text', 'XXXX XXXX XXXX XXXX')}
              <div class="barefoot-engine-booking-checkout__grid barefoot-engine-booking-checkout__grid--three">
                ${renderInputField('expiry_month', 'Expiration Month', true, 'text', 'MM')}
                ${renderInputField('expiry_year', 'Expiration Year', true, 'text', 'YYYY')}
                ${renderInputField('cvv', 'CVV', true, 'text', '123')}
              </div>
              ${renderInputField('name_on_card', 'Name on the card', true, 'text', 'Enter card holder’s name')}
              ${renderAgreementCopy(labels, links)}
              <div class="barefoot-engine-booking-checkout__actions barefoot-engine-booking-checkout__actions--split">
                <button type="button" class="barefoot-engine-booking-checkout__button barefoot-engine-booking-checkout__button--secondary" data-be-booking-checkout-action="back">${escapeHtml(labels.goBack)}</button>
                <button type="submit" class="barefoot-engine-booking-checkout__button barefoot-engine-booking-checkout__button--accent" data-be-booking-checkout-action="pay">${escapeHtml(labels.paySecurely)}</button>
              </div>
            </form>
          </section>
          <section class="barefoot-engine-booking-checkout__success" data-be-booking-checkout-success hidden>
            <h3 class="barefoot-engine-booking-checkout__success-title">${escapeHtml(labels.paymentSuccessTitle)}</h3>
            <p class="barefoot-engine-booking-checkout__success-body">${escapeHtml(labels.paymentSuccessBody)}</p>
            <dl class="barefoot-engine-booking-checkout__success-meta">
              <div>
                <dt>Amount</dt>
                <dd data-be-booking-checkout-success="amount"></dd>
              </div>
              <div>
                <dt>Reservation</dt>
                <dd data-be-booking-checkout-success="folio"></dd>
              </div>
            </dl>
          </section>
        </div>
        <aside class="barefoot-engine-booking-checkout__sidebar">
          <section class="barefoot-engine-booking-checkout__summary-card">
            ${renderPropertySummary(propertySummary, labels)}
            <div class="barefoot-engine-booking-checkout__summary-controls">
              <div class="barefoot-engine-booking-checkout__summary-compact-item">
                <label class="barefoot-engine-booking-checkout__label">${escapeHtml(labels.dates)}</label>
                <strong class="barefoot-engine-booking-checkout__summary-value" data-be-booking-checkout-summary="dates">—</strong>
                <button type="button" class="barefoot-engine-booking-checkout__summary-change" data-be-booking-checkout-action="change-dates">${escapeHtml(labels.changeDates)}</button>
              </div>
              <div class="barefoot-engine-booking-checkout__summary-compact-item" data-be-booking-checkout-compact="guests">
                <label class="barefoot-engine-booking-checkout__label">${escapeHtml(labels.guests)}</label>
                <strong class="barefoot-engine-booking-checkout__summary-value" data-be-booking-checkout-summary="guests">—</strong>
                <button type="button" class="barefoot-engine-booking-checkout__summary-change" data-be-booking-checkout-action="change-guests">${escapeHtml(labels.changeGuests)}</button>
                <div class="barefoot-engine-booking-checkout__guests-popover" data-be-booking-checkout-popover="guests" hidden>
                  <button type="button" class="barefoot-engine-booking-checkout__guest-stepper-btn" data-be-booking-checkout-action="guest-decrement" aria-label="${escapeHtml(labels.guestDecrement)}">−</button>
                  <strong class="barefoot-engine-booking-checkout__guest-stepper-value" data-be-booking-checkout-guest-value>1</strong>
                  <button type="button" class="barefoot-engine-booking-checkout__guest-stepper-btn" data-be-booking-checkout-action="guest-increment" aria-label="${escapeHtml(labels.guestIncrement)}">+</button>
                  <button type="button" class="barefoot-engine-booking-checkout__guest-apply-btn" data-be-booking-checkout-action="apply-guests">${escapeHtml(labels.applyGuestChange)}</button>
                </div>
              </div>
              <select class="barefoot-engine-booking-checkout__guests-select" aria-label="${escapeHtml(labels.guests)}" hidden></select>
              <p class="barefoot-engine-booking-checkout__quote-status" role="status" aria-live="polite"></p>
            </div>
            <div class="barefoot-engine-booking-checkout__totals">
              <div class="barefoot-engine-booking-checkout__total-row">
                <span>${escapeHtml(labels.rent)}</span>
                <strong data-be-booking-checkout-total="daily"></strong>
              </div>
              <div class="barefoot-engine-booking-checkout__total-row">
                <span data-be-booking-checkout-label="subtotal">${escapeHtml(labels.subtotal)}</span>
                <strong data-be-booking-checkout-total="subtotal"></strong>
              </div>
              <div class="barefoot-engine-booking-checkout__total-row">
                <span>${escapeHtml(labels.tax)}</span>
                <strong data-be-booking-checkout-total="tax"></strong>
              </div>
              <div class="barefoot-engine-booking-checkout__total-row" data-be-booking-checkout-row="deposit" hidden>
                <span>${escapeHtml(labels.depositAmount)}</span>
                <strong data-be-booking-checkout-total="deposit"></strong>
              </div>
              <div class="barefoot-engine-booking-checkout__total-row barefoot-engine-booking-checkout__total-row--total">
                <span>${escapeHtml(labels.total)}</span>
                <strong data-be-booking-checkout-total="grand"></strong>
              </div>
              <div class="barefoot-engine-booking-checkout__payable-footer" data-be-booking-checkout-row="payable">
                <span>${escapeHtml(labels.payableAmount)}</span>
                <strong data-be-booking-checkout-total="payable"></strong>
              </div>
            </div>
          </section>
        </aside>
      </section>
      <section class="barefoot-engine-booking-checkout__modal" data-be-booking-checkout-modal="dates" hidden>
        <div class="barefoot-engine-booking-checkout__modal-backdrop" data-be-booking-checkout-action="dismiss-dates-modal"></div>
        <div class="barefoot-engine-booking-checkout__modal-dialog" role="dialog" aria-modal="true" aria-label="${escapeHtml(labels.changeDatesModalTitle)}">
          <header class="barefoot-engine-booking-checkout__modal-header">
            <h4>${escapeHtml(labels.changeDatesModalTitle)}</h4>
          </header>
          <div class="barefoot-engine-booking-checkout__modal-body">
            <div class="barefoot-engine-booking-checkout__calendar-host"></div>
          </div>
          <footer class="barefoot-engine-booking-checkout__modal-actions">
            <button type="button" class="barefoot-engine-booking-checkout__modal-btn barefoot-engine-booking-checkout__modal-btn--secondary" data-be-booking-checkout-action="cancel-dates">${escapeHtml(labels.modalCancel)}</button>
            <button type="button" class="barefoot-engine-booking-checkout__modal-btn barefoot-engine-booking-checkout__modal-btn--secondary" data-be-booking-checkout-action="clear-dates">${escapeHtml(labels.modalClear)}</button>
            <button type="button" class="barefoot-engine-booking-checkout__modal-btn barefoot-engine-booking-checkout__modal-btn--primary" data-be-booking-checkout-action="apply-dates">${escapeHtml(labels.modalApply)}</button>
          </footer>
        </div>
      </section>
    `,
  };
}

function renderInputField(name, label, required, type = 'text', placeholder = '') {
  const requiredMark = required ? ' <span>*</span>' : '';
  const requiredAttr = required ? ' required' : '';
  const placeholderAttr = placeholder ? ` placeholder="${escapeHtml(placeholder)}"` : '';

  return `
    <label class="barefoot-engine-booking-checkout__field">
      <span class="barefoot-engine-booking-checkout__field-label">${escapeHtml(label)}${requiredMark}</span>
      <input class="barefoot-engine-booking-checkout__input" type="${escapeHtml(type)}" name="${escapeHtml(name)}"${requiredAttr}${placeholderAttr} />
    </label>
  `;
}

function renderAgreementCopy(labels, links) {
  const termsContent = links.termsUrl
    ? `${escapeHtml(labels.termsPrefix)} <a href="${escapeHtml(links.termsUrl)}" target="_blank" rel="noopener noreferrer">${escapeHtml(labels.termsLinkText)}</a>.`
    : `${escapeHtml(labels.termsPrefix)} ${escapeHtml(labels.termsLinkText)}.`;
  const rentalContent = links.rentalAgreementUrl
    ? `${escapeHtml(labels.rentalAgreementPrefix)} <a href="${escapeHtml(links.rentalAgreementUrl)}" target="_blank" rel="noopener noreferrer">${escapeHtml(labels.rentalAgreementLinkText)}</a>`
    : `${escapeHtml(labels.rentalAgreementPrefix)} ${escapeHtml(labels.rentalAgreementLinkText)}`;

  return `
    <div class="barefoot-engine-booking-checkout__agreements">
      <p>${termsContent}</p>
      <p>${rentalContent}</p>
    </div>
  `;
}

function renderPropertySummary(summary, labels) {
  const imageMarkup = summary.imageUrl
    ? `<img src="${escapeHtml(summary.imageUrl)}" alt="${escapeHtml(summary.title)}" class="barefoot-engine-booking-checkout__property-image" />`
    : '<div class="barefoot-engine-booking-checkout__property-image barefoot-engine-booking-checkout__property-image--empty"></div>';
  const linkMarkup = summary.permalink
    ? `<a href="${escapeHtml(summary.permalink)}" class="barefoot-engine-booking-checkout__property-link">${escapeHtml(labels.listingDetails)}</a>`
    : '';

  return `
    <div class="barefoot-engine-booking-checkout__property-card">
      <div class="barefoot-engine-booking-checkout__property-top">
        ${imageMarkup}
        <div class="barefoot-engine-booking-checkout__property-copy">
          <h4>${escapeHtml(summary.title || 'Property')}</h4>
          <p>${escapeHtml(summary.address || 'Address unavailable')}</p>
          ${linkMarkup}
        </div>
      </div>
      <div class="barefoot-engine-booking-checkout__property-stats">
        <div>
          <strong>${escapeHtml(String(summary.stats.sleeps ?? '—'))}</strong>
          <span>Sleeps</span>
        </div>
        <div>
          <strong>${escapeHtml(String(summary.stats.bedrooms ?? '—'))}</strong>
          <span>Bedrooms</span>
        </div>
        <div>
          <strong>${escapeHtml(String(summary.stats.bathrooms ?? '—'))}</strong>
          <span>Bathrooms</span>
        </div>
      </div>
    </div>
  `;
}

function handleCheckoutRangeSelect(state, range) {
  const start = range?.start instanceof Date ? formatDateToYmd(range.start) : '';
  const end = range?.end instanceof Date ? formatDateToYmd(range.end) : '';
  if (state.datesModalOpen) {
    state.draftCheckIn = start;
    state.draftCheckOut = end;
    return;
  }

  commitCheckoutDateRange(state, start, end);
}

function commitCheckoutDateRange(state, checkIn, checkOut) {
  state.checkIn = checkIn;
  state.checkOut = checkOut;
  syncCheckoutUrlParams(state);
  resetCheckoutSession(state, false);
  renderCheckoutSummaryMeta(state);

  if (!checkIn || !checkOut) {
    state.quoteData = null;
    renderCheckoutTotals(state, null, null);
    setCheckoutStatus(state, 'idle', 'idle');
    return;
  }

  scheduleCheckoutQuoteCheck(state);
}

function updateCheckoutStayState(state) {
  syncCheckoutUrlParams(state);
  resetCheckoutSession(state, false);
  renderCheckoutSummaryMeta(state);

  if (!state.checkIn || !state.checkOut) {
    state.quoteData = null;
    renderCheckoutTotals(state, null, null);
    setCheckoutStatus(state, 'idle', 'idle');
    return;
  }

  scheduleCheckoutQuoteCheck(state);
}

function openDateModal(state) {
  state.draftCheckIn = state.checkIn;
  state.draftCheckOut = state.checkOut;
  setGuestPopoverVisibility(state, false);
  syncCheckoutCalendarViewportMode(state);
  focusCheckoutCalendarMonth(state, state.draftCheckIn, state.draftCheckOut);
  setDateModalVisibility(state, true);
  updateCalendarSelection(state, state.draftCheckIn, state.draftCheckOut);

  window.setTimeout(() => {
    const datepickerInput = state.calendarHost.querySelector('.bp-calendar-datepicker-input');
    if (datepickerInput instanceof HTMLElement) {
      datepickerInput.focus();
      datepickerInput.click();
    }
  }, 0);
}

function cancelDateModal(state) {
  state.draftCheckIn = state.checkIn;
  state.draftCheckOut = state.checkOut;
  updateCalendarSelection(state, state.checkIn, state.checkOut);
  closeDatepickerPopup(state);
  setDateModalVisibility(state, false);
}

function clearDateModalRange(state) {
  state.draftCheckIn = '';
  state.draftCheckOut = '';
  if (state.calendar && typeof state.calendar.clearSelection === 'function') {
    state.calendar.clearSelection();
    return;
  }

  updateCalendarSelection(state, '', '');
}

function applyDateModalSelection(state) {
  closeDatepickerPopup(state);
  setDateModalVisibility(state, false);
  commitCheckoutDateRange(state, state.draftCheckIn, state.draftCheckOut);
}

function setDateModalVisibility(state, visible) {
  state.datesModalOpen = Boolean(visible);
  state.datesModalNode.hidden = !state.datesModalOpen;
  state.mountNode.classList.toggle('be-booking-checkout-date-modal-open', state.datesModalOpen);
  syncCheckoutCalendarClearVisibility(state);
}

function closeDatepickerPopup(state) {
  const popup = state.calendarHost.querySelector('.bp-calendar-datepicker-popup');
  if (popup instanceof HTMLElement) {
    popup.style.display = 'none';
  }
}

function updateCalendarSelection(state, checkIn, checkOut) {
  if (!state.calendar) {
    return;
  }

  const selectedRange = checkIn && checkOut
    ? {
      start: parseYmdDate(checkIn),
      end: parseYmdDate(checkOut),
    }
    : null;

  const hasValidRange = selectedRange?.start instanceof Date && selectedRange?.end instanceof Date;
  const dateConfig = isPlainObject(state.calendar.options?.dateConfig) ? state.calendar.options.dateConfig : {};
  const normalizedRange = hasValidRange
    ? selectedRange
    : { start: null, end: null };

  if (typeof state.calendar.updateOptions === 'function') {
    state.calendar.updateOptions({
      selectedRange: normalizedRange,
      dateConfig,
    });
    return;
  }

  state.calendar.options = {
    ...state.calendar.options,
    selectedRange: normalizedRange,
    dateConfig,
  };

  if (typeof state.calendar.renderCalendarInPopup === 'function') {
    state.calendar.renderCalendarInPopup();
  }
}

function focusCheckoutCalendarMonth(state, checkIn, checkOut) {
  if (!state || !state.calendar) {
    return;
  }

  const focusDate = resolveCheckoutCalendarFocusDate(checkIn, checkOut);
  if (!(focusDate instanceof Date)) {
    return;
  }

  const focusMonthDate = new Date(focusDate.getFullYear(), focusDate.getMonth(), 1);
  if (Number.isNaN(focusMonthDate.getTime())) {
    return;
  }

  state.calendar.currentDate = focusMonthDate;
}

function resolveCheckoutCalendarFocusDate(checkIn, checkOut) {
  const normalizedCheckIn = parseYmdDate(checkIn);
  if (normalizedCheckIn instanceof Date) {
    return normalizedCheckIn;
  }

  const normalizedCheckOut = parseYmdDate(checkOut);
  if (normalizedCheckOut instanceof Date) {
    return normalizedCheckOut;
  }

  return null;
}

function toggleGuestPopover(state) {
  const shouldOpen = !state.guestsPopoverOpen;
  if (!shouldOpen) {
    setGuestPopoverVisibility(state, false);
    return;
  }

  const currentGuests = coerceGuestCountIntoBounds(
    normalizeGuestCount(state.guestsSelect.value),
    state.guestMin,
    state.guestMax,
  );
  state.pendingGuests = currentGuests;
  syncGuestDraftDisplay(state);
  setDateModalVisibility(state, false);
  setGuestPopoverVisibility(state, true);
}

function setGuestPopoverVisibility(state, visible) {
  state.guestsPopoverOpen = Boolean(visible);
  state.guestsPopoverNode.hidden = !state.guestsPopoverOpen;
  state.mountNode.classList.toggle('be-booking-checkout-guest-popover-open', state.guestsPopoverOpen);
}

function updateGuestDraftCount(state, delta) {
  const nextValue = coerceGuestCountIntoBounds(
    Number(state.pendingGuests || state.guestMin) + Number(delta || 0),
    state.guestMin,
    state.guestMax,
  );
  state.pendingGuests = nextValue;
  syncGuestDraftDisplay(state);
}

function syncGuestDraftDisplay(state) {
  const value = coerceGuestCountIntoBounds(state.pendingGuests, state.guestMin, state.guestMax);
  state.pendingGuests = value;
  state.guestValueNode.textContent = String(value);
  state.guestDecrementButton.disabled = value <= state.guestMin;
  state.guestIncrementButton.disabled = value >= state.guestMax;
}

function applyGuestDraftCount(state) {
  const value = coerceGuestCountIntoBounds(state.pendingGuests, state.guestMin, state.guestMax);
  const optionValue = resolveGuestOptionForCount(state.guestsSelect.options, value);
  if (optionValue) {
    state.guestsSelect.value = optionValue;
  }

  setGuestPopoverVisibility(state, false);
  updateCheckoutStayState(state);
}

function resolveCheckoutMonthsToShow(baseMonthsToShow) {
  const normalizedBase = Number(baseMonthsToShow);
  const safeBase = Number.isFinite(normalizedBase) ? Math.max(1, Math.min(6, Math.round(normalizedBase))) : 2;
  const isMobileViewport = typeof window !== 'undefined'
    && typeof window.matchMedia === 'function'
    && window.matchMedia('(max-width: 767px)').matches;

  return isMobileViewport ? 1 : safeBase;
}

function syncCheckoutCalendarViewportMode(state) {
  if (!state || !state.calendar) {
    return;
  }

  const targetMonthsToShow = resolveCheckoutMonthsToShow(state.baseMonthsToShow);
  if (!Number.isFinite(targetMonthsToShow) || targetMonthsToShow < 1) {
    return;
  }

  if (targetMonthsToShow === state.currentMonthsToShow) {
    return;
  }

  state.currentMonthsToShow = targetMonthsToShow;

  if (typeof state.calendar.setPopupMonthsToShow === 'function') {
    state.calendar.setPopupMonthsToShow(targetMonthsToShow);
  }

  const currentDateConfig = isPlainObject(state.calendar.options?.dateConfig)
    ? state.calendar.options.dateConfig
    : {};

  if (typeof state.calendar.updateOptions === 'function') {
    state.calendar.updateOptions({
      monthsToShow: targetMonthsToShow,
      dateConfig: currentDateConfig,
    });
  } else {
    state.calendar.options = {
      ...state.calendar.options,
      monthsToShow: targetMonthsToShow,
      dateConfig: currentDateConfig,
    };

    if (typeof state.calendar.renderCalendarInPopup === 'function') {
      state.calendar.renderCalendarInPopup();
    }
  }

  refreshCheckoutCalendarAvailability(state, true);
}

function scheduleCheckoutQuoteCheck(state) {
  if (state.quoteTimer) {
    window.clearTimeout(state.quoteTimer);
  }

  state.quoteTimer = window.setTimeout(() => {
    runCheckoutQuoteCheck(state);
  }, QUOTE_DEBOUNCE_MS);
}

function runCheckoutQuoteCheck(state) {
  const quoteUrl = buildBookingQuoteUrl();
  if (!quoteUrl || !state.propertyId || !state.checkIn || !state.checkOut) {
    setCheckoutStatus(state, 'error', 'error');
    return;
  }

  const requestToken = Number(state.quoteRequestToken || 0) + 1;
  state.quoteRequestToken = requestToken;
  setCheckoutStatus(state, 'checking', 'loading');
  renderCheckoutTotals(state, null, null);

  fetch(quoteUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      session_token: state.sessionToken || readBookingSessionCookie(),
      property_id: state.propertyId,
      check_in: state.checkIn,
      check_out: state.checkOut,
      guests: normalizeGuestCount(state.guestsSelect.value),
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
      state.quoteData = payload;

      if (payload.available !== true) {
        renderCheckoutTotals(state, null, null);
        renderCheckoutPaymentSchedule(state, null);
        setCheckoutStatus(state, 'unavailable', 'error');
        return;
      }

      renderCheckoutTotals(state, payload.totals, payload.payableAmount ?? payload.payable_amount ?? null);
      renderCheckoutPaymentSchedule(
        state,
        payload.depositAmount ?? payload.deposit_amount ?? payload.payableAmount ?? payload.payable_amount ?? null,
      );
      setCheckoutStatus(state, 'available', 'success');
    })
    .catch((error) => {
      if (Number(state.quoteRequestToken) !== requestToken) {
        return;
      }

      console.warn('[barefoot-engine] Booking checkout quote failed.', error);
      state.quoteData = null;
      renderCheckoutTotals(state, null, null);
      renderCheckoutPaymentSchedule(state, null);
      setCheckoutStatus(state, 'error', 'error');
    });
}

function createCheckoutSession(state) {
  if (!state.quoteData || state.quoteData.available !== true) {
    setCheckoutNotice(state, 'Please select available dates before continuing.', 'error');
    return;
  }

  const guest = readGuestForm(state.guestForm);
  if (guest instanceof Error) {
    setCheckoutNotice(state, guest.message, 'error');
    return;
  }

  const sessionUrl = buildCheckoutSessionUrl();
  if (!sessionUrl) {
    setCheckoutNotice(state, state.labels.error, 'error');
    return;
  }

  setCheckoutNotice(state, state.labels.processingPayment, 'loading');
  state.proceedButton.disabled = true;

  fetch(sessionUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      property_id: state.propertyId,
      check_in: state.checkIn,
      check_out: state.checkOut,
      guests: normalizeGuestCount(state.guestsSelect.value),
      reztypeid: state.reztypeid,
      payment_mode: state.paymentMode,
      portal_id: state.portalId,
      source_of_business: state.sourceOfBusiness,
      guest,
      quote: state.quoteData,
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
      const payload = isPlainObject(response?.data) ? response.data : {};
      if (payload.available !== true || !payload.sessionToken) {
        setCheckoutNotice(state, state.labels.unavailable, 'error');
        return;
      }

      const payableAmount = resolveCheckoutAmount(payload.payableAmount, payload?.totals?.grand_total);
      const depositAmount = resolveCheckoutAmount(payload.depositAmount, payableAmount);

      state.sessionToken = String(payload.sessionToken);
      writeBookingSessionCookie(state.sessionToken);
      renderCheckoutTotals(state, payload.totals, payableAmount);
      renderCheckoutPaymentSchedule(state, depositAmount);
      openPaymentStep(state);
      syncCheckoutUrlParams(state);
      setCheckoutNotice(state, state.labels.sessionReady, 'success');
    })
    .catch((error) => {
      console.warn('[barefoot-engine] Booking checkout session failed.', error);
      const message = error instanceof Error ? error.message : state.labels.error;
      if (typeof message === 'string' && message.toLowerCase().includes('expired')) {
        state.sessionToken = '';
        clearBookingSessionCookie();
        syncCheckoutUrlParams(state);
      }
      setCheckoutNotice(state, message, 'error');
    })
    .finally(() => {
      state.proceedButton.disabled = false;
    });
}

function completeCheckoutSession(state) {
  if (!state.sessionToken) {
    setCheckoutNotice(state, 'Please confirm your stay details again before paying.', 'error');
    return;
  }

  const payment = readPaymentForm(state.paymentForm);
  if (payment instanceof Error) {
    setCheckoutNotice(state, payment.message, 'error');
    return;
  }

  const completeUrl = buildCheckoutCompleteUrl();
  if (!completeUrl) {
    setCheckoutNotice(state, state.labels.error, 'error');
    return;
  }

  setCheckoutNotice(state, state.labels.processingPayment, 'loading');
  state.payButton.disabled = true;
  state.backButton.disabled = true;

  fetch(completeUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      session_token: state.sessionToken,
      property_id: state.propertyId,
      check_in: state.checkIn,
      check_out: state.checkOut,
      guests: normalizeGuestCount(state.guestsSelect.value),
      payment,
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
      const payload = isPlainObject(response?.data) ? response.data : {};
      state.sessionToken = '';
      clearBookingSessionCookie();
      syncCheckoutUrlParams(state);
      const confirmationUrl = sanitizeText(payload?.confirmationUrl, '');
      if (confirmationUrl) {
        window.location.assign(confirmationUrl);
        return;
      }

      renderCheckoutSuccess(state, payload);
      setCheckoutNotice(state, state.labels.paymentSuccessBody, 'success');
    })
    .catch((error) => {
      console.warn('[barefoot-engine] Booking checkout completion failed.', error);
      setCheckoutNotice(state, error instanceof Error ? error.message : state.labels.error, 'error');
    })
    .finally(() => {
      state.payButton.disabled = false;
      state.backButton.disabled = false;
    });
}

function renderCheckoutSuccess(state, payload) {
  state.successPanel.hidden = false;
  state.guestForm.closest('[data-be-booking-checkout-step="guest"]')?.setAttribute('hidden', 'hidden');
  state.paymentStep.setAttribute('hidden', 'hidden');
  setDateModalVisibility(state, false);
  setGuestPopoverVisibility(state, false);
  state.successAmountNode.textContent = formatCurrency(payload?.payableAmount ?? payload?.amount ?? 0, state.currency);
  state.successFolioNode.textContent = sanitizeText(payload?.folioId, '—');
}

function openPaymentStep(state) {
  state.paymentStep.classList.remove('barefoot-engine-booking-checkout__step--disabled');
  state.paymentStep.setAttribute('aria-disabled', 'false');
  setDateModalVisibility(state, false);
  setGuestPopoverVisibility(state, false);
  state.backButton.disabled = false;
  state.payButton.disabled = false;
}

function resetCheckoutSession(state, preserveNotice) {
  state.sessionToken = readBookingSessionCookie();
  state.paymentStep.classList.add('barefoot-engine-booking-checkout__step--disabled');
  state.paymentStep.setAttribute('aria-disabled', 'true');
  state.backButton.disabled = true;
  state.payButton.disabled = true;
  state.depositRow.hidden = true;
  state.depositValueNode.textContent = '';
  syncCheckoutUrlParams(state);
  if (!preserveNotice) {
    setCheckoutNotice(state, '', 'idle');
  }
}

function renderCheckoutPaymentSchedule(state, depositAmount) {
  const normalizedDeposit = Number(depositAmount);
  if (!Number.isFinite(normalizedDeposit) || normalizedDeposit <= 0) {
    state.depositRow.hidden = true;
    state.depositValueNode.textContent = '';
    return;
  }

  state.depositRow.hidden = false;
  state.depositValueNode.textContent = formatCurrency(normalizedDeposit, state.currency);
}

function renderCheckoutSummaryMeta(state) {
  if (state.checkIn && state.checkOut) {
    state.compactDatesValueNode.textContent = `${formatCompactDate(state.checkIn)} — ${formatCompactDate(state.checkOut)}`;
  } else {
    state.compactDatesValueNode.textContent = state.labels.noDatesSelected;
  }

  state.compactGuestsValueNode.textContent = formatGuestSummary(
    normalizeGuestCount(state.guestsSelect?.value),
    state.labels
  );
}

function renderCheckoutTotals(state, totals, payableAmount) {
  if (!isPlainObject(totals)) {
    state.dailyValueNode.textContent = '';
    state.subtotalLabelNode.textContent = state.labels.subtotal;
    state.subtotalValueNode.textContent = '';
    state.taxValueNode.textContent = '';
    state.totalValueNode.textContent = '';
    state.payableValueNode.textContent = '';
    state.payableFooterNode.hidden = true;
    return;
  }

  const dailyPrice = Number(totals.daily_price);
  const subtotal = Number(totals.subtotal);
  const taxTotal = Number(totals.tax_total);
  const grandTotal = Number(totals.grand_total);
  const nights = Number(totals.nights);
  const payable = resolveCheckoutAmount(payableAmount, grandTotal);

  state.dailyValueNode.textContent = formatCurrency(dailyPrice, state.currency);
  state.subtotalLabelNode.textContent = formatCheckoutSubtotalLabel(state.labels.subtotal, nights);
  state.subtotalValueNode.textContent = formatCurrency(subtotal, state.currency);
  state.taxValueNode.textContent = formatCurrency(taxTotal, state.currency);
  state.totalValueNode.textContent = formatCurrency(grandTotal, state.currency);
  state.payableValueNode.textContent = formatCurrency(payable, state.currency);
  state.payableFooterNode.hidden = !Number.isFinite(payable)
    || !Number.isFinite(grandTotal)
    || Math.abs(payable - grandTotal) < 0.005;

  const payLabel = Number.isFinite(payable) && payable > 0
    ? `${state.labels.paySecurely} (${formatCurrency(payable, state.currency)})`
    : state.labels.paySecurely;
  state.payButton.textContent = payLabel;
}

function refreshCheckoutCalendarAvailability(state, forceFetch = false) {
  if (!state || !state.calendar || !state.propertyId) {
    return;
  }

  const calendarUrl = buildBookingCalendarUrl();
  if (!calendarUrl) {
    return;
  }

  const range = resolveCalendarRange(state.calendar);
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
      console.warn('[barefoot-engine] Booking checkout calendar request failed.', error);
    })
    .finally(() => {
      if (state.calendarFetchInFlightKey === rangeKey) {
        state.calendarFetchInFlightKey = '';
      }
    });
}

function applyDisabledDatesForRange(state, monthStart, monthEnd, disabledDates, dailyPrices = {}) {
  removeDateRangeFromDisabledSet(state.disabledDates, monthStart, monthEnd);
  removeDateRangeFromPriceMap(state.dailyPrices, monthStart, monthEnd);
  const pricedDatesInWindow = new Set();

  disabledDates.forEach((value) => {
    const normalized = normalizeDateInput(value);
    if (!isValidDateString(normalized) || normalized < monthStart || normalized > monthEnd) {
      return;
    }

    state.disabledDates.add(normalized);
  });

  Object.entries(dailyPrices).forEach(([dateKey, amountValue]) => {
    const normalized = normalizeDateInput(dateKey);
    const amount = Number(amountValue);

    if (
      !isValidDateString(normalized)
      || normalized < monthStart
      || normalized > monthEnd
      || !Number.isFinite(amount)
      || amount <= 0
    ) {
      return;
    }

    state.dailyPrices.set(normalized, amount);
    pricedDatesInWindow.add(normalized);
  });

  if (pricedDatesInWindow.size > 0) {
    let cursor = parseYmdDate(monthStart);
    const end = parseYmdDate(monthEnd);

    if (cursor instanceof Date && end instanceof Date) {
      while (cursor.getTime() <= end.getTime()) {
        const dateKey = formatDateToYmd(cursor);
        if (!pricedDatesInWindow.has(dateKey)) {
          state.disabledDates.add(dateKey);
        }

        cursor = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + 1);
      }
    }
  }

  const dateConfig = {};

  state.disabledDates.forEach((disabledDate) => {
    dateConfig[disabledDate] = {
      date: disabledDate,
      isDisabled: true,
    };
  });

  state.dailyPrices.forEach((amount, dateKey) => {
    const currentConfig = isPlainObject(dateConfig[dateKey]) ? dateConfig[dateKey] : { date: dateKey };
    dateConfig[dateKey] = {
      ...currentConfig,
      date: dateKey,
      price: formatCalendarPrice(amount, state.currency),
    };
  });

  applyCalendarDateConfig(state, dateConfig);
}

function applyCalendarDateConfig(state, dateConfig) {
  if (!state || !state.calendar || !isPlainObject(dateConfig)) {
    return;
  }

  const calendar = state.calendar;
  const isDatepickerMode = calendar.options?.mode === 'datepicker';

  if (!isDatepickerMode || typeof calendar.renderCalendarInPopup !== 'function') {
    if (typeof calendar.updateOptions === 'function') {
      calendar.updateOptions({ dateConfig });
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

function patchCalendarPopupRender(state) {
  if (!state || !state.calendar || typeof state.calendar.renderCalendarInPopup !== 'function') {
    return;
  }

  const originalRenderCalendarInPopup = state.calendar.renderCalendarInPopup.bind(state.calendar);
  state.calendar.renderCalendarInPopup = (...args) => {
    const result = originalRenderCalendarInPopup(...args);
    syncCheckoutCalendarClearVisibility(state);
    refreshCheckoutCalendarAvailability(state, false);
    return result;
  };
}

function syncCheckoutCalendarClearVisibility(state) {
  const clearButtons = document.querySelectorAll('.bp-calendar-clear');
  clearButtons.forEach((buttonNode) => {
    if (!(buttonNode instanceof HTMLElement)) {
      return;
    }

    buttonNode.style.display = state.datesModalOpen ? 'none' : '';
  });

  const clearWrappers = document.querySelectorAll('.bp-calendar-clear-wrapper');
  clearWrappers.forEach((wrapperNode) => {
    if (!(wrapperNode instanceof HTMLElement)) {
      return;
    }

    wrapperNode.style.display = state.datesModalOpen ? 'none' : '';
  });
}

function setCheckoutStatus(state, labelKey, type, customMessage = '') {
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

function setCheckoutNotice(state, message, type) {
  state.noticeNode.textContent = message || '';
  state.noticeNode.dataset.state = type || 'idle';
  state.noticeNode.hidden = !message;
}

function readGuestForm(form) {
  const data = new FormData(form);
  const guest = {
    first_name: sanitizeText(data.get('first_name'), ''),
    last_name: sanitizeText(data.get('last_name'), ''),
    email: sanitizeText(data.get('email'), ''),
    cell_phone: sanitizeText(data.get('cell_phone'), ''),
    address_1: sanitizeText(data.get('address_1'), ''),
    address_2: sanitizeText(data.get('address_2'), ''),
    city: sanitizeText(data.get('city'), ''),
    state: sanitizeText(data.get('state'), ''),
    country: sanitizeText(data.get('country'), ''),
    postal_code: sanitizeText(data.get('postal_code'), ''),
    age_confirmed: data.get('age_confirmed') === '1',
  };

  const required = [
    ['first_name', 'First name is required.'],
    ['last_name', 'Last name is required.'],
    ['email', 'Email is required.'],
    ['cell_phone', 'Cell phone is required.'],
    ['address_1', 'Address 1 is required.'],
    ['city', 'City is required.'],
    ['state', 'State is required.'],
    ['country', 'Country is required.'],
    ['postal_code', 'Postal code is required.'],
  ];

  for (const [key, message] of required) {
    if (!guest[key]) {
      return new Error(message);
    }
  }

  if (!guest.age_confirmed) {
    return new Error('Please confirm the primary guest age requirement.');
  }

  return guest;
}

function readPaymentForm(form) {
  const data = new FormData(form);
  const payment = {
    card_number: sanitizeText(data.get('card_number'), ''),
    expiry_month: sanitizeText(data.get('expiry_month'), ''),
    expiry_year: sanitizeText(data.get('expiry_year'), ''),
    cvv: sanitizeText(data.get('cvv'), ''),
    name_on_card: sanitizeText(data.get('name_on_card'), ''),
  };

  if (!payment.card_number) {
    return new Error('Card number is required.');
  }

  if (!payment.expiry_month) {
    return new Error('Expiration month is required.');
  }

  if (!payment.expiry_year) {
    return new Error('Expiration year is required.');
  }

  if (!payment.cvv) {
    return new Error('CVV is required.');
  }

  if (!payment.name_on_card) {
    return new Error('Name on card is required.');
  }

  return payment;
}

function parseCheckoutSearchState(search) {
  const params = new URLSearchParams(search || '');
  const querySessionToken = sanitizeText(params.get('booking_session'), '');
  const cookieSessionToken = readBookingSessionCookie();

  return {
    checkIn: normalizeDateInput(params.get('check_in')),
    checkOut: normalizeDateInput(params.get('check_out')),
    guests: sanitizeText(params.get('guests'), ''),
    sessionToken: querySessionToken || cookieSessionToken,
  };
}

function syncCheckoutUrlParams(state) {
  const url = new URL(window.location.href);
  const params = new URLSearchParams(url.search);

  if (state.checkIn) {
    params.set('check_in', state.checkIn);
  } else {
    params.delete('check_in');
  }

  if (state.checkOut) {
    params.set('check_out', state.checkOut);
  } else {
    params.delete('check_out');
  }

  const guestsValue = state.guestsSelect instanceof HTMLSelectElement ? String(state.guestsSelect.value || '').trim() : '';
  if (guestsValue) {
    params.set('guests', guestsValue);
  } else {
    params.delete('guests');
  }

  if (state.sessionToken) {
    params.set('booking_session', state.sessionToken);
  } else {
    params.delete('booking_session');
  }

  url.search = params.toString();
  window.history.replaceState({}, '', url.toString());
}

function resolveCalendarRange(calendar) {
  if (!calendar) {
    return null;
  }

  const currentDate = calendar.currentDate instanceof Date ? new Date(calendar.currentDate) : new Date();
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

function buildBookingCalendarUrl() {
  const bootstrap = getPublicBootstrap();
  if (!bootstrap.restBase) {
    return '';
  }

  return new URL(bootstrap.bookingCalendarEndpoint || 'booking/calendar', bootstrap.restBase).toString();
}

function buildBookingQuoteUrl() {
  const bootstrap = getPublicBootstrap();
  if (!bootstrap.restBase) {
    return '';
  }

  return new URL(bootstrap.bookingQuoteEndpoint || 'booking/quote', bootstrap.restBase).toString();
}

function buildCheckoutSessionUrl() {
  const bootstrap = getPublicBootstrap();
  if (!bootstrap.restBase) {
    return '';
  }

  return new URL(bootstrap.bookingCheckoutSessionEndpoint || 'booking-checkout/session', bootstrap.restBase).toString();
}

function buildCheckoutCompleteUrl() {
  const bootstrap = getPublicBootstrap();
  if (!bootstrap.restBase) {
    return '';
  }

  return new URL(bootstrap.bookingCheckoutCompleteEndpoint || 'booking-checkout/complete', bootstrap.restBase).toString();
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
    return sanitizeText(decodeURIComponent(value), '');
  }

  return '';
}

function writeBookingSessionCookie(sessionToken) {
  if (typeof document === 'undefined') {
    return;
  }

  const normalizedToken = sanitizeText(sessionToken, '');
  if (!normalizedToken) {
    clearBookingSessionCookie();
    return;
  }

  document.cookie = `${encodeURIComponent(BOOKING_SESSION_COOKIE)}=${encodeURIComponent(normalizedToken)}; path=/; SameSite=Lax`;
}

function clearBookingSessionCookie() {
  if (typeof document === 'undefined') {
    return;
  }

  document.cookie = `${encodeURIComponent(BOOKING_SESSION_COOKIE)}=; path=/; Max-Age=0; SameSite=Lax`;
}

function getPublicBootstrap() {
  return typeof window !== 'undefined' && isPlainObject(window.BarefootEnginePublic)
    ? window.BarefootEnginePublic
    : {};
}

function sanitizePropertySummary(summary) {
  const value = isPlainObject(summary) ? summary : {};
  const stats = isPlainObject(value.stats) ? value.stats : {};

  return {
    title: sanitizeText(value.title, 'Property'),
    address: sanitizeText(value.address, ''),
    imageUrl: sanitizeText(value.imageUrl, ''),
    permalink: sanitizeText(value.permalink, ''),
    stats: {
      sleeps: sanitizeText(stats.sleeps, '—'),
      bedrooms: sanitizeText(stats.bedrooms, '—'),
      bathrooms: sanitizeText(stats.bathrooms, '—'),
    },
  };
}

function buildCheckoutLabels(inputLabels) {
  const labels = isPlainObject(inputLabels) ? inputLabels : {};
  return {
    title: sanitizeText(labels.title, 'Complete Your Booking'),
    summaryTitle: sanitizeText(labels.summaryTitle, 'Payable Amount'),
    guestStepTitle: sanitizeText(labels.guestStepTitle, 'Primary Guest Details'),
    paymentStepTitle: sanitizeText(labels.paymentStepTitle, 'Payment Options'),
    proceedToPay: sanitizeText(labels.proceedToPay, 'Proceed to Pay'),
    paySecurely: sanitizeText(labels.paySecurely, 'Pay Securely'),
    goBack: sanitizeText(labels.goBack, 'Go Back'),
    listingDetails: sanitizeText(labels.listingDetails, 'See listing details'),
    dates: sanitizeText(labels.dates, 'Dates'),
    guests: sanitizeText(labels.guests, 'Guests'),
    changeDates: sanitizeText(labels.changeDates, 'Change dates'),
    changeGuests: sanitizeText(labels.changeGuests, 'Change guests'),
    closeEditor: sanitizeText(labels.closeEditor, 'Close'),
    changeDatesModalTitle: sanitizeText(labels.changeDatesModalTitle, 'Change dates'),
    modalApply: sanitizeText(labels.modalApply, 'Apply'),
    modalCancel: sanitizeText(labels.modalCancel, 'Cancel'),
    modalClear: sanitizeText(labels.modalClear, 'Clear'),
    guestIncrement: sanitizeText(labels.guestIncrement, 'Increase guests'),
    guestDecrement: sanitizeText(labels.guestDecrement, 'Decrease guests'),
    applyGuestChange: sanitizeText(labels.applyGuestChange, 'Apply'),
    checkIn: sanitizeText(labels.checkIn, 'Check-In'),
    checkOut: sanitizeText(labels.checkOut, 'Check-Out'),
    guestCount: sanitizeText(labels.guestCount, 'Guest'),
    noDatesSelected: sanitizeText(labels.noDatesSelected, 'No dates selected'),
    adultSingular: sanitizeText(labels.adultSingular, 'adult'),
    adultPlural: sanitizeText(labels.adultPlural, 'adults'),
    rent: sanitizeText(labels.rent, 'Rent'),
    subtotal: sanitizeText(labels.subtotal, 'Subtotal'),
    tax: sanitizeText(labels.tax, 'Tax'),
    total: sanitizeText(labels.total, 'Total'),
    depositAmount: sanitizeText(labels.depositAmount, 'Deposit Amount'),
    payableAmount: sanitizeText(labels.payableAmount, 'Payable Amount'),
    checking: sanitizeText(labels.checking, 'Checking live availability...'),
    available: sanitizeText(labels.available, 'Selected dates are available.'),
    unavailable: sanitizeText(labels.unavailable, 'Selected dates are unavailable.'),
    error: sanitizeText(labels.error, 'We could not update this booking quote right now. Please try again.'),
    idle: sanitizeText(labels.idle, 'No booking information is active.'),
    sessionReady: sanitizeText(labels.sessionReady, 'Your booking details are ready for payment.'),
    processingPayment: sanitizeText(labels.processingPayment, 'Processing booking...'),
    paymentSuccessTitle: sanitizeText(labels.paymentSuccessTitle, 'Booking Confirmed'),
    paymentSuccessBody: sanitizeText(labels.paymentSuccessBody, 'Your reservation was created successfully.'),
    missingContext: sanitizeText(labels.missingContext, 'Property context is required to load checkout.'),
    ageConfirmation: sanitizeText(labels.ageConfirmation, 'I confirm the primary guest checking in is 25 years of age or older.'),
    termsPrefix: sanitizeText(labels.termsPrefix, 'By submitting this form, you agree to this listing’s'),
    termsLinkText: sanitizeText(labels.termsLinkText, 'terms and conditions'),
    rentalAgreementPrefix: sanitizeText(labels.rentalAgreementPrefix, 'By submitting this form, you agree to abide by the terms and conditions in the rental agreement.'),
    rentalAgreementLinkText: sanitizeText(labels.rentalAgreementLinkText, 'View rental agreement.'),
  };
}

function buildCheckoutLinks(inputLinks) {
  const links = isPlainObject(inputLinks) ? inputLinks : {};
  return {
    termsUrl: sanitizeText(links.termsUrl, ''),
    rentalAgreementUrl: sanitizeText(links.rentalAgreementUrl, ''),
  };
}

function sanitizeCalendarOptions(calendarOptions) {
  const options = isPlainObject(calendarOptions) ? calendarOptions : {};
  const monthsToShow = Number(options.monthsToShow);
  const defaultMinDays = Number(options.defaultMinDays);

  return {
    monthsToShow: Number.isFinite(monthsToShow) ? Math.max(1, Math.min(6, Math.round(monthsToShow))) : 2,
    datepickerPlacement: options.datepickerPlacement === 'default' ? 'default' : 'auto',
    defaultMinDays: Number.isFinite(defaultMinDays) ? Math.max(1, Math.round(defaultMinDays)) : 1,
    tooltipLabel: sanitizeText(options.tooltipLabel, 'Nights'),
    showTooltip: options.showTooltip !== false,
    showClearButton: options.showClearButton !== false,
  };
}

function sanitizeGuestOptions(value) {
  const options = Array.isArray(value) ? value : ['1', '2', '3', '4', '5', '6', '7', '8+'];
  const normalized = options
    .map((optionValue) => String(optionValue ?? '').trim())
    .filter(Boolean);

  return normalized.length > 0 ? Array.from(new Set(normalized)) : ['1', '2', '3', '4', '5', '6', '7', '8+'];
}

function buildGuestOptionBounds(options) {
  const numericOptions = (Array.isArray(options) ? options : [])
    .map((option) => normalizeGuestCount(option))
    .filter((value, index, values) => Number.isFinite(value) && values.indexOf(value) === index)
    .sort((left, right) => left - right);

  if (numericOptions.length === 0) {
    return {
      min: 1,
      max: 8,
    };
  }

  return {
    min: numericOptions[0],
    max: numericOptions[numericOptions.length - 1],
  };
}

function coerceGuestCountIntoBounds(value, min, max) {
  const normalized = normalizeGuestCount(value);
  const lowerBound = Number.isFinite(min) ? Math.max(1, Math.round(min)) : 1;
  const upperBound = Number.isFinite(max) ? Math.max(lowerBound, Math.round(max)) : Math.max(8, lowerBound);
  return Math.min(upperBound, Math.max(lowerBound, normalized));
}

function resolveGuestOptionForCount(options, count) {
  const optionValues = Array.from(options || [])
    .map((option) => sanitizeText(option?.value, ''))
    .filter(Boolean);

  if (optionValues.length === 0) {
    return '';
  }

  const normalizedCount = normalizeGuestCount(count);
  const directMatch = optionValues.find((value) => normalizeGuestCount(value) === normalizedCount);
  if (directMatch) {
    return directMatch;
  }

  const sortedByDistance = optionValues
    .map((value) => ({
      value,
      numeric: normalizeGuestCount(value),
    }))
    .sort((left, right) => Math.abs(left.numeric - normalizedCount) - Math.abs(right.numeric - normalizedCount));

  return sortedByDistance[0]?.value || optionValues[0];
}

function sanitizePaymentMode(value, fallback) {
  const normalized = String(value ?? '').trim().toUpperCase();
  return ['ON', 'TRUE', 'FALSE'].includes(normalized) ? normalized : fallback;
}

function resolveInitialGuestValue(options, preferred) {
  const normalizedPreferred = String(preferred ?? '').trim();
  if (normalizedPreferred && options.includes(normalizedPreferred)) {
    return normalizedPreferred;
  }

  return options[0] || '1';
}

function removeDateRangeFromDisabledSet(disabledDates, startDate, endDate) {
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

function normalizeDateInput(value) {
  if (typeof value !== 'string') {
    return '';
  }

  const normalized = value.trim();
  return /^\d{4}-\d{2}-\d{2}$/.test(normalized) ? normalized : '';
}

function isValidDateString(value) {
  return /^\d{4}-\d{2}-\d{2}$/.test(String(value || ''));
}

function parseYmdDate(value) {
  if (!isValidDateString(value)) {
    return null;
  }

  const [year, month, day] = String(value).split('-').map((part) => Number(part));
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

function formatStayDate(value) {
  const date = parseYmdDate(value);
  if (!(date instanceof Date)) {
    return '—';
  }

  return date.toLocaleDateString(undefined, {
    day: 'numeric',
    month: 'short',
    weekday: 'short',
  });
}

function formatCompactDate(value) {
  const date = parseYmdDate(value);
  if (!(date instanceof Date)) {
    return '—';
  }

  return date.toLocaleDateString(undefined, {
    day: 'numeric',
    month: 'short',
  });
}

function formatGuestSummary(count, labels) {
  const normalizedCount = Number.isFinite(Number(count)) ? Math.max(1, Math.round(Number(count))) : 1;
  const noun = normalizedCount === 1 ? labels.adultSingular : labels.adultPlural;

  return `${normalizedCount} ${noun}`;
}

function formatCurrency(value, currency) {
  const amount = Number(value);
  if (!Number.isFinite(amount)) {
    return '';
  }

  return `${sanitizeText(currency, '$')}${amount.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

function formatCalendarPrice(value, currency) {
  const amount = Number(value);
  const rounded = Number.isFinite(amount) ? Math.round(amount) : 0;
  return `${sanitizeText(currency, '$')}${rounded.toLocaleString(undefined, {
    maximumFractionDigits: 0,
  })}`;
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

function resolveCheckoutAmount(value, fallback = 0) {
  const normalizedValue = Number(value);
  if (Number.isFinite(normalizedValue) && normalizedValue > 0) {
    return normalizedValue;
  }

  const normalizedFallback = Number(fallback);
  if (Number.isFinite(normalizedFallback) && normalizedFallback > 0) {
    return normalizedFallback;
  }

  return 0;
}

function formatCheckoutSubtotalLabel(baseLabel, nights) {
  const normalizedLabel = sanitizeText(baseLabel, 'Subtotal');
  const totalNights = Number(nights);

  if (!Number.isFinite(totalNights) || totalNights <= 0) {
    return normalizedLabel;
  }

  const roundedNights = Math.round(totalNights);
  const nightsLabel = roundedNights === 1 ? 'night' : 'nights';

  return `${normalizedLabel} for ${roundedNights} ${nightsLabel}`;
}

function sanitizePositiveInt(value, fallback = 0) {
  const normalized = Number(value);
  return Number.isFinite(normalized) && normalized > 0 ? Math.floor(normalized) : fallback;
}

function sanitizeText(value, fallback = '') {
  if (typeof value !== 'string' && typeof value !== 'number') {
    return fallback;
  }

  const normalized = String(value).trim();
  return normalized || fallback;
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

function isPlainObject(value) {
  return Object.prototype.toString.call(value) === '[object Object]';
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
