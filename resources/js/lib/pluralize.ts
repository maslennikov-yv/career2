export function pluralizeRu(
    n: number,
    forms: [one: string, few: string, many: string],
): string {
    const mod10 = n % 10;
    const mod100 = n % 100;

    if (mod10 === 1 && mod100 !== 11) {
        return forms[0];
    }

    if ([2, 3, 4].includes(mod10) && ![12, 13, 14].includes(mod100)) {
        return forms[1];
    }

    return forms[2];
}
