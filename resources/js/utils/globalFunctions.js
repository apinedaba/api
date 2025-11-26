export const minimumPrice = (sesiones) => {
    return Math.min(...sesiones?.map(s => {
        let final = s.precio
        if (s.discountType === 'percent') {
            final = final - (s.precio * s.discount) / 100
        }
        if (s.discountType === 'fixed') {
            final = final - s.discount
        }
        return parseInt(final)
    }))
}
export const getFinalPrice = (session) => {
    let final = session.precio
    if (session.discountType === 'percent') {
        final = final - (session.precio * session.discount) / 100
    }
    if (session.discountType === 'fixed') {
        final = final - session.discount
    }
    return parseInt(final)
}

export const BestDiscount = (sesiones) => {
    return Math.max(...sesiones?.map(s => s.discountType === 'percent' ? parseInt(s.discount) : 0))
}
export const PriceWithFormat = (number) => {
    const formattedNumber = new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(number);
    return `${formattedNumber}`
}

export const getFormatosSesiones = (sesiones) => {
    const formatos = [...new Set(sesiones.map(item => item.formato))];
    return formatos
}

export const getThumbnailUrl = (originalUrl, transformation) => {
    const segment = '/upload/';
    const uploadIndex = originalUrl.indexOf(segment);
    if (uploadIndex === -1) return originalUrl;

    const insertionIndex = uploadIndex + segment.length;

    const newUrl =
        originalUrl.substring(0, insertionIndex) +
        transformation +
        '/' +
        originalUrl.substring(insertionIndex);

    return newUrl;
};