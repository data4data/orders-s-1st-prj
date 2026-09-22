import { t } from '../../shared/i18n/translate.js';
import { setBusy } from './busy.js';

/**
 * Form validation standard (pages.html → UI standards → Form fields), for <form data-validate novalidate>:
 * red border + icon + a short message under the field; checked on blur and submit, then live while
 * typing; focus + scroll to the first error; optional error summary (data-error-summary="#id").
 * The server stays the source of truth: its errors (Symfony form theme) use the same markup.
 */

const FIELDS = 'input:not([type=hidden]):not([type=submit]):not([type=button]), select, textarea';

/** @param {HTMLInputElement} field */
function messageFor(field) {
    const { validity, dataset } = field;
    if (validity.valueMissing) return dataset.msgRequired ?? t('form.required');
    if (validity.typeMismatch && field.type === 'email') return dataset.msgEmail ?? t('form.email');
    if (validity.patternMismatch) return dataset.msgPattern ?? t('form.pattern');
    if (validity.tooShort) return dataset.msgMinlength ?? t('form.minlength', { min: field.minLength });
    if (validity.tooLong) return dataset.msgMaxlength ?? t('form.maxlength', { max: field.maxLength });
    return field.validationMessage;
}

/** @param {HTMLElement} field */
function feedbackFor(field) {
    const container = field.closest('.mb-3, .form-group, [class*="col"]') ?? field.parentElement;
    let feedback = container?.querySelector(':scope > .invalid-feedback, .invalid-feedback');
    if (!feedback) {
        feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        field.insertAdjacentElement('afterend', feedback);
    }
    if (!feedback.id) {
        feedback.id = `${field.id || field.name}-error`;
    }
    return feedback;
}

/**
 * @param {HTMLElement} field
 * @param {string} message
 */
export function showFieldError(field, message) {
    const feedback = feedbackFor(field);
    feedback.textContent = message;
    feedback.classList.add('d-block');
    field.classList.remove('is-valid');
    field.classList.add('is-invalid');
    field.setAttribute('aria-invalid', 'true');
    field.setAttribute('aria-describedby', feedback.id);
}

/** @param {HTMLElement} field */
export function clearFieldError(field) {
    const wasInvalid = field.classList.contains('is-invalid');
    field.classList.remove('is-invalid');
    field.removeAttribute('aria-invalid');
    if (wasInvalid) {
        field.classList.add('is-valid');
        feedbackFor(field).classList.remove('d-block');
        feedbackFor(field).textContent = '';
    }
}

/** @param {HTMLInputElement} field */
function validate(field) {
    if (field.checkValidity()) {
        clearFieldError(field);
        return true;
    }
    showFieldError(field, messageFor(field));
    return false;
}

/** @param {HTMLElement} field */
function labelOf(field) {
    const label = field.id ? document.querySelector(`label[for="${field.id}"]`) : null;
    return (label?.textContent ?? field.getAttribute('name') ?? '').replace('*', '').trim();
}

/**
 * @param {HTMLFormElement} form
 * @param {HTMLElement[]} invalid
 */
export function renderSummary(form, invalid) {
    const selector = form.dataset.errorSummary;
    const container = selector ? document.querySelector(selector) : null;
    if (!container) {
        return;
    }
    container.innerHTML = '';
    if (invalid.length === 0) {
        return;
    }
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger py-2';
    alert.setAttribute('role', 'alert');
    const strong = document.createElement('strong');
    strong.textContent = `${t('form.summary', { count: invalid.length })} `;
    alert.append(strong);
    invalid.forEach((field, index) => {
        const link = document.createElement('a');
        link.href = `#${field.id}`;
        link.className = 'alert-link fw-normal';
        link.textContent = labelOf(field);
        link.addEventListener('click', (event) => {
            event.preventDefault();
            field.focus();
        });
        alert.append(index ? ' · ' : '', link);
    });
    container.append(alert);
}

/** @param {HTMLFormElement} form */
export function installValidation(form) {
    form.noValidate = true;
    const fields = () => /** @type {HTMLInputElement[]} */ ([...form.querySelectorAll(FIELDS)]);

    form.addEventListener('focusout', (event) => {
        const field = /** @type {HTMLInputElement} */ (event.target);
        // Moving straight to a button of this form: the submit checks everything anyway, and showing
        // the message now would shift the button away from under the pointer (the click would miss).
        const next = /** @type {HTMLElement|null} */ (event.relatedTarget);
        if (next?.closest('button') && form.contains(next)) {
            return;
        }
        if (field.matches?.(FIELDS) && (field.value !== '' || field.dataset.touched)) {
            field.dataset.touched = '1';
            validate(field);
        }
    });
    form.addEventListener('input', (event) => {
        const field = /** @type {HTMLInputElement} */ (event.target);
        field.dataset.touched = '1';
        if (field.classList.contains('is-invalid') || field.classList.contains('is-valid')) {
            validate(field);
        }
    });
    form.addEventListener('submit', (event) => {
        const submitter = /** @type {HTMLButtonElement|null} */ (event.submitter);
        if (!submitter?.hasAttribute('formnovalidate')) {
            const invalid = fields().filter((field) => !validate(field));
            renderSummary(form, invalid);
            if (invalid.length) {
                event.preventDefault();
                invalid[0].focus();
                invalid[0].scrollIntoView({ block: 'center', behavior: 'smooth' });
                return;
            }
        }
        form.dataset.submitting = '1';
        if (submitter) {
            setBusy(submitter, true);
        }
    });

    // Errors rendered by the server (Symfony form theme): same summary and focus.
    const serverErrors = fields().filter((field) => field.classList.contains('is-invalid'));
    if (serverErrors.length) {
        renderSummary(form, serverErrors);
        serverErrors[0].focus();
    }
}

/**
 * Maps problem+json violations onto form fields (by name, including Symfony's form[name] style).
 *
 * @param {HTMLFormElement} form
 * @param {{propertyPath: string, message: string}[]} violations
 * @returns {{propertyPath: string, message: string}[]} violations without a matching field
 */
export function applyViolations(form, violations) {
    const unmatched = [];
    const invalid = [];
    for (const violation of violations) {
        const field = form.querySelector(`[name="${violation.propertyPath}"], [name$="[${violation.propertyPath}]"]`);
        if (field instanceof HTMLElement) {
            showFieldError(field, violation.message);
            invalid.push(field);
        } else {
            unmatched.push(violation);
        }
    }
    renderSummary(form, invalid);
    invalid[0]?.focus();
    return unmatched;
}

/** Full-page forms without client checks still get the busy spinner. */
export function installBusyOnSubmit(form) {
    form.addEventListener('submit', (event) => {
        const submitter = /** @type {HTMLButtonElement|null} */ (event.submitter);
        if (!event.defaultPrevented && submitter?.hasAttribute('data-busy-on-submit')) {
            setBusy(submitter, true);
        }
    });
}
