<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Booking Checkout Preset Structure
|--------------------------------------------------------------------------
|
| Each top-level key is a preset id, for example:
| - 'default'
| - 'property-checkout'
|
| Use the preset id with:
| [barefoot_booking_checkout widget_id="default"]
|
| Available preset options:
|
| 'currency' => string
|   Display currency symbol or prefix.
|   Example: '$'
|
| 'reztypeid' => int
|   Barefoot reservation type ID used for checkout.
|
| 'paymentMode' => string
|   Barefoot booking mode. Allowed: 'ON', 'TRUE', 'FALSE'
|   Default is 'ON' for safe checkout testing.
|
| 'portalId' => string
|   Optional Barefoot portal / source id passed to PropertyBookingNew.
|
| 'sourceOfBusiness' => string
|   Optional SOB passed to SetConsumerInfo.
|
| 'calendarOptions' => [
|   'monthsToShow' => int,            // 1 to 6
|   'datepickerPlacement' => string,  // 'auto' or 'default'
|   'defaultMinDays' => int,          // minimum nights, 1 or greater
|   'tooltipLabel' => string,         // example: 'Nights'
|   'showTooltip' => bool,
|   'showClearButton' => bool,
| ]
|
| 'guests' => [
|   'label' => string,
|   'placeholder' => string,
|   'defaultValue' => string|int,
|   'options' => array<string>,
| ]
|
| 'links' => [
|   'termsUrl' => string,
|   'rentalAgreementUrl' => string,
| ]
|
| 'labels' => [
|   'title' => string,
|   'summaryTitle' => string,
|   'guestStepTitle' => string,
|   'paymentStepTitle' => string,
|   'proceedToPay' => string,
|   'paySecurely' => string,
|   'goBack' => string,
|   'listingDetails' => string,
|   'dates' => string,
|   'guests' => string,
|   'changeDates' => string,
|   'changeGuests' => string,
|   'changeDatesModalTitle' => string,
|   'modalApply' => string,
|   'modalCancel' => string,
|   'modalClear' => string,
|   'guestIncrement' => string,
|   'guestDecrement' => string,
|   'applyGuestChange' => string,
|   'closeEditor' => string,
|   'checkIn' => string,
|   'checkOut' => string,
|   'guestCount' => string,
|   'noDatesSelected' => string,
|   'adultSingular' => string,
|   'adultPlural' => string,
|   'rent' => string,
|   'tax' => string,
|   'total' => string,
|   'depositAmount' => string,
|   'payableAmount' => string,
|   'checking' => string,
|   'available' => string,
|   'unavailable' => string,
|   'error' => string,
|   'idle' => string,
|   'sessionReady' => string,
|   'processingPayment' => string,
|   'paymentSuccessTitle' => string,
|   'paymentSuccessBody' => string,
|   'missingContext' => string,
|   'ageConfirmation' => string,
|   'termsPrefix' => string,
|   'termsLinkText' => string,
|   'rentalAgreementPrefix' => string,
|   'rentalAgreementLinkText' => string,
| ]
|
*/

return [
    'default' => [
        'currency' => '$',
        'reztypeid' => 26,
        'paymentMode' => 'ON',
        'portalId' => '',
        'sourceOfBusiness' => '',
        'calendarOptions' => [
            'monthsToShow' => 2,
            'datepickerPlacement' => 'auto',
            'defaultMinDays' => 1,
            'tooltipLabel' => 'Nights',
            'showTooltip' => true,
            'showClearButton' => true,
        ],
        'guests' => [
            'label' => 'Guests',
            'placeholder' => 'Select guests',
            'defaultValue' => '2',
            'options' => ['1', '2', '3', '4', '5', '6', '7', '8+'],
        ],
        'links' => [
            'termsUrl' => '',
            'rentalAgreementUrl' => '',
        ],
        'labels' => [
            'title' => 'Complete Your Booking',
            'summaryTitle' => 'Payable Amount',
            'guestStepTitle' => 'Primary Guest Details',
            'paymentStepTitle' => 'Payment Options',
            'proceedToPay' => 'Proceed to Pay',
            'paySecurely' => 'Pay Securely',
            'goBack' => 'Go Back',
            'listingDetails' => 'See listing details',
            'dates' => 'Dates',
            'guests' => 'Guests',
            'changeDates' => 'Change dates',
            'changeGuests' => 'Change guests',
            'changeDatesModalTitle' => 'Change dates',
            'modalApply' => 'Apply',
            'modalCancel' => 'Cancel',
            'modalClear' => 'Clear',
            'guestIncrement' => 'Increase guests',
            'guestDecrement' => 'Decrease guests',
            'applyGuestChange' => 'Apply',
            'closeEditor' => 'Close',
            'checkIn' => 'Check-In',
            'checkOut' => 'Check-Out',
            'guestCount' => 'Guest',
            'noDatesSelected' => 'No dates selected',
            'adultSingular' => 'adult',
            'adultPlural' => 'adults',
            'rent' => 'Rent',
            'tax' => 'Tax',
            'total' => 'Total',
            'depositAmount' => 'Deposit Amount',
            'payableAmount' => 'Payable Amount',
            'checking' => 'Checking live availability...',
            'available' => 'Selected dates are available.',
            'unavailable' => 'Selected dates are unavailable.',
            'error' => 'We could not update this booking quote right now. Please try again.',
            'idle' => 'No booking information is active.',
            'sessionReady' => 'Your booking details are ready for payment.',
            'processingPayment' => 'Processing booking...',
            'paymentSuccessTitle' => 'Booking Confirmed',
            'paymentSuccessBody' => 'Your reservation was created successfully.',
            'missingContext' => 'Property context is required to load checkout.',
            'ageConfirmation' => 'I confirm the primary guest checking in is 25 years of age or older.',
            'termsPrefix' => 'By submitting this form, you agree to this listing\'s',
            'termsLinkText' => 'terms and conditions',
            'rentalAgreementPrefix' => 'By submitting this form, you agree to abide by the terms and conditions in the rental agreement.',
            'rentalAgreementLinkText' => 'View rental agreement.',
        ],
    ],
];
