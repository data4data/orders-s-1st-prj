/** An empty address as the API expects it (AddressInput). */
export function emptyAddress(countryCode = 'NL') {
    return {
        label: null,
        firstName: '',
        lastName: '',
        company: '',
        vatId: '',
        street: '',
        houseNumber: '',
        postcode: '',
        city: '',
        countryCode,
        phone: '',
        usableForBilling: true,
        usableForShipping: true,
    };
}

/**
 * One line for an address card: "Damrak 1, 1012 AB Amsterdam, Netherlands".
 *
 * @param {Record<string, any>} address
 */
export function addressLine(address) {
    return `${address.street} ${address.houseNumber}, ${address.postcode} ${address.city}${address.countryName ? `, ${address.countryName}` : ''}`;
}
